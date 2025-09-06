<?php
require_once 'gemini_config.php';
require_once 'db_connect.php';

class MentalHealthAnalyzer {
    private $apiKey;
    private $apiUrl;
    private $pdo;
    private $fallbackEnabled;

    public function __construct() {
        $this->apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : null;
        $this->apiUrl = defined('GEMINI_API_URL') ? GEMINI_API_URL : 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent';
        global $pdo;
        $this->pdo = $pdo;
        $this->fallbackEnabled = true; // Enable fallback analysis when API fails
    }

    /**
     * Analyze diary content with enhanced error handling and fallback
     */
    public function analyzeDiaryContent($content, $userId) {
        // Validate inputs
        if (empty($content) || empty($userId)) {
            throw new Exception('Content and user ID are required');
        }

        // Check if API is configured
        if (!$this->apiKey || $this->apiKey === 'YOUR_GEMINI_API_KEY_HERE') {
            if ($this->fallbackEnabled) {
                error_log("Gemini API not configured, using fallback analysis for user $userId");
                return $this->fallbackAnalysis($content, $userId);
            } else {
                throw new Exception('Gemini AI API key not configured');
            }
        }

        try {
            // Try Gemini AI analysis
            $prompt = $this->buildAnalysisPrompt($content);
            $response = $this->callGeminiAPI($prompt);
            
            if ($response) {
                $analysis = $this->parseAnalysisResponse($response);
                $this->saveMentalHealthRecord($userId, $content, $analysis);
                
                // Check if intervention is needed
                if ($this->requiresIntervention($analysis)) {
                    $this->createCrisisAlert($userId, $analysis);
                }
                
                return $analysis;
            }
        } catch (Exception $e) {
            error_log("Gemini API analysis failed: " . $e->getMessage());
            
            // Use fallback if enabled
            if ($this->fallbackEnabled) {
                error_log("Using fallback analysis for user $userId");
                return $this->fallbackAnalysis($content, $userId);
            } else {
                throw $e;
            }
        }
        
        return null;
    }

    /**
     * Fallback analysis using keyword-based detection
     */
    private function fallbackAnalysis($content, $userId) {
        $content = strtolower($content);
        
        // Depression indicators
        $depressionKeywords = [
            'high' => ['suicide', 'kill myself', 'end it all', 'no point', 'hopeless', 'worthless'],
            'moderate' => ['depressed', 'sad', 'empty', 'alone', 'tired', 'exhausted', 'meaningless'],
            'low' => ['down', 'upset', 'worried', 'stressed', 'anxious']
        ];
        
        // Suicide risk indicators  
        $suicideKeywords = [
            'critical' => ['kill myself', 'suicide', 'end it all', 'better off dead', 'plan to die'],
            'high' => ['want to die', 'no point living', 'can\'t go on', 'hopeless'],
            'moderate' => ['worthless', 'burden', 'everyone would be better without me'],
            'low' => ['tired of life', 'what\'s the point']
        ];
        
        // Positive indicators
        $positiveKeywords = ['happy', 'grateful', 'thankful', 'blessed', 'love', 'excited', 'hope', 'better', 'improving'];
        
        // Calculate scores
        $depressionLevel = $this->calculateKeywordScore($content, $depressionKeywords);
        $suicideRiskLevel = $this->calculateSuicideScore($content, $suicideKeywords);
        $hasPositiveIndicators = $this->hasPositiveContent($content, $positiveKeywords);
        
        // Adjust scores based on positive indicators
        if ($hasPositiveIndicators) {
            $depressionLevel = max(0, $depressionLevel - 2);
            $suicideRiskLevel = max(0, $suicideRiskLevel - 2);
        }
        
        // Determine urgency
        $urgency = 'low';
        if ($suicideRiskLevel >= 7 || $depressionLevel >= 8) {
            $urgency = 'critical';
        } elseif ($suicideRiskLevel >= 5 || $depressionLevel >= 6) {
            $urgency = 'high';
        } elseif ($suicideRiskLevel >= 3 || $depressionLevel >= 4) {
            $urgency = 'moderate';
        }
        
        $analysis = [
            'depression_level' => $depressionLevel,
            'suicide_risk_level' => $suicideRiskLevel,
            'urgency' => $urgency,
            'emotional_state' => $hasPositiveIndicators ? 'mixed with positive elements' : 'concerning',
            'depression_indicators' => $this->getMatchedKeywords($content, $depressionKeywords),
            'suicide_indicators' => $this->getMatchedKeywords($content, $suicideKeywords),
            'positive_indicators' => $hasPositiveIndicators ? ['positive language detected'] : [],
            'recommendations' => $this->generateRecommendations($depressionLevel, $suicideRiskLevel, $urgency),
            'reasoning' => 'Analysis performed using keyword-based fallback system (Gemini AI unavailable)',
            'analysis_type' => 'fallback'
        ];
        
        // Save the analysis
        $this->saveMentalHealthRecord($userId, $content, $analysis);
        
        // Check if intervention is needed
        if ($this->requiresIntervention($analysis)) {
            $this->createCrisisAlert($userId, $analysis);
        }
        
        return $analysis;
    }

