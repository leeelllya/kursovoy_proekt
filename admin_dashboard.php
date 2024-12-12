<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "birzha_truda";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Ошибка соединения: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("<div class='alert error'>Не удалось подключиться к базе данных. Пожалуйста, проверьте состояние сервера MySQL.</div>");
}

// Получение информации о пользователе
$user_id = $_SESSION['user_id'];
$userQuery = $conn->query("SELECT username FROM users WHERE id='$user_id'");
$user = $userQuery->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];
    $user_id = $_POST['user_id'] ?? null;

    if ($action === 'block_user' && $user_id) {
        $update_sql = "UPDATE users SET is_blocked = 1 WHERE id = '$user_id'";
        if ($conn->query($update_sql) === TRUE) {
            echo "<div class='alert success'>Пользователь заблокирован.</div>";
        } else {
            echo "<div class='alert error'>Ошибка при блокировке пользователя: " . $conn->error . "</div>";
        }
    } elseif ($action === 'unblock_user' && $user_id) {
        $update_sql = "UPDATE users SET is_blocked = 0 WHERE id = '$user_id'";
        if ($conn->query($update_sql) === TRUE) {
            echo "<div class='alert success'>Пользователь разблокирован.</div>";
        } else {
            echo "<div class='alert error'>Ошибка при разблокировке пользователя: " . $conn->error . "</div>";
        }
    }
}

// Обработка одобрения или отклонения изменений вакансии
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $job_id = isset($_POST['job_id']) ? $_POST['job_id'] : null;

    if ($_POST['action'] === 'approve') {
        $pending_job = $conn->query("SELECT * FROM job_listings_pending WHERE job_listing_id='$job_id'")->fetch_assoc();
        if ($pending_job) {
            $existing_job = $conn->query("SELECT * FROM job_listings WHERE id='$job_id'")->fetch_assoc();
            if ($existing_job) {
                $update_sql = "UPDATE job_listings SET title='{$pending_job['title']}', description='{$pending_job['description']}', requirements='{$pending_job['requirements']}', salary='{$pending_job['salary']}', programming_languages='{$pending_job['programming_languages']}', location='{$pending_job['location']}', status='approved' WHERE id='$job_id'";
                if ($conn->query($update_sql) === TRUE) {
                    echo "<div class='alert success'>Вакансия обновлена и одобрена.</div>";
                } else {
                    echo "<div class='alert error'>Ошибка при обновлении вакансии: " . $conn->error . "</div>";
                }
            } else {
                $insert_sql = "INSERT INTO job_listings (id, title, description, requirements, salary, programming_languages, employer_id, location, status) VALUES 
                               ('$job_id', '{$pending_job['title']}', '{$pending_job['description']}', '{$pending_job['requirements']}','{$pending_job['salary']}', '{$pending_job['programming_languages']}', 
                                '{$pending_job['employer_id']}',
                                '{$pending_job['location']}','approved')";
                if ($conn->query($insert_sql) === TRUE) {
                    echo "<div class='alert success'>Новая вакансия добавлена и одобрена.</div>";
                } else {
                    echo "<div class='alert error'>Ошибка при добавлении новой вакансии: " . $conn->error . "</div>";
                }
            }
    
            if ($conn->query("DELETE FROM job_listings_pending WHERE job_listing_id='$job_id'") === TRUE) {
            } else {
                echo "<div class='alert error'>Ошибка при удалении временной записи: " . $conn->error . "</div>";
            }
        } else {
            echo "<div class='alert error'>Вакансия не найдена или уже одобрена.</div>";
        }
    }
     elseif ($_POST['action'] === 'reject') {
        $conn->query("DELETE FROM job_listings_pending WHERE job_listing_id='$job_id'");
        echo "<div class='alert info'>Заявка отклонена.</div>";
    } elseif ($_POST['action'] === 'delete_job') {
        $conn->query("DELETE FROM applications WHERE job_listing_id = '$job_id'");
        
        $sql = "DELETE FROM job_listings WHERE id='$job_id'";
        if ($conn->query($sql) === TRUE) {
            echo "<div class='alert success'>Вакансия удалена.</div>";
        } else {
            echo "<div class='alert error'>Ошибка при удалении вакансии: " . $conn->error . "</div>";
        }
    }
}

