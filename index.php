<?php
require_once 'includes/db.php'; require_once 'includes/functions.php'; require_login();
$pageTitle='Dashboard'; require 'includes/header.php';
function count_rows($conn,$table){$res=$conn->query("SELECT COUNT(*) AS n FROM `$table`");return (int)$res->fetch_assoc()['n'];}
$counts=['Students'=>count_rows($conn,'student'),'Departments'=>count_rows($conn,'department'),'Lecturers'=>count_rows($conn,'lecturer'),'Courses'=>count_rows($conn,'course')];
?>
<div class="page-heading"><div><h1>Dashboard</h1><p class="subheading">A clear overview of academic records and administration.</p></div><?php if($_SESSION['user']['role']==='admin'): ?><a class="btn" href="records.php?entity=students&action=create">＋ Add student</a><?php endif; ?></div>
<div class="stats"><?php foreach($counts as $label=>$count): ?><div class="stat"><span class="stat-icon">▦</span><div class="stat-label"><?= e($label) ?></div><div class="stat-value"><?= $count ?></div></div><?php endforeach; ?></div>
<div class="card"><h2>Welcome, <?= e($_SESSION['user']['full_name']) ?></h2><p class="subheading">Use the navigation to manage records, validate input, search and filter data, and generate reports.</p></div>
<?php if($_SESSION['user']['role']==='admin'): ?><div class="card"><h2>Quick actions</h2><div class="toolbar"><?php foreach(['students'=>'Manage students','departments'=>'Manage departments','courses'=>'Manage courses','payments'=>'Manage payments'] as $key=>$label): ?><a class="btn secondary" href="records.php?entity=<?= e($key) ?>"><?= e($label) ?> →</a><?php endforeach; ?><a class="btn secondary" href="reports.php">View reports →</a></div></div><?php else: ?><div class="card"><h2>Student services</h2><p>View your student record using the link in the navigation menu. Contact an administrator if your account is not linked to your student record.</p></div><?php endif; ?>
<?php require 'includes/footer.php'; ?>