    private function calculateKeywordScore($content, $keywords) {
        $score = 0;
        
        // High severity keywords
        foreach ($keywords['high'] as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $score += 3;
            }
        }
        
        // Moderate severity keywords
        foreach ($keywords['moderate'] as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $score += 2;
            }
        }
        
        // Low severity keywords
        foreach ($keywords['low'] as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $score += 1;
            }
        }
        
        return min(10, $score); // Cap at 10
    }

    private function calculateSuicideScore($content, $keywords) {
        $score = 0;
        
        // Critical keywords
        foreach ($keywords['critical'] as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $score += 4;
            }
        }
        
        // High risk keywords
        foreach ($keywords['high'] as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $score += 3;
            }
        }
        
        // Moderate risk keywords
        foreach ($keywords['moderate'] as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $score += 2;
            }
        }
        
        // Low risk keywords
        foreach ($keywords['low'] as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $score += 1;
            }
        }
        
        return min(10, $score); // Cap at 10
    }

    private function hasPositiveContent($content, $positiveKeywords) {
        foreach ($positiveKeywords as $keyword) {
            if (strpos($content, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }

    private function getMatchedKeywords($content, $keywords) {
        $matched = [];
        foreach ($keywords as $severity => $keywordList) {
            foreach ($keywordList as $keyword) {
                if (strpos($content, $keyword) !== false) {
                    $matched[] = "$keyword ($severity)";
                }
            }
        }
        return $matched;
    }

    private function generateRecommendations($depressionLevel, $suicideRiskLevel, $urgency) {
        $recommendations = [];
        
        if ($urgency === 'critical') {
            $recommendations[] = 'Seek immediate professional help or call crisis hotline';
            $recommendations[] = 'Do not remain alone, reach out to trusted friends or family';
        } elseif ($urgency === 'high') {
            $recommendations[] = 'Consider speaking with a mental health professional';
            $recommendations[] = 'Reach out to supportive friends or family members';
        } elseif ($urgency === 'moderate') {
            $recommendations[] = 'Consider self-care activities like exercise or meditation';
            $recommendations[] = 'Connect with supportive people in your life';
        } else {
            $recommendations[] = 'Continue monitoring your mental health';
            $recommendations[] = 'Maintain healthy habits and social connections';
        }
        
        return $recommendations;
    }

    /**
     * Enhanced Gemini API call with better error handling
     */
    private function callGeminiAPI($prompt) {
        if (!$this->apiKey || $this->apiKey === 'YOUR_GEMINI_API_KEY_HERE') {
            throw new Exception('Gemini API key not configured');
        }

        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'topK' => 1,
                'topP' => 1,
                'maxOutputTokens' => 2048,
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_NONE'
                ],
                [
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_NONE'
                ],
                [
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_NONE'
                ],
                [
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_NONE'
                ]
            ]
        ];

        $jsonData = json_encode($data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to encode request data: ' . json_last_error_msg());
        }

        $options = [
            'http' => [
                'header' => [
                    'Content-Type: application/json',
                    'x-goog-api-key: ' . $this->apiKey
                ],
                'method' => 'POST',
                'content' => $jsonData,
                'timeout' => 30,
                'ignore_errors' => true // Don't fail on HTTP errors, handle them manually
            ]
        ];

        $context = stream_context_create($options);
        $response = @file_get_contents($this->apiUrl, false, $context);
        
        if ($response === FALSE) {
            $error = error_get_last();
            throw new Exception('Failed to connect to Gemini API: ' . ($error['message'] ?? 'Unknown error'));
        }

        // Check HTTP response code
        if (isset($http_response_header)) {
            $httpCode = intval(substr($http_response_header[0], 9, 3));
            if ($httpCode !== 200) {
                throw new Exception("API returned HTTP $httpCode: $response");
            }
        }

        $result = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to decode API response: ' . json_last_error_msg());
        }

        // Check for API errors
        if (isset($result['error'])) {
            throw new Exception('API Error: ' . $result['error']['message']);
        }
        
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return $result['candidates'][0]['content']['parts'][0]['text'];
        }
        
        // Check if content was blocked
        if (isset($result['candidates'][0]['finishReason']) && 
            $result['candidates'][0]['finishReason'] === 'SAFETY') {
            throw new Exception('Content was blocked by safety filters');
        }
        
        throw new Exception('Unexpected API response format: ' . json_encode($result));
    }

    /**
     * Build comprehensive analysis prompt for Gemini AI
     */
    private function buildAnalysisPrompt($content) {
        return "As a mental health assessment tool, analyze the following diary entry for depression indicators and suicide risk. 
        
        Please provide your analysis in the following JSON format ONLY (no additional text):
        {
            \"depression_level\": [0-10 scale],
            \"depression_indicators\": [\"list of specific indicators found\"],
            \"suicide_risk_level\": [0-10 scale],
            \"suicide_indicators\": [\"list of specific risk factors\"],
            \"emotional_state\": \"description of overall emotional state\",
            \"positive_indicators\": [\"list of positive/protective factors\"],
            \"recommendations\": [\"list of supportive recommendations\"],
            \"urgency\": \"low|moderate|high|critical\",
            \"reasoning\": \"brief explanation of the assessment\"
        }

        Key indicators to look for:
        - Depression: persistent sadness, hopelessness, worthlessness, loss of interest, sleep issues, fatigue, concentration problems
        - Suicide risk: hopelessness, worthlessness, isolation, specific plans, means, previous attempts, substance abuse
        - Positive factors: social support, future plans, coping strategies, help-seeking behavior

        Be sensitive but thorough in your analysis. Err on the side of caution for safety.

        Diary content to analyze:
        \"" . addslashes($content) . "\"

        Respond with ONLY the JSON object, no additional text.";
    }

    /**
     * Parse Gemini AI response with better error handling
     */
    private function parseAnalysisResponse($response) {
        // Clean the response - remove any markdown formatting
        $response = trim($response);
        $response = preg_replace('/```json\s*/', '', $response);
        $response = preg_replace('/```\s*$/', '', $response);
        
        $analysis = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Failed to parse Gemini response: " . json_last_error_msg());
            error_log("Response was: " . $response);
            throw new Exception('Failed to parse analysis response: ' . json_last_error_msg());
        }

        // Validate and sanitize the response
        $analysis['depression_level'] = max(0, min(10, intval($analysis['depression_level'] ?? 0)));
        $analysis['suicide_risk_level'] = max(0, min(10, intval($analysis['suicide_risk_level'] ?? 0)));
        $analysis['urgency'] = in_array($analysis['urgency'] ?? '', ['low', 'moderate', 'high', 'critical']) 
                              ? $analysis['urgency'] : 'low';
        
        // Ensure arrays exist
        $analysis['depression_indicators'] = $analysis['depression_indicators'] ?? [];
        $analysis['suicide_indicators'] = $analysis['suicide_indicators'] ?? [];
        $analysis['positive_indicators'] = $analysis['positive_indicators'] ?? [];
        $analysis['recommendations'] = $analysis['recommendations'] ?? [];
        
        // Add analysis type
        $analysis['analysis_type'] = 'gemini_ai';
        
        return $analysis;
    }

    /**
     * Save mental health analysis record with better error handling
     */
    private function saveMentalHealthRecord($userId, $content, $analysis) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO mental_health_records 
                (user_id, diary_content, depression_level, suicide_risk_level, 
                 analysis_data, urgency_level, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $userId,
                $content,
                $analysis['depression_level'],
                $analysis['suicide_risk_level'],
                json_encode($analysis),
                $analysis['urgency']
            ]);
            
            if (!$result) {
                throw new Exception('Failed to save mental health record');
            }
            
        } catch (Exception $e) {
            error_log("Failed to save mental health record: " . $e->getMessage());
            // Don't throw here - we don't want to break the analysis flow
        }
    }

    /**
     * Check if immediate intervention is required
     */
    private function requiresIntervention($analysis) {
        return $analysis['suicide_risk_level'] >= (defined('SUICIDE_RISK_THRESHOLD') ? SUICIDE_RISK_THRESHOLD : 5) ||
               $analysis['urgency'] === 'critical' ||
               $analysis['urgency'] === 'high';
    }

    /**
     * Create crisis alert for high-risk users
     */
    private function createCrisisAlert($userId, $analysis) {
        try {
            // Log the crisis alert
            $stmt = $this->pdo->prepare("
                INSERT INTO crisis_alerts 
                (user_id, alert_type, risk_level, analysis_data, status, created_at)
                VALUES (?, 'mental_health', ?, ?, 'active', NOW())
            ");
            
            $stmt->execute([
                $userId,
                $analysis['suicide_risk_level'],
                json_encode($analysis)
            ]);

            // Send notification to user
            $this->sendCrisisNotification($userId, $analysis);
            
        } catch (Exception $e) {
            error_log("Failed to create crisis alert: " . $e->getMessage());
        }
    }

    /**
     * Send crisis notification to user
     */
    private function sendCrisisNotification($userId, $analysis) {
        try {
            $message = $this->buildCrisisMessage($analysis);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications 
                (user_id, message, type, priority, created_at)
                VALUES (?, ?, 'crisis', 'urgent', NOW())
            ");
            
            $stmt->execute([$userId, $message]);
            
        } catch (Exception $e) {
            error_log("Failed to send crisis notification: " . $e->getMessage());
        }
    }

    /**
     * Build crisis support message
     */
    private function buildCrisisMessage($analysis) {
        $message = "We noticed you might be going through a difficult time. ";
        
        if ($analysis['suicide_risk_level'] >= (defined('SUICIDE_RISK_THRESHOLD') ? SUICIDE_RISK_THRESHOLD : 5)) {
            $message .= "If you're having thoughts of self-harm, please reach out for help immediately. ";
            $message .= "Crisis Hotline: " . (defined('CRISIS_HOTLINE') ? CRISIS_HOTLINE : '988') . ". ";
        }
        
        $message .= "Remember that support is available and you're not alone. ";
        $message .= "Consider reaching out to a mental health professional or trusted friend.";
        
        return $message;
    }

    /**
     * Get user's mental health trends
     */
    public function getUserMentalHealthTrends($userId, $days = 30) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(created_at) as date,
                    AVG(depression_level) as avg_depression,
                    AVG(suicide_risk_level) as avg_suicide_risk,
                    urgency_level
                FROM mental_health_records 
                WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date DESC
            ");
            
            $stmt->execute([$userId, $days]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Failed to get mental health trends: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get mental health summary for user
     */
    public function getUserMentalHealthSummary($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    AVG(depression_level) as avg_depression,
                    AVG(suicide_risk_level) as avg_suicide_risk,
                    MAX(depression_level) as max_depression,
                    MAX(suicide_risk_level) as max_suicide_risk,
                    COUNT(*) as total_entries,
                    MAX(created_at) as last_analysis
                FROM mental_health_records 
                WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Failed to get mental health summary: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Test API connectivity
     */
    public function testAPIConnection() {
        if (!$this->apiKey || $this->apiKey === 'YOUR_GEMINI_API_KEY_HERE') {
            return [
                'success' => false,
                'message' => 'API key not configured',
                'fallback_available' => $this->fallbackEnabled
            ];
        }

        try {
            $testPrompt = "Respond with exactly: 'API Connection Successful'";
            $response = $this->callGeminiAPI($testPrompt);
            
            return [
                'success' => true,
                'message' => 'API connection successful',
                'response' => $response,
                'fallback_available' => $this->fallbackEnabled
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'API connection failed: ' . $e->getMessage(),
                'fallback_available' => $this->fallbackEnabled
            ];
        }
    }
}
?>