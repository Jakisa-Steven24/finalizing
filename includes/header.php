<?php
require_once __DIR__ . '/functions.php';

/*
 * This template is included into every page and therefore shares that page's
 * variables. Navigation variables use a $nav prefix and are unset at the end so
 * they can never overwrite page state (for example $entityKey in records.php).
 */
$navEntities = [
    'departments' => 'Departments',
    'lecturers' => 'Lecturers',
    'courses' => 'Courses',
    'course_units' => 'Course units',
    'students' => 'Students',
    'enrollments' => 'Enrollments',
    'fee_types' => 'Fee types',
    'payments' => 'Payments',
    'registrations' => 'Registrations',
    'marks' => 'Marks',
];
$navScript = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$navActiveEntity = '';
if ($navScript === 'records.php') {
    $navActiveEntity = get_string('entity') ?: 'students';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?= e($pageTitle ?? APP_NAME) ?> | MIU SIMS</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/app.js" defer></script>
</head>
<body>
<header class="topbar">
    <a class="brand" href="index.php" aria-label="MIU Student Information Management System home">
        <span class="brand-mark" aria-hidden="true">MIU</span>
        <span>Student Information<small>Metropolitan International University</small></span>
    </a>
    <?php if (!empty($_SESSION['user'])): ?>
        <div class="userbox">
            <span><?= e($_SESSION['user']['full_name']) ?> · <?= e(ucfirst(current_role())) ?></span>
            <a class="logout" href="logout.php">Sign out</a>
        </div>
    <?php endif; ?>
</header>
<div class="app-layout">
<?php if (!empty($_SESSION['user'])): ?>
    <aside class="sidebar" aria-label="Primary navigation">
        <div class="nav-label">WORKSPACE</div>
        <a href="index.php"<?= nav_current_attributes($navScript === 'index.php') ?>>▦ &nbsp; Dashboard</a>
        <?php if (is_staff()): ?>
            <div class="nav-label">ACADEMIC MANAGEMENT</div>
            <?php foreach ($navEntities as $navEntityKey => $navEntityLabel): ?>
                <a href="records.php?entity=<?= e($navEntityKey) ?>"<?= nav_current_attributes($navEntityKey === $navActiveEntity) ?>>▸ &nbsp; <?= e($navEntityLabel) ?></a>
            <?php endforeach; ?>
            <div class="nav-label">INSIGHTS</div>
            <a href="reports.php"<?= nav_current_attributes($navScript === 'reports.php') ?>>▤ &nbsp; Reports & exports</a>
            <a href="users.php"<?= nav_current_attributes($navScript === 'users.php') ?>>♙ &nbsp; User accounts</a>
            <?php if (is_superadmin()): ?>
                <a href="audit.php"<?= nav_current_attributes($navScript === 'audit.php') ?>>◷ &nbsp; Audit log</a>
            <?php endif; ?>
        <?php else: ?>
            <div class="nav-label">MY INFORMATION</div>
            <a href="my_record.php"<?= nav_current_attributes($navScript === 'my_record.php') ?>>♙ &nbsp; My academic record</a>
        <?php endif; ?>
        <div class="sidebar-bottom">
            Role-based access · secure records
            <br>
            <small>MIU · <?= date('Y') ?></small>
        </div>
    </aside>
<?php endif; ?>
<?php unset($navEntities, $navEntityKey, $navEntityLabel, $navScript, $navActiveEntity); ?>
<main class="main-content">
<?php show_flash(); ?>
