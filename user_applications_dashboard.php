<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
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

$limit = 5;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$totalQuery = $conn->query("
    SELECT COUNT(*) AS total 
    FROM applications 
    WHERE user_id = '$user_id'
");
$total = $totalQuery->fetch_assoc()['total'];
$totalPages = ceil($total / $limit);

$applications = $conn->query("
    SELECT a.id, a.application_date, jl.title, a.status, users.username
    FROM applications a 
    JOIN job_listings jl ON a.job_listing_id = jl.id 
    JOIN users ON jl.employer_id = users.id
    WHERE a.user_id = '$user_id'
    ORDER BY a.application_date DESC
    LIMIT $limit OFFSET $offset
");

if ($applications === false) {
    die("<div class='alert error'>Ошибка выполнения запроса: " . $conn->error . "</div>");
}

// Удаление отклика
if (isset($_GET['delete_application'])) {
    $application_id = intval($_GET['delete_application']);
    
    // Проверка существования отклика
    $check_sql = "SELECT * FROM applications WHERE id='$application_id'";
    $check_result = $conn->query($check_sql);
    
    if ($check_result->num_rows > 0) {
        $delete_sql = "DELETE FROM applications WHERE id='$application_id'";
        if ($conn->query($delete_sql) === TRUE) {
            header("Location: user_applications_dashboard.php");
            exit();
        } else {
            echo "<div class='alert error'>Ошибка при удалении отклика: " . $conn->error . "</div>";
        }
    } else {
        echo "<div class='alert error'>Отклик не найден.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои Отклики</title>
    <style>
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
        h1, h2 {
            color: #333;
            margin-bottom: 20px;
        }
        .alert {
            color: green;
            margin: 10px 0;
            padding: 10px;
            border: 1px solid green;
            border-radius: 5px;
            background-color: #d4edda;
        }
        .alert.error {
            color: red;
            border-color: #f5c6cb;
            background-color: #f8d7da;
        }
        footer {
            text-align: center;
            padding: 15px 0;
            background: #f1f1f1;
            margin-top: 20px;
            border-top: 1px solid #ddd;
        }
        ul {
            list-style-type: none;
            padding: 0;
        }
        li {
            padding: 15px;
            border: 1px solid #ddd;
            margin: 10px 0;
            border-radius: 5px;
            background-color: #f9f9f9;
            position: relative;
        }
        
        /* Стили кнопок */
        .button, .delete-button {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            color: white;
            transition: background-color 0.3s;
            display: inline-block;
        }
        .button {
            background-color: #007bff;
        }
        .button:hover {
            background-color: #0056b3;
        }
        .delete-button {
            background-color: #d9534f;
            position: absolute;
            right: 15px;
            top: 15px;
        }
        .delete-button:hover {
            background-color: #c82333;
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
        <h1>Мои Отклики</h1>
        <nav>
            <a href="index.php">Главная</a>
            <a href="logout.php">Выход</a>
        </nav>
    </header>
    <div class="container">
        <h2>Ваши отклики на вакансии</h2>
        <ul>
            <?php while ($row = $applications->fetch_assoc()): ?>
                <li>
                    <strong>Вакансия:</strong> <?= htmlspecialchars($row['title']) ?><br>
                    <strong>Работодатель:</strong> <?= htmlspecialchars($row['username']) ?><br>
                    <strong>Дата отклика:</strong> <?= htmlspecialchars($row['application_date']) ?><br>
                    <strong>Статус:</strong> <?= htmlspecialchars($row['status']) ?><br>
                    <a href="?delete_application=<?= $row['id'] ?>" class="delete-button">Удалить</a>
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
        <a href="user_dashboard.php" class="button">Вернуться в личный кабинет</a>
    </div>
    <footer>
        <p>&copy; <?= date("Y") ?> Все права защищены.</p>
    </footer>
</body>
</html>

<?php
$conn->close();
?>