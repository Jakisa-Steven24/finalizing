<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_staff();
require_once 'includes/reporting.php';

$report = get_string('report');
$allowed = ['course_summary', 'roster', 'performance', 'marks', 'payments'];
if (!in_array($report, $allowed, true)) {
    http_response_code(400);
    exit('Unknown export requested.');
}

$filters = report_filters($conn);
$filename = 'miu_' . $report . '_' . date('Y-m-d_His') . '.csv';
audit_log($conn, 'EXPORT', $report, 0, 'CSV report exported with active filters');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM for spreadsheet compatibility.

if ($report === 'course_summary') {
    fputcsv($output, [
        'Course code',
        'Course title',
        'Department',
        'Student count',
    ]);
    $rows = report_course_summary_rows($conn, $filters);
    foreach ($rows as $row) {
        fputcsv($output, [
            csv_safe($row['course_code']),
            csv_safe($row['course_title']),
            csv_safe($row['dept_code']),
            $row['student_count'],
        ]);
    }
} elseif ($report === 'roster') {
    fputcsv($output, [
        'Registration number',
        'First name',
        'Last name',
        'Gender',
        'Email',
        'Course code',
        'Course title',
        'Department code',
        'Department name',
    ]);
    $rows = report_roster_rows($conn, $filters);
    foreach ($rows as $row) {
        fputcsv($output, [
            csv_safe($row['reg_no']),
            csv_safe($row['first_name']),
            csv_safe($row['last_name']),
            csv_safe($row['gender']),
            csv_safe($row['email']),
            csv_safe($row['course_code']),
            csv_safe($row['course_title']),
            csv_safe($row['dept_code']),
            csv_safe($row['dept_name']),
        ]);
    }
} elseif ($report === 'performance') {
    fputcsv($output, [
        'Course code',
        'Course title',
        'Unit code',
        'Unit title',
        'Assessments',
        'Students assessed',
        'Average score',
        'Below pass',
    ]);
    $rows = report_performance_rows($conn, $filters);
    foreach ($rows as $row) {
        fputcsv($output, [
            csv_safe($row['course_code']),
            csv_safe($row['course_title']),
            csv_safe($row['course_unit_code']),
            csv_safe($row['course_unit_title']),
            $row['assessments'],
            $row['students_assessed'],
            $row['average_score'],
            $row['below_pass'],
        ]);
    }
} elseif ($report === 'marks') {
    fputcsv($output, [
        'Mark ID',
        'Registration number',
        'Student name',
        'Course',
        'Unit code',
        'Unit title',
        'Academic year',
        'Semester',
        'Assessment type',
        'Assessment name',
        'Score',
        'Grade',
        'Remarks',
        'Last updated',
    ]);
    $rows = report_mark_rows($conn, $filters);
    foreach ($rows as $row) {
        fputcsv($output, [
            $row['mark_id'],
            csv_safe($row['reg_no']),
            csv_safe($row['student_name']),
            csv_safe($row['course_code']),
            csv_safe($row['course_unit_code']),
            csv_safe($row['course_unit_title']),
            csv_safe($row['academic_year']),
            csv_safe($row['semester']),
            csv_safe($row['assessment_type']),
            csv_safe($row['assessment_name']),
            $row['score'],
            csv_safe($row['grade']),
            csv_safe($row['remarks']),
            $row['updated_at'],
        ]);
    }
} else {
    fputcsv($output, [
        'Payment ID',
        'Payment date',
        'Registration number',
        'Student name',
        'Course',
        'Fee type',
        'Amount',
        'Status',
        'Method',
        'Reference number',
    ]);
    $rows = report_payment_rows($conn, $filters);
    foreach ($rows as $row) {
        fputcsv($output, [
            $row['payment_id'],
            $row['payment_date'],
            csv_safe($row['reg_no']),
            csv_safe($row['student_name']),
            csv_safe($row['course_code']),
            csv_safe($row['fee_name']),
            $row['amount'],
            csv_safe($row['payment_status']),
            csv_safe($row['method']),
            csv_safe($row['reference_no']),
        ]);
    }
}

fclose($output);
exit;
