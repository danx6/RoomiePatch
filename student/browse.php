<?php
// Browse Posts: every student's questions, with Open / Resolved tabs,
// search, and filters for category, device/OS and tag. Newest first.
// Staff can view this page too (read-only).

require_once '../includes/auth.php';
require_login(['student', 'admin']);

$page_title = 'Browse Posts';
$active = 'browse';
include '../includes/header.php';

// ---------- 1. Read and clean the filters from the URL ----------
$status   = get_text('status') === 'resolved' ? 'Resolved' : 'Open';   // Open is the default tab
$q        = get_text('q', 100);
$category = (int) get_text('category');
$device   = in_array(get_text('device'), DEVICE_OPTIONS, true) ? get_text('device') : '';
$tag      = get_text('tag', 50);

$categories = $conn->query('SELECT category_id, category_name FROM categories ORDER BY category_id')->fetch_all(MYSQLI_ASSOC);

// Ignore a category ID that does not exist
$category_name = '';
foreach ($categories as $cat) {
    if ((int) $cat['category_id'] === $category) {
        $category_name = $cat['category_name'];
    }
}
if ($category_name === '') {
    $category = 0;
}

// ---------- 2. Build the WHERE conditions (prepared statement placeholders) ----------
$where  = [];   // SQL pieces, joined with AND
$types  = '';   // bind_param types, e.g. 'sss'
$params = [];   // values for the ? placeholders

if ($q !== '') {
    $where[] = '(p.title LIKE ? OR p.description LIKE ? OR p.tags LIKE ?)';
    $like = '%' . like_escape($q) . '%';
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}
if ($category > 0) {
    $where[] = 'p.category_id = ?';
    $types .= 'i';
    $params[] = $category;
}
if ($device !== '') {
    $where[] = 'p.device_os = ?';
    $types .= 's';
    $params[] = $device;
}
if ($tag !== '') {
    // Tags are stored like "wifi, driver, laptop". Wrapping them in commas
    // lets us match a whole tag, so "wifi" does not also match "wifi6".
    $where[] = "CONCAT(',', REPLACE(p.tags, ', ', ','), ',') LIKE ?";
    $types .= 's';
    $params[] = '%,' . like_escape($tag) . ',%';
}

// The Resolved tab only lists posts resolved in the last RESOLVED_VISIBLE_DAYS days.
// Older resolved posts are not deleted; they are just no longer listed here.
$recent_resolved_sql = "p.status = 'Resolved' AND p.resolved_at >= NOW() - INTERVAL " . RESOLVED_VISIBLE_DAYS . ' DAY';

