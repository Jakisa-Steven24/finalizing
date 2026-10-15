<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_login();
if (current_role() !== 'student') {
    http_response_code(403);
    exit('Student access is required.');
}

$studentId = (int)($_SESSION['user']['student_id'] ?? 0);
if ($studentId < 1) {
    http_response_code(404);
    exit('No student record is linked to this account.');
}
$year = get_string('academic_year'); if ($year !== '' && !valid_academic_year($year)) $year = '';
$semester = get_string('semester'); if (!in_array($semester, ['', 'Semester 1', 'Semester 2', 'Recess'], true)) $semester = '';
$dateFrom = get_string('date_from'); if ($dateFrom !== '' && !valid_date_value($dateFrom)) $dateFrom = '';
$dateTo = get_string('date_to'); if ($dateTo !== '' && !valid_date_value($dateTo)) $dateTo = '';
if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) { $dateFrom = ''; $dateTo = ''; }

$student = db_one($conn, 'SELECT s.reg_no, s.first_name, s.last_name, c.course_code, c.course_title FROM student s INNER JOIN course c ON c.course_id = s.course_id WHERE s.student_id = ? LIMIT 1', 'i', [$studentId]);
if (!$student) {
    http_response_code(404);
    exit('The linked student record no longer exists.');
}

$markWhere = ['m.student_id = ?']; $markTypes = 'i'; $markParams = [$studentId];
if ($year !== '') { $markWhere[] = 'm.academic_year = ?'; $markTypes .= 's'; $markParams[] = $year; }
if ($semester !== '') { $markWhere[] = 'm.semester = ?'; $markTypes .= 's'; $markParams[] = $semester; }
$marks = db_result($conn, 'SELECT m.academic_year, m.semester, cu.course_unit_code, cu.course_unit_title, m.assessment_type, m.assessment_name, m.score, m.grade, m.remarks FROM mark m INNER JOIN course_unit cu ON cu.course_unit_id = m.course_unit_id WHERE ' . implode(' AND ', $markWhere) . ' ORDER BY m.academic_year DESC, m.semester ASC, cu.course_unit_code ASC LIMIT 1000', $markTypes, $markParams);

$paymentWhere = ['p.student_id = ?']; $paymentTypes = 'i'; $paymentParams = [$studentId];
if ($dateFrom !== '') { $paymentWhere[] = 'p.payment_date >= ?'; $paymentTypes .= 's'; $paymentParams[] = $dateFrom; }
if ($dateTo !== '') { $paymentWhere[] = 'p.payment_date <= ?'; $paymentTypes .= 's'; $paymentParams[] = $dateTo; }
$payments = db_result($conn, 'SELECT p.payment_date, f.fee_name, p.amount, p.payment_status, p.method, p.reference_no FROM payment p INNER JOIN fee_type f ON f.fee_type_id = p.fee_type_id WHERE ' . implode(' AND ', $paymentWhere) . ' ORDER BY p.payment_date DESC, p.payment_id DESC LIMIT 1000', $paymentTypes, $paymentParams);

audit_log($conn, 'EXPORT', 'my_record', $studentId, 'Student exported own academic record');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="my_academic_record_' . date('Y-m-d_His') . '.csv"');
$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['MIU Student Academic Record']);
fputcsv($output, ['Registration number', csv_safe($student['reg_no'])]);
fputcsv($output, ['Student name', csv_safe($student['first_name'] . ' ' . $student['last_name'])]);
fputcsv($output, ['Course', csv_safe($student['course_code'] . ' — ' . $student['course_title'])]);
fputcsv($output, []);
fputcsv($output, ['MARKS']);
fputcsv($output, ['Academic year', 'Semester', 'Unit code', 'Unit title', 'Assessment type', 'Assessment name', 'Score', 'Grade', 'Remarks']);
while ($row = $marks->fetch_assoc()) {
    fputcsv($output, [csv_safe($row['academic_year']), csv_safe($row['semester']), csv_safe($row['course_unit_code']), csv_safe($row['course_unit_title']), csv_safe($row['assessment_type']), csv_safe($row['assessment_name']), $row['score'], csv_safe($row['grade']), csv_safe($row['remarks'])]);
}
fputcsv($output, []);
fputcsv($output, ['PAYMENTS']);
fputcsv($output, ['Date', 'Fee type', 'Amount', 'Status', 'Method', 'Reference number']);
while ($row = $payments->fetch_assoc()) {
    fputcsv($output, [$row['payment_date'], csv_safe($row['fee_name']), $row['amount'], csv_safe($row['payment_status']), csv_safe($row['method']), csv_safe($row['reference_no'])]);
}
fclose($output);
exit;
