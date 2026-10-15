<?php
/**
 * Reviewed entity allow-list used by records.php. Nothing from a URL is used as
 * an SQL identifier unless it appears in this file.
 *
 * Entity settings
 *   table, pk, title, singular  Database table, primary key and display names.
 *   default_sort, default_dir   Initial list ordering (the primary key breaks ties).
 *   fields                      Columns shown in lists, details and forms (see below).
 *   filters                     Extra list filters (fk, select, text, date_from, date_to).
 *   unique                      Duplicate checks; 'message' is optional for single columns.
 *   dependents                  Child records that must be removed before a delete.
 *
 * Field settings
 *   label, type                 type: text, email, tel, textarea, date, number, decimal, select or fk.
 *   required, hint, pattern     pattern is checked on the server and copied to the input.
 *   max / min                   Text: maximum characters. Number/decimal: allowed range.
 *   options                     Allowed values for select fields.
 *   ref, refpk, display         fk target table, key and label column(s).
 *   form => false               Calculated by the server; never accepted from a form.
 */

$genderOptions = [
    'Male' => 'Male',
    'Female' => 'Female',
    'Other' => 'Other',
];
$enrollmentOptions = [
    'Enrolled' => 'Enrolled',
    'Active' => 'Active',
    'Completed' => 'Completed',
    'Dropped' => 'Dropped',
    'Deferred' => 'Deferred',
    'Cancelled' => 'Cancelled',
];
$semesterOptions = [
    'Semester 1' => 'Semester 1',
    'Semester 2' => 'Semester 2',
    'Recess' => 'Recess',
];
$assessmentOptions = [
    'Assignment' => 'Assignment',
    'Coursework' => 'Coursework',
    'Test' => 'Test',
    'Practical' => 'Practical',
    'Exam' => 'Exam',
    'Project' => 'Project',
];
$paymentStatusOptions = [
    'Paid' => 'Paid',
    'Partial' => 'Partial',
    'Pending' => 'Pending',
    'Waived' => 'Waived',
];
$paymentMethodOptions = [
    'Cash' => 'Cash',
    'Mobile Money' => 'Mobile Money',
    'Bank' => 'Bank',
    'Card' => 'Card',
    'Other' => 'Other',
];

/*
 * Related-record lookups. The first display column identifies the record; the
 * remaining columns are shown after an em dash, e.g. "BCS — Bachelor of ...".
 */
$departmentReference = [
    'ref' => 'department',
    'refpk' => 'dept_id',
    'display' => ['dept_code', 'dept_name'],
];
$lecturerReference = [
    'ref' => 'lecturer',
    'refpk' => 'lecturer_id',
    'display' => ['staff_no', 'first_name', 'last_name'],
];
$courseReference = [
    'ref' => 'course',
    'refpk' => 'course_id',
    'display' => ['course_code', 'course_title'],
];
$courseUnitReference = [
    'ref' => 'course_unit',
    'refpk' => 'course_unit_id',
    'display' => ['course_unit_code', 'course_unit_title'],
];
$studentReference = [
    'ref' => 'student',
    'refpk' => 'student_id',
    'display' => ['reg_no', 'first_name', 'last_name'],
];
$feeTypeReference = [
    'ref' => 'fee_type',
    'refpk' => 'fee_type_id',
    'display' => ['fee_name'],
];
$userReference = [
    'ref' => 'app_users',
    'refpk' => 'user_id',
    'display' => ['username', 'full_name'],
];

/*
 * Patterns are written so they work both in PHP (PCRE) and in browser pattern
 * attributes: "/", "(", ")" and "-" are escaped inside character classes.
 */
