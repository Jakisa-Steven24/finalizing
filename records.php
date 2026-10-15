<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_staff();
require_once 'includes/entities.php';

/* -------------------------------------------------------------------------
 * Field helpers
 * ---------------------------------------------------------------------- */

function record_is_form_field(array $field): bool
{
    return !isset($field['form']) || $field['form'] !== false;
}

function record_field_bind_type(array $field): string
{
    if (in_array($field['type'], ['number', 'fk'], true)) {
        return 'i';
    }
    return 's';
}

function record_input_type(array $field): string
{
    if (in_array($field['type'], ['number', 'decimal'], true)) {
        return 'number';
    }
    return $field['type'];
}

/**
 * Extra HTML attributes (min/max/step, maxlength, pattern) for an <input>.
 */
function record_input_attributes(array $field): string
{
    $attributes = '';
    if (record_input_type($field) === 'number') {
        if (isset($field['min'])) {
            $attributes .= ' min="' . e((string)$field['min']) . '"';
        }
        if (isset($field['max'])) {
            $attributes .= ' max="' . e((string)$field['max']) . '"';
        }
        $step = $field['type'] === 'decimal' ? '0.01' : '1';
        $attributes .= ' step="' . $step . '"';
        return $attributes;
    }
    if (isset($field['max']) && $field['type'] !== 'date') {
        $attributes .= ' maxlength="' . (int)$field['max'] . '"';
    }
    if (isset($field['pattern'])) {
        $attributes .= ' pattern="' . e($field['pattern']) . '"';
    }
    return $attributes;
}

/**
 * Raw submitted values, used to re-display the form exactly as typed after a failed save.
 */
function record_submitted_values(array $config): array
{
    $values = [];
    foreach ($config['fields'] as $name => $field) {
        if (record_is_form_field($field)) {
            $values[$name] = post_string($name);
        }
    }
    return $values;
}

function record_display_value(array $row, string $name, array $field): string
{
    $labelKey = $name . '_label';
    if ($field['type'] === 'fk' && isset($row[$labelKey]) && $row[$labelKey] !== '') {
        return (string)$row[$labelKey];
    }
    $value = $row[$name] ?? null;
    if ($value === null || $value === '') {
        return '—';
    }
    if ($field['type'] === 'decimal') {
        return number_format((float)$value, 2);
    }
    return (string)$value;
}

/* -------------------------------------------------------------------------
 * Validation
 * ---------------------------------------------------------------------- */

function record_normalize_date(array $field, string $value, array &$errors): ?string
{
    if (!valid_date_value($value)) {
        $errors[] = $field['label'] . ' must be a real date in YYYY-MM-DD format.';
        return null;
    }
    if (!isset($field['min_age']) && !isset($field['max_age'])) {
        return $value;
    }
    $birthDate = new DateTime($value);
    $today = new DateTime('today');
    if ($birthDate > $today) {
        $errors[] = $field['label'] . ' cannot be in the future.';
        return null;
    }
    $age = $birthDate->diff($today)->y;
    $minimumAge = (int)($field['min_age'] ?? 0);
    $maximumAge = (int)($field['max_age'] ?? 200);
    if ($age < $minimumAge || $age > $maximumAge) {
        $errors[] = $field['label'] . ' is outside the permitted age range (' . $minimumAge . '–' . $maximumAge . ' years).';
        return null;
    }
    return $value;
}

function record_normalize_number(array $field, string $value, array &$errors): ?int
{
    if (!preg_match('/^[0-9]+$/', $value)) {
        $errors[] = $field['label'] . ' must be a whole number.';
        return null;
    }
    $number = (int)$value;
    if (isset($field['min']) && $number < $field['min']) {
        $errors[] = $field['label'] . ' must be at least ' . $field['min'] . '.';
        return null;
    }
    if (isset($field['max']) && $number > $field['max']) {
        $errors[] = $field['label'] . ' must not exceed ' . $field['max'] . '.';
        return null;
    }
    return $number;
}

