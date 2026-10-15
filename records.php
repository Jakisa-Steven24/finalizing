<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_staff();
require_once 'includes/entities.php';

function record_field_bind_type(array $field): string
{
    return in_array($field['type'], ['number', 'fk'], true) ? 'i' : 's';
}

function record_validate_and_collect(mysqli $conn, array $config, string $entityKey, array &$errors): array
{
    $data = [];
    foreach ($config['fields'] as $name => $field) {
        if (isset($field['form']) && $field['form'] === false) {
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

        if (isset($field['max']) && strlen($value) > (int)$field['max']) {
            $errors[] = $field['label'] . ' must not exceed ' . (int)$field['max'] . ' characters.';
            continue;
        }
        if (isset($field['pattern']) && preg_match('~' . $field['pattern'] . '~uD', $value) !== 1) {
            $errors[] = $field['label'] . ' has an invalid format.';
            continue;
        }

        if ($field['type'] === 'email' && !valid_email($value)) {
            $errors[] = $field['label'] . ' must be a valid email address.';
            continue;
        }
        if ($field['type'] === 'tel' && !valid_phone($value)) {
            $errors[] = $field['label'] . ' must be a valid phone number.';
            continue;
        }
        if ($field['type'] === 'date') {
            if (!valid_date_value($value)) {
                $errors[] = $field['label'] . ' must be a real date in YYYY-MM-DD format.';
                continue;
            }
            if (isset($field['min_age']) || isset($field['max_age'])) {
                $age = (new DateTime())->diff(new DateTime($value))->y;
                if ($age < (int)($field['min_age'] ?? 0) || $age > (int)($field['max_age'] ?? 200)) {
                    $errors[] = $field['label'] . ' is outside the permitted age range.';
                    continue;
                }
            }
        }
        if ($field['type'] === 'number') {
            if (!preg_match('/^[0-9]+$/', $value)) {
                $errors[] = $field['label'] . ' must be a whole number.';
                continue;
            }
            $number = (int)$value;
            if (isset($field['min']) && $number < $field['min']) {
                $errors[] = $field['label'] . ' must be at least ' . $field['min'] . '.';
                continue;
            }
            if (isset($field['max']) && $number > $field['max']) {
                $errors[] = $field['label'] . ' must not exceed ' . $field['max'] . '.';
                continue;
            }
            $value = $number;
        }
        if ($field['type'] === 'decimal') {
            if (!preg_match('/^[0-9]+(?:\.[0-9]{1,2})?$/', $value)) {
                $errors[] = $field['label'] . ' must be a positive number with at most two decimal places.';
                continue;
            }
            $number = (float)$value;
            if (isset($field['min']) && $number < (float)$field['min']) {
                $errors[] = $field['label'] . ' must be at least ' . $field['min'] . '.';
                continue;
            }
            if (isset($field['max']) && $number > (float)$field['max']) {
                $errors[] = $field['label'] . ' must not exceed ' . $field['max'] . '.';
                continue;
            }
            $value = number_format($number, 2, '.', '');
        }
        if ($field['type'] === 'select' && !array_key_exists($value, $field['options'])) {
            $errors[] = 'Select a valid option for ' . $field['label'] . '.';
            continue;
        }
        if ($field['type'] === 'fk') {
            if (!preg_match('/^[1-9][0-9]*$/', $value) || !foreign_key_exists($conn, $field['ref'], $field['refpk'], (int)$value)) {
                $errors[] = 'Select an existing ' . strtolower($field['label']) . '.';
                continue;
            }
            $value = (int)$value;
        }
        if ($name === 'academic_year' && !valid_academic_year((string)$value)) {
            $errors[] = 'Academic year must contain consecutive years, for example 2026/2027.';
            continue;
        }
        $data[$name] = $value;
    }

    if (in_array($entityKey, ['enrollments', 'registrations'], true) && isset($data['student_id'], $data['course_id']) && !in_array(null, [$data['student_id'], $data['course_id']], true)) {
        $relation = db_one($conn, 'SELECT 1 FROM student WHERE student_id = ? AND course_id = ? LIMIT 1', 'ii', [(int)$data['student_id'], (int)$data['course_id']]);
        if (!$relation) {
            $errors[] = 'The selected course does not match the selected student’s current course.';
        }
    }

    if ($entityKey === 'marks' && isset($data['student_id'], $data['course_unit_id']) && !in_array(null, [$data['student_id'], $data['course_unit_id']], true)) {
        $relation = db_one(
            $conn,
            'SELECT 1 FROM student s INNER JOIN course_unit cu ON cu.course_id = s.course_id WHERE s.student_id = ? AND cu.course_unit_id = ? LIMIT 1',
            'ii',
            [(int)$data['student_id'], (int)$data['course_unit_id']]
        );
        if (!$relation) {
            $errors[] = 'The selected course unit does not belong to the selected student’s course.';
        }
        if (isset($data['score']) && $data['score'] !== null) {
            $data['grade'] = grade_from_score((float)$data['score']);
        }
        $data['recorded_by'] = (int)$_SESSION['user']['user_id'];
    }

    return $data;
}

function record_filter_values(mysqli $conn, array $config): array
{
    $values = [];
    foreach ($config['filters'] as $name => $filter) {
        $value = get_string($name);
        if ($value === '') {
            $values[$name] = '';
            continue;
        }
        if ($filter['type'] === 'fk') {
            $values[$name] = preg_match('/^[1-9][0-9]*$/', $value) && foreign_key_exists($conn, $filter['ref'], $filter['refpk'], (int)$value) ? $value : '';
        } elseif ($filter['type'] === 'select') {
            $values[$name] = array_key_exists($value, $filter['options']) ? $value : '';
        } elseif (in_array($filter['type'], ['date_from', 'date_to'], true)) {
            $values[$name] = valid_date_value($value) ? $value : '';
        } else {
            $values[$name] = (isset($filter['max']) && strlen($value) > $filter['max']) ? '' : $value;
        }
    }
    return $values;
}

function record_display_value(array $row, string $name, array $field): string
{
    if ($field['type'] === 'fk' && isset($row[$name . '_label']) && $row[$name . '_label'] !== '') {
        return (string)$row[$name . '_label'];
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

$entityKey = get_string('entity') ?: 'students';
if (!isset($entities[$entityKey])) {
    http_response_code(404);
    exit('Unknown record type.');
}
$config = $entities[$entityKey];
$table = $config['table'];
$pk = $config['pk'];
$action = get_string('action') ?: 'list';
if (!in_array($action, ['list', 'create', 'edit', 'delete'], true)) {
    $action = 'list';
}
$id = get_positive_int('id');
$errors = [];
$record = [];

if ($action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id < 1) {
        http_response_code(405);
        exit('Invalid delete request.');
    }
    verify_csrf();
    try {
        $statement = $conn->prepare('DELETE FROM ' . sql_identifier($table) . ' WHERE ' . sql_identifier($pk) . ' = ?');
        db_execute($statement, 'i', [$id]);
        if ($statement->affected_rows === 0) {
            flash('The record was already removed or could not be found.', 'error');
        } else {
            audit_log($conn, 'DELETE', $table, $id, ucfirst($config['singular']) . ' deleted');
            flash(ucfirst($config['singular']) . ' deleted successfully.');
        }
    } catch (Throwable $exception) {
        app_log('Record delete failed', ['entity' => $entityKey, 'id' => $id, 'message' => $exception->getMessage()]);
        flash('This record cannot be deleted because it is used by related records. Remove or update those related records first.', 'error');
    }
    redirect_to('records.php?' . query_string(['entity' => $entityKey]));
}

if ($action === 'edit') {
    if ($id < 1) {
        flash('Choose a valid record to edit.', 'error');
        redirect_to('records.php?' . query_string(['entity' => $entityKey]));
    }
    $record = db_one($conn, 'SELECT * FROM ' . sql_identifier($table) . ' WHERE ' . sql_identifier($pk) . ' = ? LIMIT 1', 'i', [$id]) ?? [];
    if (!$record) {
        flash('Record not found.', 'error');
        redirect_to('records.php?' . query_string(['entity' => $entityKey]));
    }
}

if (in_array($action, ['create', 'edit'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $data = record_validate_and_collect($conn, $config, $entityKey, $errors);

    if (!$errors) {
        try {
            if ($action === 'create') {
                $columns = array_keys($data);
                $columnSql = [];
                foreach ($columns as $column) {
                    $columnSql[] = sql_identifier($column);
                }
                $types = '';
                $params = [];
                foreach ($columns as $column) {
                    $types .= record_field_bind_type($config['fields'][$column] ?? ['type' => 'text']);
                    $params[] = $data[$column];
                }
                $statement = $conn->prepare('INSERT INTO ' . sql_identifier($table) . ' (' . implode(', ', $columnSql) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')');
                db_execute($statement, $types, $params);
                $newId = (int)$conn->insert_id;
                audit_log($conn, 'CREATE', $table, $newId, ucfirst($config['singular']) . ' created');
                flash(ucfirst($config['singular']) . ' created successfully.');
            } else {
                $sets = [];
                $types = '';
                $params = [];
                foreach ($data as $column => $value) {
                    $sets[] = sql_identifier($column) . ' = ?';
                    $types .= record_field_bind_type($config['fields'][$column] ?? ['type' => 'text']);
                    $params[] = $value;
                }
                $types .= 'i';
                $params[] = $id;
                $statement = $conn->prepare('UPDATE ' . sql_identifier($table) . ' SET ' . implode(', ', $sets) . ' WHERE ' . sql_identifier($pk) . ' = ?');
                db_execute($statement, $types, $params);
                audit_log($conn, 'UPDATE', $table, $id, ucfirst($config['singular']) . ' updated');
                flash(ucfirst($config['singular']) . ' updated successfully.');
            }
            redirect_to('records.php?' . query_string(['entity' => $entityKey]));
        } catch (Throwable $exception) {
            app_log('Record save failed', ['entity' => $entityKey, 'action' => $action, 'id' => $id, 'message' => $exception->getMessage()]);
            $errors[] = 'Unable to save this record. A unique value may already exist, or a related record is missing.';
        }
    }
    foreach ($data as $name => $value) {
        $record[$name] = $value;
    }
}

$pageTitle = $config['title'];
require 'includes/header.php';
?>
<div class="page-heading">
    <div>
        <h1><?= e($config['title']) ?></h1>
        <p class="subheading">Secure create, search, filter, update and delete operations for <?= e(strtolower($config['title'])) ?>.</p>
    </div>
    <a class="btn secondary" href="index.php">← Dashboard</a>
</div>

<?php if (in_array($action, ['create', 'edit'], true)): ?>
    <section class="card">
        <h2><?= $action === 'edit' ? 'Edit' : 'Add' ?> <?= e($config['singular']) ?></h2>
        <p class="hint">Fields marked * are required. Server-side validation runs even if browser validation is bypassed.</p>
        <?php if ($errors): ?><div class="alert error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="post" action="records.php?<?= e(query_string(['entity' => $entityKey, 'action' => $action, 'id' => $action === 'edit' ? $id : null])) ?>" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-grid">
                <?php foreach ($config['fields'] as $name => $field): ?>
                    <?php if (isset($field['form']) && $field['form'] === false) continue; ?>
                    <?php $value = $record[$name] ?? ''; ?>
                    <div class="field">
                        <label for="<?= e($name) ?>"><?= e($field['label']) ?><?= !empty($field['required']) ? ' *' : '' ?></label>
                        <?php if ($field['type'] === 'fk'): ?>
                            <?php $options = foreign_options($conn, $field); ?>
                            <select id="<?= e($name) ?>" name="<?= e($name) ?>" <?= !empty($field['required']) ? 'required' : '' ?>>
                                <option value="">Choose <?= e(strtolower($field['label'])) ?></option>
                                <?php while ($option = $options->fetch_assoc()): ?>
                                    <option value="<?= (int)$option['id'] ?>" <?= (string)$value === (string)$option['id'] ? 'selected' : '' ?>><?= e($option['label']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        <?php elseif ($field['type'] === 'select'): ?>
                            <select id="<?= e($name) ?>" name="<?= e($name) ?>" <?= !empty($field['required']) ? 'required' : '' ?>><option value="">Choose <?= e(strtolower($field['label'])) ?></option><?php foreach ($field['options'] as $optionValue => $optionLabel): ?><option value="<?= e($optionValue) ?>" <?= (string)$value === (string)$optionValue ? 'selected' : '' ?>><?= e($optionLabel) ?></option><?php endforeach; ?></select>
                        <?php elseif ($field['type'] === 'textarea'): ?>
                            <textarea id="<?= e($name) ?>" name="<?= e($name) ?>" <?= !empty($field['required']) ? 'required' : '' ?> <?= isset($field['max']) ? 'maxlength="' . (int)$field['max'] . '"' : '' ?> placeholder="Enter <?= e(strtolower($field['label'])) ?>"><?= e($value) ?></textarea>
                        <?php else: ?>
                            <?php $inputType = $field['type'] === 'decimal' ? 'number' : $field['type']; ?>
                            <input id="<?= e($name) ?>" name="<?= e($name) ?>" type="<?= e($inputType) ?>" value="<?= e($value) ?>" <?= !empty($field['required']) ? 'required' : '' ?> <?= isset($field['max']) && !in_array($inputType, ['number', 'date'], true) ? 'maxlength="' . (int)$field['max'] . '"' : '' ?> <?= isset($field['pattern']) ? 'pattern="' . e($field['pattern']) . '"' : '' ?> <?= isset($field['min']) ? 'min="' . e((string)$field['min']) . '"' : '' ?> <?= isset($field['max']) && in_array($inputType, ['number'], true) ? 'max="' . e((string)$field['max']) . '"' : '' ?> <?= $inputType === 'number' ? 'step="' . e($field['step'] ?? ($field['type'] === 'decimal' ? '0.01' : '1')) . '"' : '' ?> placeholder="Enter <?= e(strtolower($field['label'])) ?>">
                        <?php endif; ?>
                        <?php if (!empty($field['hint'])): ?><small class="hint"><?= e($field['hint']) ?></small><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="btn" type="submit">Save <?= e($config['singular']) ?></button>
            <a class="btn secondary" href="records.php?<?= e(query_string(['entity' => $entityKey])) ?>">Cancel</a>
        </form>
    </section>
<?php else: ?>
    <?php
    $q = get_string('q');
    $q = strlen($q) <= 100 ? $q : '';
    $filterValues = record_filter_values($conn, $config);
    $sort = get_string('sort');
    if (!array_key_exists($sort, $config['fields'])) $sort = $config['default_sort'];
    $direction = strtoupper(get_string('dir')) === 'ASC' ? 'ASC' : 'DESC';
    $page = min(get_positive_int('page', 1), 10000);
    $perPage = 25;
    $where = [];
    $types = '';
    $params = [];
    if ($q !== '') {
        $searchParts = [];
        foreach ($config['fields'] as $column => $field) {
            $searchParts[] = 'CAST(t.' . sql_identifier($column) . ' AS CHAR) LIKE ?';
            $types .= 's';
            $params[] = '%' . $q . '%';
        }
        $where[] = '(' . implode(' OR ', $searchParts) . ')';
    }
    foreach ($config['filters'] as $name => $filter) {
        $value = $filterValues[$name];
        if ($value === '') continue;
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
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $totalRow = db_one($conn, 'SELECT COUNT(*) AS total FROM ' . sql_identifier($table) . ' t' . $whereSql, $types, $params);
    $total = (int)($totalRow['total'] ?? 0);
    $pages = max(1, (int)ceil($total / $perPage));
    if ($page > $pages) $page = $pages;
    $offset = ($page - 1) * $perPage;

    $select = ['t.*'];
    $joins = [];
    foreach ($config['fields'] as $column => $field) {
        if ($field['type'] === 'fk') {
            $alias = 'lookup_' . $column;
            $select[] = $alias . '.' . sql_identifier($field['display']) . ' AS ' . sql_identifier($column . '_label');
            $joins[] = ' LEFT JOIN ' . sql_identifier($field['ref']) . ' ' . $alias . ' ON t.' . sql_identifier($column) . ' = ' . $alias . '.' . sql_identifier($field['refpk']);
        }
    }
    $listSql = 'SELECT ' . implode(', ', $select) . ' FROM ' . sql_identifier($table) . ' t' . implode('', $joins) . $whereSql . ' ORDER BY t.' . sql_identifier($sort) . ' ' . $direction . ', t.' . sql_identifier($pk) . ' DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;
    $rows = db_result($conn, $listSql, $types, $params);
    $baseQuery = ['entity' => $entityKey, 'q' => $q, 'sort' => $sort, 'dir' => $direction];
    foreach ($filterValues as $name => $value) $baseQuery[$name] = $value;
    ?>
    <section class="card filter-card">
        <div class="toolbar"><div><h2>Find and filter records</h2><p class="hint">Filters are applied together. Sorting and paging preserve your chosen filters.</p></div><a class="btn" href="records.php?<?= e(query_string(['entity' => $entityKey, 'action' => 'create'])) ?>">＋ Add <?= e($config['singular']) ?></a></div>
        <form class="filter-form" method="get">
            <input type="hidden" name="entity" value="<?= e($entityKey) ?>">
            <div class="field"><label for="q">Search all fields</label><input id="q" type="search" name="q" value="<?= e($q) ?>" maxlength="100" placeholder="Search <?= e(strtolower($config['title'])) ?>"></div>
            <?php foreach ($config['filters'] as $name => $filter): ?>
                <div class="field"><label for="filter_<?= e($name) ?>"><?= e($filter['label']) ?></label>
                    <?php if ($filter['type'] === 'fk'): ?>
                        <?php $options = foreign_options($conn, $filter); ?><select id="filter_<?= e($name) ?>" name="<?= e($name) ?>"><option value="">All <?= e(strtolower($filter['label'])) ?></option><?php while ($option = $options->fetch_assoc()): ?><option value="<?= (int)$option['id'] ?>" <?= (string)$filterValues[$name] === (string)$option['id'] ? 'selected' : '' ?>><?= e($option['label']) ?></option><?php endwhile; ?></select>
                    <?php elseif ($filter['type'] === 'select'): ?>
                        <select id="filter_<?= e($name) ?>" name="<?= e($name) ?>"><option value="">All <?= e(strtolower($filter['label'])) ?></option><?php foreach ($filter['options'] as $optionValue => $optionLabel): ?><option value="<?= e($optionValue) ?>" <?= (string)$filterValues[$name] === (string)$optionValue ? 'selected' : '' ?>><?= e($optionLabel) ?></option><?php endforeach; ?></select>
                    <?php elseif (in_array($filter['type'], ['date_from', 'date_to'], true)): ?>
                        <input id="filter_<?= e($name) ?>" type="date" name="<?= e($name) ?>" value="<?= e($filterValues[$name]) ?>" placeholder="YYYY-MM-DD">
                    <?php else: ?>
                        <input id="filter_<?= e($name) ?>" type="text" name="<?= e($name) ?>" value="<?= e($filterValues[$name]) ?>" <?= isset($filter['max']) ? 'maxlength="' . (int)$filter['max'] . '"' : '' ?> placeholder="Filter by <?= e(strtolower($filter['label'])) ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <div class="field"><label for="sort">Sort by</label><select id="sort" name="sort"><?php foreach ($config['fields'] as $column => $field): ?><option value="<?= e($column) ?>" <?= $sort === $column ? 'selected' : '' ?>><?= e($field['label']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="dir">Direction</label><select id="dir" name="dir"><option value="DESC" <?= $direction === 'DESC' ? 'selected' : '' ?>>Newest / Z–A</option><option value="ASC" <?= $direction === 'ASC' ? 'selected' : '' ?>>Oldest / A–Z</option></select></div>
            <div class="filter-actions"><button class="btn" type="submit">Apply filters</button><a class="btn secondary" href="records.php?<?= e(query_string(['entity' => $entityKey])) ?>">Clear filters</a></div>
        </form>
    </section>
    <section class="card">
        <div class="toolbar"><h2><?= (int)$total ?> result<?= $total === 1 ? '' : 's' ?></h2><span class="hint">Page <?= (int)$page ?> of <?= (int)$pages ?></span></div>
        <div class="table-wrap"><table><thead><tr><th><?= e(str_replace('_', ' ', $pk)) ?></th><?php foreach ($config['fields'] as $column => $field): ?><th><?= e($field['label']) ?></th><?php endforeach; ?><th>Actions</th></tr></thead><tbody>
        <?php $rowCount = 0; while ($row = $rows->fetch_assoc()): $rowCount++; ?>
            <tr><td><?= e($row[$pk]) ?></td><?php foreach ($config['fields'] as $column => $field): ?><td><?= e(record_display_value($row, $column, $field)) ?></td><?php endforeach; ?><td><div class="actions"><a class="btn secondary small" href="records.php?<?= e(query_string(['entity' => $entityKey, 'action' => 'edit', 'id' => (int)$row[$pk]])) ?>">Edit</a><form class="inline-form" method="post" action="records.php?<?= e(query_string(['entity' => $entityKey, 'action' => 'delete', 'id' => (int)$row[$pk]])) ?>" onsubmit="return confirm('Delete this record? This cannot be undone.');"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="btn danger small" type="submit">Delete</button></form></div></td></tr>
        <?php endwhile; ?>
        <?php if ($rowCount === 0): ?><tr><td colspan="<?= count($config['fields']) + 2 ?>" class="empty">No records match these filters. Change or clear the filters, or add a new record.</td></tr><?php endif; ?>
        </tbody></table></div>
        <?php if ($pages > 1): ?><nav class="pagination" aria-label="Results pages"><?php if ($page > 1): ?><a class="btn secondary small" href="records.php?<?= e(query_string(array_merge($baseQuery, ['page' => $page - 1]))) ?>">← Previous</a><?php endif; ?><span>Page <?= (int)$page ?> of <?= (int)$pages ?></span><?php if ($page < $pages): ?><a class="btn secondary small" href="records.php?<?= e(query_string(array_merge($baseQuery, ['page' => $page + 1]))) ?>">Next →</a><?php endif; ?></nav><?php endif; ?>
    </section>
<?php endif; ?>
<?php require 'includes/footer.php'; ?>
