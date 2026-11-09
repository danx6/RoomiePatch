<?php
// Send a meetup request to another student, for face-to-face troubleshooting help.
//   meetup_request.php?user=ID
// The student types the date/time and location themselves (no calendar or booking system).
// Before sending, the Meetup Safety Notice popup must be acknowledged.

require_once '../includes/auth.php';
require_login('student');
require_once '../config/db.php';

$my_id = (int) $_SESSION['user_id'];
$other_id = (int) get_text('user');

// Only other students can receive meetup requests
$stmt = $conn->prepare('SELECT user_id, username FROM users WHERE user_id = ? AND role = "student" AND user_id <> ?');
$stmt->bind_param('ii', $other_id, $my_id);
$stmt->execute();
$other = $stmt->get_result()->fetch_assoc();

if (!$other) {
    set_flash('You cannot send a meetup request to that user.', 'error');
    redirect('student/meetups.php');
}

$profile_page = 'student/profile.php?id=' . $other_id;

// Only one pending request to the same student at a time
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM meetup_requests WHERE requester_id = ? AND recipient_id = ? AND status = 'Pending'");
$stmt->bind_param('ii', $my_id, $other_id);
$stmt->execute();
if ($stmt->get_result()->fetch_assoc()['total'] > 0) {
    set_flash('You already have a pending meetup request to ' . $other['username'] . '. Wait for them to reply.', 'error');
    redirect('student/meetups.php?tab=sent');
}

$datetime = '';
$location = '';
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    // The safety notice must be acknowledged for every request (never saved)
    if (post_text('safety_ack') !== '1') {
        $errors['safety'] = 'Please read and agree to the Meetup Safety Notice before sending.';
    }

    $datetime = post_text('proposed_datetime');
    $location = post_text('location');
    $message  = post_text('message');

    // Date/time from <input type="datetime-local">, e.g. 2026-11-12T14:30
    $parsed = DateTime::createFromFormat('Y-m-d\TH:i', $datetime);
    if (!$parsed || $parsed->format('Y-m-d\TH:i') !== $datetime) {
        $errors['proposed_datetime'] = 'Enter a valid date and time.';
    } elseif ($parsed <= new DateTime()) {
        $errors['proposed_datetime'] = 'Choose a date and time in the future.';
    }

    if (mb_strlen($location) < 3) {
        $errors['location'] = 'Enter where you would like to meet.';
    } elseif (mb_strlen($location) > 150) {
        $errors['location'] = 'Location must be 150 characters or fewer.';
    }

    if (mb_strlen($message) > 500) {
        $errors['message'] = 'Message must be 500 characters or fewer.';
    }

    if (!$errors) {
        $datetime_to_save = $parsed->format('Y-m-d H:i:s');
        $message_to_save = $message === '' ? null : $message;

        $stmt = $conn->prepare('INSERT INTO meetup_requests (requester_id, recipient_id, proposed_datetime, location, message) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('iisss', $my_id, $other_id, $datetime_to_save, $location, $message_to_save);
        $stmt->execute();
        $meetup_id = $conn->insert_id;

        add_notification(
            $conn,
            $other_id,
            'meetup_request',
            $_SESSION['username'] . ' sent you a meetup request',
            'student/meetups.php#meetup-' . $meetup_id
        );

        set_flash('Meetup request sent to ' . $other['username'] . '. You will be notified when they reply.');
        redirect('student/meetups.php?tab=sent');
    }
}

function field_error($errors, $field)
{
    return isset($errors[$field]) ? '<p class="field-error">' . e($errors[$field]) . '</p>' : '';
}

$page_title = 'Request a meetup';
$active = 'meetups';
include '../includes/header.php';
?>

<main class="content">
    <a class="back-link" href="<?= BASE_URL . $profile_page ?>"><?= icon('chevron-left', 16) ?>Back to <?= e($other['username']) ?>'s profile</a>

    <div class="page-head">
        <h1>Request a meetup</h1>
        <p>Ask <?= e($other['username']) ?> to meet in person for help with your problem.</p>
    </div>

    <?php if (isset($errors['safety'])): ?>
        <div class="alert alert-error" role="alert"><?= e($errors['safety']) ?></div>
    <?php elseif ($errors): ?>
        <div class="alert alert-error" role="alert">Please fix the highlighted fields below.</div>
    <?php endif; ?>

    <form class="form-card" method="post" action="meetup_request.php?user=<?= $other_id ?>" data-safety-notice novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="safety_ack" value="">

        <div class="meetup-with">
            <?= avatar($other['username']) ?>
            <div>
                <span class="muted">Meetup request to</span><br>
                <a class="author-chip" href="<?= BASE_URL . $profile_page ?>"><?= e($other['username']) ?></a>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group<?= isset($errors['proposed_datetime']) ? ' has-error' : '' ?>">
                <label for="proposed_datetime">Proposed date and time</label>
                <input class="input" type="datetime-local" id="proposed_datetime" name="proposed_datetime"
                       value="<?= e($datetime) ?>" min="<?= date('Y-m-d\TH:i') ?>" required>
                <?= field_error($errors, 'proposed_datetime') ?>
            </div>

            <div class="form-group<?= isset($errors['location']) ? ' has-error' : '' ?>">
                <label for="location">Location</label>
                <input class="input" type="text" id="location" name="location" maxlength="150"
                       placeholder="e.g. Library, level 2 study area" value="<?= e($location) ?>" required>
                <?= field_error($errors, 'location') ?>
            </div>
        </div>
        <p class="field-hint meetup-hint">Choose a public place on campus. Never share your home address.</p>

        <div class="form-group<?= isset($errors['message']) ? ' has-error' : '' ?>">
            <label for="message">Message <span class="optional">optional</span></label>
            <textarea class="input" id="message" name="message" rows="4" maxlength="500"
                      placeholder="e.g. Could you help me install Ubuntu? I will bring my laptop."><?= e($message) ?></textarea>
            <?= field_error($errors, 'message') ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= icon('meetup', 16) ?>Send Request</button>
            <a class="btn btn-secondary" href="<?= BASE_URL . $profile_page ?>">Cancel</a>
        </div>
    </form>
</main>

<?php include '../includes/safety_modal.php'; ?>
<?php include '../includes/footer.php'; ?>
