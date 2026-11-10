-- RoomiePatch migration: IT Knowledge Repository + resolution tracking.
-- Use this on an EXISTING roomiepatch database (after migration_reputation.sql) to add the
-- feature without losing data. A fresh install does not need it: roomiepatch.sql has it all.
-- Run it once, in phpMyAdmin's SQL or Import tab.

USE roomiepatch;

-- 1. Record exactly which reply the original poster accepted, and when the post was resolved.
--    Until now this was only the flag replies.is_resolved = 1 (kept, so old code still works).
--    resolved_at drives the 7-day "Resolved" tab on Browse Posts.
ALTER TABLE posts
    ADD COLUMN accepted_reply_id INT NULL AFTER status,
    ADD COLUMN resolved_at DATETIME NULL AFTER accepted_reply_id,
    ADD KEY idx_posts_resolved_at (resolved_at),
    ADD CONSTRAINT fk_posts_accepted_reply FOREIGN KEY (accepted_reply_id) REFERENCES replies(reply_id) ON DELETE SET NULL;

-- Fill them in for posts that are already resolved. The exact resolution time was never stored,
-- so the time of the last reply in the thread is used as the closest available estimate.
UPDATE posts p
JOIN replies r ON r.post_id = p.post_id AND r.is_resolved = 1
SET p.accepted_reply_id = r.reply_id,
    p.resolved_at = (SELECT MAX(r2.created_at) FROM replies r2 WHERE r2.post_id = p.post_id)
WHERE p.status = 'Resolved';

-- 2. Staff who are university-certified technicians. Only they may approve or reject
--    repository submissions; other staff can view the queue and unpublish entries.
ALTER TABLE users
    ADD COLUMN is_certified_technician TINYINT(1) NOT NULL DEFAULT 0 AFTER reputation;

-- Demo staff: Admin_Farah and Admin_Rutheshwarar are the certified technicians.
UPDATE users SET is_certified_technician = 1 WHERE login_id IN ('sd234792', 'sd231058') AND role = 'admin';

-- 3. Repository submissions and published entries.
--    One row per original Q&A post (UNIQUE post_id), so a post cannot be submitted twice.
--    The entry points at the original post and accepted reply instead of copying the discussion;
--    the text fields hold the reviewed summary and verified solution shown in the repository.
CREATE TABLE IF NOT EXISTS repository_entries (
    entry_id          INT AUTO_INCREMENT PRIMARY KEY,
    post_id           INT NULL,
    accepted_reply_id INT NULL,
    category          ENUM('Hardware', 'Software', 'Operating System', 'Networking') NOT NULL,  -- same as post categories; "Other" posts become Software
    title             VARCHAR(150) NOT NULL,
    summary           VARCHAR(300) NOT NULL,
    problem           TEXT NOT NULL,
    solution          TEXT NOT NULL,
    keywords          VARCHAR(255) NULL,
    contributor_id    INT NULL,                  -- the student who asked the original question
    solver_id         INT NULL,                  -- the student whose reply was accepted
    submitted_by      INT NULL,
    submitted_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status            ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
    reviewed_by       INT NULL,                  -- the certified technician who decided
    reviewed_at       DATETIME NULL,
    review_notes      TEXT NULL,
    rejection_reason  TEXT NULL,
    is_published      TINYINT(1) NOT NULL DEFAULT 0,   -- 1 = visible to students (Approved only)
    published_at      DATETIME NULL,
    unpublished_at    DATETIME NULL,
    UNIQUE KEY uq_repository_post (post_id),
    KEY idx_repository_status (status, is_published),
    KEY idx_repository_category (category),
    KEY idx_repository_published (published_at),
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE SET NULL,
    FOREIGN KEY (accepted_reply_id) REFERENCES replies(reply_id) ON DELETE SET NULL,
    FOREIGN KEY (contributor_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (solver_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (submitted_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;
