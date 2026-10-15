<?php
/**
 * Reviewed entity allow-list used by records.php. Nothing from a URL is used as
 * an SQL identifier unless it appears in this file.
 */

$genderOptions = ['Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'];
$enrollmentOptions = [
    'Enrolled' => 'Enrolled', 'Active' => 'Active', 'Completed' => 'Completed',
    'Dropped' => 'Dropped', 'Deferred' => 'Deferred', 'Cancelled' => 'Cancelled'
];
$semesterOptions = ['Semester 1' => 'Semester 1', 'Semester 2' => 'Semester 2', 'Recess' => 'Recess'];
$assessmentOptions = [
    'Assignment' => 'Assignment', 'Coursework' => 'Coursework', 'Test' => 'Test',
    'Practical' => 'Practical', 'Exam' => 'Exam', 'Project' => 'Project'
];
$paymentStatusOptions = ['Paid' => 'Paid', 'Partial' => 'Partial', 'Pending' => 'Pending', 'Waived' => 'Waived'];
$paymentMethodOptions = ['Cash' => 'Cash', 'Mobile Money' => 'Mobile Money', 'Bank' => 'Bank', 'Card' => 'Card', 'Other' => 'Other'];

$entities = [
    'students' => [
        'table' => 'student', 'title' => 'Students', 'singular' => 'student', 'pk' => 'student_id', 'default_sort' => 'reg_no',
        'fields' => [
            'reg_no' => ['label' => 'Registration number', 'type' => 'text', 'required' => true, 'max' => 17, 'pattern' => REGISTRATION_NUMBER_PATTERN, 'hint' => 'Use 24B/BCS/098/UMC or 24B/BCS/098/UMC/D.'],
            'first_name' => ['label' => 'First name', 'type' => 'text', 'required' => true, 'max' => 50, 'pattern' => "^[\\p{L} .'-]{2,50}$"],
            'last_name' => ['label' => 'Last name', 'type' => 'text', 'required' => true, 'max' => 50, 'pattern' => "^[\\p{L} .'-]{2,50}$"],
            'gender' => ['label' => 'Gender', 'type' => 'select', 'required' => true, 'options' => $genderOptions],
            'date_of_birth' => ['label' => 'Date of birth', 'type' => 'date', 'required' => true, 'min_age' => 10, 'max_age' => 120],
            'email' => ['label' => 'University email', 'type' => 'email', 'required' => true, 'max' => 120],
            'address' => ['label' => 'Address', 'type' => 'textarea', 'required' => true, 'max' => 150, 'pattern' => '^[\\p{L}\\p{N} .,#/()\'-]{5,150}$'],
            'course_id' => ['label' => 'Course', 'type' => 'fk', 'required' => true, 'ref' => 'course', 'refpk' => 'course_id', 'display' => 'course_code']
        ],
        'filters' => [
            'course_id' => ['label' => 'Course', 'type' => 'fk', 'ref' => 'course', 'refpk' => 'course_id', 'display' => 'course_code'],
            'gender' => ['label' => 'Gender', 'type' => 'select', 'options' => $genderOptions]
        ]
    ],
    'departments' => [
        'table' => 'department', 'title' => 'Departments', 'singular' => 'department', 'pk' => 'dept_id', 'default_sort' => 'dept_name',
        'fields' => [
            'dept_code' => ['label' => 'Department code', 'type' => 'text', 'required' => true, 'max' => 10, 'pattern' => '^[A-Za-z0-9-]{2,10}$'],
            'dept_name' => ['label' => 'Department name', 'type' => 'text', 'required' => true, 'max' => 100, 'pattern' => "^[\\p{L}0-9 .&()'/-]{2,100}$"],
            'office_location' => ['label' => 'Office location', 'type' => 'text', 'required' => true, 'max' => 80, 'pattern' => '^[A-Za-z0-9 .,#/-]{2,80}$'],
            'head_of_dept' => ['label' => 'Head of department', 'type' => 'text', 'required' => true, 'max' => 100, 'pattern' => "^[\\p{L} .'-]{2,100}$"],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'required' => true, 'max' => 20, 'pattern' => '^[0-9+() .-]{7,20}$'],
            'email' => ['label' => 'Department email', 'type' => 'email', 'required' => true, 'max' => 120]
        ],
        'filters' => ['dept_code' => ['label' => 'Department code', 'type' => 'text', 'max' => 10]]
    ],
    'lecturers' => [
        'table' => 'lecturer', 'title' => 'Lecturers', 'singular' => 'lecturer', 'pk' => 'lecturer_id', 'default_sort' => 'staff_no',
        'fields' => [
            'staff_no' => ['label' => 'Staff number', 'type' => 'text', 'required' => true, 'max' => 20, 'pattern' => '^[A-Za-z0-9/-]{2,20}$'],
            'first_name' => ['label' => 'First name', 'type' => 'text', 'required' => true, 'max' => 50, 'pattern' => "^[\\p{L} .'-]{2,50}$"],
            'last_name' => ['label' => 'Last name', 'type' => 'text', 'required' => true, 'max' => 50, 'pattern' => "^[\\p{L} .'-]{2,50}$"],
            'email' => ['label' => 'Email', 'type' => 'email', 'required' => true, 'max' => 120],
            'phone' => ['label' => 'Phone', 'type' => 'tel', 'required' => true, 'max' => 20, 'pattern' => '^[0-9+() .-]{7,20}$'],
            'dept_id' => ['label' => 'Department', 'type' => 'fk', 'required' => true, 'ref' => 'department', 'refpk' => 'dept_id', 'display' => 'dept_code']
        ],
        'filters' => ['dept_id' => ['label' => 'Department', 'type' => 'fk', 'ref' => 'department', 'refpk' => 'dept_id', 'display' => 'dept_code']]
    ],
    'courses' => [
        'table' => 'course', 'title' => 'Courses', 'singular' => 'course', 'pk' => 'course_id', 'default_sort' => 'course_code',
        'fields' => [
            'course_code' => ['label' => 'Course code', 'type' => 'text', 'required' => true, 'max' => 10, 'pattern' => '^[A-Za-z0-9-]{2,10}$'],
            'course_title' => ['label' => 'Course title', 'type' => 'text', 'required' => true, 'max' => 120, 'pattern' => "^[\\p{L}0-9 .,&()'/-]{2,120}$"],
            'credits' => ['label' => 'Credits', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 12],
            'dept_id' => ['label' => 'Department', 'type' => 'fk', 'required' => true, 'ref' => 'department', 'refpk' => 'dept_id', 'display' => 'dept_code'],
            'lecturer_id' => ['label' => 'Course lecturer', 'type' => 'fk', 'required' => true, 'ref' => 'lecturer', 'refpk' => 'lecturer_id', 'display' => 'staff_no']
        ],
        'filters' => [
            'dept_id' => ['label' => 'Department', 'type' => 'fk', 'ref' => 'department', 'refpk' => 'dept_id', 'display' => 'dept_code'],
            'lecturer_id' => ['label' => 'Lecturer', 'type' => 'fk', 'ref' => 'lecturer', 'refpk' => 'lecturer_id', 'display' => 'staff_no']
        ]
    ],
    'course_units' => [
        'table' => 'course_unit', 'title' => 'Course units', 'singular' => 'course unit', 'pk' => 'course_unit_id', 'default_sort' => 'course_unit_code',
        'fields' => [
            'course_unit_code' => ['label' => 'Unit code', 'type' => 'text', 'required' => true, 'max' => 15, 'pattern' => '^[A-Za-z0-9/-]{2,15}$'],
            'course_unit_title' => ['label' => 'Unit title', 'type' => 'text', 'required' => true, 'max' => 120, 'pattern' => "^[\\p{L}0-9 .,&()'/-]{2,120}$"],
            'credit_units' => ['label' => 'Credit units', 'type' => 'number', 'required' => true, 'min' => 1, 'max' => 12],
            'course_id' => ['label' => 'Course', 'type' => 'fk', 'required' => true, 'ref' => 'course', 'refpk' => 'course_id', 'display' => 'course_code']
        ],
        'filters' => ['course_id' => ['label' => 'Course', 'type' => 'fk', 'ref' => 'course', 'refpk' => 'course_id', 'display' => 'course_code']]
    ],
    'enrollments' => [
        'table' => 'enrollment', 'title' => 'Enrollments', 'singular' => 'enrollment', 'pk' => 'enrollment_id', 'default_sort' => 'enrollment_date',
        'fields' => [
            'enrollment_date' => ['label' => 'Enrollment date', 'type' => 'date', 'required' => true],
            'status' => ['label' => 'Status', 'type' => 'select', 'required' => true, 'options' => $enrollmentOptions],
            'student_id' => ['label' => 'Student', 'type' => 'fk', 'required' => true, 'ref' => 'student', 'refpk' => 'student_id', 'display' => 'reg_no'],
            'course_id' => ['label' => 'Course', 'type' => 'fk', 'required' => true, 'ref' => 'course', 'refpk' => 'course_id', 'display' => 'course_code']
        ],
        'filters' => [
            'status' => ['label' => 'Status', 'type' => 'select', 'options' => $enrollmentOptions],
            'course_id' => ['label' => 'Course', 'type' => 'fk', 'ref' => 'course', 'refpk' => 'course_id', 'display' => 'course_code']
        ]
    ],
    'registrations' => [
        'table' => 'registration', 'title' => 'Registrations', 'singular' => 'registration', 'pk' => 'registration_id', 'default_sort' => 'registration_date',
        'fields' => [
            'student_id' => ['label' => 'Student', 'type' => 'fk', 'required' => true, 'ref' => 'student', 'refpk' => 'student_id', 'display' => 'reg_no'],
            'course_id' => ['label' => 'Course', 'type' => 'fk', 'required' => true, 'ref' => 'course', 'refpk' => 'course_id', 'display' => 'course_code'],
            'fee_type_id' => ['label' => 'Fee type', 'type' => 'fk', 'required' => true, 'ref' => 'fee_type', 'refpk' => 'fee_type_id', 'display' => 'fee_name'],
            'registration_date' => ['label' => 'Registration date', 'type' => 'date', 'required' => true],
            'amount_paid' => ['label' => 'Amount paid (UGX)', 'type' => 'decimal', 'required' => true, 'min' => 0, 'max' => 9999999999.99]
        ],
        'filters' => [
            'course_id' => ['label' => 'Course', 'type' => 'fk', 'ref' => 'course', 'refpk' => 'course_id', 'display' => 'course_code'],
            'fee_type_id' => ['label' => 'Fee type', 'type' => 'fk', 'ref' => 'fee_type', 'refpk' => 'fee_type_id', 'display' => 'fee_name']
        ]
    ],
    'fee_types' => [
        'table' => 'fee_type', 'title' => 'Fee types', 'singular' => 'fee type', 'pk' => 'fee_type_id', 'default_sort' => 'fee_name',
        'fields' => [
            'fee_name' => ['label' => 'Fee name', 'type' => 'text', 'required' => true, 'max' => 60, 'pattern' => "^[\\p{L}0-9 .&()'/-]{2,60}$"],
            'amount' => ['label' => 'Standard amount (UGX)', 'type' => 'decimal', 'required' => true, 'min' => 0, 'max' => 9999999999.99]
        ],
        'filters' => ['fee_name' => ['label' => 'Fee name contains', 'type' => 'text', 'max' => 60]]
    ],
    'payments' => [
        'table' => 'payment', 'title' => 'Payments', 'singular' => 'payment', 'pk' => 'payment_id', 'default_sort' => 'payment_date',
        'fields' => [
            'amount' => ['label' => 'Amount (UGX)', 'type' => 'decimal', 'required' => true, 'min' => 0.01, 'max' => 9999999999.99],
            'payment_status' => ['label' => 'Payment status', 'type' => 'select', 'required' => true, 'options' => $paymentStatusOptions],
            'payment_date' => ['label' => 'Payment date', 'type' => 'date', 'required' => true],
            'method' => ['label' => 'Payment method', 'type' => 'select', 'required' => true, 'options' => $paymentMethodOptions],
            'reference_no' => ['label' => 'Reference number', 'type' => 'text', 'required' => false, 'max' => 50, 'pattern' => '^[A-Za-z0-9/-]{3,50}$', 'hint' => 'Optional: 3–50 letters, numbers, slash or hyphen.'],
            'student_id' => ['label' => 'Student', 'type' => 'fk', 'required' => true, 'ref' => 'student', 'refpk' => 'student_id', 'display' => 'reg_no'],
            'fee_type_id' => ['label' => 'Fee type', 'type' => 'fk', 'required' => true, 'ref' => 'fee_type', 'refpk' => 'fee_type_id', 'display' => 'fee_name']
        ],
        'filters' => [
            'student_id' => ['label' => 'Student', 'type' => 'fk', 'ref' => 'student', 'refpk' => 'student_id', 'display' => 'reg_no'],
            'fee_type_id' => ['label' => 'Fee type', 'type' => 'fk', 'ref' => 'fee_type', 'refpk' => 'fee_type_id', 'display' => 'fee_name'],
            'method' => ['label' => 'Method', 'type' => 'select', 'options' => $paymentMethodOptions],
            'date_from' => ['label' => 'From payment date', 'type' => 'date_from', 'field' => 'payment_date'],
            'date_to' => ['label' => 'To payment date', 'type' => 'date_to', 'field' => 'payment_date']
        ]
    ],
    'marks' => [
        'table' => 'mark', 'title' => 'Marks', 'singular' => 'mark', 'pk' => 'mark_id', 'default_sort' => 'academic_year',
        'fields' => [
            'student_id' => ['label' => 'Student', 'type' => 'fk', 'required' => true, 'ref' => 'student', 'refpk' => 'student_id', 'display' => 'reg_no'],
            'course_unit_id' => ['label' => 'Course unit', 'type' => 'fk', 'required' => true, 'ref' => 'course_unit', 'refpk' => 'course_unit_id', 'display' => 'course_unit_code'],
            'academic_year' => ['label' => 'Academic year', 'type' => 'text', 'required' => true, 'max' => 9, 'pattern' => '^(19|20)[0-9]{2}/(19|20)[0-9]{2}$', 'hint' => 'Use consecutive years, for example 2026/2027.'],
            'semester' => ['label' => 'Semester', 'type' => 'select', 'required' => true, 'options' => $semesterOptions],
            'assessment_type' => ['label' => 'Assessment type', 'type' => 'select', 'required' => true, 'options' => $assessmentOptions],
            'assessment_name' => ['label' => 'Assessment name', 'type' => 'text', 'required' => true, 'max' => 80, 'pattern' => "^[\\p{L}0-9 .,'()&/-]{2,80}$", 'hint' => 'Example: Final examination or Test 1.'],
            'score' => ['label' => 'Score (%)', 'type' => 'decimal', 'required' => true, 'min' => 0, 'max' => 100, 'step' => '0.01'],
            'grade' => ['label' => 'Grade', 'type' => 'text', 'form' => false, 'readonly' => true],
            'remarks' => ['label' => 'Remarks', 'type' => 'textarea', 'required' => false, 'max' => 250],
            'recorded_by' => ['label' => 'Recorded by user ID', 'type' => 'number', 'form' => false, 'readonly' => true]
        ],
        'filters' => [
            'course_unit_id' => ['label' => 'Course unit', 'type' => 'fk', 'ref' => 'course_unit', 'refpk' => 'course_unit_id', 'display' => 'course_unit_code'],
            'academic_year' => ['label' => 'Academic year', 'type' => 'text', 'max' => 9, 'pattern' => '^(19|20)[0-9]{2}/(19|20)[0-9]{2}$'],
            'semester' => ['label' => 'Semester', 'type' => 'select', 'options' => $semesterOptions]
        ]
    ]
];