function record_normalize_decimal(array $field, string $value, array &$errors): ?string
{
    if (!preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/', $value)) {
        $errors[] = $field['label'] . ' must be a positive number with at most two decimal places.';
        return null;
    }
    $number = (float)$value;
    if (isset($field['min']) && $number < (float)$field['min']) {
        $errors[] = $field['label'] . ' must be at least ' . $field['min'] . '.';
        return null;
    }
    if (isset($field['max']) && $number > (float)$field['max']) {
        $errors[] = $field['label'] . ' must not exceed ' . $field['max'] . '.';
        return null;
    }
    return number_format($number, 2, '.', '');
}

/**
 * Validates one non-empty submitted value and returns it normalised.
 * Problems are appended to $errors (the caller compares the error count).
 */
function record_normalize_value(mysqli $conn, string $name, array $field, string $value, array &$errors)
{
    $label = $field['label'];
    $type = $field['type'];
    $isText = in_array($type, ['text', 'email', 'tel', 'textarea'], true);

    if ($isText && isset($field['max']) && text_length($value) > (int)$field['max']) {
        $errors[] = $label . ' must not exceed ' . (int)$field['max'] . ' characters.';
        return null;
    }
    if (isset($field['pattern']) && preg_match('~' . $field['pattern'] . '~uD', $value) !== 1) {
        $message = $label . ' has an invalid format.';
        $explanation = $field['format'] ?? $field['hint'] ?? '';
        if ($explanation !== '') {
            $message .= ' ' . $explanation;
        }
        $errors[] = $message;
        return null;
    }
    if ($type === 'email' && !valid_email($value)) {
        $errors[] = $label . ' must be a valid email address.';
        return null;
    }
    if ($type === 'tel' && !valid_phone($value)) {
        $errors[] = $label . ' must be a valid phone number.';
        return null;
    }
    if ($type === 'date') {
        return record_normalize_date($field, $value, $errors);
    }
    if ($type === 'number') {
        return record_normalize_number($field, $value, $errors);
    }
    if ($type === 'decimal') {
        return record_normalize_decimal($field, $value, $errors);
    }
    if ($type === 'select') {
        if (!array_key_exists($value, $field['options'])) {
            $errors[] = 'Select a valid option for ' . $label . '.';
            return null;
        }
        return $value;
    }
    if ($type === 'fk') {
        $isId = preg_match('/^[1-9][0-9]*$/', $value) === 1;
        if (!$isId || !foreign_key_exists($conn, $field['ref'], $field['refpk'], (int)$value)) {
            $errors[] = 'Select an existing ' . strtolower($label) . '.';
            return null;
        }
        return (int)$value;
    }
    if ($name === 'academic_year' && !valid_academic_year($value)) {
        $errors[] = 'Academic year must contain consecutive years, for example 2026/2027.';
        return null;
    }
    return $value;
}

/**
 * Cross-record rules: courses must match the student's course, and marks may
 * only be recorded for units of the student's course.
 */
function record_check_relationships(mysqli $conn, string $entityKey, array $data, array &$errors): void
{
    $checksCourse = in_array($entityKey, ['enrollments', 'registrations'], true);
    if ($checksCourse && !empty($data['student_id']) && !empty($data['course_id'])) {
        $relation = db_one(
            $conn,
            'SELECT 1 FROM student WHERE student_id = ? AND course_id = ? LIMIT 1',
            'ii',
            [(int)$data['student_id'], (int)$data['course_id']]
        );
        if (!$relation) {
            $errors[] = 'The selected course does not match the selected student’s current course.';
        }
    }
    if ($entityKey === 'marks' && !empty($data['student_id']) && !empty($data['course_unit_id'])) {
        $relation = db_one(
            $conn,
            'SELECT 1 FROM student s'
            . ' INNER JOIN course_unit cu ON cu.course_id = s.course_id'
            . ' WHERE s.student_id = ? AND cu.course_unit_id = ?'
            . ' LIMIT 1',
            'ii',
            [(int)$data['student_id'], (int)$data['course_unit_id']]
        );
        if (!$relation) {
            $errors[] = 'The selected course unit does not belong to the selected student’s course.';
        }
    }
}

/**
 * Friendly duplicate checks for the unique keys listed in the entity configuration.
 */
