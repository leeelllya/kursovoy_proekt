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

// Получение информации о пользователе
$user_id = $_SESSION['user_id'];
$userQuery = $conn->query("SELECT username FROM users WHERE id='$user_id'");
$user = $userQuery->fetch_assoc();


// Обработка добавления вакансии
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_job'])) {
    try {
        $title = trim($conn->real_escape_string($_POST['title'] ?? ''));
        $description = trim($conn->real_escape_string($_POST['description'] ?? ''));
        $requirements = trim($conn->real_escape_string($_POST['requirements'] ?? ''));
        $salary = $conn->real_escape_string($_POST['salary'] ?? '');

        if (!isset($_POST['location']) || empty($_POST['location'])) {
            throw new Exception("Поле местоположения должно быть заполнено.");
        } else {
            $location = trim($conn->real_escape_string($_POST['location']));
        }

        $programming_languages = isset($_POST['programming_languages']) ? implode(',', $_POST['programming_languages']) : '';

        if (empty($title) || empty($description) || empty($requirements)) {
            throw new Exception("Все поля должны быть заполнены.");
        }

        if ($salary < 0 || $salary > 1000000) {
            throw new Exception("Зарплата должна быть в пределах от 1 до 1,000,000.");
        }

        $sql = "INSERT INTO job_listings_pending (title, description, requirements, salary, employer_id, programming_languages, location) VALUES ('$title', '$description', '$requirements', '$salary', '$user_id', '$programming_languages', '$location')";

        if ($conn->query($sql) === TRUE) {
            echo "<div class='alert success'>Вакансия успешно добавлена и ожидает проверки.</div>";
        } else {
            throw new Exception("Ошибка добавления вакансии: " . $conn->error);
        }
    } catch (Exception $e) {
        echo "<div class='alert error'>" . $e->getMessage() . "</div>";
    }
}



// Обработка добавления языка
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_language'])) {
    try {
        $name = trim($conn->real_escape_string($_POST['name']));

        if (empty($name)) {
            throw new Exception("Поле должно быть заполнено.");
        }

        $sql = "INSERT INTO programming_languages_pending (name) VALUES ('$name')";
        if ($conn->query($sql) === TRUE) {
            echo "<div class='alert success'>Навык успешно добавлен и ожидает проверки.</div>";
        } else {
            throw new Exception("Ошибка добавления навыка: " . $conn->error);
        }
    } catch (Exception $e) {
        echo "<div class='alert error'>" . $e->getMessage() . "</div>";
    }
}

// Обработка удаления вакансии
if (isset($_GET['delete_job'])) {
    $job_id = intval($_GET['delete_job']);
    
    // Удаляем вакансию из таблицы
    $delete_sql = "DELETE FROM job_listings WHERE id='$job_id'";
    if ($conn->query($delete_sql) === TRUE) {
        echo "<div class='alert success'>Вакансия успешно удалена.</div>";
    } else {
        echo "<div class='alert error'>Ошибка при удалении вакансии: " . $conn->error . "</div>";
    }
}

// Обработка редактирования вакансии
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_job'])) {
    try {
        $job_id = intval($_POST['job_id']);
        $title = trim($conn->real_escape_string($_POST['title']));
        $description = trim($conn->real_escape_string($_POST['description']));
        $requirements = trim($conn->real_escape_string($_POST['requirements']));
        $salary = $conn->real_escape_string($_POST['salary']);
        $location = trim($conn->real_escape_string($_POST['location']));
        $programming_languages = isset($_POST['programming_languages']) ? implode(',', $_POST['programming_languages']) : '';

        if (empty($title) || empty($description) || empty($requirements) || empty($location)) {
            throw new Exception("Все поля, кроме зарплаты, должны быть заполнены.");
        }

        if ($salary < 0 || $salary > 1000000) {
            throw new Exception("Зарплата должна быть в пределах 1 до 1,000,000.");
        }

        $check_sql = "SELECT * FROM job_listings_pending WHERE job_listing_id='$job_id'";
        $check_result = $conn->query($check_sql);

        if ($check_result && $check_result->num_rows > 0) {
            $update_sql = "UPDATE job_listings_pending SET title='$title', description='$description', requirements='$requirements', salary='$salary', programming_languages='$programming_languages', location='$location' WHERE job_listing_id='$job_id'";
            if ($conn->query($update_sql) === TRUE) {
                echo "<div class='alert success'>Изменения успешно обновлены и ожидают проверки.</div>";
            } else {
                throw new Exception("Ошибка обновления изменений: " . $conn->error);
            }
        } else {
            $sql = "INSERT INTO job_listings_pending (job_listing_id, title, description, requirements, salary, programming_languages, location) VALUES ('$job_id', '$title', '$description', '$requirements', '$salary', '$programming_languages', '$location')";
            if ($conn->query($sql) === TRUE) {
                echo "<div class='alert success'>Изменения успешно добавлены и ожидают проверки.</div>";
            } else {
                throw new Exception("Ошибка добавления изменений: " . $conn->error);
            }
        }
    } catch (Exception $e) {
        echo "<div class='alert error'>" . $e->getMessage() . "</div>";
    }
}

