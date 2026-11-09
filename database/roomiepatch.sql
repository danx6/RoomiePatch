-- RoomiePatch database: tables + mock data for the showcase.
-- Import this one file in phpMyAdmin (Import tab). Nothing else to run.
--
-- Demo logins (mock accounts, not real university records):
--   Students: password stud123   e.g. MC240930202 (Danial_Fahimy)
--   Staff:    password admin123  e.g. SD234792 (Admin_Farah)
-- Passwords are stored as password_hash() hashes, never as plain text.
--
-- WARNING: importing this file again deletes all existing RoomiePatch data.

CREATE DATABASE IF NOT EXISTS roomiepatch CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE roomiepatch;

-- Drop in reverse order so foreign keys do not block us
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS meetup_requests;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS reply_votes;
DROP TABLE IF EXISTS replies;
DROP TABLE IF EXISTS post_images;
DROP TABLE IF EXISTS posts;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

-- Students and staff/admin share one table. The role decides which dashboard they see.
CREATE TABLE users (
    user_id   INT AUTO_INCREMENT PRIMARY KEY,
    username  VARCHAR(50)  NOT NULL UNIQUE,
    login_id  VARCHAR(20)  NOT NULL UNIQUE,      -- matric ID or staff ID
    password  VARCHAR(255) NOT NULL,             -- password_hash() output, never plain text
    role      ENUM('student', 'admin') NOT NULL DEFAULT 'student',
    -- Reputation = upvotes minus downvotes received on all of this user's replies.
    -- Can go negative; no upper limit. Updated by cast_vote() in includes/functions.php.
    reputation INT NOT NULL DEFAULT 0,
    bio       TEXT NULL
) ENGINE=InnoDB;

