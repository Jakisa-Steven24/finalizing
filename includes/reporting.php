<?php
/** Procedural report query builders shared by the screen and CSV exporters. */

function report_filters(mysqli $conn): array
{
    $filters = [
        'department_id' => get_positive_int('department_id'),
        'course_id' => get_positive_int('course_id'),
        'fee_type_id' => get_positive_int('fee_type_id'),
        'academic_year' => get_string('academic_year'),
        'semester' => get_string('semester'),
        'date_from' => get_string('date_from'),
        'date_to' => get_string('date_to')
    ];
    if ($filters['department_id'] && !foreign_key_exists($conn, 'department', 'dept_id', $filters['department_id'])) $filters['department_id'] = 0;
    if ($filters['course_id'] && !foreign_key_exists($conn, 'course', 'course_id', $filters['course_id'])) $filters['course_id'] = 0;
    if ($filters['fee_type_id'] && !foreign_key_exists($conn, 'fee_type', 'fee_type_id', $filters['fee_type_id'])) $filters['fee_type_id'] = 0;
    if ($filters['academic_year'] !== '' && !valid_academic_year($filters['academic_year'])) $filters['academic_year'] = '';
    if (!in_array($filters['semester'], ['Semester 1', 'Semester 2', 'Recess'], true)) $filters['semester'] = '';
    if ($filters['date_from'] !== '' && !valid_date_value($filters['date_from'])) $filters['date_from'] = '';
    if ($filters['date_to'] !== '' && !valid_date_value($filters['date_to'])) $filters['date_to'] = '';
    if ($filters['date_from'] !== '' && $filters['date_to'] !== '' && $filters['date_from'] > $filters['date_to']) {
        $filters['date_from'] = '';
        $filters['date_to'] = '';
    }
    return $filters;
}

function report_mark_where(array $filters, string &$types, array &$params): string
{
    $where = [];
    if ($filters['department_id']) { $where[] = 'c.dept_id = ?'; $types .= 'i'; $params[] = $filters['department_id']; }
    if ($filters['course_id']) { $where[] = 'c.course_id = ?'; $types .= 'i'; $params[] = $filters['course_id']; }
    if ($filters['academic_year'] !== '') { $where[] = 'm.academic_year = ?'; $types .= 's'; $params[] = $filters['academic_year']; }
    if ($filters['semester'] !== '') { $where[] = 'm.semester = ?'; $types .= 's'; $params[] = $filters['semester']; }
    return $where ? ' WHERE ' . implode(' AND ', $where) : '';
}

function report_payment_where(array $filters, string &$types, array &$params): string
{
    $where = [];
    if ($filters['department_id']) { $where[] = 'c.dept_id = ?'; $types .= 'i'; $params[] = $filters['department_id']; }
    if ($filters['course_id']) { $where[] = 'c.course_id = ?'; $types .= 'i'; $params[] = $filters['course_id']; }
    if ($filters['fee_type_id']) { $where[] = 'p.fee_type_id = ?'; $types .= 'i'; $params[] = $filters['fee_type_id']; }
    if ($filters['date_from'] !== '') { $where[] = 'p.payment_date >= ?'; $types .= 's'; $params[] = $filters['date_from']; }
    if ($filters['date_to'] !== '') { $where[] = 'p.payment_date <= ?'; $types .= 's'; $params[] = $filters['date_to']; }
    return $where ? ' WHERE ' . implode(' AND ', $where) : '';
}

function report_roster_where(array $filters, string &$types, array &$params): string
{
    $where = [];
    if ($filters['department_id']) { $where[] = 'c.dept_id = ?'; $types .= 'i'; $params[] = $filters['department_id']; }
    if ($filters['course_id']) { $where[] = 'c.course_id = ?'; $types .= 'i'; $params[] = $filters['course_id']; }
    return $where ? ' WHERE ' . implode(' AND ', $where) : '';
}

function report_mark_rows(mysqli $conn, array $filters): mysqli_result
{
    $types = ''; $params = [];
    $where = report_mark_where($filters, $types, $params);
    $sql = 'SELECT m.mark_id, s.reg_no, CONCAT(s.first_name, " ", s.last_name) AS student_name, c.course_code, cu.course_unit_code, cu.course_unit_title, m.academic_year, m.semester, m.assessment_type, m.assessment_name, m.score, m.grade, m.remarks, m.updated_at FROM mark m INNER JOIN student s ON s.student_id = m.student_id INNER JOIN course_unit cu ON cu.course_unit_id = m.course_unit_id INNER JOIN course c ON c.course_id = cu.course_id' . $where . ' ORDER BY m.academic_year DESC, m.semester ASC, s.reg_no ASC, cu.course_unit_code ASC, m.mark_id DESC LIMIT 1000';
    return db_result($conn, $sql, $types, $params);
}