function record_check_unique(mysqli $conn, array $config, array $data, int $recordId, array &$errors): void
{
    foreach ($config['unique'] ?? [] as $rule) {
        $conditions = [];
        $types = '';
        $params = [];
        foreach ($rule['columns'] as $column) {
            if (!isset($data[$column])) {
                continue 2;
            }
            $conditions[] = sql_identifier($column) . ' = ?';
            $types .= record_field_bind_type($config['fields'][$column]);
            $params[] = $data[$column];
        }
        $sql = 'SELECT 1 FROM ' . sql_identifier($config['table'])
            . ' WHERE ' . implode(' AND ', $conditions);
        if ($recordId > 0) {
            $sql .= ' AND ' . sql_identifier($config['pk']) . ' <> ?';
            $types .= 'i';
            $params[] = $recordId;
        }
        $sql .= ' LIMIT 1';
        if (db_one($conn, $sql, $types, $params) === null) {
            continue;
        }
        if (isset($rule['message'])) {
            $errors[] = $rule['message'];
            continue;
        }
        $label = $config['fields'][$rule['columns'][0]]['label'];
        $errors[] = $label . ' is already used by another ' . $config['singular'] . '.';
    }
}

function record_validate_and_collect(mysqli $conn, array $config, string $entityKey, int $recordId, array &$errors): array
{
    $data = [];
    foreach ($config['fields'] as $name => $field) {
        if (!record_is_form_field($field)) {
            continue;
        }
        $value = post_string($name);
        if ($value === '') {
            if (!empty($field['required'])) {
                $errors[] = $field['label'] . ' is required.';
            } else {
                $data[$name] = null;
            }
            continue;
        }
        $errorCount = count($errors);
        $normalized = record_normalize_value($conn, $name, $field, $value, $errors);
        if (count($errors) === $errorCount) {
            $data[$name] = $normalized;
        }
    }

    record_check_relationships($conn, $entityKey, $data, $errors);
    record_check_unique($conn, $config, $data, $recordId, $errors);

    if ($entityKey === 'marks') {
        if (isset($data['score'])) {
            $data['grade'] = grade_from_score((float)$data['score']);
        }
        $data['recorded_by'] = (int)$_SESSION['user']['user_id'];
    }
    return $data;
}

/* -------------------------------------------------------------------------
 * Database access
 * ---------------------------------------------------------------------- */

/**
 * SELECT list and LEFT JOINs that add a readable "<column>_label" for every
 * related-record (fk) column.
 */
function record_select_parts(array $config): array
{
    $select = ['t.*'];
    $joins = [];
    $labels = [];
    foreach ($config['fields'] as $column => $field) {
        if ($field['type'] !== 'fk') {
            continue;
        }
        $alias = 'lookup_' . $column;
        $labelSql = foreign_label_sql($alias, $field);
        $labels[$column] = $labelSql;
        $select[] = $labelSql . ' AS ' . sql_identifier($column . '_label');
        $joins[] = ' LEFT JOIN ' . sql_identifier($field['ref']) . ' ' . sql_identifier($alias)
            . ' ON t.' . sql_identifier($column) . ' = ' . sql_identifier($alias) . '.' . sql_identifier($field['refpk']);
    }
    return [
        'select' => $select,
        'joins' => $joins,
        'labels' => $labels,
    ];
}

function record_load(mysqli $conn, array $config, int $recordId): ?array
{
    $parts = record_select_parts($config);
    $sql = 'SELECT ' . implode(', ', $parts['select'])
        . ' FROM ' . sql_identifier($config['table']) . ' t'
        . implode('', $parts['joins'])
        . ' WHERE t.' . sql_identifier($config['pk']) . ' = ?'
        . ' LIMIT 1';
    return db_one($conn, $sql, 'i', [$recordId]);
}

function record_insert(mysqli $conn, array $config, array $data): int
{
    $columns = [];
    $placeholders = [];
    $types = '';
    $params = [];
    foreach ($data as $column => $value) {
        $columns[] = sql_identifier($column);
        $placeholders[] = '?';
        $types .= record_field_bind_type($config['fields'][$column]);
        $params[] = $value;
    }
    $sql = 'INSERT INTO ' . sql_identifier($config['table'])
        . ' (' . implode(', ', $columns) . ')'
        . ' VALUES (' . implode(', ', $placeholders) . ')';
    $statement = $conn->prepare($sql);
    db_execute($statement, $types, $params);
    return (int)$conn->insert_id;
}

