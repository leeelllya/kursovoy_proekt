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

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Ошибка соединения: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];

// 1. Получение весов параметров (0-3)
$weightsQuery = "SELECT parameter_name, weight FROM parameter_weights";
$weightsResult = $conn->query($weightsQuery);
$weights = [];
while ($row = $weightsResult->fetch_assoc()) {
    $weights[$row['parameter_name']] = (int)$row['weight'];
}

// 2. Получение данных из откликов пользователя
$query = "SELECT jl.programming_languages, jl.salary, jl.location 
          FROM applications a
          JOIN job_listings jl ON a.job_listing_id = jl.id 
          WHERE a.user_id = $user_id AND a.status = 'approved'";
$result = $conn->query($query);

$languages = [];
$salaries = [];
$locations = [];

while ($row = $result->fetch_assoc()) {
    $langs = explode(',', $row['programming_languages']);
    $languages = array_merge($languages, $langs);
    $salaries[] = (float)$row['salary'];
    $locations[] = $row['location'];
}

// Подсчет частоты языков
$frequentLanguages = array_count_values($languages);
arsort($frequentLanguages);
$topLanguages = array_slice(array_keys($frequentLanguages), 0, 10);

$userSalaryMin = !empty($salaries) ? min($salaries) : 0;
$userLocationPref = !empty($locations) ? array_count_values($locations) : [];
$userLocationPref = key($userLocationPref);

// Построение условия для языка программирования
$langConditions = '1=0'; // Условие по умолчанию, если языков нет
if (!empty($topLanguages)) {
    $likeConditions = [];
    foreach ($topLanguages as $lang) {
        $likeConditions[] = "programming_languages LIKE '%" . $conn->real_escape_string($lang) . "%'";
    }
    $langConditions = implode(' OR ', $likeConditions);
}


$limit = 5;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$totalQuery = $conn->query("
SELECT COUNT(*) AS total
    FROM job_listings 
    WHERE status = 'approved' 
    AND id NOT IN (
        SELECT job_listing_id 
        FROM applications 
        WHERE user_id = $user_id
    );
");
$total = $totalQuery->fetch_assoc()['total'];
$totalPages = ceil($total / $limit);

// Составляем строку запроса
$query = "
    SELECT *, 
           ROUND((CASE WHEN $langConditions THEN (1 - (3 - {$weights['programming_languages']})) ELSE 0 END), 2) AS lang_weight,
           ROUND((CASE WHEN salary >= $userSalaryMin THEN (1 - (3 - {$weights['salary']})) ELSE 0 END), 2) AS salary_weight,
           ROUND((CASE WHEN location LIKE '%$userLocationPref%' THEN (1 - (3 - {$weights['location']})) ELSE 0 END), 2) AS location_weight,
           ROUND(
               COALESCE((CASE WHEN $langConditions THEN (1 - (3 - {$weights['programming_languages']})) ELSE 0 END), 0) +
               COALESCE((CASE WHEN salary >= $userSalaryMin THEN (1 - (3 - {$weights['salary']})) ELSE 0 END), 0) +
               COALESCE((CASE WHEN location LIKE '%$userLocationPref%' THEN (1 - (3 - {$weights['location']})) ELSE 0 END), 0),
               2
           ) AS total_weight
    FROM job_listings 
    WHERE status = 'approved' 
    AND id NOT IN (
        SELECT job_listing_id 
        FROM applications 
        WHERE user_id = $user_id
    )
    ORDER BY total_weight DESC
    LIMIT $limit OFFSET $offset
";

// Выполняем запрос
$recommendations = $conn->query($query);

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Рекомендации для вас</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
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
        .job-item {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 10px;
            background-color: #f9f9f9;
        }
        .alert {
            margin: 10px 0;
            padding: 15px;
            border-radius: 5px;
            font-size: 16px;
        }
        .alert.empty {
            background-color: #cce5ff;
            color: #004085;
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
        <a href="user_dashboard.php">Личный кабинет</a>
        <a href="logout.php">Выход</a>
    </nav>
</header>
<div class="container">
    <h1>Рекомендации для вас</h1>

    <?php if ($recommendations && $recommendations->num_rows > 0): ?>
        <ul>
            <?php while ($job = $recommendations->fetch_assoc()): ?>
                <li class="job-item">
                    <strong>Название:</strong> <?= htmlspecialchars($job['title']) ?><br>
                    <strong>Описание:</strong> <?= htmlspecialchars($job['description']) ?><br>
                    <strong>Требования:</strong> <?= htmlspecialchars($job['requirements']) ?><br>
                    <strong>Ключевые навыки:</strong> <?= htmlspecialchars($job['programming_languages']) ?><br>
                    <strong>Зарплата:</strong> <?= htmlspecialchars($job['salary']) ?><br>
                    <strong>Местоположение:</strong> <?= htmlspecialchars($job['location']) ?><br>
                </li>
            <?php endwhile; ?>
        </ul>
    <?php else: ?>
        <p>На данный момент рекомендаций нет.</p>
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
</body>
</html>

<?php
$conn->close();
?>