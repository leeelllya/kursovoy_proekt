<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "birzha_truda";

// Создание соединения
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Ошибка соединения: " . $conn->connect_error);
    }
} catch (Exception $e) {
    die("<div class='alert error'>Не удалось подключиться к базе данных. Пожалуйста, проверьте состояние сервера MySQL.</div>");
}

$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $username = trim($conn->real_escape_string($_POST['username']));
    $email = trim($conn->real_escape_string($_POST['email']));
    $password = $_POST['password'];
    $role = trim($conn->real_escape_string($_POST['role']));

    // Валидация
    if (empty($username) || empty($email) || empty($password) || empty($role)) {
        $errors[] = "Все поля должны быть заполнены.";
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Введите корректный адрес электронной почты.";
    }

    if (!empty($password) && (!preg_match('/^[a-zA-Z0-9]*$/', $password) || 
                              !preg_match('/[A-Z]/', $password) || 
                              !preg_match('/[0-9]/', $password) || 
                              strlen($password) < 8)) {
        $errors[] = "Пароль должен содержать только латинские буквы и цифры, содержать хотя бы одну заглавную букву и цифру и состоять не менее, чем из 8 символов.";
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $checkEmail = $conn->query("SELECT * FROM users WHERE email='$email'");
        if ($checkEmail === FALSE) {
            $errors[] = "Ошибка проверки email: " . $conn->error;
        } elseif ($checkEmail->num_rows == 0) {
            $sql = "INSERT INTO users (username, email, password, role) VALUES ('$username', '$email', '$hashed_password', '$role')";
            if ($conn->query($sql) === TRUE) {
                $_SESSION['user_id'] = $conn->insert_id;
                $_SESSION['role'] = $role;
                
                // Перенаправление на соответствующую страницу
                if ($role === 'admin') {
                    header("Location: admin_dashboard.php");
                } elseif ($role === 'employer') {
                    header("Location: employer_dashboard.php");
                } else {
                    header("Location: user_dashboard.php");
                }
                exit();
            } else {
                $errors[] = "Ошибка регистрации: " . $conn->error;
            }
        } else {
            $errors[] = "Пользователь с таким email уже существует.";
        }
    }
}

$conn->close();
?>


<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация</title>
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
        input, select {
            display: block;
            margin: 10px 0;
            width: calc(100% - 22px);
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        button {
            background-color: #28a745;
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
            background-color: #218838;
        }
        .error {
            color: red;
            font-size: 14px;
            text-align: center;
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
    </style>
</head>
<body>
    <div class="container">
        <h2>Регистрация</h2>
        
        <?php if (!empty($errors)): ?>
            <ul class="error-list">
                <?php foreach ($errors as $error): ?>
                    <li class="error"><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form action="register.php" method="POST">
            <input type="text" name="username" placeholder="Имя пользователя" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Пароль" required>
            
            <label for="role">Выберите роль:</label>
            <select name="role" id="role" required>
                <option value="user">Обычный пользователь</option>
                <option value="employer">Работодатель</option>
            </select>
            
            <button type="submit" name="register">Зарегистрироваться</button>
        </form>
        <a href="login.php">Уже есть аккаунт? Войдите!</a>
    </div>
</body>
</html>