$personNamePattern = "^[\\p{L} .'\\-]{2,50}$";
$titlePattern = "[\\p{L}0-9 .,&'\\/\\(\\)\\-]";
$codePattern = '^[A-Za-z0-9\-]{2,10}$';
$phonePattern = '^[0-9+ .\(\)\-]{7,20}$';
$academicYearPattern = '^(19|20)[0-9]{2}/(19|20)[0-9]{2}$';
$personNameFormat = 'Use 2–50 letters, spaces, apostrophes, dots or hyphens.';
$codeFormat = 'Use 2–10 letters, numbers or hyphens.';
$phoneFormat = 'Use 7–20 digits, spaces and + ( ) . - characters.';
$titleCharacters = 'letters, numbers, spaces and . , & \' / ( ) -';

$entities = [
    'students' => [
        'table' => 'student',
        'title' => 'Students',
        'singular' => 'student',
        'pk' => 'student_id',
        'default_sort' => 'reg_no',
        'default_dir' => 'ASC',
        'fields' => [
            'reg_no' => [
                'label' => 'Registration number',
                'type' => 'text',
                'required' => true,
                'max' => 17,
                'pattern' => REGISTRATION_NUMBER_PATTERN,
                'hint' => 'Use 24B/BCS/098/UMC or 24B/BCS/098/UMC/D.',
            ],
            'first_name' => [
                'label' => 'First name',
                'type' => 'text',
                'required' => true,
                'max' => 50,
                'pattern' => $personNamePattern,
                'format' => $personNameFormat,
            ],
            'last_name' => [
                'label' => 'Last name',
                'type' => 'text',
                'required' => true,
                'max' => 50,
                'pattern' => $personNamePattern,
                'format' => $personNameFormat,
            ],
            'gender' => [
                'label' => 'Gender',
                'type' => 'select',
                'required' => true,
                'options' => $genderOptions,
            ],
            'date_of_birth' => [
                'label' => 'Date of birth',
                'type' => 'date',
                'required' => true,
                'min_age' => 10,
                'max_age' => 120,
            ],
            'email' => [
                'label' => 'University email',
                'type' => 'email',
                'required' => true,
                'max' => 120,
            ],
            'address' => [
                'label' => 'Address',
                'type' => 'textarea',
                'required' => true,
                'max' => 150,
                'pattern' => "^[\\p{L}\\p{N} .,#&'\\/\\(\\)\\-\\r\\n]{5,150}$",
                'hint' => 'Letters, numbers, spaces, line breaks and . , # & \' / ( ) -',
            ],
            'course_id' => [
                'label' => 'Course',
                'type' => 'fk',
                'required' => true,
            ] + $courseReference,
        ],
        'filters' => [
            'course_id' => [
                'label' => 'Course',
                'type' => 'fk',
            ] + $courseReference,
            'gender' => [
                'label' => 'Gender',
                'type' => 'select',
                'options' => $genderOptions,
            ],
        ],
        'unique' => [
            ['columns' => ['reg_no']],
            ['columns' => ['email']],
        ],
        'dependents' => [
            ['table' => 'enrollment', 'column' => 'student_id', 'singular' => 'enrollment', 'plural' => 'enrollments'],
            ['table' => 'registration', 'column' => 'student_id', 'singular' => 'registration', 'plural' => 'registrations'],
            ['table' => 'payment', 'column' => 'student_id', 'singular' => 'payment', 'plural' => 'payments'],
            ['table' => 'mark', 'column' => 'student_id', 'singular' => 'mark', 'plural' => 'marks'],
            ['table' => 'app_users', 'column' => 'student_id', 'singular' => 'login account', 'plural' => 'login accounts'],
        ],
    ],
    'departments' => [
        'table' => 'department',
        'title' => 'Departments',
        'singular' => 'department',
        'pk' => 'dept_id',
        'default_sort' => 'dept_name',
        'default_dir' => 'ASC',
        'fields' => [
            'dept_code' => [
                'label' => 'Department code',
                'type' => 'text',
                'required' => true,
                'max' => 10,
                'pattern' => $codePattern,
                'format' => $codeFormat,
            ],
            'dept_name' => [
                'label' => 'Department name',
                'type' => 'text',
                'required' => true,
                'max' => 100,
                'pattern' => '^' . $titlePattern . '{2,100}$',
                'format' => 'Use 2–100 ' . $titleCharacters . '.',
            ],
            'office_location' => [
                'label' => 'Office location',
                'type' => 'text',
                'required' => true,
                'max' => 80,
                'pattern' => "^[\\p{L}0-9 .,#&'\\/\\(\\)\\-]{2,80}$",
                'format' => 'Use 2–80 letters, numbers, spaces and . , # & \' / ( ) -.',
            ],
            'head_of_dept' => [
                'label' => 'Head of department',
                'type' => 'text',
                'required' => true,
                'max' => 100,
                'pattern' => "^[\\p{L} .'\\-]{2,100}$",
                'format' => 'Use 2–100 letters, spaces, apostrophes, dots or hyphens.',
            ],
            'phone' => [
                'label' => 'Phone',
                'type' => 'tel',
                'required' => true,
                'max' => 20,
                'pattern' => $phonePattern,
                'format' => $phoneFormat,
            ],
            'email' => [
                'label' => 'Department email',
                'type' => 'email',
                'required' => true,
                'max' => 120,
            ],
        ],
        'filters' => [
            'dept_code' => [
                'label' => 'Department code',
                'type' => 'text',
                'max' => 10,
            ],
        ],
        'unique' => [
            ['columns' => ['dept_code']],
            ['columns' => ['phone']],
            ['columns' => ['email']],
        ],
        'dependents' => [
            ['table' => 'lecturer', 'column' => 'dept_id', 'singular' => 'lecturer', 'plural' => 'lecturers'],
            ['table' => 'course', 'column' => 'dept_id', 'singular' => 'course', 'plural' => 'courses'],
        ],
    ],
    'lecturers' => [
        'table' => 'lecturer',
        'title' => 'Lecturers',
        'singular' => 'lecturer',
        'pk' => 'lecturer_id',
        'default_sort' => 'staff_no',
        'default_dir' => 'ASC',
        'fields' => [
            'staff_no' => [
                'label' => 'Staff number',
                'type' => 'text',
                'required' => true,
                'max' => 20,
                'pattern' => '^[A-Za-z0-9\/\-]{2,20}$',
                'format' => 'Use 2–20 letters, numbers, slashes or hyphens.',
            ],
            'first_name' => [
                'label' => 'First name',
                'type' => 'text',
                'required' => true,
                'max' => 50,
                'pattern' => $personNamePattern,
                'format' => $personNameFormat,
            ],
            'last_name' => [
                'label' => 'Last name',
                'type' => 'text',
                'required' => true,
                'max' => 50,
                'pattern' => $personNamePattern,
                'format' => $personNameFormat,
            ],
            'email' => [
                'label' => 'Email',
                'type' => 'email',
                'required' => true,
                'max' => 120,
            ],
            'phone' => [
                'label' => 'Phone',
                'type' => 'tel',
                'required' => true,
                'max' => 20,
                'pattern' => $phonePattern,
                'format' => $phoneFormat,
            ],
            'dept_id' => [
                'label' => 'Department',
                'type' => 'fk',
                'required' => true,
            ] + $departmentReference,
        ],
        'filters' => [
            'dept_id' => [
                'label' => 'Department',
                'type' => 'fk',
            ] + $departmentReference,
        ],
        'unique' => [
            ['columns' => ['staff_no']],
            ['columns' => ['email']],
            ['columns' => ['phone']],
        ],
        'dependents' => [
            ['table' => 'course', 'column' => 'lecturer_id', 'singular' => 'course', 'plural' => 'courses'],
        ],
    ],
    'courses' => [
        'table' => 'course',
        'title' => 'Courses',
        'singular' => 'course',
        'pk' => 'course_id',
        'default_sort' => 'course_code',
        'default_dir' => 'ASC',
        'fields' => [
            'course_code' => [
                'label' => 'Course code',
                'type' => 'text',
                'required' => true,
                'max' => 10,
                'pattern' => $codePattern,
                'format' => $codeFormat,
            ],
            'course_title' => [
                'label' => 'Course title',
                'type' => 'text',
                'required' => true,
                'max' => 120,
                'pattern' => '^' . $titlePattern . '{2,120}$',
                'format' => 'Use 2–120 ' . $titleCharacters . '.',
            ],
            'credits' => [
                'label' => 'Credits',
                'type' => 'number',
                'required' => true,
                'min' => 1,
                'max' => 12,
            ],
            'dept_id' => [
                'label' => 'Department',
                'type' => 'fk',
                'required' => true,
            ] + $departmentReference,
            'lecturer_id' => [
                'label' => 'Course lecturer',
                'type' => 'fk',
                'required' => true,
            ] + $lecturerReference,
        ],
        'filters' => [
            'dept_id' => [
                'label' => 'Department',
                'type' => 'fk',
            ] + $departmentReference,
            'lecturer_id' => [
                'label' => 'Lecturer',
                'type' => 'fk',
            ] + $lecturerReference,
        ],
        'unique' => [
            ['columns' => ['course_code']],
        ],
        'dependents' => [
            ['table' => 'course_unit', 'column' => 'course_id', 'singular' => 'course unit', 'plural' => 'course units'],
            ['table' => 'student', 'column' => 'course_id', 'singular' => 'student', 'plural' => 'students'],
            ['table' => 'enrollment', 'column' => 'course_id', 'singular' => 'enrollment', 'plural' => 'enrollments'],
            ['table' => 'registration', 'column' => 'course_id', 'singular' => 'registration', 'plural' => 'registrations'],
        ],
    ],
    'course_units' => [
        'table' => 'course_unit',
        'title' => 'Course units',
        'singular' => 'course unit',
        'pk' => 'course_unit_id',
        'default_sort' => 'course_unit_code',
        'default_dir' => 'ASC',
        'fields' => [
            'course_unit_code' => [
                'label' => 'Unit code',
                'type' => 'text',
                'required' => true,
                'max' => 15,
                'pattern' => '^[A-Za-z0-9\/\-]{2,15}$',
                'format' => 'Use 2–15 letters, numbers, slashes or hyphens.',
            ],
            'course_unit_title' => [
                'label' => 'Unit title',
                'type' => 'text',
                'required' => true,
                'max' => 120,
                'pattern' => '^' . $titlePattern . '{2,120}$',
                'format' => 'Use 2–120 ' . $titleCharacters . '.',
            ],
            'credit_units' => [
                'label' => 'Credit units',
                'type' => 'number',
                'required' => true,
                'min' => 1,
                'max' => 12,
            ],
            'course_id' => [
                'label' => 'Course',
                'type' => 'fk',
                'required' => true,
            ] + $courseReference,
        ],
        'filters' => [
            'course_id' => [
                'label' => 'Course',
                'type' => 'fk',
            ] + $courseReference,
        ],
        'unique' => [
            ['columns' => ['course_unit_code']],
        ],
        'dependents' => [
            ['table' => 'mark', 'column' => 'course_unit_id', 'singular' => 'mark', 'plural' => 'marks'],
        ],
    ],
    'enrollments' => [
        'table' => 'enrollment',
        'title' => 'Enrollments',
        'singular' => 'enrollment',
        'pk' => 'enrollment_id',
        'default_sort' => 'enrollment_date',
        'default_dir' => 'DESC',
        'fields' => [
            'enrollment_date' => [
                'label' => 'Enrollment date',
                'type' => 'date',
                'required' => true,
            ],
            'status' => [
                'label' => 'Status',
                'type' => 'select',
                'required' => true,
                'options' => $enrollmentOptions,
            ],
            'student_id' => [
                'label' => 'Student',
                'type' => 'fk',
                'required' => true,
            ] + $studentReference,
            'course_id' => [
                'label' => 'Course',
                'type' => 'fk',
                'required' => true,
                'hint' => 'Must be the student\'s current course.',
            ] + $courseReference,
        ],
        'filters' => [
            'status' => [
                'label' => 'Status',
                'type' => 'select',
                'options' => $enrollmentOptions,
            ],
            'course_id' => [
                'label' => 'Course',
                'type' => 'fk',
            ] + $courseReference,
        ],
        'unique' => [
            [
                'columns' => ['student_id', 'course_id'],
                'message' => 'This student is already enrolled in the selected course.',
            ],
        ],
        'dependents' => [],
    ],
    'registrations' => [
        'table' => 'registration',
        'title' => 'Registrations',
        'singular' => 'registration',
        'pk' => 'registration_id',
        'default_sort' => 'registration_date',
        'default_dir' => 'DESC',
        'fields' => [
            'student_id' => [
                'label' => 'Student',
                'type' => 'fk',
                'required' => true,
            ] + $studentReference,
            'course_id' => [
                'label' => 'Course',
                'type' => 'fk',
                'required' => true,
                'hint' => 'Must be the student\'s current course.',
            ] + $courseReference,
            'fee_type_id' => [
                'label' => 'Fee type',
                'type' => 'fk',
                'required' => true,
            ] + $feeTypeReference,
            'registration_date' => [
                'label' => 'Registration date',
                'type' => 'date',
                'required' => true,
            ],
            'amount_paid' => [
                'label' => 'Amount paid (UGX)',
                'type' => 'decimal',
                'required' => true,
                'min' => 0,
                'max' => 9999999999.99,
            ],
        ],
        'filters' => [
            'course_id' => [
                'label' => 'Course',
                'type' => 'fk',
            ] + $courseReference,
            'fee_type_id' => [
                'label' => 'Fee type',
                'type' => 'fk',
            ] + $feeTypeReference,
        ],
        'unique' => [],
        'dependents' => [],
    ],
    'fee_types' => [
        'table' => 'fee_type',
        'title' => 'Fee types',
        'singular' => 'fee type',
        'pk' => 'fee_type_id',
        'default_sort' => 'fee_name',
        'default_dir' => 'ASC',
        'fields' => [
            'fee_name' => [
                'label' => 'Fee name',
                'type' => 'text',
                'required' => true,
                'max' => 60,
                'pattern' => '^' . $titlePattern . '{2,60}$',
                'format' => 'Use 2–60 ' . $titleCharacters . '.',
            ],
            'amount' => [
                'label' => 'Standard amount (UGX)',
                'type' => 'decimal',
                'required' => true,
                'min' => 0,
                'max' => 9999999999.99,
            ],
        ],
        'filters' => [
            'fee_name' => [
                'label' => 'Fee name contains',
                'type' => 'text',
                'max' => 60,
            ],
        ],
        'unique' => [
            ['columns' => ['fee_name']],
        ],
        'dependents' => [
            ['table' => 'registration', 'column' => 'fee_type_id', 'singular' => 'registration', 'plural' => 'registrations'],
            ['table' => 'payment', 'column' => 'fee_type_id', 'singular' => 'payment', 'plural' => 'payments'],
        ],
    ],
    'payments' => [
        'table' => 'payment',
        'title' => 'Payments',
        'singular' => 'payment',
        'pk' => 'payment_id',
        'default_sort' => 'payment_date',
        'default_dir' => 'DESC',
        'fields' => [
            'amount' => [
                'label' => 'Amount (UGX)',
                'type' => 'decimal',
                'required' => true,
                'min' => 0.01,
                'max' => 9999999999.99,
            ],
            'payment_status' => [
                'label' => 'Payment status',
                'type' => 'select',
                'required' => true,
                'options' => $paymentStatusOptions,
            ],
            'payment_date' => [
                'label' => 'Payment date',
                'type' => 'date',
                'required' => true,
            ],
            'method' => [
                'label' => 'Payment method',
                'type' => 'select',
                'required' => true,
                'options' => $paymentMethodOptions,
            ],
            'reference_no' => [
                'label' => 'Reference number',
                'type' => 'text',
                'required' => false,
                'max' => 50,
                'pattern' => '^[A-Za-z0-9\/\-]{3,50}$',
                'hint' => 'Optional: 3–50 letters, numbers, slash or hyphen.',
            ],
            'student_id' => [
                'label' => 'Student',
                'type' => 'fk',
                'required' => true,
            ] + $studentReference,
            'fee_type_id' => [
                'label' => 'Fee type',
                'type' => 'fk',
                'required' => true,
            ] + $feeTypeReference,
        ],
        'filters' => [
            'student_id' => [
                'label' => 'Student',
                'type' => 'fk',
            ] + $studentReference,
            'fee_type_id' => [
                'label' => 'Fee type',
                'type' => 'fk',
            ] + $feeTypeReference,
            'method' => [
                'label' => 'Method',
                'type' => 'select',
                'options' => $paymentMethodOptions,
            ],
            'date_from' => [
                'label' => 'From payment date',
                'type' => 'date_from',
                'field' => 'payment_date',
            ],
            'date_to' => [
                'label' => 'To payment date',
                'type' => 'date_to',
                'field' => 'payment_date',
            ],
        ],
        'unique' => [
            ['columns' => ['reference_no']],
        ],
        'dependents' => [],
    ],
    'marks' => [
        'table' => 'mark',
        'title' => 'Marks',
        'singular' => 'mark',
        'pk' => 'mark_id',
        'default_sort' => 'academic_year',
        'default_dir' => 'DESC',
        'fields' => [
            'student_id' => [
                'label' => 'Student',
                'type' => 'fk',
                'required' => true,
            ] + $studentReference,
            'course_unit_id' => [
                'label' => 'Course unit',
                'type' => 'fk',
                'required' => true,
                'hint' => 'Only units that belong to the student\'s course are accepted.',
            ] + $courseUnitReference,
            'academic_year' => [
                'label' => 'Academic year',
                'type' => 'text',
                'required' => true,
                'max' => 9,
                'pattern' => $academicYearPattern,
                'hint' => 'Use consecutive years, for example 2026/2027.',
            ],
            'semester' => [
                'label' => 'Semester',
                'type' => 'select',
                'required' => true,
                'options' => $semesterOptions,
            ],
            'assessment_type' => [
                'label' => 'Assessment type',
                'type' => 'select',
                'required' => true,
                'options' => $assessmentOptions,
            ],
            'assessment_name' => [
                'label' => 'Assessment name',
                'type' => 'text',
                'required' => true,
                'max' => 80,
                'pattern' => '^' . $titlePattern . '{2,80}$',
                'format' => 'Use 2–80 ' . $titleCharacters . '.',
                'hint' => 'Example: Final examination or Test 1.',
            ],
            'score' => [
                'label' => 'Score (%)',
                'type' => 'decimal',
                'required' => true,
                'min' => 0,
                'max' => 100,
                'hint' => 'The grade is calculated automatically from the score.',
            ],
            'grade' => [
                'label' => 'Grade',
                'type' => 'text',
                'form' => false,
            ],
            'remarks' => [
                'label' => 'Remarks',
                'type' => 'textarea',
                'required' => false,
                'max' => 250,
            ],
            'recorded_by' => [
                'label' => 'Recorded by',
                'type' => 'fk',
                'form' => false,
            ] + $userReference,
        ],
        'filters' => [
            'course_unit_id' => [
                'label' => 'Course unit',
                'type' => 'fk',
            ] + $courseUnitReference,
            'academic_year' => [
                'label' => 'Academic year',
                'type' => 'text',
                'max' => 9,
                'pattern' => $academicYearPattern,
            ],
            'semester' => [
                'label' => 'Semester',
                'type' => 'select',
                'options' => $semesterOptions,
            ],
        ],
        'unique' => [
            [
                'columns' => ['student_id', 'course_unit_id', 'academic_year', 'semester', 'assessment_type', 'assessment_name'],
                'message' => 'This assessment is already recorded for the selected student, course unit, year and semester.',
            ],
        ],
        'dependents' => [],
    ],
];