CREATE TABLE categories (
    category_id   INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT INTO categories (category_name) VALUES
    ('Hardware'),
    ('Software'),
    ('Operating System'),
    ('Networking'),
    ('Other');

CREATE TABLE posts (
    post_id     INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    title       VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    category_id INT NOT NULL,
    device_os   ENUM('Windows', 'macOS', 'Linux', 'Android', 'iOS', 'Other') NOT NULL,
    tags        VARCHAR(255) NULL,                -- comma separated, e.g. "wifi, driver"
    status      ENUM('Open', 'Resolved') NOT NULL DEFAULT 'Open',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(category_id)
) ENGINE=InnoDB;

-- Screenshots/photos attached to a post (up to 3 per post).
-- The files live in uploads/posts/; this table stores their file names.
CREATE TABLE post_images (
    image_id   INT AUTO_INCREMENT PRIMARY KEY,
    post_id    INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,             -- file name inside uploads/posts/
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE replies (
    reply_id    INT AUTO_INCREMENT PRIMARY KEY,
    post_id     INT NOT NULL,
    user_id     INT NOT NULL,
    content     TEXT NOT NULL,
    is_resolved TINYINT(1) NOT NULL DEFAULT 0,    -- 1 = the reply the author marked as resolved
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(post_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Upvotes and downvotes on replies. One active vote per student per reply.
-- Deleting a reply or a user removes their votes (CASCADE); the PHP code then
-- recalculates the affected authors' reputation so totals stay consistent.
CREATE TABLE reply_votes (
    vote_id    INT AUTO_INCREMENT PRIMARY KEY,
    reply_id   INT NOT NULL,
    user_id    INT NOT NULL,                      -- the student who voted
    vote_type  ENUM('upvote', 'downvote') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vote_user_reply (user_id, reply_id),   -- no duplicate votes
    KEY idx_votes_reply (reply_id),                     -- score of one reply
    KEY idx_votes_updated (updated_at),                 -- "recently helpful" window
    FOREIGN KEY (reply_id) REFERENCES replies(reply_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- A report points at either a post or a reply.
-- When the admin removes that content, the link becomes NULL but the report is kept.
CREATE TABLE reports (
    report_id         INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id       INT NOT NULL,
    reported_post_id  INT NULL,
    reported_reply_id INT NULL,
    reason            ENUM('Spam', 'Harassment', 'Inappropriate content', 'Misinformation', 'Other') NOT NULL,
    description       TEXT NULL,
    status            ENUM('Pending', 'Removed', 'Dismissed') NOT NULL DEFAULT 'Pending',
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (reported_post_id) REFERENCES posts(post_id) ON DELETE SET NULL,
    FOREIGN KEY (reported_reply_id) REFERENCES replies(reply_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Direct messages. A conversation is all messages between the same two users.
CREATE TABLE messages (
    message_id  INT AUTO_INCREMENT PRIMARY KEY,
    sender_id   INT NOT NULL,
    receiver_id INT NOT NULL,
    content     TEXT NULL,                        -- can be empty if only an image is sent
    image_path  VARCHAR(255) NULL,                -- file name inside uploads/messages/
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    -- "Delete chat" only hides the chat for the student who deleted it.
    -- When both sides have deleted a message, the row itself is removed.
    sender_deleted   TINYINT(1) NOT NULL DEFAULT 0,
    receiver_deleted TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE meetup_requests (
    meetup_id         INT AUTO_INCREMENT PRIMARY KEY,
    requester_id      INT NOT NULL,
    recipient_id      INT NOT NULL,
    proposed_datetime DATETIME NOT NULL,
    location          VARCHAR(150) NOT NULL,
    message           TEXT NULL,
    status            ENUM('Pending', 'Accepted', 'Rejected') NOT NULL DEFAULT 'Pending',
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- changes automatically when the status changes; live updates use it to find changed requests
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,                 -- who receives the notification
    type            ENUM('message', 'meetup_request', 'meetup_accepted', 'meetup_rejected', 'reply') NOT NULL,
    message         VARCHAR(255) NOT NULL,        -- text shown in the dropdown
    link            VARCHAR(255) NOT NULL,        -- page opened when clicked
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;


-- =====================================================================
-- MOCK DATA
-- Times are relative to when you import (NOW() - INTERVAL ... HOUR),
-- so the demo always looks recent. Malaysia time, same as config/db.php.
-- =====================================================================
SET time_zone = '+08:00';

-- ---------- Users ----------
-- Students: 1-12 (password stud123). Staff: 13-15 (password admin123).
-- Login IDs are stored in lower case; the login page accepts upper or lower case.
INSERT INTO users (user_id, username, login_id, password, role, bio) VALUES
    (1, 'Danial_Fahimy', 'mc240930202', '$2y$10$pEnBMiu0ndPKF9n8vYAmOOp9aXS248WAFIxy7AwTc/Pdjx.f5zELW', 'student', 'Diploma in IT student. I enjoy fixing Windows laptops and sorting out home WiFi for friends.'),
    (2, 'Aron_Paul', 'mc240934817', '$2y$10$99pbxedU2el6Yo5srEIIcORyDL1RuRVQUJYkqhnbPKdMwrL9Lz4Ba', 'student', 'Year 2 Diploma in IT. Into Linux, the terminal and small Python scripts.'),
    (3, 'Mei_Liu', 'mc241022096', '$2y$10$eGGuYvf6e5882rdmeQ6UveqoAc/RqQPBbRtwE6Dyt0VWk4kyogSk2', 'student', 'Graphic design student on a MacBook every day. Still learning the technical side.'),
    (4, 'Arul_Subramanian', 'mc250117351', '$2y$10$LMST6rR0XfLcIlM5g41EKelzpvSTHsVgBjXaVG.kkfgkNqF91ZMU2', 'student', 'Self-taught PC builder. Ask me about RAM, SSD upgrades and noisy fans.'),
    (5, 'Siti_Farah', 'mc240930638', '$2y$10$upC/PAb2DoJJk1Sr8GsZkuhwDo4YZqGtImAXwYZiZd/Lklp6.ULMK', 'student', 'Computer Science student and Android user. Happy to help with phones and hotspots.'),
    (6, 'Jason_Lim', 'mc241025523', '$2y$10$0sqhpL87jWdnT.Qhag3K1.9vFjWBaK69YmIqYjbms8x/dmaFjF8.i', 'student', 'Gamer and part-time technician at a computer shop on weekends.'),
    (7, 'Nur_Aisyah', 'mc250119164', '$2y$10$0LZ6PqJA8PkdPdOmEeJud.Nhp.63i5w3RhTM9BJQktq9SFfA2SKVe', 'student', 'First year student, learning how to take care of my own laptop.'),
    (8, 'Kevin_Raj', 'mc240933742', '$2y$10$O6CRPMEPM7Br3r8O1RkxzO0IoSV8Ic/wWmzwZHGQE382OxMBqh8Aq', 'student', 'Networking enthusiast, currently studying for a CCNA certificate.'),
    (9, 'Hafiz_Rahman', 'mc241026085', '$2y$10$U86J2RAsd8cR.EN.CGh7NugeiyAKtCDnemTHFKElR992GSuxRw2OS', 'student', 'Software engineering student. Mostly Python and Java, sometimes broken virtual environments.'),
    (10, 'Priya_Nair', 'mc250111479', '$2y$10$WZV7CCMeJHk8NoXYAPJ02unGydSsYSb7Nw3mIM1yhQZQ9ZCXYwBmm', 'student', 'Business student who somehow keeps breaking her printer settings.'),
    (11, 'Wei_Jie_Tan', 'mc240938230', '$2y$10$CPisF6G7z406NLzoGydlnuZ833rEveNE0hb4zTJEcnXaLkvP3tFr6', 'student', 'Cybersecurity club member. Please keep your software updated.'),
    (12, 'Amirah_Zulkifli', 'mc241022957', '$2y$10$aA5.9QzlebSxRFQkBOBtTeCbiVdWpkglgYrl2SwjP9abLCJQ6/oQ2', 'student', 'Multimedia student editing videos on a Windows laptop.'),
    (13, 'Admin_Farah', 'sd234792', '$2y$10$y1RrvacQyw.WU907.CiEp.zP/kY1py/tTC15lIaGZcfCfJVdxz/BW', 'admin', 'University IT staff. Reviews reports on RoomiePatch.'),
    (14, 'Admin_Rutheshwarar', 'sd231058', '$2y$10$tIoeduK87Rs1f67mNKXFv.jBS9K6nEWqifWks.v8keT5EUtMQ.SuC', 'admin', 'University staff account for community moderation.'),
    (15, 'Admin_Nadia', 'sd238417', '$2y$10$eLtA2wxqwx5CTwOAI8IhsebzaBBqgPT6T1VOrzOdFROrvWhatWa7K', 'admin', 'Student affairs staff. Monitors meetup safety.');

-- ---------- Posts ----------
-- Categories: 1 Hardware, 2 Software, 3 Operating System, 4 Networking, 5 Other
-- 19 posts: 8 Resolved, 11 Open (two of them with no replies yet).
INSERT INTO posts (post_id, user_id, title, description, category_id, device_os, tags, status, created_at) VALUES
    (1, 7, 'Laptop keeps disconnecting from WiFi every few minutes',
        'My laptop connects to WiFi fine, but every 5 to 10 minutes it drops the connection and reconnects. My phone on the same network has no problem. I already restarted the router.',
        4, 'Windows', 'wifi, driver, laptop', 'Resolved', NOW() - INTERVAL 480 HOUR),
    (2, 3, 'Homebrew install fails with permission denied',
        'I am trying to install Homebrew for my programming class. The install script stops halfway with a permission denied error on /usr/local. What should I check?',
        2, 'macOS', 'homebrew, terminal, permissions', 'Resolved', NOW() - INTERVAL 430 HOUR),
    (3, 1, 'Is it worth upgrading my old laptop to an SSD?',
        'My laptop is about 5 years old with a 1TB hard drive. It takes almost 3 minutes to boot. Would an SSD make a real difference, and is it hard to install myself?',
        1, 'Windows', 'ssd, upgrade, slow boot', 'Resolved', NOW() - INTERVAL 400 HOUR),
    (4, 5, 'Phone storage still full after deleting photos',
        'I deleted around 2GB of photos but my phone still says storage is almost full. Where did the space go?',
        3, 'Android', 'storage, photos', 'Resolved', NOW() - INTERVAL 360 HOUR),
    (5, 8, 'Dual boot Ubuntu not showing in boot menu',
        'I installed Ubuntu next to Windows 11 but the computer boots straight into Windows. The Ubuntu option never appears.',
        3, 'Linux', 'dual boot, ubuntu, grub', 'Open', NOW() - INTERVAL 330 HOUR),
    (6, 10, 'Printer shows offline even though it is switched on',
        'My printer is on and connected by USB, but Windows says it is offline and every print job just sits in the queue.',
        1, 'Windows', 'printer, offline, usb', 'Resolved', NOW() - INTERVAL 300 HOUR),
    (7, 6, 'Game stutters even though the FPS counter shows 144',
        'The FPS counter says I am getting around 144 FPS, but the game still feels choppy and stutters every few seconds, especially when turning quickly.',
        1, 'Windows', 'gaming, stutter, gpu', 'Open', NOW() - INTERVAL 260 HOUR),
    (8, 9, 'pip install says "externally managed environment"',
        'On my Ubuntu laptop, pip install now fails with an error about an externally managed environment. It used to work last semester. How do I install packages for my assignment?',
        2, 'Linux', 'python, pip, venv', 'Resolved', NOW() - INTERVAL 230 HOUR),
    (9, 12, 'Premiere Pro crashes when exporting 1080p video',
        'Every time I export a 1080p video in Premiere Pro, it gets to around 40 percent and then the whole program closes without any error message.',
        2, 'Windows', 'premiere, export, crash', 'Open', NOW() - INTERVAL 200 HOUR),
    (10, 1, 'Windows Update stuck at 0% for hours',
        'Windows Update has been stuck at 0% downloading for most of the day. I restarted twice already. Is there a way to fix it without reinstalling Windows?',
        3, 'Windows', 'windows update, stuck', 'Open', NOW() - INTERVAL 170 HOUR),
    (11, 4, 'Laptop fan very loud even when idle',
        'My laptop fan runs at full speed even when I only have a browser open. The laptop is not very hot to touch. It started after a recent update.',
        1, 'Windows', 'fan, overheating, noise', 'Resolved', NOW() - INTERVAL 150 HOUR),
    (12, 11, 'Got an email saying my university account will be suspended',
        'I received an email saying my student account will be suspended in 24 hours unless I log in through a link and confirm my password. Is this real?',
        5, 'Other', 'phishing, email, security', 'Resolved', NOW() - INTERVAL 120 HOUR),
    (13, 3, 'MacBook battery drains fast after macOS update',
        'Since updating macOS last week, my battery goes from 100 to 20 percent in about three hours. Before the update it lasted most of the day.',
        3, 'macOS', 'battery, macos update', 'Open', NOW() - INTERVAL 96 HOUR),
    (14, 2, 'SSH to my Raspberry Pi says connection refused',
        'I can ping my Raspberry Pi on the hostel network, but ssh pi@ the IP address says connection refused. What am I missing?',
        4, 'Linux', 'ssh, raspberry pi', 'Open', NOW() - INTERVAL 72 HOUR),
    (15, 5, 'Hotspot from my phone works on laptop but not on tablet',
        'My phone hotspot works fine on my laptop, but my tablet cannot see it in the WiFi list at all.',
        4, 'Android', 'hotspot, tablet', 'Open', NOW() - INTERVAL 50 HOUR),
    (16, 7, 'Accidentally deleted a folder, can I recover it?',
        'I deleted a folder with my assignment drafts using Shift and Delete. Is there any way to get it back?',
        3, 'Windows', 'recovery, recycle bin, files', 'Open', NOW() - INTERVAL 30 HOUR),
    (17, 9, 'iPhone not showing up in Windows File Explorer',
        'I want to copy photos from my iPhone to my laptop, but the iPhone does not appear in File Explorer when I plug it in.',
        3, 'iOS', 'iphone, usb, photos', 'Open', NOW() - INTERVAL 20 HOUR),
    (18, 1, 'Which VPN setting should I use for the campus WiFi?',
        'The campus WiFi blocks some websites I need for research. Is there a VPN setting the university recommends, or should I avoid using a VPN on campus?',
        4, 'Windows', 'vpn, campus wifi', 'Open', NOW() - INTERVAL 8 HOUR),
    (19, 10, 'Excel file says it is locked for editing by another user',
        'I am the only person using this Excel file on my laptop, but it says the file is locked for editing by another user. I cannot save my changes.',
        2, 'Windows', 'excel, office, locked file', 'Open', NOW() - INTERVAL 3 HOUR);

-- ---------- Replies ----------
-- is_resolved = 1 marks the reply the post author chose as the solution.
INSERT INTO replies (reply_id, post_id, user_id, content, is_resolved, created_at) VALUES
    (1, 1, 8, 'Try forgetting the network and connecting again. Also check whether your router is on the 2.4GHz or 5GHz band.', 0, NOW() - INTERVAL 478 HOUR),
    (2, 1, 1, 'Open Device Manager, find your wireless adapter, go to Power Management and untick "Allow the computer to turn off this device to save power". Windows often switches it off to save battery.', 1, NOW() - INTERVAL 476 HOUR),
    (3, 1, 7, 'That fixed it. No disconnects for two days now. Thank you!', 0, NOW() - INTERVAL 430 HOUR),

    (4, 2, 2, 'Newer Macs with Apple chips install Homebrew in /opt/homebrew, not /usr/local. Use the latest install command from the official Homebrew website.', 1, NOW() - INTERVAL 428 HOUR),
    (5, 2, 9, 'Also make sure your Mac user account is an administrator, otherwise sudo will fail.', 0, NOW() - INTERVAL 425 HOUR),

    (6, 3, 4, 'Yes, it is the biggest upgrade you can do for an old laptop. Check first whether it takes a 2.5 inch SATA drive or an M.2 drive.', 1, NOW() - INTERVAL 398 HOUR),
    (7, 3, 6, 'Use the SSD maker''s free cloning tool to copy Windows across, so you do not need to reinstall everything.', 0, NOW() - INTERVAL 396 HOUR),
    (8, 3, 1, 'Installed a 500GB SATA SSD and it boots in about 15 seconds now. Thanks both!', 0, NOW() - INTERVAL 300 HOUR),

    (9, 4, 8, 'Check whether WhatsApp is keeping its own copies of the photos in its media folder.', 0, NOW() - INTERVAL 355 HOUR),
    (10, 4, 11, 'Deleted photos stay in the Recently Deleted or Trash album for 30 days. Empty it in your gallery app and the space comes back.', 1, NOW() - INTERVAL 350 HOUR),

    (11, 5, 2, 'Your PC is probably booting straight to Windows Boot Manager. Go into the BIOS and move "ubuntu" above Windows in the boot order.', 0, NOW() - INTERVAL 320 HOUR),
    (12, 5, 9, 'Also turn off Fast Startup in Windows. It can stop the GRUB menu from showing.', 0, NOW() - INTERVAL 310 HOUR),
    (13, 5, 8, 'I do not see an "ubuntu" entry in the BIOS at all. Does that mean the install failed?', 0, NOW() - INTERVAL 300 HOUR),

    (14, 6, 6, 'Open Settings, go to Printers, click your printer and untick "Use Printer Offline".', 1, NOW() - INTERVAL 296 HOUR),
    (15, 6, 1, 'If that does not work, restart the Print Spooler service from the Services app.', 0, NOW() - INTERVAL 290 HOUR),

    (16, 7, 4, 'High FPS with stutter is usually frame time spikes. Check whether your RAM is running in single channel.', 0, NOW() - INTERVAL 250 HOUR),
    (17, 7, 11, 'Turn off overlays like Discord and Xbox Game Bar and test again.', 0, NOW() - INTERVAL 240 HOUR),

    (18, 8, 2, 'Newer Ubuntu versions block system-wide pip installs. Create a virtual environment with python3 -m venv .venv, activate it with source .venv/bin/activate, then pip install inside it.', 1, NOW() - INTERVAL 228 HOUR),

    (19, 9, 6, 'Try turning off hardware encoding in the export settings to see if the graphics driver is the problem.', 0, NOW() - INTERVAL 195 HOUR),
    (20, 9, 1, 'Also update your graphics driver directly from NVIDIA or AMD, not through Windows Update.', 0, NOW() - INTERVAL 190 HOUR),
    (21, 9, 6, 'Just delete the System32 folder, it frees up space and makes exports faster.', 0, NOW() - INTERVAL 185 HOUR),

    (22, 10, 11, 'Run the Windows Update Troubleshooter first. If it is still stuck, restart and leave it for an hour. Sometimes 0% is just very slow.', 0, NOW() - INTERVAL 165 HOUR),
    (23, 10, 8, 'You can also reset the update components: stop the wuauserv and bits services, rename the SoftwareDistribution folder, then start the services again.', 0, NOW() - INTERVAL 160 HOUR),
    (24, 10, 5, 'Make sure you have at least 20GB free on the C drive. Updates can fail quietly when the disk is nearly full.', 0, NOW() - INTERVAL 150 HOUR),

    (25, 11, 1, 'Blow out the vents with compressed air and check Task Manager for anything using CPU in the background.', 0, NOW() - INTERVAL 148 HOUR),
    (26, 11, 6, 'Install the laptop maker''s control app and switch the fan profile to Quiet. Mine was stuck on Performance mode after an update.', 1, NOW() - INTERVAL 145 HOUR),

    (27, 12, 5, 'That is a phishing email. Real university emails never ask for your password. Do not click the link and report it to the IT helpdesk.', 1, NOW() - INTERVAL 118 HOUR),
    (28, 12, 9, 'Check the sender address carefully. It is usually a fake domain that looks similar to the university one.', 0, NOW() - INTERVAL 115 HOUR),

    (29, 13, 4, 'Go to Settings, then Battery, and see which app uses the most. After a big update, Spotlight re-indexes your files for a day or two.', 0, NOW() - INTERVAL 90 HOUR),
    (30, 13, 3, 'Spotlight is at the top of the list. I will leave it charging overnight and see if it settles down.', 0, NOW() - INTERVAL 80 HOUR),

    (31, 14, 8, 'Connection refused usually means the SSH service is not running. Enable it with sudo raspi-config under Interface Options.', 0, NOW() - INTERVAL 70 HOUR),

    (32, 15, 1, 'Some tablets only support 2.4GHz. Set your phone hotspot band to 2.4GHz and try again.', 0, NOW() - INTERVAL 45 HOUR),
    (33, 15, 8, 'Also check whether the hotspot has a device limit or a MAC filter turned on.', 0, NOW() - INTERVAL 40 HOUR),

    (34, 16, 9, 'Check the Recycle Bin first. Shift and Delete skips it, so if it is not there, right-click the parent folder and look under Previous Versions.', 0, NOW() - INTERVAL 28 HOUR),
    (35, 16, 12, 'Stop saving new files to that drive until you recover it, otherwise the deleted data can be overwritten.', 0, NOW() - INTERVAL 25 HOUR),
    (36, 16, 11, 'Windows File Recovery from the Microsoft Store can help if the other methods do not work.', 0, NOW() - INTERVAL 20 HOUR),

    (37, 17, 3, 'Unlock the iPhone and tap Trust when it asks whether to trust this computer. It will not show up until you do.', 0, NOW() - INTERVAL 18 HOUR);

-- ---------- Reply votes ----------
-- (reply, voter, type, when). Nobody votes on their own reply.
-- Votes older than 14 days count for lifetime reputation only;
-- newer ones also decide the "Recently Helpful / Not Helpful" label.
INSERT INTO reply_votes (reply_id, user_id, vote_type, created_at, updated_at) VALUES
    -- Danial_Fahimy (1)
    (2, 7, 'upvote', NOW() - INTERVAL 470 HOUR, NOW() - INTERVAL 470 HOUR),
    (2, 8, 'upvote', NOW() - INTERVAL 460 HOUR, NOW() - INTERVAL 460 HOUR),
    (2, 5, 'upvote', NOW() - INTERVAL 440 HOUR, NOW() - INTERVAL 440 HOUR),
    (2, 3, 'upvote', NOW() - INTERVAL 420 HOUR, NOW() - INTERVAL 420 HOUR),
    (2, 11, 'upvote', NOW() - INTERVAL 400 HOUR, NOW() - INTERVAL 400 HOUR),
    (15, 10, 'upvote', NOW() - INTERVAL 285 HOUR, NOW() - INTERVAL 285 HOUR),
    (15, 6, 'upvote', NOW() - INTERVAL 280 HOUR, NOW() - INTERVAL 280 HOUR),
    (20, 12, 'upvote', NOW() - INTERVAL 185 HOUR, NOW() - INTERVAL 185 HOUR),
    (20, 9, 'upvote', NOW() - INTERVAL 180 HOUR, NOW() - INTERVAL 180 HOUR),
    (25, 4, 'upvote', NOW() - INTERVAL 146 HOUR, NOW() - INTERVAL 146 HOUR),
    (25, 11, 'upvote', NOW() - INTERVAL 140 HOUR, NOW() - INTERVAL 140 HOUR),
    (32, 5, 'upvote', NOW() - INTERVAL 44 HOUR, NOW() - INTERVAL 44 HOUR),
    (32, 8, 'upvote', NOW() - INTERVAL 40 HOUR, NOW() - INTERVAL 40 HOUR),
    (32, 2, 'upvote', NOW() - INTERVAL 30 HOUR, NOW() - INTERVAL 30 HOUR),
    -- Aron_Paul (2)
    (4, 3, 'upvote', NOW() - INTERVAL 425 HOUR, NOW() - INTERVAL 425 HOUR),
    (4, 9, 'upvote', NOW() - INTERVAL 420 HOUR, NOW() - INTERVAL 420 HOUR),
    (4, 1, 'upvote', NOW() - INTERVAL 410 HOUR, NOW() - INTERVAL 410 HOUR),
    (11, 8, 'upvote', NOW() - INTERVAL 318 HOUR, NOW() - INTERVAL 318 HOUR),
    (18, 9, 'upvote', NOW() - INTERVAL 225 HOUR, NOW() - INTERVAL 225 HOUR),
    (18, 5, 'upvote', NOW() - INTERVAL 220 HOUR, NOW() - INTERVAL 220 HOUR),
    (18, 1, 'upvote', NOW() - INTERVAL 210 HOUR, NOW() - INTERVAL 210 HOUR),
    (18, 11, 'upvote', NOW() - INTERVAL 200 HOUR, NOW() - INTERVAL 200 HOUR),
    -- Arul_Subramanian (4)
    (6, 1, 'upvote', NOW() - INTERVAL 395 HOUR, NOW() - INTERVAL 395 HOUR),
    (6, 6, 'upvote', NOW() - INTERVAL 390 HOUR, NOW() - INTERVAL 390 HOUR),
    (6, 7, 'upvote', NOW() - INTERVAL 380 HOUR, NOW() - INTERVAL 380 HOUR),
    (6, 12, 'upvote', NOW() - INTERVAL 370 HOUR, NOW() - INTERVAL 370 HOUR),
    (6, 3, 'upvote', NOW() - INTERVAL 350 HOUR, NOW() - INTERVAL 350 HOUR),
    (16, 6, 'upvote', NOW() - INTERVAL 245 HOUR, NOW() - INTERVAL 245 HOUR),
    (16, 11, 'downvote', NOW() - INTERVAL 240 HOUR, NOW() - INTERVAL 240 HOUR),
    (29, 3, 'upvote', NOW() - INTERVAL 88 HOUR, NOW() - INTERVAL 88 HOUR),
    -- Siti_Farah (5)
    (24, 1, 'upvote', NOW() - INTERVAL 148 HOUR, NOW() - INTERVAL 148 HOUR),
    (24, 11, 'upvote', NOW() - INTERVAL 147 HOUR, NOW() - INTERVAL 147 HOUR),
    (24, 9, 'downvote', NOW() - INTERVAL 146 HOUR, NOW() - INTERVAL 146 HOUR),
    (27, 11, 'upvote', NOW() - INTERVAL 116 HOUR, NOW() - INTERVAL 116 HOUR),
    (27, 9, 'upvote', NOW() - INTERVAL 112 HOUR, NOW() - INTERVAL 112 HOUR),
    (27, 3, 'upvote', NOW() - INTERVAL 108 HOUR, NOW() - INTERVAL 108 HOUR),
    (27, 7, 'upvote', NOW() - INTERVAL 104 HOUR, NOW() - INTERVAL 104 HOUR),
    (27, 12, 'upvote', NOW() - INTERVAL 100 HOUR, NOW() - INTERVAL 100 HOUR),
    -- Jason_Lim (6): good older answers, but a harmful tip recently
    (7, 1, 'upvote', NOW() - INTERVAL 390 HOUR, NOW() - INTERVAL 390 HOUR),
    (7, 4, 'upvote', NOW() - INTERVAL 385 HOUR, NOW() - INTERVAL 385 HOUR),
    (7, 9, 'upvote', NOW() - INTERVAL 380 HOUR, NOW() - INTERVAL 380 HOUR),
    (7, 11, 'upvote', NOW() - INTERVAL 375 HOUR, NOW() - INTERVAL 375 HOUR),
    (7, 2, 'upvote', NOW() - INTERVAL 370 HOUR, NOW() - INTERVAL 370 HOUR),
    (7, 3, 'upvote', NOW() - INTERVAL 365 HOUR, NOW() - INTERVAL 365 HOUR),
    (7, 8, 'upvote', NOW() - INTERVAL 360 HOUR, NOW() - INTERVAL 360 HOUR),
    (14, 10, 'upvote', NOW() - INTERVAL 294 HOUR, NOW() - INTERVAL 294 HOUR),
    (14, 1, 'upvote', NOW() - INTERVAL 290 HOUR, NOW() - INTERVAL 290 HOUR),
    (19, 12, 'upvote', NOW() - INTERVAL 194 HOUR, NOW() - INTERVAL 194 HOUR),
    (26, 4, 'upvote', NOW() - INTERVAL 144 HOUR, NOW() - INTERVAL 144 HOUR),
    (26, 1, 'upvote', NOW() - INTERVAL 142 HOUR, NOW() - INTERVAL 142 HOUR),
    (21, 12, 'downvote', NOW() - INTERVAL 184 HOUR, NOW() - INTERVAL 184 HOUR),
    (21, 1, 'downvote', NOW() - INTERVAL 183 HOUR, NOW() - INTERVAL 183 HOUR),
    (21, 4, 'downvote', NOW() - INTERVAL 182 HOUR, NOW() - INTERVAL 182 HOUR),
    (21, 11, 'downvote', NOW() - INTERVAL 181 HOUR, NOW() - INTERVAL 181 HOUR),
    (21, 2, 'downvote', NOW() - INTERVAL 170 HOUR, NOW() - INTERVAL 170 HOUR),
    (21, 9, 'downvote', NOW() - INTERVAL 160 HOUR, NOW() - INTERVAL 160 HOUR),
    (21, 8, 'downvote', NOW() - INTERVAL 150 HOUR, NOW() - INTERVAL 150 HOUR),
    (21, 5, 'downvote', NOW() - INTERVAL 149 HOUR, NOW() - INTERVAL 149 HOUR),
    -- Kevin_Raj (8): mixed feedback lately
    (1, 5, 'upvote', NOW() - INTERVAL 470 HOUR, NOW() - INTERVAL 470 HOUR),
    (9, 5, 'downvote', NOW() - INTERVAL 354 HOUR, NOW() - INTERVAL 354 HOUR),
    (9, 3, 'upvote', NOW() - INTERVAL 352 HOUR, NOW() - INTERVAL 352 HOUR),
    (23, 1, 'upvote', NOW() - INTERVAL 158 HOUR, NOW() - INTERVAL 158 HOUR),
    (23, 9, 'downvote', NOW() - INTERVAL 155 HOUR, NOW() - INTERVAL 155 HOUR),
    (31, 2, 'upvote', NOW() - INTERVAL 69 HOUR, NOW() - INTERVAL 69 HOUR),
    (33, 5, 'downvote', NOW() - INTERVAL 39 HOUR, NOW() - INTERVAL 39 HOUR),
    (33, 10, 'downvote', NOW() - INTERVAL 38 HOUR, NOW() - INTERVAL 38 HOUR),
    -- Hafiz_Rahman (9)
    (5, 3, 'upvote', NOW() - INTERVAL 423 HOUR, NOW() - INTERVAL 423 HOUR),
    (12, 8, 'upvote', NOW() - INTERVAL 305 HOUR, NOW() - INTERVAL 305 HOUR),
    (28, 11, 'upvote', NOW() - INTERVAL 114 HOUR, NOW() - INTERVAL 114 HOUR),
    (34, 7, 'upvote', NOW() - INTERVAL 26 HOUR, NOW() - INTERVAL 26 HOUR),
    (34, 12, 'upvote', NOW() - INTERVAL 24 HOUR, NOW() - INTERVAL 24 HOUR),
    -- Wei_Jie_Tan (11)
    (10, 5, 'upvote', NOW() - INTERVAL 345 HOUR, NOW() - INTERVAL 345 HOUR),
    (10, 8, 'upvote', NOW() - INTERVAL 340 HOUR, NOW() - INTERVAL 340 HOUR),
    (17, 6, 'upvote', NOW() - INTERVAL 238 HOUR, NOW() - INTERVAL 238 HOUR),
    (22, 1, 'upvote', NOW() - INTERVAL 164 HOUR, NOW() - INTERVAL 164 HOUR),
    (22, 8, 'downvote', NOW() - INTERVAL 159 HOUR, NOW() - INTERVAL 159 HOUR),
    (36, 7, 'upvote', NOW() - INTERVAL 19 HOUR, NOW() - INTERVAL 19 HOUR),
    -- Mei_Liu (3) and Amirah_Zulkifli (12): too few recent votes for a label
    (37, 9, 'upvote', NOW() - INTERVAL 17 HOUR, NOW() - INTERVAL 17 HOUR),
    (35, 7, 'upvote', NOW() - INTERVAL 24 HOUR, NOW() - INTERVAL 24 HOUR);

-- Stored reputation must equal the votes above (upvotes minus downvotes on each user's replies)
UPDATE users u
SET u.reputation = (
    SELECT COALESCE(SUM(IF(v.vote_type = 'upvote', 1, -1)), 0)
    FROM reply_votes v
    JOIN replies r ON r.reply_id = v.reply_id
    WHERE r.user_id = u.user_id
);

-- ---------- Reports ----------
-- One report waiting for staff review (a harmful "tip" on post 9).
INSERT INTO reports (reporter_id, reported_post_id, reported_reply_id, reason, description, status, created_at) VALUES
    (12, NULL, 21, 'Misinformation', 'Deleting System32 would break Windows completely. Please remove this before someone tries it.', 'Pending', NOW() - INTERVAL 180 HOUR);

-- ---------- Meetup requests ----------
-- 2 Pending, 2 Accepted, 1 Rejected
INSERT INTO meetup_requests (meetup_id, requester_id, recipient_id, proposed_datetime, location, message, status, created_at, updated_at) VALUES
    (1, 3, 1, CURDATE() + INTERVAL 2 DAY + INTERVAL 14 HOUR, 'Library level 2, discussion room 3',
        'Could you help me check why my MacBook battery drains so fast? I will bring my charger.', 'Pending',
        NOW() - INTERVAL 6 HOUR, NOW() - INTERVAL 6 HOUR),
    (2, 1, 4, CURDATE() + INTERVAL 3 DAY + INTERVAL 10 HOUR + INTERVAL 30 MINUTE, 'Block B computer lab',
        'Can you show me how to clone my old hard drive to the new SSD?', 'Accepted',
        NOW() - INTERVAL 40 HOUR, NOW() - INTERVAL 36 HOUR),
    (3, 8, 2, CURDATE() + INTERVAL 1 DAY + INTERVAL 16 HOUR, 'Student centre, ground floor',
        'Need help fixing GRUB on my dual boot laptop.', 'Pending',
        NOW() - INTERVAL 10 HOUR, NOW() - INTERVAL 10 HOUR),
    (4, 5, 8, CURDATE() + INTERVAL 4 DAY + INTERVAL 11 HOUR, 'Cafeteria near the main hall',
        'Could you look at my phone hotspot settings with me?', 'Accepted',
        NOW() - INTERVAL 30 HOUR, NOW() - INTERVAL 26 HOUR),
    (5, 6, 12, CURDATE() + INTERVAL 1 DAY + INTERVAL 15 HOUR, 'Library level 1, near the printers',
        'Want me to look at the Premiere export crash in person?', 'Rejected',
        NOW() - INTERVAL 48 HOUR, NOW() - INTERVAL 44 HOUR);

-- ---------- Direct messages ----------
INSERT INTO messages (message_id, sender_id, receiver_id, content, is_read, created_at) VALUES
    -- Danial and Arul: arranging the SSD cloning meetup
    (1, 1, 4, 'Hi Arul, I saw your reply about SSDs. Do you have a SATA to USB cable I can borrow for cloning?', 1, NOW() - INTERVAL 42 HOUR),
    (2, 4, 1, 'Yes, I have one. Bring your laptop and the new SSD, cloning takes about 30 minutes.', 1, NOW() - INTERVAL 41 HOUR),
    (3, 1, 4, 'Great, I just sent you a meetup request for the Block B lab.', 1, NOW() - INTERVAL 40 HOUR),
    (4, 4, 1, 'Accepted. Remember to back up your important files first, just in case.', 0, NOW() - INTERVAL 36 HOUR),

    -- Mei and Danial: battery problem
    (5, 3, 1, 'Hi Danial, is it okay if we meet about my MacBook battery? I sent you a meetup request.', 1, NOW() - INTERVAL 6 HOUR),
    (6, 1, 3, 'Sure. Can you send me a screenshot of the Battery settings page first?', 1, NOW() - INTERVAL 5 HOUR),
    (7, 3, 1, 'Spotlight and Photos are using the most battery. Is that normal after an update?', 0, NOW() - INTERVAL 2 HOUR),

    -- Kevin and Siti: hotspot problem
    (8, 8, 5, 'About your hotspot post, did switching to 2.4GHz work?', 1, NOW() - INTERVAL 38 HOUR),
    (9, 5, 8, 'Not yet, the tablet still cannot see it. I will try your MAC filter idea.', 1, NOW() - INTERVAL 37 HOUR),
    (10, 8, 5, 'Also restart the phone after changing the band. Sometimes the setting does not apply straight away.', 1, NOW() - INTERVAL 36 HOUR),

    -- Hafiz and Aron: Python virtual environments
    (11, 9, 2, 'Thanks for the venv tip. Do I need to activate it every time I open a new terminal?', 1, NOW() - INTERVAL 220 HOUR),
    (12, 2, 9, 'Yes, every new terminal. VS Code can also pick the venv for you automatically.', 1, NOW() - INTERVAL 219 HOUR);

-- ---------- Notifications ----------
INSERT INTO notifications (user_id, type, message, link, is_read, created_at) VALUES
    -- Danial (main account): a mix of read and unread
    (1, 'reply', 'Arul_Subramanian replied to your question: Is it worth upgrading my old laptop to an SSD?', 'student/post.php?id=3#reply-6', 1, NOW() - INTERVAL 398 HOUR),
    (1, 'reply', 'Wei_Jie_Tan replied to your question: Windows Update stuck at 0% for hours', 'student/post.php?id=10#reply-22', 1, NOW() - INTERVAL 165 HOUR),
    (1, 'reply', 'Kevin_Raj replied to your question: Windows Update stuck at 0% for hours', 'student/post.php?id=10#reply-23', 1, NOW() - INTERVAL 160 HOUR),
    (1, 'reply', 'Siti_Farah replied to your question: Windows Update stuck at 0% for hours', 'student/post.php?id=10#reply-24', 0, NOW() - INTERVAL 150 HOUR),
    (1, 'meetup_accepted', 'Arul_Subramanian accepted your meetup request', 'student/meetups.php?tab=sent#meetup-2', 1, NOW() - INTERVAL 36 HOUR),
    (1, 'message', 'Arul_Subramanian sent you a message', 'student/messages.php?user=4', 0, NOW() - INTERVAL 36 HOUR),
    (1, 'meetup_request', 'Mei_Liu sent you a meetup request', 'student/meetups.php#meetup-1', 0, NOW() - INTERVAL 6 HOUR),
    (1, 'message', 'Mei_Liu sent you a message', 'student/messages.php?user=3', 0, NOW() - INTERVAL 2 HOUR),
    -- Other students
    (4, 'meetup_request', 'Danial_Fahimy sent you a meetup request', 'student/meetups.php#meetup-2', 1, NOW() - INTERVAL 40 HOUR),
    (2, 'meetup_request', 'Kevin_Raj sent you a meetup request', 'student/meetups.php#meetup-3', 0, NOW() - INTERVAL 10 HOUR),
    (5, 'meetup_accepted', 'Kevin_Raj accepted your meetup request', 'student/meetups.php?tab=sent#meetup-4', 0, NOW() - INTERVAL 26 HOUR),
    (6, 'meetup_rejected', 'Amirah_Zulkifli declined your meetup request', 'student/meetups.php?tab=sent#meetup-5', 0, NOW() - INTERVAL 44 HOUR),
    (8, 'reply', 'Aron_Paul replied to your question: Dual boot Ubuntu not showing in boot menu', 'student/post.php?id=5#reply-11', 1, NOW() - INTERVAL 320 HOUR),
    (8, 'reply', 'Hafiz_Rahman replied to your question: Dual boot Ubuntu not showing in boot menu', 'student/post.php?id=5#reply-12', 1, NOW() - INTERVAL 310 HOUR),
    (12, 'reply', 'Jason_Lim replied to your question: Premiere Pro crashes when exporting 1080p video', 'student/post.php?id=9#reply-19', 1, NOW() - INTERVAL 195 HOUR),
    (12, 'reply', 'Danial_Fahimy replied to your question: Premiere Pro crashes when exporting 1080p video', 'student/post.php?id=9#reply-20', 1, NOW() - INTERVAL 190 HOUR),
    (7, 'reply', 'Wei_Jie_Tan replied to your question: Accidentally deleted a folder, can I recover it?', 'student/post.php?id=16#reply-36', 0, NOW() - INTERVAL 20 HOUR),
    (9, 'reply', 'Mei_Liu replied to your question: iPhone not showing up in Windows File Explorer', 'student/post.php?id=17#reply-37', 0, NOW() - INTERVAL 18 HOUR);