function record_update(mysqli $conn, array $config, int $recordId, array $data): void
{
    $assignments = [];
    $types = '';
    $params = [];
    foreach ($data as $column => $value) {
        $assignments[] = sql_identifier($column) . ' = ?';
        $types .= record_field_bind_type($config['fields'][$column]);
        $params[] = $value;
    }
    $types .= 'i';
    $params[] = $recordId;
    $sql = 'UPDATE ' . sql_identifier($config['table'])
        . ' SET ' . implode(', ', $assignments)
        . ' WHERE ' . sql_identifier($config['pk']) . ' = ?';
    $statement = $conn->prepare($sql);
    db_execute($statement, $types, $params);
}

function record_save_error_message(Throwable $exception): string
{
    $code = db_error_code($exception);
    if ($code === 1062) {
        return 'Another record already uses one of these unique values. Change the duplicated value and try again.';
    }
    if ($code === 1452) {
        return 'A selected related record no longer exists. Reload the form and choose again.';
    }
    return 'Unable to save this record. Please check the values and try again.';
}

/**
 * Counts child records that would block a delete, e.g. ["1 lecturer", "2 courses"].
 */
function record_dependents_summary(mysqli $conn, array $config, int $recordId): array
{
    $usage = [];
    foreach ($config['dependents'] ?? [] as $dependent) {
        $sql = 'SELECT COUNT(*) AS total FROM ' . sql_identifier($dependent['table'])
            . ' WHERE ' . sql_identifier($dependent['column']) . ' = ?';
        $row = db_one($conn, $sql, 'i', [$recordId]);
        $total = (int)($row['total'] ?? 0);
        if ($total === 1) {
            $usage[] = '1 ' . $dependent['singular'];
        } elseif ($total > 1) {
            $usage[] = $total . ' ' . $dependent['plural'];
        }
    }
    return $usage;
}

/* -------------------------------------------------------------------------
 * List state (search, filters, sort, page) carried through view/edit/delete
 * ---------------------------------------------------------------------- */

function record_filter_values(mysqli $conn, array $config): array
{
    $values = [];
    foreach ($config['filters'] as $name => $filter) {
        $value = get_string($name);
        $values[$name] = '';
        if ($value === '') {
            continue;
        }
        if ($filter['type'] === 'fk') {
            $isId = preg_match('/^[1-9][0-9]*$/', $value) === 1;
            if ($isId && foreign_key_exists($conn, $filter['ref'], $filter['refpk'], (int)$value)) {
                $values[$name] = $value;
            }
        } elseif ($filter['type'] === 'select') {
            if (array_key_exists($value, $filter['options'])) {
                $values[$name] = $value;
            }
        } elseif (in_array($filter['type'], ['date_from', 'date_to'], true)) {
            if (valid_date_value($value)) {
                $values[$name] = $value;
            }
        } elseif (!isset($filter['max']) || text_length($value) <= $filter['max']) {
            $values[$name] = $value;
        }
    }
    $hasRange = isset($values['date_from'], $values['date_to']);
    if ($hasRange && $values['date_from'] !== '' && $values['date_to'] !== '' && $values['date_from'] > $values['date_to']) {
        $values['date_from'] = '';
        $values['date_to'] = '';
    }
    return $values;
}

function record_list_state(array $config, string $entityKey, array $filterValues): array
{
    $state = ['entity' => $entityKey];
    $q = get_string('q');
    if ($q !== '' && text_length($q) <= 100) {
        $state['q'] = $q;
    }
    foreach ($filterValues as $name => $value) {
        $state[$name] = $value;
    }
    $sort = get_string('sort');
    if (array_key_exists($sort, $config['fields'])) {
        $state['sort'] = $sort;
    }
    $direction = strtoupper(get_string('dir'));
    if (in_array($direction, ['ASC', 'DESC'], true)) {
        $state['dir'] = $direction;
    }
    $page = min(get_positive_int('page', 1), 10000);
    if ($page > 1) {
        $state['page'] = $page;
    }
    return $state;
}

function record_url(array $state, array $extra = []): string
{
    return 'records.php?' . query_string(array_merge($state, $extra));
}

/* -------------------------------------------------------------------------
 * Request handling
 * ---------------------------------------------------------------------- */

