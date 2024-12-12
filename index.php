<?php
session_start();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Главная страница</title>
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
            margin-bottom: 10px;
        }
        p {
            line-height: 1.6;
            color: #555;
        }
        footer {
            text-align: center;
            padding: 15px 0;
            background: #f1f1f1;
            margin-top: 20px;
            border-top: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <header>
        <h1>Добро пожаловать на главную страницу</h1>
        <nav>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <a href="login.php">Вход</a>
                <a href="register.php">Регистрация</a>
            <?php else: ?>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="admin_dashboard.php">Личный кабинет</a>
                <?php elseif ($_SESSION['role'] === 'employer'): ?>
                    <a href="employer_dashboard.php">Личный кабинет</a>
                <?php elseif ($_SESSION['role'] === 'user'): ?>
                    <a href="user_dashboard.php">Личный кабинет</a>
                <?php else: ?>
                    <span>Неизвестная роль пользователя.</span>
                <?php endif; ?>
                <a href="logout.php">Выход</a>
            <?php endif; ?>
        </nav>
    </header>
    <div class="container">
        <h2>Описание приложения</h2>
        <p>
            Это приложение позволяет пользователям регистрироваться, входить в систему и управлять своими резюме.
            Используйте навигацию выше, чтобы перемещаться по сайту.
        </p>
        <p>
            Если у вас возникли вопросы, пожалуйста, обратитесь в службу поддержки.
        </p>
    </div>
    <footer>
        <p>&copy; <?= date("Y") ?> Все права защищены.</p>
    </footer>
</body>
</html>