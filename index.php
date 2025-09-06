<?php
session_start();
include 'db_connect.php';
require_once 'mental_health_analyzer.php';

// Initialize mental health analyzer
$mentalHealthAnalyzer = new MentalHealthAnalyzer();

// Mock AI Roadmap Generator
function generateRoadmap($tasks) {
    $suggestions = ["Build a small project", "Follow C++ tutorial", "Review CS basics"];
    $roadmap = [];
    $start_date = new DateTime('2025-09-06'); // Start from today
    for ($i = 0; $i < 7; $i++) {
        $date = clone $start_date;
        $date->modify("+$i days");
        $suggestion = $tasks ? $suggestions[array_rand($suggestions)] : "Start with basics";
        $roadmap[] = ["date" => $date->format("Y-m-d"), "suggestion" => $suggestion];
    }
    return $roadmap;
}

// Update Streak
function updateStreak($user) {
    global $pdo;
    $today = new DateTime('2025-09-06 12:35:00 +06'); // Current date and time
    $last_login = new DateTime($user['last_login']);
    if ($today->diff($last_login)->days >= 1) {
        $user['streak']++;
        if ($user['streak'] % 5 == 0) {
            $user['level']++;
        }
        $stmt = $pdo->prepare("UPDATE users SET streak = ?, level = ?, last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['streak'], $user['level'], $user['id']]);
    }
    return $user;
}

// Handle AJAX request for tasks
if (isset($_GET['ajax']) && $_GET['ajax'] === 'tasks' && isset($_SESSION['user_id'])) {
    $tasks = $pdo->query("SELECT * FROM tasks WHERE user_id = " . $_SESSION['user_id'] . " AND date BETWEEN '2025-09-01' AND '2025-09-30'")->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($tasks);
    exit;
}

// Handle AJAX request for mental health analysis
if (isset($_GET['ajax']) && $_GET['ajax'] === 'analyze_diary' && isset($_SESSION['user_id'])) {
    $content = $_POST['content'] ?? '';
    if (!empty($content)) {
        $analysis = $mentalHealthAnalyzer->analyzeDiaryContent($content, $_SESSION['user_id']);
        header('Content-Type: application/json');
        echo json_encode($analysis);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Content is required']);
    }
    exit;
}

// Handle Requests
if (!isset($_SESSION['user_id'])) {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
        $stmt->execute([$_POST['username'], $_POST['password']]); // Hash in prod
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            header("Location: index.php");
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$_POST['username']]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->execute([$_POST['username'], $_POST['password']]); // Hash in prod
            $_SESSION['user_id'] = $pdo->lastInsertId();
            header("Location: index.php");
            exit;
        } else {
            $error = 'Username already exists';
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Productivity Hub - Login</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            body {
                background: linear-gradient(135deg, #6B46C1, #B794F4);
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                font-family: 'Arial', sans-serif;
            }
            .login-container {
                background: white;
                padding: 2rem;
                border-radius: 15px;
                box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
                width: 100%;
                max-width: 400px;
                transition: transform 0.3s ease;
            }
            .login-container:hover {
                transform: translateY(-5px);
            }
            .login-container h2 {
                color: #6B46C1;
                text-align: center;
                margin-bottom: 1.5rem;
                font-size: 1.8rem;
                font-weight: bold;
            }
            .login-container input {
                width: 100%;
                padding: 0.75rem;
                margin: 0.5rem 0;
                border: 1px solid #ddd;
                border-radius: 8px;
                font-size: 1rem;
                transition: border-color 0.3s ease;
            }
            .login-container input:focus {
                outline: none;
                border-color: #6B46C1;
                box-shadow: 0 0 5px rgba(107, 70, 193, 0.3);
            }
            .login-container button {
                width: 100%;
                padding: 0.75rem;
                margin: 0.5rem 0;
                border: none;
                border-radius: 8px;
                font-size: 1rem;
                cursor: pointer;
                transition: background-color 0.3s ease, transform 0.2s ease;
            }
            .login-container button:hover {
                transform: translateY(-2px);
            }
            .login-button {
                background-color: #6B46C1;
                color: white;
            }
            .login-button:hover {
                background-color: #553C9A;
            }
            .signup-button {
                background-color: #48BB78;
                color: white;
            }
            .signup-button:hover {
                background-color: #38A169;
            }
            .error {
                color: #E53E3E;
                text-align: center;
                margin-bottom: 1rem;
                font-size: 0.9rem;
            }
            .toggle-form {
                text-align: center;
                margin-top: 1rem;
                color: #6B46C1;
                cursor: pointer;
                text-decoration: underline;
            }
            .toggle-form:hover {
                color: #553C9A;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2 id="form-title">Login to Productivity Hub</h2>
            <?php if ($error): ?>
                <p class="error"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
            <form id="login-form" method="post">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="login" class="login-button">Login</button>
                <p class="toggle-form" onclick="toggleForm('signup')">Don't have an account? Sign up</p>
            </form>
            <form id="signup-form" method="post" style="display: none;">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="signup" class="signup-button">Sign Up</button>
                <p class="toggle-form" onclick="toggleForm('login')">Already have an account? Login</p>
            </form>
        </div>
        <script>
            function toggleForm(form) {
                const loginForm = document.getElementById('login-form');
                const signupForm = document.getElementById('signup-form');
                const title = document.getElementById('form-title');
                if (form === 'signup') {
                    loginForm.style.display = 'none';
                    signupForm.style.display = 'block';
                    title.textContent = 'Sign Up for Productivity Hub';
                } else {
                    loginForm.style.display = 'block';
                    signupForm.style.display = 'none';
                    title.textContent = 'Login to Productivity Hub';
                }
            }
        </script>
    </body>
    </html>
    <?php
    exit;
}

$user = $pdo->query("SELECT * FROM users WHERE id = " . $_SESSION['user_id'])->fetch(PDO::FETCH_ASSOC);
$user = updateStreak($user);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    $stmt = $pdo->prepare("INSERT INTO tasks (user_id, description) VALUES (?, ?)");
    $stmt->execute([$_SESSION['user_id'], $_POST['description']]);
    header("Location: index.php");
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_task'])) {
    $stmt = $pdo->prepare("UPDATE tasks SET completed = TRUE WHERE id = ? AND user_id = ?");
    $stmt->execute([$_POST['task_id'], $_SESSION['user_id']]);
    header("Location: index.php");
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_journal'])) {
    $stmt = $pdo->prepare("INSERT INTO journals (user_id, content) VALUES (?, ?)");
    $stmt->execute([$_SESSION['user_id'], $_POST['content']]);
    header("Location: index.php");
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_diary'])) {
    $content = $_POST['content'];
    
    // Add diary entry
    $stmt = $pdo->prepare("INSERT INTO diaries (user_id, content) VALUES (?, ?)");
    $stmt->execute([$_SESSION['user_id'], $content]);
    
    // Check if mental health analysis is enabled for this user
    $stmt = $pdo->prepare("SELECT enable_analysis FROM user_mental_health_settings WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If analysis is enabled (default is true), analyze the diary content
    if (!$settings || $settings['enable_analysis']) {
        try {
            $mentalHealthAnalyzer->analyzeDiaryContent($content, $_SESSION['user_id']);
        } catch (Exception $e) {
            // Log error but don't interrupt user flow
            error_log("Mental health analysis failed: " . $e->getMessage());
        }
    }
    
    header("Location: index.php");
    exit;
} elseif (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

$tasks = $pdo->query("SELECT * FROM tasks WHERE user_id = " . $_SESSION['user_id'] . " AND date BETWEEN '2025-09-01' AND '2025-09-30'")->fetchAll(PDO::FETCH_ASSOC);
$completed = array_sum(array_column(array_filter($tasks, fn($t) => $t['completed']), 'completed'));
$score = $tasks ? ($completed / count($tasks) * 100) : 0;

$journals = $pdo->query("SELECT u.username, j.content FROM journals j JOIN users u ON j.user_id = u.id LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
$roadmap = generateRoadmap($tasks);

// Fetch diary entries for the current user
$diaries = $pdo->query("SELECT * FROM diaries WHERE user_id = " . $_SESSION['user_id'] . " ORDER BY date DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

// Get mental health alerts
$stmt = $pdo->prepare("SELECT * FROM crisis_alerts WHERE user_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 3");
$stmt->execute([$_SESSION['user_id']]);
$mentalHealthAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get urgent notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND priority IN ('high', 'urgent') AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$urgentNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare tasks by date for calendar
$tasks_by_date = [];
foreach ($tasks as $task) {
    $tasks_by_date[$task['date']][] = $task['description'] . ($task['completed'] ? ' (Completed)' : '');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productivity Hub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="script.js"></script>
    <link rel="stylesheet" href="styles.css">
    <style>
        .crisis-alert {
            background: linear-gradient(90deg, #fef2f2, #fee2e2);
            border-left: 4px solid #ef4444;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        .mental-health-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        .risk-low { background-color: #10b981; }
        .risk-moderate { background-color: #f59e0b; }
        .risk-high { background-color: #ef4444; }
    </style>
</head>
<body class="bg-gray-100 font-sans flex h-screen">
    <nav class="w-64 bg-purple-800 text-white p-4 space-y-2">
        <h2 class="text-2xl font-bold mb-4">Productivity Hub</h2>
        <ul class="space-y-2">
            <li class="hover:bg-purple-700 p-2 rounded cursor-pointer" data-section="calendar">Calendar</li>
            <li class="hover:bg-purple-700 p-2 rounded cursor-pointer" data-section="todo">To-Do</li>
            <li class="hover:bg-purple-700 p-2 rounded cursor-pointer" data-section="diary">
                Diary
                <?php if (!empty($mentalHealthAlerts)): ?>
                <span class="mental-health-indicator risk-high" title="Mental health alert active"></span>
                <?php endif; ?>
            </li>
            <li class="hover:bg-purple-700 p-2 rounded cursor-pointer" data-section="friends">Friends Feed</li>
            <li class="hover:bg-purple-700 p-2 rounded cursor-pointer" data-section="roadmap">Learning Roadmap</li>
            <li class="hover:bg-purple-700 p-2 rounded cursor-pointer" data-section="gamification">Gamification</li>
            <li class="hover:bg-purple-700 p-2 rounded cursor-pointer">
                <a href="mental_health_dashboard.php" class="block">Mental Health</a>
            </li>
        </ul>
        <div class="mt-auto">
            <p class="text-sm">Welcome, <?php echo htmlspecialchars($user['username']); ?>! <a href="?logout=1" class="underline">Logout</a></p>
        </div>
    </nav>

    <main class="flex-1 p-6 overflow-y-auto">
        <!-- Mental Health Alerts -->
        <?php if (!empty($mentalHealthAlerts)): ?>
        <div class="crisis-alert p-4 rounded-lg shadow mb-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-red-800">🚨 Mental Health Alert</h3>
                    <p class="text-red-700">We've detected concerning patterns in your recent diary entries.</p>
                    <p class="text-sm text-red-600">If you're in crisis, please call <?php echo CRISIS_HOTLINE; ?> immediately.</p>
                </div>
                <a href="mental_health_dashboard.php" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                    View Details
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Urgent Notifications -->
        <?php if (!empty($urgentNotifications)): ?>
        <div class="bg-orange-100 border border-orange-400 text-orange-700 px-4 py-3 rounded mb-4">
            <h4 class="font-bold">Important Notifications:</h4>
            <?php foreach ($urgentNotifications as $notification): ?>
            <p class="text-sm"><?php echo htmlspecialchars($notification['message']); ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <section id="calendar" class="bg-white p-4 rounded-lg shadow">
            <h2 class="text-lg font-semibold text-purple-800 mb-2">Calendar - September 2025</h2>
            <div class="grid grid-cols-7 gap-1 text-center">
                <div class="p-2 bg-gray-200 font-bold">Sun</div>
                <div class="p-2 bg-gray-200 font-bold">Mon</div>
                <div class="p-2 bg-gray-200 font-bold">Tue</div>
                <div class="p-2 bg-gray-200 font-bold">Wed</div>
                <div class="p-2 bg-gray-200 font-bold">Thu</div>
                <div class="p-2 bg-gray-200 font-bold">Fri</div>
                <div class="p-2 bg-gray-200 font-bold">Sat</div>
                <?php
                $first_day = new DateTime('2025-09-01');
                $first_day_of_week = $first_day->format('w'); // 0 (Sunday) to 6 (Saturday)
                $last_day = new DateTime('2025-09-30');
                $current_date = clone $first_day;
                $current_date->modify("-$first_day_of_week days"); // Start from the first Sunday before Sept 1

                while ($current_date <= $last_day || $current_date->format('w') != 0) {
                    $class = ($current_date->format('Y-m-d') == '2025-09-06') ? 'bg-blue-100 font-bold' : '';
                    $tasks = isset($tasks_by_date[$current_date->format('Y-m-d')]) ? implode(', ', $tasks_by_date[$current_date->format('Y-m-d')]) : '';
                    $is_current_month = ($current_date->format('m') == '09');
                    echo '<div class="p-2 ' . $class . ($is_current_month ? '' : 'text-gray-400') . '" title="' . htmlspecialchars($tasks) . '">' . ($is_current_month ? $current_date->format('d') : '') . '</div>';
                    $current_date->modify('+1 day');
                }
                ?>
            </div>
        </section>

        <section id="todo" class="bg-white p-4 rounded-lg shadow hidden">
            <h2 class="text-lg font-semibold text-purple-800 mb-2">Today's To-Do</h2>
            <ul class="space-y-2">
                <?php foreach ($tasks as $task): if ($task['date'] == '2025-09-06'): ?>
                <li class="flex items-center justify-between">
                    <span><?php echo htmlspecialchars($task['description']); ?></span>
                    <?php if (!$task['completed']): ?>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="complete_task" value="1">
                        <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                        <button type="submit" class="bg-green-500 text-white px-2 py-1 rounded hover:bg-green-600">Complete</button>
                    </form>
                    <?php else: ?>
                    <span class="text-green-500">(Completed)</span>
                    <?php endif; ?>
                </li>
                <?php endif; endforeach; ?>
            </ul>
            <form method="post" class="mt-4">
                <input type="hidden" name="add_task" value="1">
                <input type="text" name="description" placeholder="Add task" class="w-full p-2 border rounded">
                <button type="submit" class="mt-2 bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">Add</button>
            </form>
            <p class="mt-4 text-purple-800 font-bold">Productivity Score: <?php echo round($score); ?>%</p>
        </section>

        <section id="roadmap" class="bg-white p-4 rounded-lg shadow hidden">
            <h2 class="text-lg font-semibold text-purple-800 mb-2">Learning Roadmap</h2>
            <div class="grid grid-cols-7 gap-1 text-center">
                <div class="p-2 bg-gray-200">Sun</div>
                <div class="p-2 bg-gray-200">Mon</div>
                <div class="p-2 bg-gray-200">Tue</div>
                <div class="p-2 bg-gray-200">Wed</div>
                <div class="p-2 bg-gray-200">Thu</div>
                <div class="p-2 bg-gray-200">Fri</div>
                <div class="p-2 bg-gray-200">Sat</div>
                <?php
                $start_date = new DateTime('2025-09-06');
                for ($i = 0; $i < 7; $i++) {
                    $date = clone $start_date;
                    $date->modify("+$i days");
                    $class = ($date->format('Y-m-d') == '2025-09-06') ? 'bg-blue-100' : '';
                    echo '<div class="p-2 ' . $class . '">' . $date->format('d') . '</div>';
                }
                ?>
            </div>
            <h3 class="mt-4 text-md font-semibold text-purple-800">AI Suggested Tasks:</h3>
            <ol class="list-decimal list-inside mt-2 text-gray-700">
                <?php foreach ($roadmap as $item): ?>
                <li><?php echo htmlspecialchars($item['date']); ?>: <?php echo htmlspecialchars($item['suggestion']); ?></li>
                <?php endforeach; ?>
            </ol>
            <button class="mt-4 bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">Generate Full Roadmap</button>
        </section>

        <section id="friends" class="bg-white p-4 rounded-lg shadow hidden">
            <h2 class="text-lg font-semibold text-purple-800 mb-2">Friends' Progress Journal</h2>
            <div class="space-y-4">
                <?php foreach ($journals as $journal): ?>
                <div class="flex items-center">
                    <span class="w-8 h-8 bg-purple-200 rounded-full flex items-center justify-center mr-2"><?php echo strtoupper(substr($journal['username'], 0, 1)); ?></span>
                    <p class="text-gray-700"><?php echo htmlspecialchars($journal['username']); ?>: <?php echo htmlspecialchars($journal['content']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <form method="post" class="mt-4">
                <input type="hidden" name="add_journal" value="1">
                <textarea name="content" placeholder="Share progress" class="w-full p-2 border rounded"></textarea>
                <button type="submit" class="mt-2 bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">Post</button>
            </form>
        </section>

        <section id="diary" class="bg-white p-4 rounded-lg shadow hidden">
            <h2 class="text-lg font-semibold text-purple-800 mb-2">Personal Diary</h2>
            
            <!-- Mental Health Analysis Status -->
            <div id="analysis-status" class="mb-4 p-3 rounded-lg bg-blue-50 hidden">
                <p class="text-blue-800 text-sm">
                    <span id="analysis-message">Your diary entry is being analyzed for mental health insights...</span>
                </p>
            </div>
            
            <form method="post" id="diary-form">
                <input type="hidden" name="add_diary" value="1">
                <textarea name="content" id="diary-content" placeholder="Write your thoughts..." class="w-full p-2 border rounded h-32"></textarea>
                <div class="flex justify-between items-center mt-2">
                    <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">Save</button>
                    <button type="button" id="analyze-btn" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        Analyze Mental Health
                    </button>
                </div>
            </form>
            
            <h3 class="mt-4 text-md font-semibold text-purple-800">Previous Entries</h3>
            <ul class="space-y-2 mt-2">
                <?php foreach ($diaries as $diary): ?>
                <li class="relative p-2 border rounded">
                    <p class="text-gray-700"><?php echo htmlspecialchars($diary['content']); ?></p>
                    <p class="text-xs text-gray-500 mt-1"><?php echo (new DateTime($diary['date']))->format('Y-m-d H:i'); ?></p>
                </li>
                <?php endforeach; ?>
            </ul>
            
            <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                <p class="text-sm text-gray-600">
                    <strong>Privacy Note:</strong> Your diary entries may be analyzed using AI to provide mental health insights. 
                    This analysis is kept private and is used only to help identify when you might benefit from additional support. 
                    <a href="mental_health_dashboard.php" class="text-purple-600 underline">Manage settings</a>
                </p>
            </div>
        </section>

        <section id="gamification" class="bg-white p-4 rounded-lg shadow hidden">
            <h2 class="text-lg font-semibold text-purple-800 mb-2">Gamification</h2>
            <p class="text-gray-700"><?php echo $user['streak']; ?>-day streak <span class="text-yellow-500">🔥</span></p>
            <p class="text-gray-700">Level <?php echo $user['level']; ?></p>
        </section>
    </main>

    <script>
        // Mental health analysis functionality
        document.addEventListener('DOMContentLoaded', function() {
            const analyzeBtn = document.getElementById('analyze-btn');
            const analysisStatus = document.getElementById('analysis-status');
            const analysisMessage = document.getElementById('analysis-message');
            const diaryContent = document.getElementById('diary-content');

            if (analyzeBtn) {
                analyzeBtn.addEventListener('click', async function() {
                    const content = diaryContent.value.trim();
                    
                    if (!content) {
                        alert('Please write something in your diary first.');
                        return;
                    }

                    // Show analysis status
                    analysisStatus.classList.remove('hidden');
                    analysisMessage.textContent = 'Analyzing your diary entry for mental health insights...';
                    analyzeBtn.disabled = true;
                    analyzeBtn.textContent = 'Analyzing...';

                    try {
                        const formData = new FormData();
                        formData.append('content', content);

                        const response = await fetch('index.php?ajax=analyze_diary', {
                            method: 'POST',
                            body: formData
                        });

                        if (!response.ok) {
                            throw new Error('Analysis failed');
                        }

                        const analysis = await response.json();
                        
                        // Display results
                        analysisMessage.innerHTML = `
                            <strong>Mental Health Analysis Complete:</strong><br>
                            Depression Level: ${analysis.depression_level}/10<br>
                            Suicide Risk: ${analysis.suicide_risk_level}/10<br>
                            Urgency: ${analysis.urgency}<br>
                            <a href="mental_health_dashboard.php" class="text-blue-600 underline">View detailed analysis</a>
                        `;

                        // Change background color based on urgency
                        analysisStatus.className = 'mb-4 p-3 rounded-lg ';
                        if (analysis.urgency === 'critical' || analysis.urgency === 'high') {
                            analysisStatus.className += 'bg-red-50 border border-red-200';
                            analysisMessage.classList.add('text-red-800');
                        } else if (analysis.urgency === 'moderate') {
                            analysisStatus.className += 'bg-yellow-50 border border-yellow-200';
                            analysisMessage.classList.add('text-yellow-800');
                        } else {
                            analysisStatus.className += 'bg-green-50 border border-green-200';
                            analysisMessage.classList.add('text-green-800');
                        }

                    } catch (error) {
                        console.error('Analysis error:', error);
                        analysisMessage.innerHTML = '<strong>Analysis failed.</strong> Please try again later.';
                        analysisStatus.className = 'mb-4 p-3 rounded-lg bg-red-50 border border-red-200';
                        analysisMessage.classList.add('text-red-800');
                    } finally {
                        analyzeBtn.disabled = false;
                        analyzeBtn.textContent = 'Analyze Mental Health';
                    }
                });
            }
        });
    </script>
</body>
</html>