$entityKey = get_string('entity') ?: 'students';
if (!isset($entities[$entityKey])) {
    http_response_code(404);
    exit('Unknown record type.');
}
$config = $entities[$entityKey];
$table = $config['table'];
$pk = $config['pk'];
$singular = $config['singular'];
$action = get_string('action') ?: 'list';
if (!in_array($action, ['list', 'view', 'create', 'edit', 'delete'], true)) {
    $action = 'list';
}
$id = get_positive_int('id');
$errors = [];
$record = [];
$formValues = [];
$filterValues = record_filter_values($conn, $config);
$listState = record_list_state($config, $entityKey, $filterValues);
$listUrl = record_url($listState);

if ($action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id < 1) {
        http_response_code(405);
        exit('Invalid delete request.');
    }
    verify_csrf();
    $usage = record_dependents_summary($conn, $config, $id);
    if ($usage) {
        $message = 'This ' . $singular . ' cannot be deleted because it is still used by ' . human_list($usage) . '.';
        flash($message . ' Remove or reassign those records first.', 'error');
        redirect_to($listUrl);
    }
    try {
        $statement = $conn->prepare('DELETE FROM ' . sql_identifier($table) . ' WHERE ' . sql_identifier($pk) . ' = ?');
        db_execute($statement, 'i', [$id]);
        if ($statement->affected_rows === 0) {
            flash('The record was already removed or could not be found.', 'error');
        } else {
            audit_log($conn, 'DELETE', $table, $id, ucfirst($singular) . ' deleted');
            flash(ucfirst($singular) . ' deleted successfully.');
        }
    } catch (Throwable $exception) {
        app_log('Record delete failed', [
            'entity' => $entityKey,
            'id' => $id,
            'message' => $exception->getMessage(),
        ]);
        if (db_error_code($exception) === 1451) {
            flash('This ' . $singular . ' cannot be deleted because it is used by related records. Remove or update those records first.', 'error');
        } else {
            flash('The ' . $singular . ' could not be deleted. Please try again.', 'error');
        }
    }
    redirect_to($listUrl);
}

if (in_array($action, ['view', 'edit'], true)) {
    if ($id < 1) {
        flash('Choose a valid record.', 'error');
        redirect_to($listUrl);
    }
    $record = record_load($conn, $config, $id) ?? [];
    if (!$record) {
        flash('Record not found. It may have been deleted.', 'error');
        redirect_to($listUrl);
    }
    $formValues = $record;
}

