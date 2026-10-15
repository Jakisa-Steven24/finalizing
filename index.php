<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_login();

$pageTitle = 'Dashboard';

if (is_staff()) {
    $courseId = get_positive_int('course_id');
    if ($courseId && !foreign_key_exists($conn, 'course', 'course_id', $courseId)) $courseId = 0;
    $courses = $conn->query('SELECT course_id, course_code, course_title FROM course ORDER BY course_code');
    $studentSql = 'SELECT COUNT(*) AS total FROM student'; $studentTypes = ''; $studentParams = [];
    $courseSql = 'SELECT COUNT(*) AS total FROM course'; $courseTypes = ''; $courseParams = [];
    $markSql = 'SELECT COUNT(*) AS total FROM mark m INNER JOIN course_unit cu ON cu.course_unit_id = m.course_unit_id'; $markTypes = ''; $markParams = [];
    $paymentSql = 'SELECT COALESCE(SUM(p.amount), 0) AS total FROM payment p INNER JOIN student s ON s.student_id = p.student_id'; $paymentTypes = ''; $paymentParams = [];
    if ($courseId) {
        $studentSql .= ' WHERE course_id = ?'; $studentTypes = 'i'; $studentParams = [$courseId];
        $courseSql .= ' WHERE course_id = ?'; $courseTypes = 'i'; $courseParams = [$courseId];
        $markSql .= ' WHERE cu.course_id = ?'; $markTypes = 'i'; $markParams = [$courseId];
        $paymentSql .= ' WHERE s.course_id = ?'; $paymentTypes = 'i'; $paymentParams = [$courseId];
    }
    $studentsTotal = (int)(db_one($conn, $studentSql, $studentTypes, $studentParams)['total'] ?? 0);
    $coursesTotal = (int)(db_one($conn, $courseSql, $courseTypes, $courseParams)['total'] ?? 0);
    $marksTotal = (int)(db_one($conn, $markSql, $markTypes, $markParams)['total'] ?? 0);
    $paymentTotal = (float)(db_one($conn, $paymentSql, $paymentTypes, $paymentParams)['total'] ?? 0);
} else {
    $studentId = (int)($_SESSION['user']['student_id'] ?? 0);
    $year = get_string('academic_year'); if ($year !== '' && !valid_academic_year($year)) $year = '';
    $semester = get_string('semester'); if (!in_array($semester, ['', 'Semester 1', 'Semester 2', 'Recess'], true)) $semester = '';
    $student = $studentId ? db_one($conn, 'SELECT s.reg_no, c.course_code, c.course_title FROM student s INNER JOIN course c ON c.course_id = s.course_id WHERE s.student_id = ? LIMIT 1', 'i', [$studentId]) : null;
    $where = ['student_id = ?']; $types = 'i'; $params = [$studentId];
    if ($year !== '') { $where[] = 'academic_year = ?'; $types .= 's'; $params[] = $year; }
    if ($semester !== '') { $where[] = 'semester = ?'; $types .= 's'; $params[] = $semester; }
    $summary = $studentId ? db_one($conn, 'SELECT COUNT(*) AS total, COALESCE(AVG(score), 0) AS average FROM mark WHERE ' . implode(' AND ', $where), $types, $params) : ['total' => 0, 'average' => 0];
    $paymentSummary = $studentId ? db_one($conn, 'SELECT COUNT(*) AS total, COALESCE(SUM(amount), 0) AS paid FROM payment WHERE student_id = ?', 'i', [$studentId]) : ['total' => 0, 'paid' => 0];
}

