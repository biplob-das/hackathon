<?php
session_start();
include 'db_connect.php';

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

// Handle Requests
if (!isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
        $stmt->execute([$_POST['username'], $_POST['password']]); // Hash in prod
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            header("Location: index.php");
            exit;
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
        }
    }
    echo '<!DOCTYPE html><html><body>';
    echo '<form method="post"><input type="text" name="username" placeholder="Username" required><input type="password" name="password" placeholder="Password" required><input type="submit" name="login" value="Login" class="bg-blue-500 text-white px-4 py-2 rounded"></form>';
    echo '<form method="post"><input type="text" name="username" placeholder="Username" required><input type="password" name="password" placeholder="Password" required><input type="submit" name="signup" value="Signup" class="bg-green-500 text-white px-4 py-2 rounded"></form>';
    echo '</body></html>';
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
    $stmt = $pdo->prepare("INSERT INTO diaries (user_id, content) VALUES (?, ?)");
    $stmt->execute([$_SESSION['user_id'], $_POST['content']]);
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
</head>
<body class="bg-gray-100 font-sans flex h-screen">
    <nav class="w-64 bg-purple-800 text-white p-4 space-y-2">
        <h2 class="text-2xl font-bold mb-4">Productivity Hub</h2>
        <ul class="space-y-2">
            <li class="hover:bg-purple-700 p-2 rounded cursor-pointer" data-section="calendar">Calendar</li>
            <li class="hover:bg-purple-700 p-2 rounded cursor-pointer" data-section="todo">To-Do</li>
            <li class="hover:bg-purple-700 p-2 rounded cursor-pointer" data-section="diary">Diary</li>
            <li class="hover:bg-purple-700 p-2 rounded cursor-pointer" data-section="friends">Friends Feed</li>
            <li class="hover:bg-purple-700 p-2 rounded cursor-pointer" data-section="roadmap">Learning Roadmap</li>
            <li class="hover:bg-purple-700 p-2 rounded cursor-pointer" data-section="gamification">Gamification</li>
        </ul>
        <div class="mt-auto">
            <p class="text-sm">Welcome, <?php echo htmlspecialchars($user['username']); ?>! <a href="?logout=1" class="underline">Logout</a></p>
        </div>
    </nav>

    <main class="flex-1 p-6 overflow-y-auto">
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
            <form method="post">
                <input type="hidden" name="add_diary" value="1">
                <textarea name="content" placeholder="Write your thoughts..." class="w-full p-2 border rounded h-32"></textarea>
                <button type="submit" class="mt-2 bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">Save</button>
            </form>
            <h3 class="mt-4 text-md font-semibold text-purple-800">Previous Entries</h3>
            <ul class="space-y-2 mt-2">
                <?php foreach ($diaries as $diary): ?>
                <li class="text-gray-700"><?php echo htmlspecialchars($diary['content']); ?> (<?php echo (new DateTime($diary['date']))->format('Y-m-d H:i'); ?>)</li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section id="gamification" class="bg-white p-4 rounded-lg shadow hidden">
            <h2 class="text-lg font-semibold text-purple-800 mb-2">Gamification</h2>
            <p class="text-gray-700"><?php echo $user['streak']; ?>-day streak <span class="text-yellow-500">🔥</span></p>
            <p class="text-gray-700">Level <?php echo $user['level']; ?></p>
        </section>
    </main>
</body>
</html>