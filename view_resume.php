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

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("<div class='error'>Ошибка соединения: " . $conn->connect_error . "</div>");
}

function renderError($message) {
    echo "<!DOCTYPE html>
    <html lang='ru'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Ошибка</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f8d7da;
                color: #721c24;
                margin: 0;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                text-align: center;
            }
            .error-container {
                background-color: #fff;
                padding: 20px 30px;
                border-radius: 10px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                border: 1px solid #f5c6cb;
            }
            h1 {
                font-size: 24px;
                margin-bottom: 10px;
            }
            p {
                font-size: 16px;
            }
        </style>
    </head>
    <body>
        <div class='error-container'>
            <h1>Ошибка</h1>
            <p>$message</p>
        </div>
    </body>
    </html>";
    exit;
}

if (isset($_GET['user_id'])) {
    $user_id = intval($_GET['user_id']);
    $userQuery = $conn->query("SELECT resume_path FROM users WHERE id='$user_id'");
    $user = $userQuery->fetch_assoc();

    if ($user && !empty($user['resume_path'])) {
        $filePath = $user['resume_path'];
        if (file_exists($filePath)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
            readfile($filePath);
            exit;
        } else {
            renderError("Резюме не найдено. Возможно, файл был удален.");
        }
    } else {
        renderError("Резюме еще не загружено.");
    }
} else {
    renderError("Некорректный запрос. Проверьте параметры URL.");
}
?>
