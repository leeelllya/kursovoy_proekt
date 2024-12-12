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

// Получение информации о пользователе
$user_id = $_SESSION['user_id'];
$userQuery = $conn->query("SELECT username, email, is_blocked, resume_path FROM users WHERE id='$user_id'");
$user = $userQuery->fetch_assoc();

if ($user['is_blocked']) {
    echo "<div class='alert error'>Ваш аккаунт заблокирован. Обратитесь к администратору для получения дополнительной информации.</div>";
    exit();
}

$limit = 5;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$totalQuery = $conn->query("
    SELECT COUNT(*) AS total 
    FROM job_listings 
    WHERE status = 'approved'
");
$total = $totalQuery->fetch_assoc()['total'];
$totalPages = ceil($total / $limit);

$jobs = $conn->query("
    SELECT * FROM job_listings WHERE status='approved'
    LIMIT $limit OFFSET $offset
");

// Обработка отклика на вакансию
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['apply'])) {
    $job_listing_id = intval($_POST['job_listing_id']);
    try {
        $stmt = $conn->prepare("INSERT INTO applications (job_listing_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $job_listing_id, $user_id);
        if (!$stmt->execute()) {
            throw new Exception("Ошибка при отклике на вакансию: " . $stmt->error);
        }
        echo "<div class='alert'>Вы успешно откликнулись на вакансию!</div>";
    } catch (Exception $e) {
        echo "<div class='alert error'>" . $e->getMessage() . "</div>";
    }
}

// Обработка поиска вакансий
$searchResults = null; 
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search'])) {
    try {
        $searchQuery = $conn->real_escape_string(trim($_POST['search_query']));
        
        // Выполнение запроса с фильтрацией по статусу и зарплате
        $searchResults = $conn->query("SELECT * FROM job_listings WHERE status='approved' AND (title LIKE '%$searchQuery%' OR description LIKE '%$searchQuery%' OR requirements LIKE '%$searchQuery%')");
        
        // Проверка на ошибки выполнения запроса
        if ($searchResults === FALSE) {
            throw new Exception("Ошибка поиска вакансий: " . $conn->error);
        }
    } catch (Exception $e) {
        echo "<div class='alert error'>" . $e->getMessage() . "</div>";
    }
}

// Обработка фильтрации по зарплате
$filteredJobs = null;
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['filter_salary'])) {
    $minSalary = intval($_POST['min_salary']);
    $maxSalary = intval($_POST['max_salary']);
    
    try {
        $filteredJobs = $conn->query("SELECT * FROM job_listings WHERE status='approved' AND salary BETWEEN $minSalary AND $maxSalary");
        
        // Проверка на ошибки выполнения запроса
        if ($filteredJobs === FALSE) {
            throw new Exception("Ошибка фильтрации вакансий: " . $conn->error);
        }
    } catch (Exception $e) {
        echo "<div class='alert error'>" . $e->getMessage() . "</div>";
    }
}

function isppt($filePath, $fileType): bool {
    if ($fileType === 'pdf') {
        $fileContent = file_get_contents($filePath, false, null, 0, 1024);
        
        // Check for PDF header
        if (stripos($fileContent, '%PDF-') !== 0) {
            unlink($filePath);
            throw new Exception("Ошибка: файл поврежден или не является допустимой презентацией.");
            return false;
        }
        
        return true;
    } else {
        unlink($filePath);
        throw new Exception("Ошибка: неизвестный формат файла.");
        return false;
    }
}