// Получение информации о вакансии для редактирования
$pending_job = null; // Инициализируем переменную
$selectedLanguages = []; // Инициализируем массив выбранных языков
if (isset($_GET['edit_job_id'])) {
    $job_id = intval($_GET['edit_job_id']);
    $pending_job_query = $conn->query("SELECT * FROM job_listings_pending WHERE job_listing_id='$job_id'");
    
    if ($pending_job_query && $pending_job_query->num_rows > 0) {
        $pending_job = $pending_job_query->fetch_assoc();
    }
}

// Извлечение выбранных языков, если $pending_job не null
$programming_languages = $pending_job['programming_languages'] ?? ''; // Используем пустую строку, если значение null
$selectedLanguages = explode(',', $programming_languages);

$limit = 5;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$totalQuery = $conn->query("
    SELECT COUNT(*) AS total 
    FROM job_listings 
    WHERE employer_id='$user_id' AND status='approved'
");
$total = $totalQuery->fetch_assoc()['total'];
$totalPages = ceil($total / $limit);

$jobs = $conn->query("
    SELECT * FROM job_listings WHERE employer_id='$user_id' AND status='approved'
    LIMIT $limit OFFSET $offset
");

$languages = $conn->query("SELECT * FROM programming_languages");

// Получение всех местоположений
$locations = $conn->query("SELECT * FROM locations");

$selected_location = $pending_job['location_id'] ?? ''; 

// Получение всех местоположений для формы
$locationsForAdd = $conn->query("SELECT * FROM locations");
$locationsForEdit = $conn->query("SELECT * FROM locations");


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
        h2, h3 {
            color: #333;
        }
        .job-form input,
        .edit-form input {
            margin: 10px 0;
            padding: 12px;
            width: calc(100% - 22px);
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        .job-form button,
        .edit-form button {
            padding: 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .job-form button:hover,
        .edit-form button:hover {
            background-color: #0056b3;
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
        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .job-item {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
            background-color: #f9f9f9;
        }
        .delete-button, .edit-button {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-left: 10px;
        }
        .delete-button {
            background-color: #d9534f;
            color: white;
        }
        .delete-button:hover {
            background-color: #c82333;
        }
        .edit-button {
            background-color: #007bff;
            color: white;
        }
        .edit-button:hover {
            background-color: #0056b3;
        }
        .edit-form {
            margin-top: 10px;
            padding: 15px;
            border: 1px solid #007bff;
            border-radius: 4px;
            background-color: #f1f9ff;
        }
        .cancel-button {
            background-color: #f44336;
            color: white;
            margin-left: 10px;
        }
        .cancel-button:hover {
            background-color: #c62828;
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
        .language-select {
        margin-top: 10px;
        }

        .language-select select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
            font-size: 16px;
        }

        .language-select select:focus {
            border-color: #007bff;
            outline: none;
        }

        .language-select option {
            padding: 10px;
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
            <a href="applications_dashboard.php">Отклики на вакансии</a>
            <a href="logout.php">Выход</a>
        </nav>
    </header>
    <div class="container">
        <h2>Добро пожаловать, <?= htmlspecialchars($user['username']) ?>!</h2>

        <h3>Добавить новый навык</h3>
        <form action="employer_dashboard.php" method="POST" class="job-form">
            <input type="text" name="name" placeholder="Название навыка" required>
            <button type="submit" name="add_language">Добавить навык</button>
        </form>

        <h3>Добавить новую вакансию</h3>
        <form action="employer_dashboard.php" method="POST" class="job-form">
            <input type="text" name="title" placeholder="Название вакансии" required>
            <input type="text" name="description" placeholder="Описание вакансии" required>
            <input type="text" name="requirements" placeholder="Требования" required>
            <input type="number" name="salary" placeholder="Зарплата" min="1" required>
            
            <div class="language-select">
                <label for="location">Выберите местоположение:</label>
                <select name="location" required>
                    <option value="">Выберите местоположение</option>
                    <?php while ($location = $locationsForAdd->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($location['name']) ?>"><?= htmlspecialchars($location['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="language-select">
                <label for="languages">Выберите ключевые навыки:</label>
                <select name="programming_languages[]" multiple>
                    <?php
                    $allLanguages = $conn->query("SELECT * FROM programming_languages");
                    while ($lang = $allLanguages->fetch_assoc()): 
                    ?>
                        <option value="<?= htmlspecialchars($lang['name']) ?>">
                            <?= htmlspecialchars($lang['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <button type="submit" name="add_job">Добавить вакансию</button>
        </form>

        <h3>Ваши одобренные вакансии</h3>
<ul>
    <?php while ($row = $jobs->fetch_assoc()): ?>
        <li class="job-item">
            <strong>Название:</strong> <?= htmlspecialchars($row['title']) ?><br>
            <strong>Описание:</strong> <?= htmlspecialchars($row['description']) ?><br>
            <strong>Требования:</strong> <?= htmlspecialchars($row['requirements']) ?><br>
            <strong>Зарплата:</strong> <?= htmlspecialchars($row['salary']) ?><br>
            <strong>Ключевые навыки:</strong> <?= htmlspecialchars($row['programming_languages']) ?><br>
            <strong>Местоположение:</strong> <?= htmlspecialchars($row['location'] ?? '') ?><br>
            <a href="?delete_job=<?= $row['id'] ?>" class="delete-button">Удалить</a>
            <button class="edit-button" onclick="document.getElementById('edit-form-<?= $row['id'] ?>').style.display='block'">Редактировать</button>
            <div id="edit-form-<?= $row['id'] ?>" class="edit-form" style="display:none;">
                <form action="employer_dashboard.php" method="POST">
                    <input type="hidden" name="job_id" value="<?= $row['id'] ?>">
                    <input type="text" name="title" value="<?= htmlspecialchars($row['title']) ?>" required>
                    <input type="text" name="description" value="<?= htmlspecialchars($row['description']) ?>" required>
                    <input type="text" name="requirements" value="<?= htmlspecialchars($row['requirements']) ?>" required>
                    <input type="number" name="salary" value="<?= htmlspecialchars($row['salary']) ?>" required>

                    <div class="language-select">
                        <label for="location">Выберите местоположение:</label>
                        <select name="location" required>
                            <option value="">Выберите местоположение</option>
                            <?php
                            // Пересоздание запроса для получения списка локаций
                            $locationsForEdit = $conn->query("SELECT * FROM locations");
                            while ($editLocation = $locationsForEdit->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($editLocation['name']) ?>" <?= ($editLocation['name'] == $row['location']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($editLocation['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="language-select">
                        <label for="languages">Выберите ключевые навыки:</label>
                        <select name="programming_languages[]" multiple>
                            <?php
                            $allLanguages = $conn->query("SELECT * FROM programming_languages");
                            while ($lang = $allLanguages->fetch_assoc()):
                            ?>
                                <option value="<?= htmlspecialchars($lang['name']) ?>" 
                                    <?= in_array($lang['name'], $selectedLanguages) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lang['name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                                        
                    <button type="submit" name="edit_job">Сохранить изменения</button>
                    <button type="button" class="cancel-button" onclick="document.getElementById('edit-form-<?= $row['id'] ?>').style.display='none'">Отмена</button>
                </form>
            </div>
        </li>
    <?php endwhile; ?>
</ul>
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