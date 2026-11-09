<?php
// Create Post: a student asks a new troubleshooting question.
// The form submits to this same page. If anything is wrong we show the form
// again with the errors; if everything is fine we save it and redirect.

require_once '../includes/auth.php';
require_login('student');
require_once '../config/db.php';

$categories = $conn->query('SELECT category_id, category_name FROM categories ORDER BY category_id')->fetch_all(MYSQLI_ASSOC);
$category_ids = array_map('intval', array_column($categories, 'category_id'));

// Values shown in the form (empty at first, the student's input after a failed submit)
$title = '';
$description = '';
$category = 0;
$device = '';
$tags = '';
$errors = [];   // field name => error message

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    $title       = post_text('title');
    $description = post_text('description');
    $category    = (int) post_text('category');
    $device      = post_text('device');
    $tags        = post_text('tags');

    // ---------- Validate each field ----------
    if (mb_strlen($title) < 10) {
        $errors['title'] = 'Title must be at least 10 characters, so others understand the problem.';
    } elseif (mb_strlen($title) > 150) {
        $errors['title'] = 'Title must be 150 characters or fewer.';
    }

    if (mb_strlen($description) < 20) {
        $errors['description'] = 'Please describe the problem in at least 20 characters.';
    } elseif (mb_strlen($description) > 5000) {
        $errors['description'] = 'Description must be 5000 characters or fewer.';
    }

    if (!in_array($category, $category_ids, true)) {
        $errors['category'] = 'Choose a category.';
    }

    if (!in_array($device, DEVICE_OPTIONS, true)) {
        $errors['device'] = 'Choose a device or operating system.';
    }

    // Tags: split on commas, tidy up, remove duplicates, then join as "a, b, c"
    $tag_items = [];
    foreach (tag_list($tags) as $item) {
        $item = mb_strtolower(preg_replace('/\s+/', ' ', $item));
        if (mb_strlen($item) > 30) {
            $errors['tags'] = 'Each tag must be 30 characters or fewer.';
        }
        if (!in_array($item, $tag_items, true)) {
            $tag_items[] = $item;
        }
    }
    if (count($tag_items) > 5) {
        $errors['tags'] = 'Use 5 tags or fewer.';
    }
    $tags_to_save = implode(', ', $tag_items);

    // Images: optional, up to 3 (screenshots of the error, photo of the device, etc.)
    $images = check_image_uploads('images', 3, $image_error);
    if ($image_error !== '') {
        $errors['images'] = $image_error;
    }

    // ---------- Save ----------
    if (!$errors) {
        // Status is not taken from the form: new posts always start as Open.
        $stmt = $conn->prepare('INSERT INTO posts (user_id, title, description, category_id, device_os, tags, status) VALUES (?, ?, ?, ?, ?, ?, "Open")');
        $stmt->bind_param('ississ', $_SESSION['user_id'], $title, $description, $category, $device, $tags_to_save);
        $stmt->execute();
        $post_id = $conn->insert_id;

        // Save each image file, then remember its file name in post_images
        $stmt = $conn->prepare('INSERT INTO post_images (post_id, image_path) VALUES (?, ?)');
        foreach ($images as $image) {
            $file_name = save_image($image, 'uploads/posts');
            $stmt->bind_param('is', $post_id, $file_name);
            $stmt->execute();
        }

        set_flash('Your question was posted. Other students can now reply to it.');
        redirect('student/post.php?id=' . $post_id);
    }
}

// Small helper: print the error message for one field, if there is one.
function field_error($errors, $field)
{
    if (isset($errors[$field])) {
        return '<p class="field-error" id="' . $field . '-error">' . e($errors[$field]) . '</p>';
    }
    return '';
}

$page_title = 'Ask a question';
$active = '';
include '../includes/header.php';
?>

