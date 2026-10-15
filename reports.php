<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_staff();
require_once 'includes/reporting.php';

$filters = report_filters($conn);
$metrics = report_metrics($conn, $filters);
$departments = $conn->query('SELECT dept_id, dept_code, dept_name FROM department ORDER BY dept_code');
$courses = $conn->query('SELECT course_id, course_code, course_title FROM course ORDER BY course_code');
$fees = $conn->query('SELECT fee_type_id, fee_name FROM fee_type ORDER BY fee_name');
$filterQuery = $filters;
$pageTitle = 'Reports and exports';
require 'includes/header.php';
?>
<div class="page-heading">
    <div><h1>Reports & exports</h1><p class="subheading">Apply a consistent filter set, inspect the results, print them, or download a CSV report.</p></div>
    <button class="btn secondary" type="button" data-print-page>Print / save as PDF</button>
</div>
<section class="card filter-card no-print">
    <h2>Report filters</h2>
    <p class="hint">Department and course filter student, academic, and financial data. Academic year/semester apply to marks; date/fee type apply to payments.</p>
    <form class="filter-form" method="get">
        <div class="field"><label for="department_id">Department</label><select id="department_id" name="department_id"><option value="">All departments</option><?php while ($department = $departments->fetch_assoc()): ?><option value="<?= (int)$department['dept_id'] ?>" <?= $filters['department_id'] === (int)$department['dept_id'] ? 'selected' : '' ?>><?= e($department['dept_code'] . ' — ' . $department['dept_name']) ?></option><?php endwhile; ?></select></div>
        <div class="field"><label for="course_id">Course</label><select id="course_id" name="course_id"><option value="">All courses</option><?php while ($course = $courses->fetch_assoc()): ?><option value="<?= (int)$course['course_id'] ?>" <?= $filters['course_id'] === (int)$course['course_id'] ? 'selected' : '' ?>><?= e($course['course_code'] . ' — ' . $course['course_title']) ?></option><?php endwhile; ?></select></div>
        <div class="field"><label for="academic_year">Academic year</label><input id="academic_year" name="academic_year" value="<?= e($filters['academic_year']) ?>" maxlength="9" pattern="(19|20)[0-9]{2}/(19|20)[0-9]{2}" placeholder="Example: 2026/2027"></div>
        <div class="field"><label for="semester">Semester</label><select id="semester" name="semester"><option value="">All semesters</option><?php foreach (['Semester 1', 'Semester 2', 'Recess'] as $semester): ?><option value="<?= e($semester) ?>" <?= $filters['semester'] === $semester ? 'selected' : '' ?>><?= e($semester) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="fee_type_id">Fee type</label><select id="fee_type_id" name="fee_type_id"><option value="">All fee types</option><?php while ($fee = $fees->fetch_assoc()): ?><option value="<?= (int)$fee['fee_type_id'] ?>" <?= $filters['fee_type_id'] === (int)$fee['fee_type_id'] ? 'selected' : '' ?>><?= e($fee['fee_name']) ?></option><?php endwhile; ?></select></div>
        <div class="field"><label for="date_from">Payment date from</label><input id="date_from" type="date" name="date_from" value="<?= e($filters['date_from']) ?>" placeholder="YYYY-MM-DD"></div>
        <div class="field"><label for="date_to">Payment date to</label><input id="date_to" type="date" name="date_to" value="<?= e($filters['date_to']) ?>" placeholder="YYYY-MM-DD"></div>
        <div class="filter-actions"><button class="btn" type="submit">Apply report filters</button><a class="btn secondary" href="reports.php">Clear filters</a></div>
    </form>
</section>

<section class="stats">
    <div class="stat"><span class="stat-icon">♙</span><div class="stat-label">Students in scope</div><div class="stat-value"><?= (int)$metrics['students'] ?></div></div>
    <div class="stat"><span class="stat-icon">✓</span><div class="stat-label">Marks in scope</div><div class="stat-value"><?= (int)$metrics['assessments'] ?></div></div>
    <div class="stat"><span class="stat-icon">%</span><div class="stat-label">Average score</div><div class="stat-value"><?= number_format($metrics['average_score'], 2) ?>%</div></div>
    <div class="stat"><span class="stat-icon">₵</span><div class="stat-label">Payments recorded</div><div class="stat-value">UGX <?= number_format($metrics['total_paid'], 2) ?></div></div>
</section>