$uploadDir = 'uploads/';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['resume'])) {
    $uploadDir = 'uploads/';
    $file = $_FILES['resume'];
    $fileName = basename($file['name']);
    $filePath = $uploadDir . $fileName;

    try {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Ошибка при загрузке файла: " . $file['error']);
        }

        $fileType = pathinfo($filePath, PATHINFO_EXTENSION);
        $allowedTypes = ['pdf'];
        if (!in_array(strtolower($fileType), $allowedTypes)) {
            throw new Exception("Ошибка: разрешены только файлы PDF.");
        }

        $maxFileSize = 10 * 1024 * 1024;
        if ($file['size'] > $maxFileSize) {
            throw new Exception("Ошибка: размер файла превышает допустимый лимит в 10 МБ.");
        }

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            throw new Exception("Ошибка: невозможно создать папку для загрузки.");
        }
        if (!is_writable($uploadDir)) {
            throw new Exception("Ошибка: нет прав на запись в папку для загрузок.");
        }
        if (is_dir($filePath)) {
            throw new Exception("Ошибка: директория с именем загружаемого файла уже существует.");
        }

        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception("Ошибка: не удалось сохранить файл.");
        }

        if (!$fileHandle = fopen($filePath, 'r')) {
            throw new Exception("Ошибка: файл поврежден или недоступен для чтения.");
        }
        fclose($fileHandle);

        if (isppt($filePath, $fileType)) {
            $relativePath = 'uploads/' . $fileName;
            $stmt = $conn->prepare("UPDATE users SET resume_path = ? WHERE id = ?");
            $stmt->bind_param("si", $relativePath, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Ошибка базы данных при сохранении пути к резюме.");
            }
        }
          

        echo "<div class='alert'>Резюме успешно загружена!</div>";
    } catch (Exception $e) {
        echo "<div class='alert error'>" . htmlspecialchars($e->getMessage()) . "</div>";
    }
}




