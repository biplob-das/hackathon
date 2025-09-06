<?php
session_start();
require_once 'db_connect.php';
require_once 'mental_health_analyzer.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$analyzer = new MentalHealthAnalyzer();
$userId = $_SESSION['user_id'];

// Get report parameters
$days = isset($_GET['days']) ? intval($_GET['days']) : 30;
$format = isset($_GET['format']) ? $_GET['format'] : 'html';

// Generate comprehensive report
$report = generateMentalHealthReport($userId, $days);

if ($format === 'pdf') {
    generatePDFReport($report);
    exit;
} elseif ($format === 'json') {
    header('Content-Type: application/json');
    echo json_encode($report, JSON_PRETTY_PRINT);
    exit;
}

function generateMentalHealthReport($userId, $days) {
    global $pdo, $analyzer;
    
    // Get user information
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get mental health records
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            depression_level,
            suicide_risk_level,
            analysis_data,
            urgency_level
        FROM mental_health_records 
        WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ORDER BY created_at ASC
    ");
    $stmt->execute([$userId, $days]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get crisis alerts
    $stmt = $pdo->prepare("
        SELECT * FROM crisis_alerts 
        WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ORDER BY created_at DESC
    ");
    $stmt->execute([$userId, $days]);
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $stats = calculateStatistics($records);
    $trends = analyzeTrends($records);
    $recommendations = generateRecommendations($stats, $trends, $alerts);
    
    return [
        'user' => $user,
        'period' => $days,
        'generated_at' => date('Y-m-d H:i:s'),
        'statistics' => $stats,
        'trends' => $trends,
        'records' => $records,
        'crisis_alerts' => $alerts,
        'recommendations' => $recommendations
    ];
}

function calculateStatistics($records) {
    if (empty($records)) {
        return [
            'total_entries' => 0,
            'avg_depression' => 0,
            'avg_suicide_risk' => 0,
            'max_depression' => 0,
            'max_suicide_risk' => 0,
            'risk_days' => 0,
            'safe_days' => 0
        ];
    }
    
    $depressionLevels = array_column($records, 'depression_level');
    $suicideRiskLevels = array_column($records, 'suicide_risk_level');
    
    $riskDays = count(array_filter($records, function($record) {
        return $record['urgency_level'] === 'high' || $record['urgency_level'] === 'critical';
    }));
    
    return [
        'total_entries' => count($records),
        'avg_depression' => round(array_sum($depressionLevels) / count($depressionLevels), 2),
        'avg_suicide_risk' => round(array_sum($suicideRiskLevels) / count($suicideRiskLevels), 2),
        'max_depression' => max($depressionLevels),
        'max_suicide_risk' => max($suicideRiskLevels),
        'risk_days' => $riskDays,
        'safe_days' => count($records) - $riskDays
    ];
}

function analyzeTrends($records) {
    if (count($records) < 2) {
        return [
            'depression_trend' => 'insufficient_data',
            'suicide_risk_trend' => 'insufficient_data',
            'overall_trend' => 'insufficient_data'
        ];
    }
    
    $firstHalf = array_slice($records, 0, floor(count($records) / 2));
    $secondHalf = array_slice($records, floor(count($records) / 2));
    
    $firstHalfDepression = array_sum(array_column($firstHalf, 'depression_level')) / count($firstHalf);
    $secondHalfDepression = array_sum(array_column($secondHalf, 'depression_level')) / count($secondHalf);
    
    $firstHalfSuicideRisk = array_sum(array_column($firstHalf, 'suicide_risk_level')) / count($firstHalf);
    $secondHalfSuicideRisk = array_sum(array_column($secondHalf, 'suicide_risk_level')) / count($secondHalf);
    
    $depressionTrend = 'stable';
    if ($secondHalfDepression > $firstHalfDepression + 1) {
        $depressionTrend = 'worsening';
    } elseif ($secondHalfDepression < $firstHalfDepression - 1) {
        $depressionTrend = 'improving';
    }
    
    $suicideRiskTrend = 'stable';
    if ($secondHalfSuicideRisk > $firstHalfSuicideRisk + 1) {
        $suicideRiskTrend = 'worsening';
    } elseif ($secondHalfSuicideRisk < $firstHalfSuicideRisk - 1) {
        $suicideRiskTrend = 'improving';
    }
    
    $overallTrend = 'stable';
    if ($depressionTrend === 'worsening' || $suicideRiskTrend === 'worsening') {
        $overallTrend = 'concerning';
    } elseif ($depressionTrend === 'improving' && $suicideRiskTrend === 'improving') {
        $overallTrend = 'improving';
    }
    
    return [
        'depression_trend' => $depressionTrend,
        'suicide_risk_trend' => $suicideRiskTrend,
        'overall_trend' => $overallTrend
    ];
}

function generateRecommendations($stats, $trends, $alerts) {
    $recommendations = [];
    
    // High-risk recommendations
    if ($stats['avg_depression'] >= 7 || $stats['avg_suicide_risk'] >= 5 || !empty($alerts)) {
        $recommendations[] = [
            'priority' => 'urgent',
            'text' => 'Seek immediate professional mental health support. Consider contacting a crisis hotline or visiting an emergency room if having thoughts of self-harm.'
        ];
    }
    
    // Trending recommendations
    if ($trends['overall_trend'] === 'concerning') {
        $recommendations[] = [
            'priority' => 'high',
            'text' => 'Your mental health indicators show concerning trends. Consider scheduling an appointment with a mental health professional.'
        ];
    }
    
    // General recommendations based on levels
    if ($stats['avg_depression'] >= 4) {
        $recommendations[] = [
            'priority' => 'medium',
            'text' => 'Consider incorporating mood-boosting activities like exercise, meditation, or social connections into your daily routine.'
        ];
    }
    
    if ($stats['risk_days'] > $stats['safe_days']) {
        $recommendations[] = [
            'priority' => 'medium',
            'text' => 'You\'ve had more high-risk days recently. Consider developing a safety plan and identifying trusted support contacts.'
        ];
    }
    
    // Positive reinforcement
    if ($trends['overall_trend'] === 'improving') {
        $recommendations[] = [
            'priority' => 'low',
            'text' => 'Great progress! Continue the positive practices that are helping improve your mental health.'
        ];
    }
    
    // Always include resources
    $recommendations[] = [
        'priority' => 'info',
        'text' => 'Remember: National Suicide Prevention Lifeline: 988, Crisis Text Line: Text HOME to 741741'
    ];
    
    return $recommendations;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mental Health Report - Productivity Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @media print {
            .no-print { display: none; }
            body { font-size: 12pt; }
        }
        .priority-urgent { border-left: 4px solid #ef4444; background: #fef2f2; }
        .priority-high { border-left: 4px solid #f59e0b; background: #fefbf2; }
        .priority-medium { border-left: 4px solid #3b82f6; background: #eff6ff; }
        .priority-info { border-left: 4px solid #10b981; background: #f0fdf4; }
    </style>
</head>
<body class="bg-gray-100">
    <div class="no-print bg-purple-800 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold">Mental Health Report</h1>
            <div class="space-x-4">
                <a href="mental_health_dashboard.php" class="hover:text-purple-200">Back to Dashboard</a>
                <button onclick="window.print()" class="bg-purple-600 px-4 py-2 rounded hover:bg-purple-700">Print Report</button>
                <a href="?days=<?php echo $days; ?>&format=json" class="bg-green-600 px-4 py-2 rounded hover:bg-green-700">Download JSON</a>
            </div>
        </div>
    </div>

    <div class="container mx-auto p-6">
        <!-- Report Header -->
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-2">Mental Health Analysis Report</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-600">
                <p><strong>User:</strong> <?php echo htmlspecialchars($report['user']['username']); ?></p>
                <p><strong>Period:</strong> Last <?php echo $report['period']; ?> days</p>
                <p><strong>Generated:</strong> <?php echo $report['generated_at']; ?></p>
            </div>
        </div>

        <!-- Statistics Overview -->
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Statistical Overview</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-4 bg-blue-50 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600"><?php echo $report['statistics']['total_entries']; ?></div>
                    <div class="text-sm text-blue-800">Total Entries</div>
                </div>
                <div class="text-center p-4 bg-yellow-50 rounded-lg">
                    <div class="text-2xl font-bold text-yellow-600"><?php echo $report['statistics']['avg_depression']; ?>/10</div>
                    <div class="text-sm text-yellow-800">Avg Depression</div>
                </div>
                <div class="text-center p-4 bg-red-50 rounded-lg">
                    <div class="text-2xl font-bold text-red-600"><?php echo $report['statistics']['avg_suicide_risk']; ?>/10</div>
                    <div class="text-sm text-red-800">Avg Suicide Risk</div>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <div class="text-2xl font-bold text-green-600"><?php echo $report['statistics']['safe_days']; ?></div>
                    <div class="text-sm text-green-800">Safe Days</div>
                </div>
            </div>
        </div>

        <!-- Trends Analysis -->
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Trend Analysis</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-4 border rounded-lg">
                    <h4 class="font-semibold text-gray-700">Depression Trend</h4>
                    <p class="text-lg font-bold 
                        <?php echo $report['trends']['depression_trend'] === 'improving' ? 'text-green-600' : 
                            ($report['trends']['depression_trend'] === 'worsening' ? 'text-red-600' : 'text-yellow-600'); ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $report['trends']['depression_trend'])); ?>
                    </p>
                </div>
                <div class="p-4 border rounded-lg">
                    <h4 class="font-semibold text-gray-700">Suicide Risk Trend</h4>
                    <p class="text-lg font-bold 
                        <?php echo $report['trends']['suicide_risk_trend'] === 'improving' ? 'text-green-600' : 
                            ($report['trends']['suicide_risk_trend'] === 'worsening' ? 'text-red-600' : 'text-yellow-600'); ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $report['trends']['suicide_risk_trend'])); ?>
                    </p>
                </div>
                <div class="p-4 border rounded-lg">
                    <h4 class="font-semibold text-gray-700">Overall Trend</h4>
                    <p class="text-lg font-bold 
                        <?php echo $report['trends']['overall_trend'] === 'improving' ? 'text-green-600' : 
                            ($report['trends']['overall_trend'] === 'concerning' ? 'text-red-600' : 'text-yellow-600'); ?>">
                        <?php echo ucfirst($report['trends']['overall_trend']); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Chart Visualization -->
        <?php if (!empty($report['records'])): ?>
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Mental Health Timeline</h3>
            <canvas id="timelineChart" width="400" height="100"></canvas>
        </div>
        <?php endif; ?>

        <!-- Recommendations -->
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Recommendations</h3>
            <div class="space-y-3">
                <?php foreach ($report['recommendations'] as $rec): ?>
                <div class="p-4 rounded-lg priority-<?php echo $rec['priority']; ?>">
                    <p><?php echo htmlspecialchars($rec['text']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Crisis Alerts -->
        <?php if (!empty($report['crisis_alerts'])): ?>
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Crisis Alerts (<?php echo count($report['crisis_alerts']); ?>)</h3>
            <div class="space-y-3">
                <?php foreach ($report['crisis_alerts'] as $alert): ?>
                <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-semibold text-red-800">Risk Level: <?php echo $alert['risk_level']; ?>/10</p>
                            <p class="text-sm text-red-600"><?php echo date('M j, Y g:i A', strtotime($alert['created_at'])); ?></p>
                        </div>
                        <span class="px-2 py-1 bg-red-200 text-red-800 rounded text-xs">
                            <?php echo ucfirst($alert['status']); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Disclaimer -->
        <div class="bg-gray-50 p-4 rounded-lg text-sm text-gray-600">
            <p><strong>Disclaimer:</strong> This report is generated by AI analysis and is not a substitute for professional mental health assessment. If you are experiencing a mental health crisis, please contact emergency services or a crisis hotline immediately. National Suicide Prevention Lifeline: 988</p>
        </div>
    </div>

    <script>
        // Render timeline chart if data exists
        <?php if (!empty($report['records'])): ?>
        const ctx = document.getElementById('timelineChart').getContext('2d');
        const timelineChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($report['records'], 'date')); ?>,
                datasets: [{
                    label: 'Depression Level',
                    data: <?php echo json_encode(array_column($report['records'], 'depression_level')); ?>,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: false
                }, {
                    label: 'Suicide Risk Level',
                    data: <?php echo json_encode(array_column($report['records'], 'suicide_risk_level')); ?>,
                    borderColor: 'rgb(239, 68, 68)',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Mental Health Levels Over Time'
                    },
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 10,
                        title: {
                            display: true,
                            text: 'Risk Level (0-10)'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>