// ---------- 3. Count Open and (recently) Resolved posts for the tab labels ----------
$count_sql = "SELECT COALESCE(SUM(p.status = 'Open'), 0) AS open_total,
                     COALESCE(SUM($recent_resolved_sql), 0) AS resolved_total
              FROM posts p"
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '');
$stmt = $conn->prepare($count_sql);
if ($types !== '') {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$counts = ['Open' => (int) $row['open_total'], 'Resolved' => (int) $row['resolved_total']];

// ---------- 4. Load the posts for the current tab, newest first ----------
$list_where = array_merge($where, ['p.status = ?']);
if ($status === 'Resolved') {
    $list_where[] = 'p.resolved_at >= NOW() - INTERVAL ' . RESOLVED_VISIBLE_DAYS . ' DAY';
}
$stmt = $conn->prepare(POST_LIST_SQL . ' WHERE ' . implode(' AND ', $list_where) . ' ORDER BY p.created_at DESC');
$stmt->bind_param($types . 's', ...array_merge($params, [$status]));
$stmt->execute();
$posts = $stmt->get_result();

// ---------- 5. Popular tags for the right column ----------
$tag_counts = [];
foreach ($conn->query('SELECT tags FROM posts') as $row) {
    foreach (tag_list($row['tags']) as $t) {
        $key = strtolower($t);
        $tag_counts[$key] = ($tag_counts[$key] ?? 0) + 1;
    }
}
arsort($tag_counts);
$tag_counts = array_slice($tag_counts, 0, 12, true);

// Build a browse.php link that keeps the current filters, with some values changed.
// Example: browse_url(['status' => 'resolved'])
function browse_url($changes = [])
{
    global $status, $q, $category, $device, $tag;
    $query = array_merge([
        'status'   => $status === 'Resolved' ? 'resolved' : '',
        'q'        => $q,
        'category' => $category ?: '',
        'device'   => $device,
        'tag'      => $tag,
    ], $changes);
    $query = array_filter($query, function ($value) {
        return $value !== '' && $value !== null;
    });
    return BASE_URL . 'student/browse.php' . ($query ? '?' . http_build_query($query) : '');
}

$has_filters = $q !== '' || $category > 0 || $device !== '' || $tag !== '';
?>

<main class="content">
    <div class="page-head">
        <h1>Browse Posts</h1>
        <p>IT problems posted by students. Open a question to read the replies or help out.</p>
    </div>

    <?php show_flash(); ?>

    <div class="tabs-row">
        <nav class="tabs" aria-label="Post status">
            <a class="tab<?= $status === 'Open' ? ' active' : '' ?>" href="<?= e(browse_url(['status' => ''])) ?>"
               <?= $status === 'Open' ? 'aria-current="page"' : '' ?>>
                Open Questions <span class="tab-count"><?= $counts['Open'] ?></span>
            </a>
            <a class="tab<?= $status === 'Resolved' ? ' active' : '' ?>" href="<?= e(browse_url(['status' => 'resolved'])) ?>"
               <?= $status === 'Resolved' ? 'aria-current="page"' : '' ?>>
                Resolved <span class="tab-count"><?= $counts['Resolved'] ?></span>
            </a>
        </nav>
        <span class="sort-label"><?= $status === 'Resolved' ? 'Resolved in the last ' . RESOLVED_VISIBLE_DAYS . ' days' : 'Newest first' ?></span>
    </div>

    <form class="filter-bar" action="browse.php" method="get">
        <?php if ($status === 'Resolved'): ?><input type="hidden" name="status" value="resolved"><?php endif; ?>
        <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>

        <label class="sr-only" for="f-category">Category</label>
        <select class="input input-sm" id="f-category" name="category">
            <option value="">All categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= (int) $cat['category_id'] ?>" <?= (int) $cat['category_id'] === $category ? 'selected' : '' ?>>
                    <?= e($cat['category_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label class="sr-only" for="f-device">Device / OS</label>
        <select class="input input-sm" id="f-device" name="device">
            <option value="">All devices</option>
            <?php foreach (DEVICE_OPTIONS as $option): ?>
                <option value="<?= e($option) ?>" <?= $option === $device ? 'selected' : '' ?>><?= e($option) ?></option>
            <?php endforeach; ?>
        </select>

        <label class="sr-only" for="f-tag">Tag</label>
        <input class="input input-sm" type="text" id="f-tag" name="tag" placeholder="Tag, e.g. wifi" value="<?= e($tag) ?>">

        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    </form>

    <?php if ($has_filters): ?>
        <div class="active-filters">
            <span>Showing</span>
            <?php if ($q !== ''): ?>
                <a class="filter-pill" href="<?= e(browse_url(['q' => ''])) ?>" title="Remove search">
                    "<?= e($q) ?>" <?= icon('close', 14) ?>
                </a>
            <?php endif; ?>
            <?php if ($category > 0): ?>
                <a class="filter-pill" href="<?= e(browse_url(['category' => ''])) ?>" title="Remove category filter">
                    <?= e($category_name) ?> <?= icon('close', 14) ?>
                </a>
            <?php endif; ?>
            <?php if ($device !== ''): ?>
                <a class="filter-pill" href="<?= e(browse_url(['device' => ''])) ?>" title="Remove device filter">
                    <?= e($device) ?> <?= icon('close', 14) ?>
                </a>
            <?php endif; ?>
            <?php if ($tag !== ''): ?>
                <a class="filter-pill" href="<?= e(browse_url(['tag' => ''])) ?>" title="Remove tag filter">
                    Tag: <?= e($tag) ?> <?= icon('close', 14) ?>
                </a>
            <?php endif; ?>
            <a class="clear-filters" href="<?= e(browse_url(['q' => '', 'category' => '', 'device' => '', 'tag' => ''])) ?>">Clear all</a>
        </div>
    <?php endif; ?>

    <div class="feed">
        <?php if ($posts->num_rows === 0): ?>
            <div class="empty-state">
                <?php if ($has_filters): ?>
                    <strong>No <?= $status === 'Open' ? 'open' : 'resolved' ?> questions match</strong>
                    Try removing a filter or searching for something else.
                <?php elseif ($status === 'Open'): ?>
                    <strong>No open questions</strong>
                    Every question has been resolved. Nice work, everyone.
                <?php else: ?>
                    <strong>No recently resolved questions</strong>
                    Questions appear here for <?= RESOLVED_VISIBLE_DAYS ?> days after the author marks a reply as resolved.
                    Approved solutions are kept in the <a href="<?= BASE_URL ?>student/repository.php">IT Knowledge Repository</a>.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php while ($post = $posts->fetch_assoc()): ?>
                <?php include '../includes/post_card.php'; ?>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</main>

<aside class="rail">
    <section class="rail-box">
        <h2>Popular tags</h2>
        <?php if (!$tag_counts): ?>
            <p>No tags yet.</p>
        <?php else: ?>
            <div class="tag-cloud">
                <?php foreach ($tag_counts as $name => $total): ?>
                    <a class="chip<?= strcasecmp($name, $tag) === 0 ? ' chip-active' : '' ?>" href="<?= e(browse_url(['tag' => $name])) ?>">
                        <?= e($name) ?><span class="chip-count"><?= $total ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if (!is_staff()): ?>
        <section class="rail-box">
            <h2>Can you help?</h2>
            <p>Anyone can reply to an open question. If your reply fixes the problem, the author can mark it as resolved.</p>
        </section>
    <?php endif; ?>
</aside>

<?php include '../includes/footer.php'; ?>