<section class="report-grid">
    <div class="card">
        <div class="toolbar"><div><h2>Course enrolment summary</h2><p class="hint">Roster count by course.</p></div><div class="actions no-print"><a class="btn secondary small" href="export.php?<?= e(query_string(array_merge(['report' => 'course_summary'], $filterQuery))) ?>">Summary CSV</a><a class="btn secondary small" href="export.php?<?= e(query_string(array_merge(['report' => 'roster'], $filterQuery))) ?>">Student roster CSV</a></div></div>
        <div class="table-wrap"><table><thead><tr><th>Course</th><th>Title</th><th>Department</th><th>Students</th></tr></thead><tbody><?php $courseRows = report_course_summary_rows($conn, $filters); $count = 0; while ($row = $courseRows->fetch_assoc()): $count++; ?><tr><td><?= e($row['course_code']) ?></td><td><?= e($row['course_title']) ?></td><td><?= e($row['dept_code']) ?></td><td><?= (int)$row['student_count'] ?></td></tr><?php endwhile; if (!$count): ?><tr><td colspan="4" class="empty">No course data matches the selected filters.</td></tr><?php endif; ?></tbody></table></div>
    </div>
    <div class="card">
        <div class="toolbar"><div><h2>Grade distribution</h2><p class="hint">Assessment count by calculated grade.</p></div><a class="btn secondary small no-print" href="export.php?<?= e(query_string(array_merge(['report' => 'marks'], $filterQuery))) ?>">Download marks CSV</a></div>
        <div class="table-wrap"><table><thead><tr><th>Grade</th><th>Assessments</th></tr></thead><tbody><?php $gradeRows = report_grade_rows($conn, $filters); $count = 0; while ($row = $gradeRows->fetch_assoc()): $count++; ?><tr><td><span class="badge"><?= e($row['grade']) ?></span></td><td><?= (int)$row['total'] ?></td></tr><?php endwhile; if (!$count): ?><tr><td colspan="2" class="empty">No mark data matches the selected filters.</td></tr><?php endif; ?></tbody></table></div>
    </div>
</section>

<section class="card">
    <div class="toolbar"><div><h2>Academic performance by course unit</h2><p class="hint">Use the below-pass count to identify units requiring academic follow-up.</p></div><a class="btn secondary small no-print" href="export.php?<?= e(query_string(array_merge(['report' => 'performance'], $filterQuery))) ?>">Download CSV</a></div>
    <div class="table-wrap"><table><thead><tr><th>Course</th><th>Unit</th><th>Assessments</th><th>Students assessed</th><th>Average score</th><th>Below pass (&lt;50)</th></tr></thead><tbody><?php $performanceRows = report_performance_rows($conn, $filters); $count = 0; while ($row = $performanceRows->fetch_assoc()): $count++; ?><tr><td><?= e($row['course_code']) ?></td><td><?= e($row['course_unit_code'] . ' — ' . $row['course_unit_title']) ?></td><td><?= (int)$row['assessments'] ?></td><td><?= (int)$row['students_assessed'] ?></td><td><?= number_format((float)$row['average_score'], 2) ?>%</td><td><?= (int)$row['below_pass'] ?></td></tr><?php endwhile; if (!$count): ?><tr><td colspan="6" class="empty">No academic performance data matches the selected filters.</td></tr><?php endif; ?></tbody></table></div>
</section>

<section class="card">
    <div class="toolbar"><div><h2>Detailed marks report</h2><p class="hint">Up to 1,000 matching assessments are displayed/exported.</p></div><a class="btn secondary small no-print" href="export.php?<?= e(query_string(array_merge(['report' => 'marks'], $filterQuery))) ?>">Download CSV</a></div>
    <div class="table-wrap"><table><thead><tr><th>Student</th><th>Course</th><th>Unit</th><th>Year / semester</th><th>Assessment</th><th>Score</th><th>Grade</th><th>Remarks</th></tr></thead><tbody><?php $markRows = report_mark_rows($conn, $filters); $count = 0; while ($row = $markRows->fetch_assoc()): $count++; ?><tr><td><?= e($row['reg_no'] . ' — ' . $row['student_name']) ?></td><td><?= e($row['course_code']) ?></td><td><?= e($row['course_unit_code']) ?></td><td><?= e($row['academic_year'] . ' / ' . $row['semester']) ?></td><td><?= e($row['assessment_type'] . ': ' . $row['assessment_name']) ?></td><td><?= number_format((float)$row['score'], 2) ?>%</td><td><span class="badge"><?= e($row['grade']) ?></span></td><td><?= e($row['remarks'] ?: '—') ?></td></tr><?php endwhile; if (!$count): ?><tr><td colspan="8" class="empty">No marks match the selected filters.</td></tr><?php endif; ?></tbody></table></div>
</section>

<section class="card">
    <div class="toolbar"><div><h2>Financial transactions</h2><p class="hint">Payments only; filters by fee type and payment date apply here.</p></div><a class="btn secondary small no-print" href="export.php?<?= e(query_string(array_merge(['report' => 'payments'], $filterQuery))) ?>">Download CSV</a></div>
    <div class="table-wrap"><table><thead><tr><th>Date</th><th>Student</th><th>Course</th><th>Fee type</th><th>Amount (UGX)</th><th>Status</th><th>Method</th><th>Reference</th></tr></thead><tbody><?php $paymentRows = report_payment_rows($conn, $filters); $count = 0; while ($row = $paymentRows->fetch_assoc()): $count++; ?><tr><td><?= e($row['payment_date']) ?></td><td><?= e($row['reg_no'] . ' — ' . $row['student_name']) ?></td><td><?= e($row['course_code']) ?></td><td><?= e($row['fee_name']) ?></td><td><?= number_format((float)$row['amount'], 2) ?></td><td><?= e($row['payment_status']) ?></td><td><?= e($row['method']) ?></td><td><?= e($row['reference_no'] ?: '—') ?></td></tr><?php endwhile; if (!$count): ?><tr><td colspan="8" class="empty">No payments match the selected filters.</td></tr><?php endif; ?></tbody></table></div>
</section>
<?php require 'includes/footer.php'; ?>
