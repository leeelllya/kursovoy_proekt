<?php
session_start();

// Удаление всех сессионных переменных
$_SESSION = [];

// Уничтожение сессии
session_destroy();

// Удаление куки "remember_token", если она существует
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');  // Устанавливаем истекший срок действия куки
}

// Перенаправление на страницу входа или главную страницу
header("Location: index.php");
exit();
?>
