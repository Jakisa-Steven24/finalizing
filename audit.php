<?php
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_superadmin();

$q = get_string('q');
if (strlen($q) > 100) {
    $q = '';
}

$actionFilter = get_string('action');
if ($actionFilter !== '' && !preg_match('/^[A-Z_]{2,30}$/', $actionFilter)) {
    $actionFilter = '';
}

$entityFilter = get_string('entity');
if (strlen($entityFilter) > 60) {
    $entityFilter = '';
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

$page = min(get_positive_int('page', 1), 10000);
$perPage = 50;

$where = [];
$types = '';
$params = [];

if ($q !== '') {
    $where[] = '(a.description LIKE ? OR u.username LIKE ? OR u.full_name LIKE ?)';
    $types .= 'sss';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

if ($actionFilter !== '') {
    $where[] = 'a.action = ?';
    $types .= 's';
    $params[] = $actionFilter;
}

if ($entityFilter !== '') {
    $where[] = 'a.entity LIKE ?';
    $types .= 's';
    $params[] = '%' . $entityFilter . '%';
}

if ($dateFrom !== '') {
    $where[] = 'a.created_at >= ?';
    $types .= 's';
    $params[] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {
    $where[] = 'a.created_at <= ?';
    $types .= 's';
    $params[] = $dateTo . ' 23:59:59';
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$total = (int)(db_one($conn, 'SELECT COUNT(*) AS total FROM audit_log a LEFT JOIN app_users u ON u.user_id = a.user_id' . $whereSql, $types, $params)['total'] ?? 0);

$pages = max(1, (int)ceil($total / $perPage));
if ($page > $pages) {
    $page = $pages;
}
$offset = ($page - 1) * $perPage;

$rows = db_result($conn, 'SELECT a.audit_id, a.action, a.entity, a.record_id, a.description, a.ip_address, a.created_at, u.full_name, u.username FROM audit_log a LEFT JOIN app_users u ON u.user_id = a.user_id' . $whereSql . ' ORDER BY a.audit_id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset, $types, $params);

$actions = $conn->query('SELECT DISTINCT action FROM audit_log ORDER BY action');

$pageTitle = 'Audit log';
require 'includes/header.php';
?>
<div class="page-heading"><div><h1>Audit log</h1><p class="subheading">Immutable activity history for troubleshooting, accountability and security review.</p></div><a class="btn secondary" href="index.php">← Dashboard</a></div>
<section class="card filter-card"><h2>Filter audit activity</h2><form class="filter-form" method="get"><div class="field"><label for="q">Search activity</label><input id="q" type="search" name="q" maxlength="100" value="<?= e($q) ?>" placeholder="Search user or description"></div><div class="field"><label for="action">Action</label><select id="action" name="action"><option value="">All actions</option><?php while ($row = $actions->fetch_assoc()): ?><option value="<?= e($row['action']) ?>" <?= $actionFilter === $row['action'] ? 'selected' : '' ?>><?= e($row['action']) ?></option><?php endwhile; ?></select></div><div class="field"><label for="entity">Entity</label><input id="entity" name="entity" maxlength="60" value="<?= e($entityFilter) ?>" placeholder="Example: mark or app_users"></div><div class="field"><label for="date_from">From date</label><input id="date_from" type="date" name="date_from" value="<?= e($dateFrom) ?>" placeholder="YYYY-MM-DD"></div><div class="field"><label for="date_to">To date</label><input id="date_to" type="date" name="date_to" value="<?= e($dateTo) ?>" placeholder="YYYY-MM-DD"></div><div class="filter-actions"><button class="btn">Apply filters</button><a class="btn secondary" href="audit.php">Clear filters</a></div></form></section>
<section class="card"><div class="toolbar"><h2><?= $total ?> event<?= $total === 1 ? '' : 's' ?></h2><span class="hint">Page <?= $page ?> of <?= $pages ?></span></div><div class="table-wrap"><table><thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Entity / ID</th><th>Description</th><th>IP address</th></tr></thead><tbody><?php $count = 0; while ($row = $rows->fetch_assoc()): $count++; ?><tr><td><?= e($row['created_at']) ?></td><td><?= e($row['username'] ? $row['full_name'] . ' (' . $row['username'] . ')' : 'System / deleted user') ?></td><td><span class="badge"><?= e($row['action']) ?></span></td><td><?= e($row['entity'] . ($row['record_id'] ? ' #' . $row['record_id'] : '')) ?></td><td><?= e($row['description']) ?></td><td><?= e($row['ip_address'] ?: '—') ?></td></tr><?php endwhile; if (!$count): ?><tr><td colspan="6" class="empty">No audit events match these filters.</td></tr><?php endif; ?></tbody></table></div><?php $base = ['q' => $q, 'action' => $actionFilter, 'entity' => $entityFilter, 'date_from' => $dateFrom, 'date_to' => $dateTo]; if ($pages > 1): ?><nav class="pagination"><?php if ($page > 1): ?><a class="btn secondary small" href="audit.php?<?= e(query_string(array_merge($base, ['page' => $page - 1]))) ?>">← Previous</a><?php endif; ?><span>Page <?= $page ?> of <?= $pages ?></span><?php if ($page < $pages): ?><a class="btn secondary small" href="audit.php?<?= e(query_string(array_merge($base, ['page' => $page + 1]))) ?>">Next →</a><?php endif; ?></nav><?php endif; ?></section>
<?php require 'includes/footer.php'; ?>