<main class="content">
    <div class="page-head">
        <h1>Ask a question</h1>
        <p>Describe a problem with your own laptop, phone or software.</p>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-error" role="alert">
            Please fix the highlighted fields below.
            <?php if (!empty($_FILES['images']['name'][0])): ?>
                Browsers clear chosen images after an error, so please choose your images again.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- enctype="multipart/form-data" is required for file uploads -->
    <form class="form-card" method="post" action="create_post.php" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>

        <div class="form-group<?= isset($errors['title']) ? ' has-error' : '' ?>">
            <label for="title">Title</label>
            <p class="field-hint">Sum up the problem in one line, like you would tell a friend.</p>
            <input class="input" type="text" id="title" name="title" maxlength="150"
                   placeholder="e.g. Laptop keeps disconnecting from WiFi every few minutes"
                   value="<?= e($title) ?>" required>
            <?= field_error($errors, 'title') ?>
        </div>

        <div class="form-group<?= isset($errors['description']) ? ' has-error' : '' ?>">
            <label for="description">Description</label>
            <p class="field-hint">What happens, when it started, any error messages, and what you already tried.</p>
            <textarea class="input" id="description" name="description" rows="8" maxlength="5000" required><?= e($description) ?></textarea>
            <?= field_error($errors, 'description') ?>
        </div>

        <div class="form-row">
            <div class="form-group<?= isset($errors['category']) ? ' has-error' : '' ?>">
                <label for="category">Category</label>
                <select class="input" id="category" name="category" required>
                    <option value="">Choose a category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int) $cat['category_id'] ?>" <?= (int) $cat['category_id'] === $category ? 'selected' : '' ?>>
                            <?= e($cat['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'category') ?>
            </div>

            <div class="form-group<?= isset($errors['device']) ? ' has-error' : '' ?>">
                <label for="device">Device / OS</label>
                <select class="input" id="device" name="device" required>
                    <option value="">Choose a device / OS</option>
                    <?php foreach (DEVICE_OPTIONS as $option): ?>
                        <option value="<?= e($option) ?>" <?= $option === $device ? 'selected' : '' ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= field_error($errors, 'device') ?>
            </div>
        </div>

        <div class="form-group<?= isset($errors['tags']) ? ' has-error' : '' ?>">
            <label for="tags">Tags <span class="optional">optional</span></label>
            <p class="field-hint">Up to 5, separated by commas.</p>
            <input class="input" type="text" id="tags" name="tags" placeholder="e.g. wifi, driver, laptop"
                   value="<?= e($tags) ?>">
            <?= field_error($errors, 'tags') ?>
        </div>

        <div class="form-group<?= isset($errors['images']) ? ' has-error' : '' ?>">
            <label for="images">Images <span class="optional">optional</span></label>
            <p class="field-hint">Screenshots of the error or photos of the device. Up to 3 images, JPG, PNG, GIF or WebP, 5 MB each.</p>
            <label class="file-drop" for="images">
                <?= icon('image', 22) ?>
                <span><strong>Choose images</strong> from your device</span>
            </label>
            <input class="file-input" type="file" id="images" name="images[]" multiple
                   accept="image/jpeg,image/png,image/gif,image/webp" data-image-preview="image-preview" data-max-files="3">
            <div class="image-preview" id="image-preview"></div>
            <?= field_error($errors, 'images') ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Post question</button>
            <a class="btn btn-secondary" href="<?= BASE_URL ?>student/browse.php">Cancel</a>
        </div>
    </form>
</main>

<aside class="rail">
    <section class="rail-box">
        <h2>Writing a good question</h2>
        <ol class="steps">
            <li><strong>Be specific.</strong> "WiFi drops every 5 minutes" helps more than "internet broken".</li>
            <li><strong>Include error messages</strong> exactly as they appear.</li>
            <li><strong>Say what you tried</strong>, so helpers do not repeat it.</li>
        </ol>
    </section>

    <section class="rail-box">
        <h2>Keep it safe</h2>
        <p>Do not post passwords, your IC number, phone number or home address.</p>
    </section>
</aside>

<?php include '../includes/footer.php'; ?>