// Обработка одобрения или отклонения добавления нового языка
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $language_id = isset($_POST['language_id']) ? $_POST['language_id'] : null;

    if ($_POST['action'] === 'approve_language') {
        $pending_language = $conn->query("SELECT * FROM programming_languages_pending WHERE id='$language_id'")->fetch_assoc();

        if ($pending_language) {
            $existing_language = $conn->query("SELECT * FROM programming_languages WHERE name='{$pending_language['name']}'")->fetch_assoc();
            
            if ($existing_language) {
                echo "<div class='alert error'>Навык с таким именем уже существует.</div>";
            } else {
                $update_sql = "UPDATE programming_languages SET name='{$pending_language['name']}' WHERE id={$pending_language['id']}";
                if ($conn->query($update_sql) === TRUE) {
                    $conn->query("DELETE FROM programming_languages_pending WHERE id='$language_id'");
                    echo "<div class='alert success'>Навык добавлен.</div>";
                } else {
                    echo "<div class='alert error'>Ошибка при добавлении навыка: " . $conn->error . "</div>";
                }
            }
        } else {
            echo "<div class='alert error'>Навык не найден или уже одобрен.</div>";
        }
    } elseif ($_POST['action'] === 'reject_language') {
        $conn->query("DELETE FROM programming_languages_pending WHERE id='$language_id'");
        echo "<div class='alert info'>Заявка отклонена.</div>";
    } elseif ($_POST['action'] === 'delete_language') {
        $conn->query("DELETE FROM applications WHERE id = '$language_id'");
        
        $sql = "DELETE FROM programming_languages WHERE id='$language_id'";
        if ($conn->query($sql) === TRUE) {
            echo "<div class='alert success'>Навык удален.</div>";
        } else {
            echo "<div class='alert error'>Ошибка при удалении навыка: " . $conn->error . "</div>";
        }
    }
}

