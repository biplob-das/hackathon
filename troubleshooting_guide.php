<?php
// Troubleshooting and Testing Script for Mental Health Analysis
session_start();
require_once 'db_connect.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mental Health System Troubleshooting</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .test-success { background-color: #d1fae5; border-color: #10b981; }
        .test-failed { background-color: #fee2e2; border-color: #ef4444; }
        .test-warning { background-color: #fef3c7; border-color: #f59e0b; }
    </style>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Mental Health System Diagnostics</h1>
        
        <?php
        echo "<div class='space-y-4'>";
        
        // Test 1: Check if required files exist
        echo "<div class='bg-white p-4 rounded-lg shadow border-l-4 test-" . testFileExists() . "'>";
        echo "<h3 class='font-bold text-lg mb-2'>📁 File Structure Test</h3>";
        testFileExists(true);
        echo "</div>";
        
        // Test 2: Check database tables
        echo "<div class='bg-white p-4 rounded-lg shadow border-l-4 test-" . testDatabaseTables() . "'>";
        echo "<h3 class='font-bold text-lg mb-2'>🗄️ Database Tables Test</h3>";
        testDatabaseTables(true);
        echo "</div>";
        
        // Test 3: Check Gemini API configuration
        echo "<div class='bg-white p-4 rounded-lg shadow border-l-4 test-" . testGeminiConfig() . "'>";
        echo "<h3 class='font-bold text-lg mb-2'>🔧 Gemini API Configuration Test</h3>";
        testGeminiConfig(true);
        echo "</div>";
        
        // Test 4: Test API connectivity
        echo "<div class='bg-white p-4 rounded-lg shadow border-l-4 test-" . testAPIConnectivity() . "'>";
        echo "<h3 class='font-bold text-lg mb-2'>🌐 API Connectivity Test</h3>";
        testAPIConnectivity(true);
        echo "</div>";
        
        // Test 5: Test analysis with mock data
        echo "<div class='bg-white p-4 rounded-lg shadow border-l-4 test-" . testMockAnalysis() . "'>";
        echo "<h3 class='font-bold text-lg mb-2'>🧠 Mock Analysis Test</h3>";
        testMockAnalysis(true);
        echo "</div>";
        
        echo "</div>";
        
        // Functions for testing
        function testFileExists($output = false) {
            $files = [
                'gemini_config.php',
                'mental_health_analyzer.php', 
                'mental_health_dashboard.php',
                'mental_health_report.php'
            ];
            
            $missing = [];
            foreach ($files as $file) {
                if (!file_exists($file)) {
                    $missing[] = $file;
                }
            }
            
            if ($output) {
                if (empty($missing)) {
                    echo "<p class='text-green-700'>✅ All required files are present</p>";
                } else {
                    echo "<p class='text-red-700'>❌ Missing files: " . implode(', ', $missing) . "</p>";
                    echo "<p class='text-sm text-gray-600 mt-2'>Please upload the missing files from the artifacts.</p>";
                }
            }
            
            return empty($missing) ? 'success' : 'failed';
        }
        
        function testDatabaseTables($output = false) {
            global $pdo;
            
            $tables = [
                'mental_health_records',
                'crisis_alerts', 
                'mental_health_resources',
                'user_mental_health_settings'
            ];
            
            $missing = [];
            foreach ($tables as $table) {
                try {
                    $stmt = $pdo->query("SELECT 1 FROM $table LIMIT 1");
                } catch (Exception $e) {
                    $missing[] = $table;
                }
            }
            
            if ($output) {
                if (empty($missing)) {
                    echo "<p class='text-green-700'>✅ All database tables exist</p>";
                } else {
                    echo "<p class='text-red-700'>❌ Missing tables: " . implode(', ', $missing) . "</p>";
                    echo "<p class='text-sm text-gray-600 mt-2'>Please run mental_health_tables.sql on your database.</p>";
                    echo "<div class='bg-gray-100 p-2 mt-2 rounded text-xs'>";
                    echo "SQL Command: <code>SOURCE mental_health_tables.sql;</code>";
                    echo "</div>";
                }
            }
            
            return empty($missing) ? 'success' : 'failed';
        }
        
        function testGeminiConfig($output = false) {
            if (!file_exists('gemini_config.php')) {
                if ($output) {
                    echo "<p class='text-red-700'>❌ gemini_config.php file not found</p>";
                }
                return 'failed';
            }
            
            include_once 'gemini_config.php';
            
            if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY_HERE') {
                if ($output) {
                    echo "<p class='text-red-700'>❌ Gemini API key not configured</p>";
                    echo "<div class='bg-yellow-50 p-3 mt-2 rounded border border-yellow-200'>";
                    echo "<p class='text-sm font-medium text-yellow-800'>To fix this:</p>";
                    echo "<ol class='text-sm text-yellow-700 list-decimal list-inside mt-1'>";
                    echo "<li>Visit <a href='https://makersuite.google.com/app/apikey' target='_blank' class='underline'>Google AI Studio</a></li>";
                    echo "<li>Create a new API key</li>";
                    echo "<li>Replace 'YOUR_GEMINI_API_KEY_HERE' in gemini_config.php with your actual API key</li>";
                    echo "</ol>";
                    echo "</div>";
                }
                return 'failed';
            }
            
            if ($output) {
                echo "<p class='text-green-700'>✅ Gemini API key is configured</p>";
                echo "<p class='text-sm text-gray-600'>Key: " . substr(GEMINI_API_KEY, 0, 8) . "..." . substr(GEMINI_API_KEY, -4) . "</p>";
            }
            
            return 'success';
        }
        
        function testAPIConnectivity($output = false) {
            if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY_HERE') {
                if ($output) {
                    echo "<p class='text-yellow-700'>⚠️ Cannot test connectivity: API key not configured</p>";
                }
                return 'warning';
            }
            
            $testData = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => 'Hello, respond with just "API Working"']
                        ]
                    ]
                ]
            ];
            
            $options = [
                'http' => [
                    'header' => [
                        'Content-Type: application/json',
                        'x-goog-api-key: ' . GEMINI_API_KEY
                    ],
                    'method' => 'POST',
                    'content' => json_encode($testData),
                    'timeout' => 30
                ]
            ];
            
            try {
                $context = stream_context_create($options);
                $response = @file_get_contents(GEMINI_API_URL, false, $context);
                
                if ($response === FALSE) {
                    if ($output) {
                        echo "<p class='text-red-700'>❌ Failed to connect to Gemini API</p>";
                        echo "<p class='text-sm text-gray-600'>Check your internet connection and API key validity.</p>";
                    }
                    return 'failed';
                }
                
                $result = json_decode($response, true);
                
                if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                    if ($output) {
                        echo "<p class='text-green-700'>✅ Successfully connected to Gemini API</p>";
                        echo "<p class='text-sm text-gray-600'>Response: " . htmlspecialchars($result['candidates'][0]['content']['parts'][0]['text']) . "</p>";
                    }
                    return 'success';
                } else {
                    if ($output) {
                        echo "<p class='text-red-700'>❌ Unexpected API response format</p>";
                        echo "<div class='bg-gray-100 p-2 mt-2 rounded text-xs'><pre>" . htmlspecialchars($response) . "</pre></div>";
                    }
                    return 'failed';
                }
                
            } catch (Exception $e) {
                if ($output) {
                    echo "<p class='text-red-700'>❌ API connection error: " . htmlspecialchars($e->getMessage()) . "</p>";
                }
                return 'failed';
            }
        }
        
        function testMockAnalysis($output = false) {
            if (!file_exists('mental_health_analyzer.php')) {
                if ($output) {
                    echo "<p class='text-yellow-700'>⚠️ Cannot test: mental_health_analyzer.php not found</p>";
                }
                return 'warning';
            }
            
            try {
                require_once 'mental_health_analyzer.php';
                
                if ($output) {
                    echo "<p class='text-green-700'>✅ Mental health analyzer class loaded successfully</p>";
                    
                    // Test with mock analysis
                    echo "<div class='mt-4 p-3 bg-blue-50 border border-blue-200 rounded'>";
                    echo "<h4 class='font-medium text-blue-800 mb-2'>Mock Analysis Test:</h4>";
                    echo "<p class='text-sm text-blue-700'>Test diary content: 'I had a good day today and feel grateful for my friends.'</p>";
                    
                    $mockAnalysis = [
                        'depression_level' => 2,
                        'suicide_risk_level' => 1,
                        'urgency' => 'low',
                        'emotional_state' => 'positive',
                        'reasoning' => 'Content shows positive emotions and gratitude'
                    ];
                    
                    echo "<p class='text-sm text-blue-700 mt-2'>Mock result:</p>";
                    echo "<ul class='text-xs text-blue-600 list-disc list-inside'>";
                    foreach ($mockAnalysis as $key => $value) {
                        echo "<li>" . htmlspecialchars($key) . ": " . htmlspecialchars($value) . "</li>";
                    }
                    echo "</ul>";
                    echo "</div>";
                }
                
                return 'success';
                
            } catch (Exception $e) {
                if ($output) {
                    echo "<p class='text-red-700'>❌ Error loading analyzer: " . htmlspecialchars($e->getMessage()) . "</p>";
                }
                return 'failed';
            }
        }
        ?>
        
        <!-- Manual Test Section -->
        <div class="bg-white p-6 rounded-lg shadow mt-6">
            <h3 class="font-bold text-lg mb-4">🧪 Manual Analysis Test</h3>
            <p class="text-gray-600 mb-4">Test the mental health analysis with custom text:</p>
            
            <form id="manual-test-form" class="space-y-4">
                <textarea 
                    id="test-content" 
                    class="w-full p-3 border rounded-lg h-32" 
                    placeholder="Enter diary content to test analysis..."
                >I feel really sad today and nothing seems to matter anymore. I don't see the point in continuing.</textarea>
                
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    Test Analysis
                </button>
                
                <div id="test-results" class="hidden p-4 border rounded-lg"></div>
            </form>
        </div>
        
        <!-- Configuration Helper -->
        <div class="bg-white p-6 rounded-lg shadow mt-6">
            <h3 class="font-bold text-lg mb-4">⚙️ Quick Configuration</h3>
            
            <?php if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY_HERE'): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <h4 class="font-medium text-red-800 mb-2">API Key Setup Required</h4>
                <p class="text-red-700 text-sm mb-3">To enable mental health analysis, you need a Gemini AI API key.</p>
                
                <div class="space-y-2 text-sm">
                    <p class="font-medium">Steps:</p>
                    <ol class="list-decimal list-inside space-y-1 text-red-600">
                        <li>Visit <a href="https://makersuite.google.com/app/apikey" target="_blank" class="underline">Google AI Studio</a></li>
                        <li>Create a new API key</li>
                        <li>Edit <code>gemini_config.php</code></li>
                        <li>Replace <code>YOUR_GEMINI_API_KEY_HERE</code> with your actual key</li>
                        <li>Refresh this page to test</li>
                    </ol>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="mt-4 space-y-2">
                <h4 class="font-medium text-gray-800">System Status:</h4>
                <ul class="text-sm space-y-1">
                    <li>PHP Version: <span class="font-mono"><?php echo PHP_VERSION; ?></span></li>
                    <li>PDO MySQL: <?php echo extension_loaded('pdo_mysql') ? '✅ Available' : '❌ Missing'; ?></li>
                    <li>cURL: <?php echo extension_loaded('curl') ? '✅ Available' : '❌ Missing'; ?></li>
                    <li>OpenSSL: <?php echo extension_loaded('openssl') ? '✅ Available' : '❌ Missing'; ?></li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('manual-test-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const content = document.getElementById('test-content').value;
            const resultsDiv = document.getElementById('test-results');
            
            if (!content.trim()) {
                alert('Please enter some content to analyze');
                return;
            }
            
            resultsDiv.className = 'p-4 border rounded-lg bg-blue-50 border-blue-200';
            resultsDiv.innerHTML = '<p class="text-blue-700">Analyzing content...</p>';
            resultsDiv.classList.remove('hidden');
            
            try {
                const formData = new FormData();
                formData.append('content', content);
                
                const response = await fetch('index.php?ajax=analyze_diary', {
                    method: 'POST',
                    body: formData
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const analysis = await response.json();
                
                resultsDiv.className = 'p-4 border rounded-lg bg-green-50 border-green-200';
                resultsDiv.innerHTML = `
                    <h4 class="font-medium text-green-800 mb-2">✅ Analysis Successful</h4>
                    <div class="text-sm text-green-700 space-y-1">
                        <p><strong>Depression Level:</strong> ${analysis.depression_level}/10</p>
                        <p><strong>Suicide Risk Level:</strong> ${analysis.suicide_risk_level}/10</p>
                        <p><strong>Urgency:</strong> ${analysis.urgency}</p>
                        <p><strong>Emotional State:</strong> ${analysis.emotional_state || 'Not specified'}</p>
                        ${analysis.reasoning ? `<p><strong>Reasoning:</strong> ${analysis.reasoning}</p>` : ''}
                    </div>
                `;
                
            } catch (error) {
                console.error('Analysis error:', error);
                resultsDiv.className = 'p-4 border rounded-lg bg-red-50 border-red-200';
                resultsDiv.innerHTML = `
                    <h4 class="font-medium text-red-800 mb-2">❌ Analysis Failed</h4>
                    <p class="text-sm text-red-700">Error: ${error.message}</p>
                    <div class="mt-2 text-xs text-red-600">
                        <p>Possible causes:</p>
                        <ul class="list-disc list-inside">
                            <li>API key not configured</li>
                            <li>Internet connectivity issues</li>
                            <li>API quota exceeded</li>
                            <li>Server configuration problems</li>
                        </ul>
                    </div>
                `;
            }
        });
    </script>
</body>
</html>