-- MIU Student Information Management System
-- Fresh-install schema for MySQL 8.0+ / compatible MariaDB.
-- WARNING: this script drops the listed application tables before rebuilding them.

CREATE DATABASE IF NOT EXISTS miu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE miu;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS mark;
DROP TABLE IF EXISTS app_users;
DROP TABLE IF EXISTS payment;
DROP TABLE IF EXISTS registration;
DROP TABLE IF EXISTS enrollment;
DROP TABLE IF EXISTS course_unit;
DROP TABLE IF EXISTS student;
DROP TABLE IF EXISTS course;
DROP TABLE IF EXISTS lecturer;
DROP TABLE IF EXISTS fee_type;
DROP TABLE IF EXISTS department;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE department (
    dept_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dept_code VARCHAR(10) NOT NULL,
    dept_name VARCHAR(100) NOT NULL,
    office_location VARCHAR(80) NOT NULL,
    head_of_dept VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_department_code (dept_code),
    UNIQUE KEY uq_department_phone (phone),
    UNIQUE KEY uq_department_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lecturer (
    lecturer_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_no VARCHAR(20) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(120) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    dept_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_lecturer_staff_no (staff_no),
    UNIQUE KEY uq_lecturer_email (email),
    UNIQUE KEY uq_lecturer_phone (phone),
    KEY idx_lecturer_department (dept_id),
    CONSTRAINT fk_lecturer_department FOREIGN KEY (dept_id) REFERENCES department(dept_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE course (
    course_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_code VARCHAR(10) NOT NULL,
    course_title VARCHAR(120) NOT NULL,
    credits TINYINT UNSIGNED NOT NULL,
    dept_id INT UNSIGNED NOT NULL,
    lecturer_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_course_code (course_code),
    KEY idx_course_department (dept_id),
    KEY idx_course_lecturer (lecturer_id),
    CONSTRAINT fk_course_department FOREIGN KEY (dept_id) REFERENCES department(dept_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_course_lecturer FOREIGN KEY (lecturer_id) REFERENCES lecturer(lecturer_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE course_unit (
    course_unit_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_unit_code VARCHAR(15) NOT NULL,
    course_unit_title VARCHAR(120) NOT NULL,
    credit_units TINYINT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_course_unit_code (course_unit_code),
    KEY idx_course_unit_course (course_id),
    CONSTRAINT fk_course_unit_course FOREIGN KEY (course_id) REFERENCES course(course_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student (
    student_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reg_no VARCHAR(20) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    gender ENUM('Male','Female','Other') NOT NULL,
    date_of_birth DATE NOT NULL,
    email VARCHAR(120) NOT NULL,
    address VARCHAR(150) NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_student_reg_no (reg_no),
    UNIQUE KEY uq_student_email (email),
    KEY idx_student_course (course_id),
    CONSTRAINT fk_student_course FOREIGN KEY (course_id) REFERENCES course(course_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE enrollment (
    enrollment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enrollment_date DATE NOT NULL,
    status ENUM('Enrolled','Active','Completed','Dropped','Deferred','Cancelled') NOT NULL,
    student_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_enrollment_student_course (student_id, course_id),
    KEY idx_enrollment_status (status),
    KEY idx_enrollment_course (course_id),
    CONSTRAINT fk_enrollment_student FOREIGN KEY (student_id) REFERENCES student(student_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_enrollment_course FOREIGN KEY (course_id) REFERENCES course(course_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fee_type (
    fee_type_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fee_name VARCHAR(60) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_fee_type_name (fee_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE registration (
    registration_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    fee_type_id INT UNSIGNED NOT NULL,
    registration_date DATE NOT NULL,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_registration_student (student_id),
    KEY idx_registration_course (course_id),
    KEY idx_registration_fee_type (fee_type_id),
    KEY idx_registration_date (registration_date),
    CONSTRAINT fk_registration_student FOREIGN KEY (student_id) REFERENCES student(student_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_registration_course FOREIGN KEY (course_id) REFERENCES course(course_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_registration_fee_type FOREIGN KEY (fee_type_id) REFERENCES fee_type(fee_type_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment (
    payment_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    amount DECIMAL(12,2) NOT NULL,
    payment_status ENUM('Paid','Partial','Pending','Waived') NOT NULL,
    payment_date DATE NOT NULL,
    method ENUM('Cash','Mobile Money','Bank','Card','Other') NOT NULL,
    reference_no VARCHAR(50) NULL,
    student_id INT UNSIGNED NOT NULL,
    fee_type_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payment_reference (reference_no),
    KEY idx_payment_student (student_id),
    KEY idx_payment_fee_type (fee_type_id),
    KEY idx_payment_date (payment_date),
    CONSTRAINT fk_payment_student FOREIGN KEY (student_id) REFERENCES student(student_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_payment_fee_type FOREIGN KEY (fee_type_id) REFERENCES fee_type(fee_type_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE app_users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(60) NOT NULL,
    email VARCHAR(120) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('superadmin','admin','student') NOT NULL DEFAULT 'student',
    student_id INT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_app_user_username (username),
    UNIQUE KEY uq_app_user_email (email),
    UNIQUE KEY uq_app_user_student (student_id),
    KEY idx_app_user_role_active (role, is_active),
    CONSTRAINT fk_app_user_student FOREIGN KEY (student_id) REFERENCES student(student_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mark (
    mark_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    course_unit_id INT UNSIGNED NOT NULL,
    academic_year VARCHAR(9) NOT NULL,
    semester ENUM('Semester 1','Semester 2','Recess') NOT NULL,
    assessment_type ENUM('Assignment','Coursework','Test','Practical','Exam','Project') NOT NULL,
    assessment_name VARCHAR(80) NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    grade VARCHAR(2) NOT NULL,
    remarks VARCHAR(250) NULL,
    recorded_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mark_assessment (student_id, course_unit_id, academic_year, semester, assessment_type, assessment_name),
    KEY idx_mark_student_period (student_id, academic_year, semester),
    KEY idx_mark_course_unit (course_unit_id),
    CONSTRAINT fk_mark_student FOREIGN KEY (student_id) REFERENCES student(student_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_mark_course_unit FOREIGN KEY (course_unit_id) REFERENCES course_unit(course_unit_id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_mark_recorded_by FOREIGN KEY (recorded_by) REFERENCES app_users(user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
    audit_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(30) NOT NULL,
    entity VARCHAR(60) NOT NULL,
    record_id INT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_created_at (created_at),
    KEY idx_audit_user (user_id),
    KEY idx_audit_entity_record (entity, record_id),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES app_users(user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create the first superadmin through setup_admin.php after importing this file.
