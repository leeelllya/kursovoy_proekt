<?php  
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employer') {
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
    die("<div class='alert error'>Не удалось подключиться к базе данных.</div>");
}

$user_id = $_SESSION['user_id'];
$userQuery = $conn->query("SELECT username FROM users WHERE id='$user_id'");
$user = $userQuery->fetch_assoc();


$limit = 5;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$totalQuery = $conn->query("
SELECT COUNT(*) AS total
FROM applications a
JOIN job_listings jl ON a.job_listing_id = jl.id
JOIN users u ON a.user_id = u.id;
");
$total = $totalQuery->fetch_assoc()['total'];
$totalPages = ceil($total / $limit);

$applications = $conn->query("
        SELECT a.id AS application_id, a.application_date, a.status, jl.title, u.username 
    FROM applications a 
    JOIN job_listings jl ON a.job_listing_id = jl.id 
    JOIN users u ON a.user_id = u.id 
    WHERE jl.employer_id = '$user_id'
    LIMIT $limit OFFSET $offset
");


// Проверка успешности выполнения запроса
if ($applications === false) {
    die("<div class='alert error'>Ошибка выполнения запроса: " . $conn->error . "</div>");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $application_id = intval($_POST['application_id']);
    if (isset($_POST['approve_application'])) {
        $updateQuery = "UPDATE applications SET status = 'approved' WHERE id = $application_id";
        $conn->query($updateQuery);
    } elseif (isset($_POST['reject_application'])) {
        $updateQuery = "UPDATE applications SET status = 'rejected' WHERE id = $application_id";
        $conn->query($updateQuery);
    }
    header("Location: applications_dashboard.php");
    exit();
}


?>


<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отклики на Вакансии</title>
    <style>
        /* Основные стили */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            line-height: 1.6;
            color: #333;
        }
        header {
            background-color: #007bff;
            color: white;
            padding: 15px 0;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
        h2, h3 {
            color: #333;
        }
        ul {
            list-style-type: none;
            padding: 0;
        }
        li {
            padding: 15px;
            border: 1px solid #ddd;
            margin-bottom: 10px;
            border-radius: 5px;
            background-color: #f9f9f9;
            position: relative;
        }

        /* Статус отклика */
        .status {
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 5px;
            display: inline-block;
        }
        .status.pending {
            color: #856404;
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
        }
        .status.approved {
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }
        .status.rejected {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }

        /* Кнопки управления статусом */
        .action-buttons {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }
        .approve-btn, .reject-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            color: white;
            transition: background-color 0.3s;
            text-decoration: none;
        }
        .approve-btn {
            background-color: #28a745;
        }
        .approve-btn:hover {
            background-color: #218838;
        }
        .reject-btn {
            background-color: #dc3545;
        }
        .reject-btn:hover {
            background-color: #c82333;
        }
        footer {
            text-align: center;
            padding: 15px 0;
            background: #f1f1f1;
            margin-top: 20px;
            border-top: 1px solid #ddd;
        }
        .view-resume-btn {
    display: inline-block;
    padding: 10px 20px;
    background-color: #007bff;
    color: white;
    text-decoration: none;
    border-radius: 5px;
    transition: background-color 0.3s ease;
    font-size: 14px;
    font-weight: bold;
}

.view-resume-btn:hover {
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
</head>
<body>
    <header>
        <h1>Отклики на Вакансии</h1>
        <nav>
            <a href="index.php">Главная</a>
            <a href="employer_dashboard.php">Личный кабинет</a>
            <a href="logout.php">Выход</a>
        </nav>
    </header>
    <div class="container">
    <h2>Добро пожаловать, <?= htmlspecialchars($user['username']) ?>!</h2>
        <h3>Ваши отклики на вакансии</h3>
        <ul>
        <?php while ($row = $applications->fetch_assoc()): ?>
    <li>
        <strong>Кандидат:</strong> <?= htmlspecialchars($row['username']) ?><br>
        <strong>Вакансия:</strong> <?= htmlspecialchars($row['title']) ?><br>
        <strong>Дата отклика:</strong> <?= htmlspecialchars($row['application_date']) ?><br>
        <strong>Статус:</strong> 
        <span class="status <?= htmlspecialchars($row['status']) ?>">
            <?= $row['status'] === 'pending' ? 'В ожидании' : ($row['status'] === 'approved' ? 'Одобрено' : 'Отклонено') ?>
        </span>

        <?php if ($row['status'] == 'pending'): ?>
            <div class="action-buttons">
                <form action="applications_dashboard.php" method="POST" style="display:inline;">
                    <input type="hidden" name="application_id" value="<?= $row['application_id'] ?>">
                    <button type="submit" name="approve_application" class="approve-btn">Одобрить</button>
                    <button type="submit" name="reject_application" class="reject-btn">Отклонить</button>
                </form>
            </div>
        <?php endif; ?>

<?php if (!empty($row['resume_path'])): ?>
    <a href="view_resume.php?user_id=<?= $row['user_id'] ?>" class="view-resume-btn">Просмотреть резюме</a>
<?php endif; ?>


    </li>
<?php endwhile; ?>
        </ul>
        <?php if ($applications->num_rows == 0): ?>
            <p>Нет откликов на ваши вакансии.</p>
        <?php endif; ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>">&laquo; Предыдущая</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>">Следующая &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <footer>
        <p>&copy; <?= date("Y") ?> Все права защищены.</p>
    </footer>
</body>
</html>

<?php
$conn->close();
?>