if (in_array($action, ['create', 'edit'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $recordId = $action === 'edit' ? $id : 0;
    $data = record_validate_and_collect($conn, $config, $entityKey, $recordId, $errors);
    if (!$errors) {
        try {
            if ($action === 'create') {
                $newId = record_insert($conn, $config, $data);
                audit_log($conn, 'CREATE', $table, $newId, ucfirst($singular) . ' created');
                flash(ucfirst($singular) . ' created successfully.');
                redirect_to(record_url(['entity' => $entityKey]));
            }
            record_update($conn, $config, $id, $data);
            audit_log($conn, 'UPDATE', $table, $id, ucfirst($singular) . ' updated');
            flash(ucfirst($singular) . ' updated successfully.');
            redirect_to($listUrl);
        } catch (Throwable $exception) {
            app_log('Record save failed', [
                'entity' => $entityKey,
                'action' => $action,
                'id' => $id,
                'message' => $exception->getMessage(),
            ]);
            $errors[] = record_save_error_message($exception);
        }
    }
    $formValues = array_merge($formValues, record_submitted_values($config));
}

$formFields = array_filter($config['fields'], 'record_is_form_field');
if ($action === 'edit') {
    $formAction = record_url($listState, ['action' => 'edit', 'id' => $id]);
} else {
    $formAction = record_url(['entity' => $entityKey], ['action' => 'create']);
}

if ($action === 'list') {
    $selectParts = record_select_parts($config);
    $q = $listState['q'] ?? '';
    $sort = $listState['sort'] ?? $config['default_sort'];
    $direction = $listState['dir'] ?? ($config['default_dir'] ?? 'ASC');
    $page = (int)($listState['page'] ?? 1);
    $perPage = 25;
    $where = [];
    $types = '';
    $params = [];

    if ($q !== '') {
        $searchParts = [];
        foreach ($config['fields'] as $column => $field) {
            if (isset($selectParts['labels'][$column])) {
                $searchParts[] = $selectParts['labels'][$column] . ' LIKE ?';
            } else {
                $searchParts[] = 'CAST(t.' . sql_identifier($column) . ' AS CHAR) LIKE ?';
            }
            $types .= 's';
            $params[] = '%' . $q . '%';
        }
        $where[] = '(' . implode(' OR ', $searchParts) . ')';
    }

    foreach ($config['filters'] as $name => $filter) {
        $value = $filterValues[$name];
        if ($value === '') {
            continue;
        }
        $column = sql_identifier($filter['field'] ?? $name);
        if ($filter['type'] === 'date_from') {
            $where[] = 't.' . $column . ' >= ?';
            $types .= 's';
            $params[] = $value;
        } elseif ($filter['type'] === 'date_to') {
            $where[] = 't.' . $column . ' <= ?';
            $types .= 's';
            $params[] = $value;
        } elseif ($filter['type'] === 'text') {
            $where[] = 't.' . $column . ' LIKE ?';
            $types .= 's';
            $params[] = '%' . $value . '%';
        } elseif ($filter['type'] === 'fk') {
            $where[] = 't.' . $column . ' = ?';
            $types .= 'i';
            $params[] = (int)$value;
        } else {
            $where[] = 't.' . $column . ' = ?';
            $types .= 's';
            $params[] = $value;
        }
    }

    $fromSql = ' FROM ' . sql_identifier($table) . ' t' . implode('', $selectParts['joins']);
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $totalRow = db_one($conn, 'SELECT COUNT(*) AS total' . $fromSql . $whereSql, $types, $params);
    $total = (int)($totalRow['total'] ?? 0);
    $pages = max(1, (int)ceil($total / $perPage));
    if ($page > $pages) {
        $page = $pages;
    }
    unset($listState['page']);
    if ($page > 1) {
        $listState['page'] = $page;
    }
    $offset = ($page - 1) * $perPage;

    if (isset($selectParts['labels'][$sort])) {
        $orderSql = sql_identifier($sort . '_label');
    } else {
        $orderSql = 't.' . sql_identifier($sort);
    }
    $listSql = 'SELECT ' . implode(', ', $selectParts['select'])
        . $fromSql
        . $whereSql
        . ' ORDER BY ' . $orderSql . ' ' . $direction . ', t.' . sql_identifier($pk) . ' DESC'
        . ' LIMIT ' . $perPage . ' OFFSET ' . $offset;
    $rows = db_result($conn, $listSql, $types, $params);
}

$pageTitle = $config['title'];
require 'includes/header.php';
?>
<div class="page-heading">
    <div>
        <h1><?= e($config['title']) ?></h1>
        <p class="subheading">Create, view, search, filter, update and delete <?= e(strtolower($config['title'])) ?>.</p>
    </div>
    <a class="btn secondary" href="index.php">← Dashboard</a>
</div>

<?php if ($action === 'view'): ?>
    <section class="card">
        <div class="toolbar">
            <div>
                <h2>Record details</h2>
                <p class="hint"><?= e(ucfirst($singular)) ?> #<?= (int)$record[$pk] ?></p>
            </div>
            <div class="actions">
                <a class="btn" href="<?= e(record_url($listState, ['action' => 'edit', 'id' => $id])) ?>">Edit</a>
                <form class="inline-form" method="post" action="<?= e(record_url($listState, ['action' => 'delete', 'id' => $id])) ?>" data-confirm="Delete this <?= e($singular) ?>? This cannot be undone.">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="btn danger" type="submit">Delete</button>
                </form>
                <a class="btn secondary" href="<?= e($listUrl) ?>">Back to list</a>
            </div>
        </div>
        <div class="profile-grid">
            <?php foreach ($config['fields'] as $name => $field): ?>
                <div class="field">
                    <span class="field-label"><?= e($field['label']) ?></span>
                    <div class="readonly-value"><?= e(record_display_value($record, $name, $field)) ?></div>
                </div>
            <?php endforeach; ?>
            <div class="field">
                <span class="field-label">Created</span>
                <div class="readonly-value"><?= e($record['created_at'] ?? '—') ?></div>
            </div>
            <div class="field">
                <span class="field-label">Last updated</span>
                <div class="readonly-value"><?= e($record['updated_at'] ?? '—') ?></div>
            </div>
        </div>
    </section>
<?php elseif (in_array($action, ['create', 'edit'], true)): ?>
    <section class="card">
        <h2><?= $action === 'edit' ? 'Edit' : 'Add' ?> <?= e($singular) ?></h2>
        <p class="hint">Fields marked * are required. Server-side validation runs even if browser validation is bypassed.</p>
        <?php if ($errors): ?>
            <div class="alert error" role="alert">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="post" action="<?= e($formAction) ?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-grid">
                <?php foreach ($formFields as $name => $field): ?>
                    <?php $value = (string)($formValues[$name] ?? ''); ?>
                    <?php $required = !empty($field['required']); ?>
                    <div class="field">
                        <label for="<?= e($name) ?>"><?= e($field['label']) ?><?= $required ? ' *' : '' ?></label>
                        <?php if ($field['type'] === 'fk'): ?>
                            <?php $options = foreign_options($conn, $field); ?>
                            <select id="<?= e($name) ?>" name="<?= e($name) ?>"<?= $required ? ' required' : '' ?>>
                                <option value="">Choose <?= e(strtolower($field['label'])) ?></option>
                                <?php foreach ($options as $option): ?>
                                    <option value="<?= (int)$option['id'] ?>"<?= $value === (string)$option['id'] ? ' selected' : '' ?>><?= e($option['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($field['type'] === 'select'): ?>
                            <select id="<?= e($name) ?>" name="<?= e($name) ?>"<?= $required ? ' required' : '' ?>>
                                <option value="">Choose <?= e(strtolower($field['label'])) ?></option>
                                <?php foreach ($field['options'] as $optionValue => $optionLabel): ?>
                                    <option value="<?= e($optionValue) ?>"<?= $value === (string)$optionValue ? ' selected' : '' ?>><?= e($optionLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($field['type'] === 'textarea'): ?>
                            <textarea id="<?= e($name) ?>" name="<?= e($name) ?>"<?= $required ? ' required' : '' ?><?= isset($field['max']) ? ' maxlength="' . (int)$field['max'] . '"' : '' ?> placeholder="Enter <?= e(strtolower($field['label'])) ?>"><?= e($value) ?></textarea>
                        <?php else: ?>
                            <input id="<?= e($name) ?>" name="<?= e($name) ?>" type="<?= e(record_input_type($field)) ?>" value="<?= e($value) ?>"<?= $required ? ' required' : '' ?><?= record_input_attributes($field) ?> placeholder="Enter <?= e(strtolower($field['label'])) ?>">
                        <?php endif; ?>
                        <?php if (!empty($field['hint'])): ?>
                            <small class="hint"><?= e($field['hint']) ?></small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="btn" type="submit">Save <?= e($singular) ?></button>
            <a class="btn secondary" href="<?= e($listUrl) ?>">Cancel</a>
        </form>
    </section>
<?php else: ?>
    <section class="card filter-card">
        <div class="toolbar">
            <div>
                <h2>Find and filter records</h2>
                <p class="hint">Search also matches related records (for example a student’s registration number). Sorting and paging keep your filters.</p>
            </div>
            <a class="btn" href="<?= e(record_url(['entity' => $entityKey], ['action' => 'create'])) ?>">＋ Add <?= e($singular) ?></a>
        </div>
        <form class="filter-form" method="get" action="records.php">
            <input type="hidden" name="entity" value="<?= e($entityKey) ?>">
            <div class="field">
                <label for="q">Search all fields</label>
                <input id="q" type="search" name="q" value="<?= e($q) ?>" maxlength="100" placeholder="Search <?= e(strtolower($config['title'])) ?>">
            </div>
            <?php foreach ($config['filters'] as $name => $filter): ?>
                <div class="field">
                    <label for="filter_<?= e($name) ?>"><?= e($filter['label']) ?></label>
                    <?php if ($filter['type'] === 'fk'): ?>
                        <?php $options = foreign_options($conn, $filter); ?>
                        <select id="filter_<?= e($name) ?>" name="<?= e($name) ?>">
                            <option value="">All <?= e(strtolower($filter['label'])) ?></option>
                            <?php foreach ($options as $option): ?>
                                <option value="<?= (int)$option['id'] ?>"<?= $filterValues[$name] === (string)$option['id'] ? ' selected' : '' ?>><?= e($option['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($filter['type'] === 'select'): ?>
                        <select id="filter_<?= e($name) ?>" name="<?= e($name) ?>">
                            <option value="">All <?= e(strtolower($filter['label'])) ?></option>
                            <?php foreach ($filter['options'] as $optionValue => $optionLabel): ?>
                                <option value="<?= e($optionValue) ?>"<?= $filterValues[$name] === (string)$optionValue ? ' selected' : '' ?>><?= e($optionLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif (in_array($filter['type'], ['date_from', 'date_to'], true)): ?>
                        <input id="filter_<?= e($name) ?>" type="date" name="<?= e($name) ?>" value="<?= e($filterValues[$name]) ?>" placeholder="YYYY-MM-DD">
                    <?php else: ?>
                        <input id="filter_<?= e($name) ?>" type="text" name="<?= e($name) ?>" value="<?= e($filterValues[$name]) ?>"<?= isset($filter['max']) ? ' maxlength="' . (int)$filter['max'] . '"' : '' ?> placeholder="Filter by <?= e(strtolower($filter['label'])) ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div class="field">
                <label for="sort">Sort by</label>
                <select id="sort" name="sort">
                    <?php foreach ($config['fields'] as $column => $field): ?>
                        <option value="<?= e($column) ?>"<?= $sort === $column ? ' selected' : '' ?>><?= e($field['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="dir">Direction</label>
                <select id="dir" name="dir">
                    <option value="DESC"<?= $direction === 'DESC' ? ' selected' : '' ?>>Newest / Z–A</option>
                    <option value="ASC"<?= $direction === 'ASC' ? ' selected' : '' ?>>Oldest / A–Z</option>
                </select>
            </div>
            <div class="filter-actions">
                <button class="btn" type="submit">Apply filters</button>
                <a class="btn secondary" href="<?= e(record_url(['entity' => $entityKey])) ?>">Clear filters</a>
            </div>
        </form>
    </section>
    <section class="card">
        <div class="toolbar">
            <h2><?= (int)$total ?> result<?= $total === 1 ? '' : 's' ?></h2>
            <span class="hint">Page <?= (int)$page ?> of <?= (int)$pages ?></span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th><?= e(str_replace('_', ' ', $pk)) ?></th>
                        <?php foreach ($config['fields'] as $column => $field): ?>
                            <th><?= e($field['label']) ?></th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $rowId = (int)$row[$pk]; ?>
                        <tr>
                            <td><?= $rowId ?></td>
                            <?php foreach ($config['fields'] as $column => $field): ?>
                                <td><?= e(record_display_value($row, $column, $field)) ?></td>
                            <?php endforeach; ?>
                            <td>
                                <div class="actions">
                                    <a class="btn secondary small" href="<?= e(record_url($listState, ['action' => 'view', 'id' => $rowId])) ?>">View</a>
                                    <a class="btn secondary small" href="<?= e(record_url($listState, ['action' => 'edit', 'id' => $rowId])) ?>">Edit</a>
                                    <form class="inline-form" method="post" action="<?= e(record_url($listState, ['action' => 'delete', 'id' => $rowId])) ?>" data-confirm="Delete this <?= e($singular) ?>? This cannot be undone.">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <button class="btn danger small" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($total === 0): ?>
                        <tr>
                            <td colspan="<?= count($config['fields']) + 2 ?>" class="empty">No records match these filters. Change or clear the filters, or add a new record.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
            <nav class="pagination" aria-label="Results pages">
                <?php if ($page > 1): ?>
                    <a class="btn secondary small" href="<?= e(record_url($listState, ['page' => $page - 1])) ?>">← Previous</a>
                <?php endif; ?>
                <span>Page <?= (int)$page ?> of <?= (int)$pages ?></span>
                <?php if ($page < $pages): ?>
                    <a class="btn secondary small" href="<?= e(record_url($listState, ['page' => $page + 1])) ?>">Next →</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