function report_performance_rows(mysqli $conn, array $filters): mysqli_result
{
    $types = ''; $params = [];
    $where = report_mark_where($filters, $types, $params);
    $sql = 'SELECT c.course_code, c.course_title, cu.course_unit_code, cu.course_unit_title, COUNT(m.mark_id) AS assessments, COUNT(DISTINCT m.student_id) AS students_assessed, ROUND(AVG(m.score), 2) AS average_score, SUM(CASE WHEN m.score < 50 THEN 1 ELSE 0 END) AS below_pass FROM mark m INNER JOIN course_unit cu ON cu.course_unit_id = m.course_unit_id INNER JOIN course c ON c.course_id = cu.course_id' . $where . ' GROUP BY c.course_id, c.course_code, c.course_title, cu.course_unit_id, cu.course_unit_code, cu.course_unit_title ORDER BY average_score DESC, c.course_code ASC, cu.course_unit_code ASC';
    return db_result($conn, $sql, $types, $params);
}

function report_grade_rows(mysqli $conn, array $filters): mysqli_result
{
    $types = ''; $params = [];
    $where = report_mark_where($filters, $types, $params);
    $sql = 'SELECT m.grade, COUNT(*) AS total FROM mark m INNER JOIN course_unit cu ON cu.course_unit_id = m.course_unit_id INNER JOIN course c ON c.course_id = cu.course_id' . $where . ' GROUP BY m.grade ORDER BY FIELD(m.grade, "A", "B+", "B", "C+", "C", "D+", "D", "F")';
    return db_result($conn, $sql, $types, $params);
}

function report_payment_rows(mysqli $conn, array $filters): mysqli_result
{
    $types = ''; $params = [];
    $where = report_payment_where($filters, $types, $params);
    $sql = 'SELECT p.payment_id, p.payment_date, s.reg_no, CONCAT(s.first_name, " ", s.last_name) AS student_name, c.course_code, f.fee_name, p.amount, p.payment_status, p.method, p.reference_no FROM payment p INNER JOIN student s ON s.student_id = p.student_id INNER JOIN course c ON c.course_id = s.course_id INNER JOIN fee_type f ON f.fee_type_id = p.fee_type_id' . $where . ' ORDER BY p.payment_date DESC, p.payment_id DESC LIMIT 1000';
    return db_result($conn, $sql, $types, $params);
}

function report_roster_rows(mysqli $conn, array $filters): mysqli_result
{
    $types = ''; $params = [];
    $where = report_roster_where($filters, $types, $params);
    $sql = 'SELECT s.reg_no, s.first_name, s.last_name, s.gender, s.email, c.course_code, c.course_title, d.dept_code, d.dept_name FROM student s INNER JOIN course c ON c.course_id = s.course_id INNER JOIN department d ON d.dept_id = c.dept_id' . $where . ' ORDER BY c.course_code ASC, s.reg_no ASC LIMIT 1000';
    return db_result($conn, $sql, $types, $params);
}

function report_course_summary_rows(mysqli $conn, array $filters): mysqli_result
{
    $types = ''; $params = [];
    $where = report_roster_where($filters, $types, $params);
    $sql = 'SELECT c.course_code, c.course_title, d.dept_code, COUNT(s.student_id) AS student_count FROM course c INNER JOIN department d ON d.dept_id = c.dept_id LEFT JOIN student s ON s.course_id = c.course_id' . $where . ' GROUP BY c.course_id, c.course_code, c.course_title, d.dept_code ORDER BY student_count DESC, c.course_code ASC';
    return db_result($conn, $sql, $types, $params);
}

function report_metrics(mysqli $conn, array $filters): array
{
    $rosterTypes = ''; $rosterParams = [];
    $rosterWhere = report_roster_where($filters, $rosterTypes, $rosterParams);
    $student = db_one($conn, 'SELECT COUNT(*) AS total FROM student s INNER JOIN course c ON c.course_id = s.course_id' . $rosterWhere, $rosterTypes, $rosterParams);

    $markTypes = ''; $markParams = [];
    $markWhere = report_mark_where($filters, $markTypes, $markParams);
    $marks = db_one($conn, 'SELECT COUNT(*) AS assessments, COALESCE(AVG(m.score), 0) AS average_score FROM mark m INNER JOIN course_unit cu ON cu.course_unit_id = m.course_unit_id INNER JOIN course c ON c.course_id = cu.course_id' . $markWhere, $markTypes, $markParams);

    $paymentTypes = ''; $paymentParams = [];
    $paymentWhere = report_payment_where($filters, $paymentTypes, $paymentParams);
    $payments = db_one($conn, 'SELECT COUNT(*) AS payments, COALESCE(SUM(p.amount), 0) AS total_paid FROM payment p INNER JOIN student s ON s.student_id = p.student_id INNER JOIN course c ON c.course_id = s.course_id' . $paymentWhere, $paymentTypes, $paymentParams);

    return [
        'students' => (int)($student['total'] ?? 0),
        'assessments' => (int)($marks['assessments'] ?? 0),
        'average_score' => (float)($marks['average_score'] ?? 0),
        'payments' => (int)($payments['payments'] ?? 0),
        'total_paid' => (float)($payments['total_paid'] ?? 0)
    ];
}
