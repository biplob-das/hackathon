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

// Get user's mental health data
$trends = $analyzer->getUserMentalHealthTrends($userId);
$summary = $analyzer->getUserMentalHealthSummary($userId);

// Get recent crisis alerts
$stmt = $pdo->prepare("
    SELECT * FROM crisis_alerts 
    WHERE user_id = ? AND status = 'active' 
    ORDER BY created_at DESC LIMIT 5
");
$stmt->execute([$userId]);
$crisisAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get mental health resources
$stmt = $pdo->query("
    SELECT * FROM mental_health_resources 
    WHERE is_active = 1 
    ORDER BY category, title
");
$resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user settings
$stmt = $pdo->prepare("
    SELECT * FROM user_mental_health_settings 
    WHERE user_id = ?
");
$stmt->execute([$userId]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $stmt = $pdo->prepare("
        INSERT INTO user_mental_health_settings 
        (user_id, enable_analysis, crisis_contact_phone, crisis_contact_name, enable_notifications, analysis_frequency, privacy_level)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        enable_analysis = VALUES(enable_analysis),
        crisis_contact_phone = VALUES(crisis_contact_phone),
        crisis_contact_name = VALUES(crisis_contact_name),
        enable_notifications = VALUES(enable_notifications),
        analysis_frequency = VALUES(analysis_frequency),
        privacy_level = VALUES(privacy_level),
        updated_at = NOW()
    ");
    
    $stmt->execute([
        $userId,
        isset($_POST['enable_analysis']) ? 1 : 0,
        $_POST['crisis_contact_phone'] ?? '',
        $_POST['crisis_contact_name'] ?? '',
        isset($_POST['enable_notifications']) ? 1 : 0,
        $_POST['analysis_frequency'] ?? 'every_entry',
        $_POST['privacy_level'] ?? 'private'
    ]);
    
    header('Location: mental_health_dashboard.php?updated=1');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mental Health Dashboard - Productivity Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .crisis-alert {
            border-left: 4px solid #ef4444;
            background: #fef2f2;
        }
        .warning-alert {
            border-left: 4px solid #f59e0b;
            background: #fefbf2;
        }
        .safe-indicator {
            border-left: 4px solid #10b981;
            background: #f0fdf4;
        }
    </style>
</head>
<body class="bg-gray-100">
    <nav class="bg-purple-800 text-white p-4">
        <div class="container mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold">Mental Health Dashboard</h1>
            <div class="space-x-4">
                <a href="index.php" class="hover:text-purple-200">Back to Hub</a>
                <a href="?logout=1" class="hover:text-purple-200">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mx-auto p-6">
        <?php if (isset($_GET['updated'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            Settings updated successfully.
        </div>
        <?php endif; ?>

        <!-- Crisis Alerts -->
        <?php if (!empty($crisisAlerts)): ?>
        <div class="crisis-alert p-4 rounded-lg mb-6">
            <h2 class="text-lg font-bold text-red-800 mb-2">🚨 Active Crisis Alerts</h2>
            <?php foreach ($crisisAlerts as $alert): ?>
            <div class="mb-2">
                <p class="text-red-700">High risk detected on <?php echo date('M j, Y', strtotime($alert['created_at'])); ?></p>
                <p class="text-sm text-red-600">If you're in crisis, please call <?php echo CRISIS_HOTLINE; ?> immediately.</p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Mental Health Summary -->
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Mental Health Overview (30 days)</h2>
                
                <?php if ($summary && $summary['total_entries'] > 0): ?>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Average Depression Level:</span>
                        <div class="flex items-center">
                            <div class="w-24 h-2 bg-gray-200 rounded-full mr-2">
                                <div class="h-2 bg-blue-500 rounded-full" 
                                     style="width: <?php echo ($summary['avg_depression'] / 10) * 100; ?>%"></div>
                            </div>
                            <span class="text-sm font-medium"><?php echo number_format($summary['avg_depression'], 1); ?>/10</span>
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <span class="text-gray-600">Average Suicide Risk:</span>
                        <div class="flex items-center">
                            <div class="w-24 h-2 bg-gray-200 rounded-full mr-2">
                                <div class="h-2 bg-red-500 rounded-full" 
                                     style="width: <?php echo ($summary['avg_suicide_risk'] / 10) * 100; ?>%"></div>
                            </div>
                            <span class="text-sm font-medium"><?php echo number_format($summary['avg_suicide_risk'], 1); ?>/10</span>
                        </div>
                    </div>
                    
                    <div class="text-sm text-gray-500">
                        <p>Total diary entries analyzed: <?php echo $summary['total_entries']; ?></p>
                        <p>Last analysis: <?php echo date('M j, Y g:i A', strtotime($summary['last_analysis'])); ?></p>
                    </div>
                </div>
                <?php else: ?>
                <p class="text-gray-500">No mental health data available yet. Start writing diary entries to see your analysis.</p>
                <?php endif; ?>
            </div>

            <!-- Trends Chart -->
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Mood Trends</h2>
                <?php if (!empty($trends)): ?>
                <canvas id="trendsChart" width="400" height="200"></canvas>
                <?php else: ?>
                <p class="text-gray-500">Not enough data to show trends yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Mental Health Resources -->
        <div class="bg-white p-6 rounded-lg shadow mt-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Mental Health Resources</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($resources as $resource): ?>
                <div class="border p-4 rounded-lg">
                    <h3 class="font-semibold text-purple-800"><?php echo htmlspecialchars($resource['title']); ?></h3>
                    <p class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($resource['description']); ?></p>
                    <?php if ($resource['phone']): ?>
                    <p class="text-sm font-medium mt-2">
                        📞 <a href="tel:<?php echo $resource['phone']; ?>" class="text-purple-600 hover:underline">
                            <?php echo $resource['phone']; ?>
                        </a>
                    </p>
                    <?php endif; ?>
                    <?php if ($resource['url']): ?>
                    <p class="text-sm mt-1">
                        🌐 <a href="<?php echo $resource['url']; ?>" target="_blank" class="text-purple-600 hover:underline">
                            Visit Website
                        </a>
                    </p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Settings -->
        <div class="bg-white p-6 rounded-lg shadow mt-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Mental Health Settings</h2>
            <form method="POST" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="enable_analysis" 
                                   <?php echo ($settings['enable_analysis'] ?? 1) ? 'checked' : ''; ?> 
                                   class="mr-2">
                            Enable mental health analysis
                        </label>
                    </div>
                    
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="enable_notifications" 
                                   <?php echo ($settings['enable_notifications'] ?? 1) ? 'checked' : ''; ?> 
                                   class="mr-2">
                            Enable crisis notifications
                        </label>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Analysis Frequency</label>
                        <select name="analysis_frequency" class="mt-1 block w-full border-gray-300 rounded-md">
                            <option value="every_entry" <?php echo ($settings['analysis_frequency'] ?? '') === 'every_entry' ? 'selected' : ''; ?>>
                                Every diary entry
                            </option>
                            <option value="daily" <?php echo ($settings['analysis_frequency'] ?? '') === 'daily' ? 'selected' : ''; ?>>
                                Daily summary
                            </option>
                            <option value="weekly" <?php echo ($settings['analysis_frequency'] ?? '') === 'weekly' ? 'selected' : ''; ?>>
                                Weekly summary
                            </option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Privacy Level</label>
                        <select name="privacy_level" class="mt-1 block w-full border-gray-300 rounded-md">
                            <option value="private" <?php echo ($settings['privacy_level'] ?? '') === 'private' ? 'selected' : ''; ?>>
                                Private (only you)
                            </option>
                            <option value="anonymous" <?php echo ($settings['privacy_level'] ?? '') === 'anonymous' ? 'selected' : ''; ?>>
                                Anonymous research
                            </option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Crisis Contact Name</label>
                        <input type="text" name="crisis_contact_name" 
                               value="<?php echo htmlspecialchars($settings['crisis_contact_name'] ?? ''); ?>"
                               class="mt-1 block w-full border-gray-300 rounded-md p-2"
                               placeholder="Trusted friend or family member">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Crisis Contact Phone</label>
                        <input type="tel" name="crisis_contact_phone" 
                               value="<?php echo htmlspecialchars($settings['crisis_contact_phone'] ?? ''); ?>"
                               class="mt-1 block w-full border-gray-300 rounded-md p-2"
                               placeholder="Emergency contact number">
                    </div>
                </div>
                
                <button type="submit" name="update_settings" 
                        class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">
                    Update Settings
                </button>
            </form>
        </div>
    </div>

    <script>
        // Render trends chart if data exists
        <?php if (!empty($trends)): ?>
        const ctx = document.getElementById('trendsChart').getContext('2d');
        const trendsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_reverse(array_column($trends, 'date'))); ?>,
                datasets: [{
                    label: 'Depression Level',
                    data: <?php echo json_encode(array_reverse(array_column($trends, 'avg_depression'))); ?>,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Suicide Risk',
                    data: <?php echo json_encode(array_reverse(array_column($trends, 'avg_suicide_risk'))); ?>,
                    borderColor: 'rgb(239, 68, 68)',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 10
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>