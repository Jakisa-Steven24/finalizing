<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_login();
if (current_role() !== 'student') {
    redirect_to('index.php');
}

$studentId = (int)($_SESSION['user']['student_id'] ?? 0);
$year = get_string('academic_year');
if ($year !== '' && !valid_academic_year($year)) {
    $year = '';
}
$semester = get_string('semester');
if (!in_array($semester, ['', 'Semester 1', 'Semester 2', 'Recess'], true)) {
    $semester = '';
}
$dateFrom = get_string('date_from');
if ($dateFrom !== '' && !valid_date_value($dateFrom)) {
    $dateFrom = '';
}
$dateTo = get_string('date_to');
if ($dateTo !== '' && !valid_date_value($dateTo)) {
    $dateTo = '';
}
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    $dateFrom = '';
    $dateTo = '';
}

$student = null;
$marks = null;
$payments = null;
$markSummary = [
    'total' => 0,
    'average' => 0,
];
if ($studentId > 0) {
    $student = db_one(
        $conn,
        'SELECT s.student_id, s.reg_no, s.first_name, s.last_name, s.gender, s.date_of_birth, s.email, s.address,'
        . ' c.course_code, c.course_title, d.dept_name'
        . ' FROM student s'
        . ' INNER JOIN course c ON c.course_id = s.course_id'
        . ' INNER JOIN department d ON d.dept_id = c.dept_id'
        . ' WHERE s.student_id = ?'
        . ' LIMIT 1',
        'i',
        [$studentId]
    );
}
if ($student) {
    $markWhere = ['m.student_id = ?'];
    $markTypes = 'i';
    $markParams = [$studentId];
    if ($year !== '') {
        $markWhere[] = 'm.academic_year = ?';
        $markTypes .= 's';
        $markParams[] = $year;
    }
    if ($semester !== '') {
        $markWhere[] = 'm.semester = ?';
        $markTypes .= 's';
        $markParams[] = $semester;
    }
    $markWhereSql = ' WHERE ' . implode(' AND ', $markWhere);
    $marks = db_result(
        $conn,
        'SELECT m.academic_year, m.semester, m.assessment_type, m.assessment_name, m.score, m.grade, m.remarks,'
        . ' cu.course_unit_code, cu.course_unit_title'
        . ' FROM mark m'
        . ' INNER JOIN course_unit cu ON cu.course_unit_id = m.course_unit_id'
        . $markWhereSql
        . ' ORDER BY m.academic_year DESC, m.semester ASC, cu.course_unit_code ASC, m.assessment_type ASC'
        . ' LIMIT 500',
        $markTypes,
        $markParams
    );
    $summaryRow = db_one(
        $conn,
        'SELECT COUNT(*) AS total, COALESCE(AVG(m.score), 0) AS average FROM mark m' . $markWhereSql,
        $markTypes,
        $markParams
    );
    if ($summaryRow) {
        $markSummary = $summaryRow;
    }

    $paymentWhere = ['p.student_id = ?'];
    $paymentTypes = 'i';
    $paymentParams = [$studentId];
    if ($dateFrom !== '') {
        $paymentWhere[] = 'p.payment_date >= ?';
        $paymentTypes .= 's';
        $paymentParams[] = $dateFrom;
    }
    if ($dateTo !== '') {
        $paymentWhere[] = 'p.payment_date <= ?';
        $paymentTypes .= 's';
        $paymentParams[] = $dateTo;
    }
    $payments = db_result(
        $conn,
        'SELECT p.payment_date, p.amount, p.payment_status, p.method, p.reference_no, f.fee_name'
        . ' FROM payment p'
        . ' INNER JOIN fee_type f ON f.fee_type_id = p.fee_type_id'
        . ' WHERE ' . implode(' AND ', $paymentWhere)
        . ' ORDER BY p.payment_date DESC, p.payment_id DESC'
        . ' LIMIT 500',
        $paymentTypes,
        $paymentParams
    );
}

$profileFields = [
    'reg_no' => 'Registration number',
    'first_name' => 'First name',
    'last_name' => 'Last name',
    'gender' => 'Gender',
    'date_of_birth' => 'Date of birth',
    'email' => 'University email',
    'address' => 'Address',
    'course_code' => 'Course code',
    'course_title' => 'Course title',
    'dept_name' => 'Department',
];
$exportQuery = query_string([
    'academic_year' => $year,
    'semester' => $semester,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
]);

