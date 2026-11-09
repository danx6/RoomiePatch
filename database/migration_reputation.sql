-- RoomiePatch migration: reply voting and reputation.
-- Use this on an EXISTING roomiepatch database to add the new feature without losing data.
-- (A fresh install does not need it: roomiepatch.sql already contains these changes.)
-- Run it once, in phpMyAdmin's SQL or Import tab.

USE roomiepatch;

-- 1. Stored reputation on each user: upvotes minus downvotes on all their replies.
--    INT: can be negative, no artificial maximum. Starts at 0.
ALTER TABLE users
    ADD COLUMN reputation INT NOT NULL DEFAULT 0 AFTER role;

-- 2. One row per vote. A student has at most one active vote per reply.
CREATE TABLE IF NOT EXISTS reply_votes (
    vote_id    INT AUTO_INCREMENT PRIMARY KEY,
    reply_id   INT NOT NULL,
    user_id    INT NOT NULL,
    vote_type  ENUM('upvote', 'downvote') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vote_user_reply (user_id, reply_id),
    KEY idx_votes_reply (reply_id),
    KEY idx_votes_updated (updated_at),
    FOREIGN KEY (reply_id) REFERENCES replies(reply_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. Make every stored reputation match the votes that exist (safe to run again).
UPDATE users u
SET u.reputation = (
    SELECT COALESCE(SUM(IF(v.vote_type = 'upvote', 1, -1)), 0)
    FROM reply_votes v
    JOIN replies r ON r.reply_id = v.reply_id
    WHERE r.user_id = u.user_id
);