// Получение весов из базы данных
$weightsQuery = $conn->query("SELECT * FROM parameter_weights");
$weights = [];
while ($row = $weightsQuery->fetch_assoc()) {
    $weights[$row['parameter_name']] = $row['weight'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'update_weights') {
    $programming_weight = isset($_POST['programming_weight']) ? (int)$_POST['programming_weight'] : null;
    $salary_weight = isset($_POST['salary_weight']) ? (int)$_POST['salary_weight'] : null;
    $location_weight = isset($_POST['location_weight']) ? (int)$_POST['location_weight'] : null;

    $valid = true;
    $messages = [];

    if ($programming_weight < 0 || $programming_weight > 3) {
        $valid = false;
        $messages[] = "Вес для языков программирования должен быть от 0 до 3.";
    }
    if ($salary_weight < 0 || $salary_weight > 3) {
        $valid = false;
        $messages[] = "Вес для зарплаты должен быть от 0 до 3.";
    }
    if ($location_weight < 0 || $location_weight > 3) {
        $valid = false;
        $messages[] = "Вес для местоположения должен быть от 0 до 3.";
    }

    if ($valid) {
        $updateQueries = [
            "UPDATE parameter_weights SET weight='$programming_weight' WHERE parameter_name='programming_languages'",
            "UPDATE parameter_weights SET weight='$salary_weight' WHERE parameter_name='salary'",
            "UPDATE parameter_weights SET weight='$location_weight' WHERE parameter_name='location'"
        ];

        foreach ($updateQueries as $updateQuery) {
            $conn->query($updateQuery);
        }

        echo "<div class='alert success'>Веса успешно обновлены.</div>";
    } else {
        foreach ($messages as $message) {
            echo "<div class='alert error'>$message</div>";
        }
    }
}

$limit = 5;
$page1 = isset($_GET['page1']) ? intval($_GET['page1']) : 1;
$offset1 = ($page1 - 1) * $limit;

$totalQuery1 = $conn->query("
    SELECT COUNT(*) AS total 
    FROM users WHERE role IN ('user', 'employer')
");
$total1 = $totalQuery1->fetch_assoc()['total'];
$totalPages1 = ceil($total1 / $limit);

$users = $conn->query("
    SELECT * FROM users WHERE role IN ('user', 'employer')
    LIMIT $limit OFFSET $offset1
");

$pending_jobs = $conn->query("SELECT * FROM job_listings_pending");

$page2 = isset($_GET['page2']) ? intval($_GET['page2']) : 1;
$offset2 = ($page2 - 1) * $limit;

$totalQuery2 = $conn->query("
    SELECT COUNT(*) AS total 
    FROM job_listings 
    WHERE status = 'approved'
");
$total2 = $totalQuery2->fetch_assoc()['total'];
$totalPages2 = ceil($total2 / $limit);

$all_jobs = $conn->query("
    SELECT * FROM job_listings WHERE status='approved'
    LIMIT $limit OFFSET $offset2
");

$pending_languages = $conn->query("SELECT * FROM programming_languages_pending");
$page3 = isset($_GET['page3']) ? intval($_GET['page3']) : 1;
$offset3 = ($page3 - 1) * $limit;

$totalQuery3 = $conn->query("
    SELECT COUNT(*) AS total 
    FROM programming_languages
");
$total3 = $totalQuery3->fetch_assoc()['total'];
$totalPages3 = ceil($total3 / $limit);

$all_languages = $conn->query("
    SELECT * FROM programming_languages
    LIMIT $limit OFFSET $offset3
");
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            line-height: 1.6;
        }
        header {
            background: #007bff;
            color: white;
            padding: 15px 0;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        nav a {
            color: white;
            margin: 0 15px;
            text-decoration: none;
            font-weight: bold;
        }
        nav a:hover {
            text-decoration: underline;
        }
        .container {
            max-width: 900px;
            margin: 20px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        h1, h2, h3 {
            color: #333;
            margin-bottom: 10px;
        }
        footer {
            text-align: center;
            padding: 15px 0;
            background: #f1f1f1;
            margin-top: 20px;
            border-top: 1px solid #ddd;
            position: relative;
            bottom: 0;
            width: 100%;
        }
        .alert {
            margin: 10px 0;
            padding: 15px;
            border-radius: 5px;
            font-size: 16px;
        }
        .alert.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert.info {
            background-color: #cce5ff;
            color: #004085;
            border: 1px solid #b8daff;
        }
        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .job-item, .user-item {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 15px;
            background-color: #f9f9f9;
            transition: background-color 0.3s;
        }
        .job-item:hover, .user-item:hover {
            background-color: #f1f1f1;
        }
        button {
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 10px 15px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #0056b3;
        }
        .tabs {
            display: flex;
            margin-bottom: 15px;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px 5px 0 0;
            margin-right: 5px;
        }
        .tab:hover {
            background-color: #0056b3;
        }
        .tab-content {
            display: none;
            border: 1px solid #ddd;
            border-radius: 0 0 5px 5px;
            padding: 15px;
            background-color: white;
        }
        .active {
            display: block;
        }
        #weights {
        padding: 20px;
        background-color: #f9f9f9;
        border-radius: 8px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        max-width: 500px;
        margin: 20px auto;
        font-family: Arial, sans-serif;
    }

    #weights h3 {
        font-size: 24px;
        color: #333;
        text-align: center;
        margin-bottom: 20px;
    }

    .weights-form .form-group {
        margin-bottom: 15px;
    }

    .weights-form label {
        font-size: 16px;
        color: #555;
        display: block;
        margin-bottom: 5px;
    }

    .weights-form input[type="number"] {
        width: 100%;
        padding: 8px;
        font-size: 16px;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-sizing: border-box;
        outline: none;
        transition: border-color 0.3s ease;
    }

    .weights-form input[type="number"]:focus {
        border-color: #007bff;
    }

    .weights-form .btn-submit {
        width: 100%;
        padding: 10px;
        font-size: 16px;
        background-color: #007bff;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    .weights-form .btn-submit:hover {
        background-color: #0056b3;
    }
    .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        .pagination a {
            padding: 10px 15px;
            margin: 0 5px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        .pagination a:hover {
            background-color: #0056b3;
        }
        .pagination .active {
            background-color: #0056b3;
            pointer-events: none;
        }
    </style>
    <script>
        function showTab(tabName) {
            var tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(function(tab) {
                tab.classList.remove('active');
            });
            document.getElementById(tabName).classList.add('active');
        }
    </script>
</head>
<body>
    <header>
        <h1>Личный кабинет</h1>
        <nav>
            <a href="index.php">Главная</a>
            <a href="logout.php">Выход</a>
        </nav>
    </header>
    <div class="container">
        <h2>Добро пожаловать, <?= htmlspecialchars($user['username']) ?>!</h2>

        <div class="tabs">
            <button class="tab" onclick="showTab('jobs')">Управление вакансиями</button>
            <button class="tab" onclick="showTab('users')">Управление пользователями</button>
            <button class="tab" onclick="showTab('languages')">Управление навыками</button>
            <button class="tab" onclick="showTab('weights')">Управление весами</button>
        </div>

        <div id="jobs" class="tab-content active">
            <h3>Ожидающие проверки</h3>
            <ul>
                <?php while ($row = $pending_jobs->fetch_assoc()): ?>
                    <li class="job-item">
                        <strong>Название:</strong> <?= htmlspecialchars($row['title']) ?><br>
                        <strong>Описание:</strong> <?= htmlspecialchars($row['description']) ?><br>
                        <strong>Требования:</strong> <?= htmlspecialchars($row['requirements']) ?><br>
                        <strong>Ключевые навыки:</strong> <?= htmlspecialchars($row['programming_languages']) ?><br>
                        <strong>Зарплата:</strong> <?= htmlspecialchars($row['salary']) ?><br>
                        <strong>Местоположение:</strong> <?= htmlspecialchars($row['location']) ?><br>
                        <form action="admin_dashboard.php" method="POST">
                            <input type="hidden" name="job_id" value="<?= $row['job_listing_id'] ?>">
                            <button type="submit" name="action" value="approve">Одобрить</button>
                            <button type="submit" name="action" value="reject">Отклонить</button>
                        </form>
                    </li>
                <?php endwhile; ?>
            </ul>

            <h3>Все вакансии</h3>
            <ul>
                <?php while ($row = $all_jobs->fetch_assoc()): ?>
                    <li class="job-item">
                        <strong>Название:</strong> <?= htmlspecialchars($row['title']) ?><br>
                        <strong>Описание:</strong> <?= htmlspecialchars($row['description']) ?><br>
                        <strong>Требования:</strong> <?= htmlspecialchars($row['requirements']) ?><br>
                        <strong>Ключевые навыки:</strong> <?= htmlspecialchars($row['programming_languages']) ?><br>
                        <strong>Зарплата:</strong> <?= htmlspecialchars($row['salary']) ?><br>
                        <strong>Местоположение:</strong> <?= htmlspecialchars($row['location']) ?><br>
                        <form action="admin_dashboard.php" method="POST">
                            <input type="hidden" name="job_id" value="<?= $row['id'] ?>">
                            <button type="submit" name="action" value="delete_job">Удалить</button>
                        </form>
                    </li>
                <?php endwhile; ?>
            </ul>
            <div class="pagination">
            <?php if ($page2 > 1): ?>
                <a href="?tab=jobs&page2=<?= $page2 - 1 ?>">&laquo; Предыдущая</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages2; $i++): ?>
                <a href="?tab=jobs&page2=<?= $i ?>" class="<?= $i == $page2 ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page2 < $totalPages2): ?>
                <a href="?tab=jobs&page2=<?= $page2 + 1 ?>">Следующая &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
        </div>

        <div id="languages" class="tab-content">
            <h3>Ожидающие проверки</h3>
            <ul>
                <?php while ($row = $pending_languages->fetch_assoc()): ?>
                    <li class="language-item">
                        <strong>Название:</strong> <?= htmlspecialchars($row['name']) ?><br>
                        <form action="admin_dashboard.php" method="POST">
                            <input type="hidden" name="language_id" value="<?= $row['id'] ?>">
                            <button type="submit" name="action" value="approve_language">Одобрить</button>
                            <button type="submit" name="action" value="reject_language">Отклонить</button>
                        </form>
                    </li>
                <?php endwhile; ?>
            </ul>

            <h3>Ключевые навыки</h3>
            <ul>
                <?php while ($row = $all_languages->fetch_assoc()): ?>
                    <li class="language-item">
                        <strong>Название:</strong> <?= htmlspecialchars($row['name']) ?><br>
                        <form action="admin_dashboard.php" method="POST">
                            <input type="hidden" name="language_id" value="<?= $row['id'] ?>">
                            <button type="submit" name="action" value="delete_language">Удалить</button>
                        </form>
                    </li>
                <?php endwhile; ?>
            </ul>
            <div class="pagination">
            <?php if ($page3 > 1): ?>
                <a href="?tab=languages&page3=<?= $page3 - 1 ?>">&laquo; Предыдущая</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages3; $i++): ?>
                <a href="?tab=languages&page3=<?= $i ?>" class="<?= $i == $page3 ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page3 < $totalPages3): ?>
                <a href="?tab=languages&page3=<?= $page3 + 1 ?>">Следующая &raquo;</a>
            <?php endif; ?>
        </div>
        </div>

        <div id="users" class="tab-content">
            <h3>Список пользователей</h3>
            <ul>
                <?php while ($userRow = $users->fetch_assoc()): ?>
                    <li class="user-item">
                        <strong>Имя пользователя:</strong> <?= htmlspecialchars($userRow['username']) ?><br>
                        <strong>Email:</strong> <?= htmlspecialchars($userRow['email']) ?><br>
                        <form action="admin_dashboard.php" method="POST">
                            <input type="hidden" name="user_id" value="<?= $userRow['id'] ?>">
                            <?php if ($userRow['is_blocked']): ?>
                                <button type="submit" name="action" value="unblock_user">Разблокировать</button>
                            <?php else: ?>
                                <button type="submit" name="action" value="block_user">Заблокировать</button>
                            <?php endif; ?>
                        </form>
                    </li>
                <?php endwhile; ?>
            </ul>
            <div class="pagination">
            <?php if ($page1 > 1): ?>
                <a href="?tab=users&page1=<?= $page1 - 1 ?>">&laquo; Предыдущая</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages1; $i++): ?>
                <a href="?tab=users&page1=<?= $i ?>" class="<?= $i == $page1 ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page1 < $totalPages1): ?>
                <a href="?tab=users&page1=<?= $page1 + 1 ?>">Следующая &raquo;</a>
            <?php endif; ?>
        </div>
        </div>
    </div>
    <div id="weights" class="tab-content">
    <h3>Управление весами для рекомендаций</h3>
    <form action="admin_dashboard.php" method="POST" class="weights-form">
        <input type="hidden" name="action" value="update_weights">

        <div class="form-group">
            <label for="programming_weight">Вес для языков программирования:</label>
            <input type="number" name="programming_weight" id="programming_weight" 
                   value="<?= htmlspecialchars($weights['programming_languages'] ?? 0) ?>" required>
        </div>

        <div class="form-group">
            <label for="salary_weight">Вес для зарплаты:</label>
            <input type="number" name="salary_weight" id="salary_weight" 
                   value="<?= htmlspecialchars($weights['salary'] ?? 0) ?>" required>
        </div>

        <div class="form-group">
            <label for="location_weight">Вес для местоположения:</label>
            <input type="number" name="location_weight" id="location_weight" 
                   value="<?= htmlspecialchars($weights['location'] ?? 0) ?>" required>
        </div>

        <button type="submit" class="btn-submit">Обновить веса</button>
    </form>
</div>

<script>
    function switchTab(tabName) {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tabName);
        window.location.href = url.toString();
    }

    document.addEventListener('DOMContentLoaded', () => {
        const tab = new URL(window.location.href).searchParams.get('tab') || 'jobs';
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        document.getElementById(tab).classList.add('active');
    });
</script>
    <footer>
        <p>&copy; <?= date("Y") ?> Все права защищены.</p>
    </footer>
    <script>
        showTab('jobs');
    </script>
</body>
</html>

<?php
$conn->close();
?>