$pageTitle = 'My academic record';
require 'includes/header.php';
?>
<div class="page-heading">
    <div>
        <h1>My academic record</h1>
        <p class="subheading">Read-only access to your personal profile, marks and payments. No other student’s data is shown.</p>
    </div>
</div>
<?php if (!$student): ?>
    <section class="card">
        <h2>No linked record</h2>
        <p>Your login is not currently linked to a student record. Please contact the university administrator.</p>
    </section>
<?php else: ?>
    <section class="card">
        <h2>Personal profile</h2>
        <div class="profile-grid">
            <?php foreach ($profileFields as $key => $label): ?>
                <div class="field">
                    <span class="field-label"><?= e($label) ?></span>
                    <div class="readonly-value"><?= e($student[$key] ?? '—') ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="card filter-card">
        <div class="toolbar">
            <div>
                <h2>Filter my information</h2>
                <p class="hint">Academic filters apply to marks. Payment dates apply to payments.</p>
            </div>
            <a class="btn secondary small" href="my_export.php?<?= e($exportQuery) ?>">Download my CSV</a>
        </div>
        <form class="filter-form" method="get" action="my_record.php">
            <div class="field">
                <label for="academic_year">Academic year</label>
                <input id="academic_year" name="academic_year" value="<?= e($year) ?>" maxlength="9" pattern="(19|20)[0-9]{2}/(19|20)[0-9]{2}" placeholder="Example: 2026/2027">
            </div>
            <div class="field">
                <label for="semester">Semester</label>
                <select id="semester" name="semester">
                    <option value="">All semesters</option>
                    <?php foreach (['Semester 1', 'Semester 2', 'Recess'] as $option): ?>
                        <option value="<?= e($option) ?>"<?= $semester === $option ? ' selected' : '' ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="date_from">Payment date from</label>
                <input id="date_from" type="date" name="date_from" value="<?= e($dateFrom) ?>" placeholder="YYYY-MM-DD">
            </div>
            <div class="field">
                <label for="date_to">Payment date to</label>
                <input id="date_to" type="date" name="date_to" value="<?= e($dateTo) ?>" placeholder="YYYY-MM-DD">
            </div>
            <div class="filter-actions">
                <button class="btn" type="submit">Apply filters</button>
                <a class="btn secondary" href="my_record.php">Clear filters</a>
            </div>
        </form>
    </section>
    <section class="stats">
        <div class="stat">
            <span class="stat-icon">✓</span>
            <div class="stat-label">My assessments</div>
            <div class="stat-value"><?= (int)$markSummary['total'] ?></div>
        </div>
        <div class="stat">
            <span class="stat-icon">%</span>
            <div class="stat-label">My average score</div>
            <div class="stat-value"><?= number_format((float)$markSummary['average'], 2) ?>%</div>
        </div>
        <div class="stat">
            <span class="stat-icon">♙</span>
            <div class="stat-label">My course</div>
            <div class="stat-value text-stat"><?= e($student['course_code']) ?></div>
        </div>
    </section>
    <section class="card">
        <h2>My marks</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Academic year</th>
                        <th>Semester</th>
                        <th>Course unit</th>
                        <th>Assessment</th>
                        <th>Score</th>
                        <th>Grade</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($marks as $row): ?>
                        <tr>
                            <td><?= e($row['academic_year']) ?></td>
                            <td><?= e($row['semester']) ?></td>
                            <td><?= e($row['course_unit_code'] . ' — ' . $row['course_unit_title']) ?></td>
                            <td><?= e($row['assessment_type'] . ': ' . $row['assessment_name']) ?></td>
                            <td><?= number_format((float)$row['score'], 2) ?>%</td>
                            <td><span class="badge"><?= e($row['grade']) ?></span></td>
                            <td><?= e($row['remarks'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($marks->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" class="empty">No marks match the selected academic filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <section class="card">
        <h2>My payments</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Fee type</th>
                        <th>Amount (UGX)</th>
                        <th>Status</th>
                        <th>Method</th>
                        <th>Reference</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $row): ?>
                        <tr>
                            <td><?= e($row['payment_date']) ?></td>
                            <td><?= e($row['fee_name']) ?></td>
                            <td><?= number_format((float)$row['amount'], 2) ?></td>
                            <td><?= e($row['payment_status']) ?></td>
                            <td><?= e($row['method']) ?></td>
                            <td><?= e($row['reference_no'] ?: '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($payments->num_rows === 0): ?>
                        <tr>
                            <td colspan="6" class="empty">No payments match the selected date filters.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
