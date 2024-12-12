<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "birzha_truda";

// Создание соединения
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Проверка соединения
    if ($conn->connect_error) {
        throw new Exception("Ошибка соединения: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("<div class='alert error'>Не удалось подключиться к базе данных. Пожалуйста, проверьте состояние сервера MySQL.</div>");
}

// Массив для хранения сообщений об ошибках
$errors = [];

// Проверка, была ли отправлена форма
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conn->real_escape_string(trim($_POST['email']));
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    // Получение данных о пользователе
    $sql = "SELECT * FROM users WHERE email='$email'";
    $result = $conn->query($sql);

    if ($result === FALSE) {
        $errors[] = "Ошибка выполнения запроса: " . $conn->error;
    } elseif ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        // Проверка пароля
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role']; // Сохраняем роль в сессии

            // Если выбрана опция "Запомнить меня"
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expiry = time() + (30 * 24 * 60 * 60); // Токен действителен 30 дней
            
                // Сохранение токена в базе данных
                $updateTokenSql = "UPDATE users SET remember_token='$token' WHERE id='{$user['id']}'";
                if ($conn->query($updateTokenSql) === FALSE) {
                    $errors[] = "Не удалось сохранить токен: " . $conn->error;
                } else {
                    // Установка cookie с токеном
                    if (setcookie("remember_token", $token, $expiry, "/", "", true, true)) {
                        echo "Кука создана.<br>";
                    } else {
                        echo "Не удалось создать куку.<br>";
                    }
                }
            }

            // Перенаправление на соответствующую панель в зависимости от роли
            if ($user['role'] === 'admin') {
                header("Location: admin_dashboard.php");
            } elseif ($user['role'] === 'employer') {
                header("Location: employer_dashboard.php");
            } else {
                header("Location: user_dashboard.php");
            }
            exit();
        } else {
            $errors[] = "Неверный пароль.";
        }
    } else {
        $errors[] = "Пользователь с таким email не найден.";
    }
}

// Проверка токена при загрузке страницы
if (isset($_COOKIE['remember_token'])) {
    $token = $conn->real_escape_string($_COOKIE['remember_token']);
    $sql = "SELECT * FROM users WHERE remember_token='$token'";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];

        // Перенаправление на соответствующую панель
        if ($user['role'] === 'admin') {
            header("Location: admin_dashboard.php");
        } elseif ($user['role'] === 'employer') {
            header("Location: employer_dashboard.php");
        } else {
            header("Location: user_dashboard.php");
        }
        exit();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            width: 300px;
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
        input {
            display: block;
            margin: 10px 0;
            width: calc(100% - 22px);
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #0056b3;
        }
        .error {
            color: red;
            font-size: 14px;
            text-align: center;
            margin-bottom: 10px;
        }
        .error-list {
            list-style-type: none;
            padding: 0;
            margin: 10px 0;
            color: red;
        }
        a {
            display: block;
            text-align: center;
            margin-top: 10px;
            color: #007bff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .remember {
            display: flex;
            align-items: center;
            font-size: 14px;
        }
        .remember input {
            margin-right: 5px;
            width: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Вход</h2>
        
        <?php if (!empty($errors)): ?>
            <ul class="error-list">
                <?php foreach ($errors as $error): ?>
                    <li class="error"><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Пароль" required>
            <div class="remember">
                <input type="checkbox" name="remember" id="remember">
                <label for="remember">Запомнить меня</label>
            </div>
            <button type="submit" name="login">Войти</button>
        </form>
        <a href="register.php">Нет аккаунта? Зарегистрируйтесь!</a>
    </div>
</body>
</html>
