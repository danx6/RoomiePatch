<?php
// IT Knowledge Repository: permanent, technician-approved solutions.
// Only entries with status Approved AND is_published = 1 are ever listed (REPO_PUBLIC_SQL).
// Search, category filter and sorting all work together.

require_once '../includes/auth.php';
require_login(['student', 'admin']);

$page_title = 'IT Knowledge Repository';
$active = 'repository';
include '../includes/header.php';

$q = get_text('q', 100);
$category = in_array(get_text('category'), REPO_CATEGORIES, true) ? get_text('category') : '';
$sorts = [
    'newest' => ['Newest first', 'e.published_at DESC, e.entry_id DESC'],
    'oldest' => ['Oldest first', 'e.published_at ASC, e.entry_id ASC'],
    'az'     => ['A to Z', 'e.title ASC'],
    'za'     => ['Z to A', 'e.title DESC'],
];
$sort = array_key_exists(get_text('sort'), $sorts) ? get_text('sort') : 'newest';

$where = [REPO_PUBLIC_SQL];
$types = '';
$params = [];
if ($q !== '') {
    // Titles, keywords, short description, problem and solution text
    $where[] = '(e.title LIKE ? OR e.keywords LIKE ? OR e.summary LIKE ? OR e.problem LIKE ? OR e.solution LIKE ?)';
    $like = '%' . like_escape($q) . '%';
    $types .= 'sssss';
    array_push($params, $like, $like, $like, $like, $like);
}

// Counts per category (respecting the search), for the category chips
$stmt = $conn->prepare('SELECT e.category, COUNT(*) AS n FROM repository_entries e WHERE ' . implode(' AND ', $where) . ' GROUP BY e.category');
if ($types !== '') { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$cat_counts = array_fill_keys(REPO_CATEGORIES, 0);
foreach ($stmt->get_result() as $row) { $cat_counts[$row['category']] = (int) $row['n']; }
$all_count = array_sum($cat_counts);

if ($category !== '') {
    $where[] = 'e.category = ?';
    $types .= 's';
    $params[] = $category;
}
$stmt = $conn->prepare('
    SELECT e.entry_id, e.title, e.category, e.summary, e.keywords, e.published_at,
           c.username AS contributor, s.username AS solver
    FROM repository_entries e
    LEFT JOIN users c ON c.user_id = e.contributor_id
    LEFT JOIN users s ON s.user_id = e.solver_id
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY ' . $sorts[$sort][1]);
if ($types !== '') { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$entries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function repo_url($changes)
{
    global $q, $category, $sort;
    $query = array_filter(array_merge(['q' => $q, 'category' => $category, 'sort' => $sort === 'newest' ? '' : $sort], $changes), 'strlen');
    return BASE_URL . 'student/repository.php' . ($query ? '?' . http_build_query($query) : '');
}
?>

<main class="content">
    <div class="page-head">
        <h1>IT Knowledge Repository</h1>
        <p>Reviewed solutions to common IT problems, kept permanently for every student.</p>
    </div>

    <!-- Permanent notice: not dismissible -->
    <div class="repo-notice" role="note">
        <span class="repo-notice-icon"><?= icon('shield-check', 22) ?></span>
        <p>All knowledge entries in this repository have been reviewed and evaluated by university-certified technicians to ensure their technical relevance and accuracy. These entries are provided as a reference to help students understand and troubleshoot common IT-related problems.</p>
    </div>

    <form class="repo-search" action="repository.php" method="get" role="search">
        <div class="repo-search-box">
            <?= icon('search', 18) ?>
            <label class="sr-only" for="repo-q">Search the repository</label>
            <input type="search" id="repo-q" name="q" value="<?= e($q) ?>" placeholder="Search titles, keywords, problems and solutions">
        </div>
        <label class="sr-only" for="repo-category">Category</label>
        <select class="input input-sm" id="repo-category" name="category">
            <option value="">All categories</option>
            <?php foreach (REPO_CATEGORIES as $cat): ?>
                <option <?= $cat === $category ? 'selected' : '' ?>><?= e($cat) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="sr-only" for="repo-sort">Sort</label>
        <select class="input input-sm" id="repo-sort" name="sort">
            <?php foreach ($sorts as $key => [$label]): ?>
                <option value="<?= $key ?>" <?= $key === $sort ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
    </form>

    <nav class="repo-cats" aria-label="Repository categories">
        <a class="chip<?= $category === '' ? ' chip-active' : '' ?>" href="<?= e(repo_url(['category' => ''])) ?>">All<span class="chip-count"><?= $all_count ?></span></a>
        <?php foreach (REPO_CATEGORIES as $cat): ?>
            <a class="chip<?= $cat === $category ? ' chip-active' : '' ?>" href="<?= e(repo_url(['category' => $cat])) ?>"><?= e($cat) ?><span class="chip-count"><?= $cat_counts[$cat] ?></span></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($q !== '' || $category !== ''): ?>
        <p class="muted repo-result-line">
            <?= count($entries) ?> <?= count($entries) === 1 ? 'entry' : 'entries' ?>
            <?= $q !== '' ? 'matching "' . e($q) . '"' : '' ?>
            <?= $category !== '' ? 'in ' . e($category) : '' ?>
            &middot; <a href="<?= e(repo_url(['q' => '', 'category' => ''])) ?>">Clear</a>
        </p>
    <?php endif; ?>

    <?php if (!$entries): ?>
        <div class="feed">
            <div class="empty-state">
                <?php if ($q !== '' || $category !== ''): ?>
                    <strong>No entries match your search</strong>
                    Try other keywords, a different category, or <a href="<?= e(repo_url(['q' => '', 'category' => ''])) ?>">clear the filters</a>.
                <?php else: ?>
                    <strong>No entries yet</strong>
                    Approved solutions will appear here after technician evaluation.
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="feed">
            <?php foreach ($entries as $en): ?>
                <article class="repo-card">
                    <div class="post-meta">
                        <span class="type-badge"><?= e($en['category']) ?></span>
                        <span class="repo-verified"><?= icon('shield-check', 14) ?>Technician evaluated</span>
                    </div>
                    <h3 class="post-title"><a href="<?= BASE_URL ?>student/repository_entry.php?id=<?= (int) $en['entry_id'] ?>"><?= e($en['title']) ?></a></h3>
                    <p class="post-excerpt"><?= e($en['summary']) ?></p>
                    <div class="repo-card-foot">
                        <span>Asked by <strong><?= e($en['contributor'] ?? 'former student') ?></strong></span>
                        <?php if ($en['solver']): ?><span class="dot" aria-hidden="true"></span><span>Solved by <strong><?= e($en['solver']) ?></strong></span><?php endif; ?>
                        <span class="dot" aria-hidden="true"></span>
                        <time datetime="<?= e($en['published_at']) ?>">Published <?= e(date('j M Y', strtotime($en['published_at']))) ?></time>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php include '../includes/footer.php'; ?>