require 'includes/header.php';
?>
<?php if (is_staff()): ?>
    <div class="page-heading"><div><h1>Administration dashboard</h1><p class="subheading">Operational overview of student, academic and financial records.</p></div><a class="btn" href="records.php?entity=students&amp;action=create">＋ Add student</a></div>
    <section class="card filter-card"><div class="toolbar"><div><h2>Dashboard scope</h2><p class="hint">Filter these summary metrics by course.</p></div></div><form class="inline-filter" method="get"><div class="field"><label for="course_id">Course</label><select id="course_id" name="course_id"><option value="">All courses</option><?php while ($course = $courses->fetch_assoc()): ?><option value="<?= (int)$course['course_id'] ?>" <?= $courseId === (int)$course['course_id'] ? 'selected' : '' ?>><?= e($course['course_code'] . ' — ' . $course['course_title']) ?></option><?php endwhile; ?></select></div><div class="filter-actions"><button class="btn">Apply filter</button><a class="btn secondary" href="index.php">Clear filter</a></div></form></section>
    <section class="stats"><div class="stat"><span class="stat-icon">♙</span><div class="stat-label">Students</div><div class="stat-value"><?= $studentsTotal ?></div></div><div class="stat"><span class="stat-icon">▦</span><div class="stat-label">Courses</div><div class="stat-value"><?= $coursesTotal ?></div></div><div class="stat"><span class="stat-icon">✓</span><div class="stat-label">Marks recorded</div><div class="stat-value"><?= $marksTotal ?></div></div><div class="stat"><span class="stat-icon">₵</span><div class="stat-label">Payments total</div><div class="stat-value">UGX <?= number_format($paymentTotal, 2) ?></div></div></section>
    <section class="card"><h2>Quick actions</h2><div class="toolbar"><a class="btn secondary" href="records.php?entity=students">Manage students →</a><a class="btn secondary" href="records.php?entity=marks&amp;action=create">Record marks →</a><a class="btn secondary" href="records.php?entity=payments&amp;action=create">Record payment →</a><a class="btn secondary" href="reports.php">Reports & exports →</a></div></section>
<?php else: ?>
    <div class="page-heading"><div><h1>My dashboard</h1><p class="subheading">A private overview of your personal academic information.</p></div><a class="btn" href="my_record.php">View my record</a></div>
    <section class="card filter-card"><h2>Academic period filter</h2><form class="inline-filter" method="get"><div class="field"><label for="academic_year">Academic year</label><input id="academic_year" name="academic_year" value="<?= e($year) ?>" maxlength="9" pattern="(19|20)[0-9]{2}/(19|20)[0-9]{2}" placeholder="Example: 2026/2027"></div><div class="field"><label for="semester">Semester</label><select id="semester" name="semester"><option value="">All semesters</option><?php foreach (['Semester 1', 'Semester 2', 'Recess'] as $option): ?><option value="<?= e($option) ?>" <?= $semester === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></div><div class="filter-actions"><button class="btn">Apply filter</button><a class="btn secondary" href="index.php">Clear filter</a></div></form></section>
    <?php if (!$student): ?><section class="card"><h2>No linked student record</h2><p>Contact an administrator to link your login account. You cannot view or edit any other student’s information.</p></section><?php else: ?>
        <section class="stats"><div class="stat"><span class="stat-icon">♙</span><div class="stat-label">Registration number</div><div class="stat-value text-stat"><?= e($student['reg_no']) ?></div></div><div class="stat"><span class="stat-icon">▦</span><div class="stat-label">My course</div><div class="stat-value text-stat"><?= e($student['course_code']) ?></div></div><div class="stat"><span class="stat-icon">✓</span><div class="stat-label">My assessments</div><div class="stat-value"><?= (int)$summary['total'] ?></div></div><div class="stat"><span class="stat-icon">%</span><div class="stat-label">My average score</div><div class="stat-value"><?= number_format((float)$summary['average'], 2) ?>%</div></div></section>
        <section class="card"><h2>Welcome, <?= e($_SESSION['user']['full_name']) ?></h2><p class="subheading">Your account is read-only. View your own marks and payments, then download your personal CSV record when needed.</p><a class="btn secondary" href="my_record.php?<?= e(query_string(['academic_year' => $year, 'semester' => $semester])) ?>">Open my filtered academic record →</a></section>
    <?php endif; ?>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