if (isset($_POST['open_resume'])) {
    $fullPath = $user['resume_path'];
    $fileType = pathinfo($fullPath, PATHINFO_EXTENSION);
   //$fileContent = file_get_contents($fullPath, false, null, 0, 1024); 
    

    try {
        if (empty($user['resume_path'])) {
            throw new Exception("Резюме еще не загружена.");
        }

        if (!file_exists($fullPath)) {
            throw new Exception("Файл с резюме не найден или был удален.");
        }

        if (!is_readable($fullPath) || !is_writable($fullPath)) {
            throw new Exception("Ошибка: нет прав доступа к файлу.");
        }

        $uploadDir = dirname($fullPath);
        if (!is_dir($uploadDir) || !is_readable($uploadDir)) {
            throw new Exception("Ошибка: директория загрузки недоступна.");
        }

        if (!is_file($fullPath)) {
            throw new Exception("Ошибка: указанный путь ведёт на папку, а не на файл.");
        }

        $fileType = pathinfo($fullPath, PATHINFO_EXTENSION);
        $fileContent = file_get_contents($fullPath, false, null, 0, 1024); 
    
        if (isppt($fullPath, $fileType)) {
            header('Content-Type: application/pdf; charset=UTF-8');
            $filename = basename($fullPath);
            header('Content-Disposition: inline; filename*=UTF-8\'\'' . rawurlencode($filename));
            header('Content-Length: ' . filesize($fullPath));
            readfile($fullPath);
            exit;
        }

    } catch (Exception $e) {
        echo "<div class='alert error'>" . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
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
        h1, h2, h3 {
            color: #333;
            margin-bottom: 10px;
        }
        form {
            margin-bottom: 20px;
        }
        input[type="text"], input[type="number"] {
            width: calc(100% - 22px);
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            margin-bottom: 10px;
        }
        button {
            padding: 12px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #218838;
        }
        .button {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 16px;
            margin: 10px 0;
            display: inline-block;
        }
        .button:hover {
            background-color: #0056b3;
        }
        .error {
            color: red;
            margin: 10px 0;
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
            position: relative;
            bottom: 0;
            width: 100%;
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
        li button {
            position: absolute;
            right: 15px;
            top: 15px;
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
        <h1>Личный кабинет</h1>
        <nav>
            <a href="index.php">Главная</a>
            <a href="recomendations.php">Рекомендации</a>
            <a href="logout.php">Выход</a>
        </nav>
    </header>
    <div class="container">
        <h2>Добро пожаловать, <?= htmlspecialchars($user['username']) ?>!</h2>

        <a href="user_applications_dashboard.php" class="button">Посмотреть мои отклики</a>

        <h3>Загрузить резюме</h3>
            <form action="user_dashboard.php" method="POST" enctype="multipart/form-data">
                <input type="file" name="resume" accept=".pdf" required>
                <button type="submit">Загрузить</button>
            </form>


                <form action="user_dashboard.php" method="POST">
                    <button type="submit" name="open_resume">Открыть мое резюме</button>
                </form>


        <?php if ($user['is_blocked']): ?>
            <div class="alert error">Ваш аккаунт заблокирован. Обратитесь к администратору для получения дополнительной информации.</div>
        <?php else: ?>
            <h3>Поиск вакансий</h3>
            <form action="user_dashboard.php" method="POST">
                <input type="text" name="search_query" placeholder="Поиск среди вакансий..." required>
                <button type="submit" name="search">Поиск</button>
            </form>

            <h3>Фильтрация вакансий по зарплате</h3>
            <form action="user_dashboard.php" method="POST">
                <input type="number" name="min_salary" placeholder="Минимальная зарплата" min="0">
                <input type="number" name="max_salary" placeholder="Максимальная зарплата" min="0">
                <button type="submit" name="filter_salary">Фильтровать</button>
            </form>

            <?php
            if (isset($searchResults) && $searchResults && $searchResults->num_rows > 0): ?>
                <h3>Результаты поиска</h3>
                <ul>
                    <?php while ($row = $searchResults->fetch_assoc()): ?>
                        <li>
                            <strong>Название:</strong> <?= htmlspecialchars($row['title']) ?><br>
                            <strong>Описание:</strong> <?= htmlspecialchars($row['description']) ?><br>
                            <strong>Требования:</strong> <?= htmlspecialchars($row['requirements']) ?><br>
                            <strong>Ключевые навыки:</strong> <?= htmlspecialchars($row['programming_languages']) ?><br>
                            <strong>Зарплата:</strong> <?= htmlspecialchars($row['salary']) ?><br>
                            <strong>Местоположение:</strong> <?= htmlspecialchars($row['location']) ?>
                            <form action="user_dashboard.php" method="POST" style="display:inline;">
                                <input type="hidden" name="job_listing_id" value="<?= $row['id'] ?>">
                                <button type="submit" name="apply">Откликнуться</button>
                            </form>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php elseif (isset($filteredJobs) && $filteredJobs && $filteredJobs->num_rows > 0): ?>
                <h3>Результаты фильтрации по зарплате</h3>
                <ul>
                    <?php while ($row = $filteredJobs->fetch_assoc()): ?>
                        <li>
                            <strong>Название:</strong> <?= htmlspecialchars($row['title']) ?><br>
                            <strong>Описание:</strong> <?= htmlspecialchars($row['description']) ?><br>
                            <strong>Требования:</strong> <?= htmlspecialchars($row['requirements']) ?><br>
                            <strong>Ключевые навыки:</strong> <?= htmlspecialchars($row['programming_languages']) ?><br>
                            <strong>Зарплата:</strong> <?= htmlspecialchars($row['salary']) ?><br>
                            <strong>Местоположение:</strong> <?= htmlspecialchars($row['location']) ?>
                            <form action="user_dashboard.php" method="POST" style="display:inline;">
                                <input type="hidden" name="job_listing_id" value="<?= $row['id'] ?>">
                                <button type="submit" name="apply">Откликнуться</button>
                            </form>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <p>Ничего не найдено.</p>
            <?php endif; ?>

            <h3>Все вакансии</h3>
                <ul>
                    <?php if (!empty($jobs)): ?>
                        <?php foreach ($jobs as $row): ?>
                            <li>
                                <strong>Название:</strong> <?= htmlspecialchars($row['title']) ?><br>
                                <strong>Описание:</strong> <?= htmlspecialchars($row['description']) ?><br>
                                <strong>Требования:</strong> <?= htmlspecialchars($row['requirements']) ?><br>
                                <strong>Ключевые навыки:</strong> <?= htmlspecialchars($row['programming_languages']) ?><br>
                                <strong>Местоположение:</strong> <?= htmlspecialchars($row['location'] ?? '') ?><br>
                                <strong>Зарплата:</strong> <?= htmlspecialchars($row['salary']) ?>
                                <form action="user_dashboard.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="job_listing_id" value="<?= $row['id'] ?>">
                                    <button type="submit" name="apply">Откликнуться</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>Вакансии не найдены.</li>
                    <?php endif; ?>
                </ul>
        <?php if ($jobs->num_rows == 0): ?>
            <p>Нет вакансий.</p>
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
        <?php endif; ?>
    </div>
    <footer>
        <p>&copy; <?= date("Y") ?> Все права защищены.</p>
    </footer>
</body>
</html>

<?php
$conn->close();
?>