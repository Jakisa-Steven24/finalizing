# Entity Relationship Diagram (ERD)

The diagram is written in Mermaid so GitHub-compatible Markdown viewers can render it. Cardinality markers describe logical relationships; implementation details such as indexes and `ON DELETE` actions are in `database.sql`.

```mermaid
erDiagram
    DEPARTMENT ||--o{ LECTURER : employs
    DEPARTMENT ||--o{ COURSE : owns
    LECTURER ||--o{ COURSE : teaches
    COURSE ||--o{ COURSE_UNIT : contains
    COURSE ||--o{ STUDENT : admits
    STUDENT ||--o{ ENROLLMENT : has
    COURSE ||--o{ ENROLLMENT : applies_to
    STUDENT ||--o{ REGISTRATION : has
    COURSE ||--o{ REGISTRATION : applies_to
    FEE_TYPE ||--o{ REGISTRATION : classifies
    STUDENT ||--o{ PAYMENT : makes
    FEE_TYPE ||--o{ PAYMENT : classifies
    STUDENT ||--o| APP_USERS : authenticates_as
    STUDENT ||--o{ MARK : receives
    COURSE_UNIT ||--o{ MARK : assesses
    APP_USERS o|--o{ MARK : records
    APP_USERS o|--o{ AUDIT_LOG : performs

    DEPARTMENT {
      int dept_id PK
      string dept_code UK
      string dept_name
      string office_location
      string head_of_dept
      string phone UK
      string email UK
    }
    LECTURER {
      int lecturer_id PK
      string staff_no UK
      string first_name
      string last_name
      string email UK
      string phone UK
      int dept_id FK
    }
    COURSE {
      int course_id PK
      string course_code UK
      string course_title
      int credits
      int dept_id FK
      int lecturer_id FK
    }
    COURSE_UNIT {
      int course_unit_id PK
      string course_unit_code UK
      string course_unit_title
      int credit_units
      int course_id FK
    }
    STUDENT {
      int student_id PK
      string reg_no UK
      string first_name
      string last_name
      string gender
      date date_of_birth
      string email UK
      string address
      int course_id FK
    }
    ENROLLMENT {
      int enrollment_id PK
      date enrollment_date
      string status
      int student_id FK
      int course_id FK
    }
    FEE_TYPE {
      int fee_type_id PK
      string fee_name UK
      decimal amount
    }
    REGISTRATION {
      int registration_id PK
      int student_id FK
      int course_id FK
      int fee_type_id FK
      date registration_date
      decimal amount_paid
    }
    PAYMENT {
      int payment_id PK
      decimal amount
      string payment_status
      date payment_date
      string method
      string reference_no UK
      int student_id FK
      int fee_type_id FK
    }
    APP_USERS {
      int user_id PK
      string full_name
      string username UK
      string email UK
      string password_hash
      string role
      int student_id FK_UK
      boolean is_active
      datetime locked_until
    }
    MARK {
      int mark_id PK
      int student_id FK
      int course_unit_id FK
      string academic_year
      string semester
      string assessment_type
      string assessment_name
      decimal score
      string grade
      int recorded_by FK
    }
    AUDIT_LOG {
      int audit_id PK
      int user_id FK
      string action
      string entity
      int record_id
      string description
      datetime created_at
    }
```

## Relationship notes

1. `department` is the root of the academic structure. A lecturer and course must belong to a department; a course also identifies the lecturer assigned to it.
2. A course has many units and students. A student may have many enrollment and registration events, but the application uses the student’s current course to ensure a mark is entered against a compatible unit.
3. Payments and registrations refer to `fee_type` so financial reporting can group by fee category.
4. `app_users.student_id` is nullable for staff and **unique** when present, which permits one student login per student record. It is not a student-profile table; it is an authentication/authorization table.
5. Marks use a composite unique key covering the student, unit, academic period and assessment identity. This prevents accidental duplicate entry of the same assessment.
6. The audit log uses `ON DELETE SET NULL` for the actor relationship so audit history survives a user-account deletion. Mark recorder attribution follows the same preservation approach.
