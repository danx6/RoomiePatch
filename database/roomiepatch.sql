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
-- A simulated few weeks of activity by 27 students and 3 staff, created by using the
-- platform itself. Times are relative to when you import (NOW() - INTERVAL ...),
-- so the demo always looks recent. Malaysia time, same as config/db.php.
-- =====================================================================
SET time_zone = '+08:00';

-- ---------- Users ----------
-- Students: password stud123. Staff (sd...): password admin123.
-- Login IDs are stored in lower case; the login page accepts upper or lower case.
-- Reputation is filled in from the votes further down.
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
    (15, 'Admin_Nadia', 'sd238417', '$2y$10$eLtA2wxqwx5CTwOAI8IhsebzaBBqgPT6T1VOrzOdFROrvWhatWa7K', 'admin', 'Student affairs staff. Monitors meetup safety.'),
    (16, 'Haziq_Iskandar', 'mc240937205', '$2y$10$GG6r3sX6MPbTNvEl0ZWkgeNHJSB1TthCR0mppaXvVka3V5mGsvLK6', 'student', 'Final year Diploma in IT. Fixes laptops for half the hostel, usually for free.'),
    (17, 'Chloe_Tan', 'mc241021846', '$2y$10$Xxh7fAdoDxBNYqLnl/003OZrMTBSRJzlyU2a8S3VNysNPXTJcz6wa', 'student', 'Networking and Linux fan. I run a small home lab on old office PCs.'),
    (18, 'Ravi_Kumar', 'mc250115390', '$2y$10$0U9GnOjlJCrGyfewx5mYUOSuzfFi9H1JMGc195Dvp34jpaZSCIlJ.', 'student', 'Part-time phone repair technician. Screens, batteries and charging ports.'),
    (19, 'Nurul_Izzah', 'mc240931168', '$2y$10$DSMiUqLio0nTCagw.gAdA.qcaKs/3mpJQpRCg7FsRMAfYqSMIHTfO', 'student', 'Accounting student. My laptop and I are friends again, thanks to this platform.'),
    (20, 'Daniel_Wong', 'mc241027734', '$2y$10$OcXrkIDRzlK8W8VcMmsZF.5hBSUgJ6I8Iq.d65LDj917fhrbmTUcm', 'student', 'Computer Science, year 2. Coffee in exchange for fixing your laptop is a fair trade.'),
    (21, 'Farah_Nadia', 'mc250118023', '$2y$10$vRAtpN5O1kp0QDQZJuEq4e2gCSHBWAvq1A5dGWTvIKzydpZThKLB.', 'student', 'Mass communication student. Mostly here because my MacBook keeps acting up.'),
    (22, 'Jun_Hao_Lim', 'mc240935591', '$2y$10$YKZznKRObjq8MesJozKQ.uqkFPsk.T7K.BcX/gS.3LsHt4UQN2cGu', 'student', 'Into PC gaming and building custom keyboards.'),
    (23, 'Kavitha_Rao', 'mc241024417', '$2y$10$kteda.HBlamMPK3SYwxBOuaxSTZ7aOy/2sz4nd1I5SIT3dH5ysGXi', 'student', 'Nursing student. Ask me about hospital and hostel WiFi problems.'),
    (24, 'Ahmad_Zaki', 'mc250112689', '$2y$10$kZhQLfes2djgLWkFbhXm0eMbK2rgvsWTtVnQjL.XkEdnOKnH9XXRq', 'student', 'Engineering student. Arduino and Raspberry Pi tinkerer.'),
    (25, 'Sarah_Ong', 'mc240939312', '$2y$10$Q4lTw9qH8iVQFogBAGx8wOIic/LccPWM5f/YwbWGLaJLTGGQNZyA.', 'student', NULL),
    (26, 'Muthu_Velu', 'mc241020578', '$2y$10$I2iikiGMBv4YiTehMGBg5OxleKIp4CU5T5UgN5Qm6LX2IKwSvO3Pe', 'student', 'Just here to ask friends for help now and then.'),
    (27, 'Aina_Sofea', 'mc250116744', '$2y$10$rY.LksaS4N7DC8DE8FEwyO5JEOeZW4b8jTfi3DEUkw/KaqPqwpuMO', 'student', 'Foundation student. First laptop, first problems.'),
    (28, 'Brandon_Lee', 'mc240932950', '$2y$10$1NSQYcERdkfDsZ4Zq5kTKePPv5M9iH2y729kwGrVoLu.4fcnngUJ2', 'student', 'Business student who uses Windows and Android.'),
    (29, 'Irfan_Hakim', 'mc241028361', '$2y$10$r45FLE2q0/CMavSobB/LV.vfAehiBvT.nvXzdwQmEJ/YhBYDy7HCW', 'student', 'Selling stuff. DM me.'),
    (30, 'Yasmin_Rahim', 'mc250110925', '$2y$10$UV/JwVW/wIujKnXtC2ulq.bpXomfEV9qQccKYq8AxzlIufTTIhg8q', 'student', NULL);

-- ---------- Posts ----------
-- Categories: 1 Hardware, 2 Software, 3 Operating System, 4 Networking, 5 Other
INSERT INTO posts (post_id, user_id, title, description, category_id, device_os, tags, status, created_at) VALUES
    (1, 7, 'Laptop keeps disconnecting from WiFi every few minutes',
        'My laptop connects to WiFi fine, but every 5 to 10 minutes it drops the connection and reconnects. My phone on the same network has no problem. I already restarted the router.',
        4, 'Windows', 'wifi, driver, laptop', 'Resolved', NOW() - INTERVAL 1805545 SECOND),
    (2, 3, 'Homebrew install fails with permission denied',
        'I am trying to install Homebrew for my programming class. The install script stops halfway with a permission denied error on /usr/local. What should I check?',
        2, 'macOS', 'homebrew, terminal, permissions', 'Resolved', NOW() - INTERVAL 1625545 SECOND),
    (3, 1, 'Is it worth upgrading my old laptop to an SSD?',
        'My laptop is about 5 years old with a 1TB hard drive. It takes almost 3 minutes to boot. Would an SSD make a real difference, and is it hard to install myself?',
        1, 'Windows', 'ssd, upgrade, slow boot', 'Resolved', NOW() - INTERVAL 1517545 SECOND),
    (4, 5, 'Phone storage still full after deleting photos',
        'I deleted around 2GB of photos but my phone still says storage is almost full. Where did the space go?',
        3, 'Android', 'storage, photos', 'Resolved', NOW() - INTERVAL 1373545 SECOND),
    (5, 8, 'Dual boot Ubuntu not showing in boot menu',
        'I installed Ubuntu next to Windows 11 but the computer boots straight into Windows. The Ubuntu option never appears.',
        3, 'Linux', 'dual boot, ubuntu, grub', 'Open', NOW() - INTERVAL 1265545 SECOND),
    (6, 10, 'Printer shows offline even though it is switched on',
        'My printer is on and connected by USB, but Windows says it is offline and every print job just sits in the queue.',
        1, 'Windows', 'printer, offline, usb', 'Resolved', NOW() - INTERVAL 1157545 SECOND),
    (7, 6, 'Game stutters even though the FPS counter shows 144',
        'The FPS counter says I am getting around 144 FPS, but the game still feels choppy and stutters every few seconds, especially when turning quickly.',
        1, 'Windows', 'gaming, stutter, gpu', 'Open', NOW() - INTERVAL 1013545 SECOND),
    (8, 9, 'pip install says \"externally managed environment\"',
        'On my Ubuntu laptop, pip install now fails with an error about an externally managed environment. It used to work last semester. How do I install packages for my assignment?',
        2, 'Linux', 'python, pip, venv', 'Resolved', NOW() - INTERVAL 905545 SECOND),
    (9, 12, 'Premiere Pro crashes when exporting 1080p video',
        'Every time I export a 1080p video in Premiere Pro, it gets to around 40 percent and then the whole program closes without any error message.',
        2, 'Windows', 'premiere, export, crash', 'Open', NOW() - INTERVAL 797545 SECOND),
    (10, 1, 'Windows Update stuck at 0% for hours',
        'Windows Update has been stuck at 0% downloading for most of the day. I restarted twice already. Is there a way to fix it without reinstalling Windows?',
        3, 'Windows', 'windows update, stuck', 'Open', NOW() - INTERVAL 689545 SECOND),
    (11, 4, 'Laptop fan very loud even when idle',
        'My laptop fan runs at full speed even when I only have a browser open. The laptop is not very hot to touch. It started after a recent update.',
        1, 'Windows', 'fan, overheating, noise', 'Resolved', NOW() - INTERVAL 617545 SECOND),
    (12, 11, 'Got an email saying my university account will be suspended',
        'I received an email saying my student account will be suspended in 24 hours unless I log in through a link and confirm my password. Is this real?',
        5, 'Other', 'phishing, email, security', 'Resolved', NOW() - INTERVAL 509545 SECOND),
    (13, 3, 'MacBook battery drains fast after macOS update',
        'Since updating macOS last week, my battery goes from 100 to 20 percent in about three hours. Before the update it lasted most of the day.',
        3, 'macOS', 'battery, macos update', 'Open', NOW() - INTERVAL 423145 SECOND),
    (14, 2, 'SSH to my Raspberry Pi says connection refused',
        'I can ping my Raspberry Pi on the hostel network, but ssh pi@ the IP address says connection refused. What am I missing?',
        4, 'Linux', 'ssh, raspberry pi', 'Open', NOW() - INTERVAL 336745 SECOND),
    (15, 5, 'Hotspot from my phone works on laptop but not on tablet',
        'My phone hotspot works fine on my laptop, but my tablet cannot see it in the WiFi list at all.',
        4, 'Android', 'hotspot, tablet', 'Open', NOW() - INTERVAL 257545 SECOND),
    (16, 7, 'Accidentally deleted a folder, can I recover it?',
        'I deleted a folder with my assignment drafts using Shift and Delete. Is there any way to get it back?',
        3, 'Windows', 'recovery, recycle bin, files', 'Open', NOW() - INTERVAL 185545 SECOND),
    (17, 9, 'iPhone not showing up in Windows File Explorer',
        'I want to copy photos from my iPhone to my laptop, but the iPhone does not appear in File Explorer when I plug it in.',
        3, 'iOS', 'iphone, usb, photos', 'Open', NOW() - INTERVAL 149545 SECOND),
    (18, 1, 'Which VPN setting should I use for the campus WiFi?',
        'The campus WiFi blocks some websites I need for research. Is there a VPN setting the university recommends, or should I avoid using a VPN on campus?',
        4, 'Windows', 'vpn, campus wifi', 'Open', NOW() - INTERVAL 106345 SECOND),
    (19, 10, 'Excel file says it is locked for editing by another user',
        'I am the only person using this Excel file on my laptop, but it says the file is locked for editing by another user. I cannot save my changes.',
        2, 'Windows', 'excel, office, locked file', 'Open', NOW() - INTERVAL 88345 SECOND),
    (20, 19, 'Laptop screen flickers when I move the hinge',
        'My laptop screen flickers and sometimes goes black when I open it past about 90 degrees. If I hold it at a certain angle it works fine. I attached a photo of what it looks like.',
        1, 'Windows', 'screen, hinge, display', 'Resolved', NOW() - INTERVAL 471906 SECOND),
    (21, 27, 'How do I check if my laptop is actually charging?',
        'The charging light is on but the battery percentage barely goes up, and sometimes it even drops while plugged in. Is my charger broken?',
        1, 'Windows', 'battery, charger', 'Resolved', NOW() - INTERVAL 470406 SECOND),
    (22, 23, 'Phone cannot open the hospital guest WiFi login page',
        'During my placement my phone connects to the hospital guest WiFi but the login page never appears, so I have no internet. Other nurses\' phones work.',
        4, 'Android', 'wifi, captive portal, login page', 'Resolved', NOW() - INTERVAL 468906 SECOND),
    (23, 28, 'Phone gets very hot while charging overnight',
        'My phone gets really hot when I charge it overnight, hot enough that it is uncomfortable to hold in the morning. Is this dangerous?',
        1, 'Android', 'charging, overheating, battery', 'Open', NOW() - INTERVAL 395106 SECOND),
    (25, 22, 'Mechanical keyboard types some letters twice',
        'My mechanical keyboard sometimes types the letter E and the space bar twice when I press them once. It started last week.',
        1, 'Windows', 'keyboard, double typing', 'Open', NOW() - INTERVAL 375906 SECOND),
    (26, 21, 'MacBook keyboard stopped working after I spilled tea on it',
        'I spilled some sweet tea on my MacBook keyboard. The laptop still turns on but several keys do not work and the trackpad clicks feel sticky.',
        1, 'macOS', 'liquid damage, keyboard, macbook', 'Open', NOW() - INTERVAL 241506 SECOND),
    (27, 24, 'Arduino board not showing up as a COM port',
        'I plugged my Arduino Nano clone into my laptop but the Arduino IDE does not show any COM port. The board\'s power light is on.',
        2, 'Windows', 'arduino, driver, usb', 'Resolved', NOW() - INTERVAL 240006 SECOND);

-- ---------- Post images ----------
-- The files are in uploads/posts/ (included in the repository).
INSERT INTO post_images (image_id, post_id, image_path) VALUES
    (1, 20, 'demo-hinge-photo.png');

-- ---------- Replies ----------
-- is_resolved = 1 marks the reply the post author chose as the solution.
INSERT INTO replies (reply_id, post_id, user_id, content, is_resolved, created_at) VALUES
    (1, 1, 8, 'Try forgetting the network and connecting again. Also check whether your router is on the 2.4GHz or 5GHz band.', 0, NOW() - INTERVAL 1798345 SECOND),
    (2, 1, 1, 'Open Device Manager, find your wireless adapter, go to Power Management and untick \"Allow the computer to turn off this device to save power\". Windows often switches it off to save battery.', 1, NOW() - INTERVAL 1791145 SECOND),
    (3, 1, 7, 'That fixed it. No disconnects for two days now. Thank you!', 0, NOW() - INTERVAL 1625545 SECOND),
    (4, 2, 2, 'Newer Macs with Apple chips install Homebrew in /opt/homebrew, not /usr/local. Use the latest install command from the official Homebrew website.', 1, NOW() - INTERVAL 1618345 SECOND),
    (5, 2, 9, 'Also make sure your Mac user account is an administrator, otherwise sudo will fail.', 0, NOW() - INTERVAL 1607545 SECOND),
    (6, 3, 4, 'Yes, it is the biggest upgrade you can do for an old laptop. Check first whether it takes a 2.5 inch SATA drive or an M.2 drive.', 1, NOW() - INTERVAL 1510345 SECOND),
    (7, 3, 6, 'Use the SSD maker\'s free cloning tool to copy Windows across, so you do not need to reinstall everything.', 0, NOW() - INTERVAL 1503145 SECOND),
    (8, 3, 1, 'Installed a 500GB SATA SSD and it boots in about 15 seconds now. Thanks both!', 0, NOW() - INTERVAL 1157545 SECOND),
    (9, 4, 8, 'Check whether WhatsApp is keeping its own copies of the photos in its media folder.', 0, NOW() - INTERVAL 1355545 SECOND),
    (10, 4, 11, 'Deleted photos stay in the Recently Deleted or Trash album for 30 days. Empty it in your gallery app and the space comes back.', 1, NOW() - INTERVAL 1337545 SECOND),
    (11, 5, 2, 'Your PC is probably booting straight to Windows Boot Manager. Go into the BIOS and move \"ubuntu\" above Windows in the boot order.', 0, NOW() - INTERVAL 1229545 SECOND),
    (12, 5, 9, 'Also turn off Fast Startup in Windows. It can stop the GRUB menu from showing.', 0, NOW() - INTERVAL 1193545 SECOND),
    (13, 5, 8, 'I do not see an \"ubuntu\" entry in the BIOS at all. Does that mean the install failed?', 0, NOW() - INTERVAL 1157545 SECOND),
    (14, 6, 6, 'Open Settings, go to Printers, click your printer and untick \"Use Printer Offline\".', 1, NOW() - INTERVAL 1143145 SECOND),
    (15, 6, 1, 'If that does not work, restart the Print Spooler service from the Services app.', 0, NOW() - INTERVAL 1121545 SECOND),
    (16, 7, 4, 'High FPS with stutter is usually frame time spikes. Check whether your RAM is running in single channel.', 0, NOW() - INTERVAL 977545 SECOND),
    (17, 7, 11, 'Turn off overlays like Discord and Xbox Game Bar and test again.', 0, NOW() - INTERVAL 941545 SECOND),
    (18, 8, 2, 'Newer Ubuntu versions block system-wide pip installs. Create a virtual environment with python3 -m venv .venv, activate it with source .venv/bin/activate, then pip install inside it.', 1, NOW() - INTERVAL 898345 SECOND),
    (19, 9, 6, 'Try turning off hardware encoding in the export settings to see if the graphics driver is the problem.', 0, NOW() - INTERVAL 779545 SECOND),
    (20, 9, 1, 'Also update your graphics driver directly from NVIDIA or AMD, not through Windows Update.', 0, NOW() - INTERVAL 761545 SECOND),
    (21, 9, 6, 'Just delete the System32 folder, it frees up space and makes exports faster.', 0, NOW() - INTERVAL 743545 SECOND),
    (22, 10, 11, 'Run the Windows Update Troubleshooter first. If it is still stuck, restart and leave it for an hour. Sometimes 0% is just very slow.', 0, NOW() - INTERVAL 671545 SECOND),
    (23, 10, 8, 'You can also reset the update components: stop the wuauserv and bits services, rename the SoftwareDistribution folder, then start the services again.', 0, NOW() - INTERVAL 653545 SECOND),
    (24, 10, 5, 'Make sure you have at least 20GB free on the C drive. Updates can fail quietly when the disk is nearly full.', 0, NOW() - INTERVAL 617545 SECOND),
    (25, 11, 1, 'Blow out the vents with compressed air and check Task Manager for anything using CPU in the background.', 0, NOW() - INTERVAL 610345 SECOND),
    (26, 11, 6, 'Install the laptop maker\'s control app and switch the fan profile to Quiet. Mine was stuck on Performance mode after an update.', 1, NOW() - INTERVAL 599545 SECOND),
    (27, 12, 5, 'That is a phishing email. Real university emails never ask for your password. Do not click the link and report it to the IT helpdesk.', 1, NOW() - INTERVAL 502345 SECOND),
    (28, 12, 9, 'Check the sender address carefully. It is usually a fake domain that looks similar to the university one.', 0, NOW() - INTERVAL 491545 SECOND),
    (29, 13, 4, 'Go to Settings, then Battery, and see which app uses the most. After a big update, Spotlight re-indexes your files for a day or two.', 0, NOW() - INTERVAL 401545 SECOND),
    (30, 13, 3, 'Spotlight is at the top of the list. I will leave it charging overnight and see if it settles down.', 0, NOW() - INTERVAL 365545 SECOND),
    (31, 14, 8, 'Connection refused usually means the SSH service is not running. Enable it with sudo raspi-config under Interface Options.', 0, NOW() - INTERVAL 329545 SECOND),
    (32, 15, 1, 'Some tablets only support 2.4GHz. Set your phone hotspot band to 2.4GHz and try again.', 0, NOW() - INTERVAL 239545 SECOND),
    (33, 15, 8, 'Also check whether the hotspot has a device limit or a MAC filter turned on.', 0, NOW() - INTERVAL 221545 SECOND),
    (34, 16, 9, 'Check the Recycle Bin first. Shift and Delete skips it, so if it is not there, right-click the parent folder and look under Previous Versions.', 0, NOW() - INTERVAL 178345 SECOND),
    (35, 16, 12, 'Stop saving new files to that drive until you recover it, otherwise the deleted data can be overwritten.', 0, NOW() - INTERVAL 167545 SECOND),
    (36, 16, 11, 'Windows File Recovery from the Microsoft Store can help if the other methods do not work.', 0, NOW() - INTERVAL 149545 SECOND),
    (37, 17, 3, 'Unlock the iPhone and tap Trust when it asks whether to trust this computer. It will not show up until you do.', 0, NOW() - INTERVAL 142345 SECOND),
    (38, 20, 16, 'That is almost always the display cable that runs through the hinge. It gets worn from opening and closing. Reseating it often fixes it, replacing it costs around RM40 to RM70.', 1, NOW() - INTERVAL 471906 SECOND),
    (39, 20, 18, 'Agree with Haziq. Before opening it, plug into an external monitor. If the external screen is fine, it is definitely the cable or the panel, not the graphics.', 0, NOW() - INTERVAL 469506 SECOND),
    (40, 20, 20, 'Could also be the backlight connector. If you buy the coffee I can open it up and check, it only takes about 15 minutes with the right screwdriver.', 0, NOW() - INTERVAL 467106 SECOND),
    (41, 22, 17, 'That login page is called a captive portal. Turn off Private DNS in Settings, then open a plain http site like neverssl.com in the browser and the login page should appear.', 1, NOW() - INTERVAL 464706 SECOND),
    (42, 22, 5, 'Also turn off any VPN or ad blocker app while you connect. They often hide the login page.', 0, NOW() - INTERVAL 462306 SECOND),
    (43, 23, 18, 'Some warmth is normal, very hot is not. Take the case off while charging, use the original charger, and do not charge it under your pillow. If the battery looks swollen, stop using it.', 0, NOW() - INTERVAL 395106 SECOND),
    (44, 23, 22, 'Just restart your phone, that fixes most things.', 0, NOW() - INTERVAL 392706 SECOND),
    (45, 23, 1, 'Check Settings > Battery for an app using power in the background while it charges. A stuck app can keep the phone working all night.', 0, NOW() - INTERVAL 390306 SECOND),
    (47, 25, 20, 'That is called key chatter. Blow out the switch with compressed air first. If it keeps happening, the switch is wearing out and needs replacing.', 0, NOW() - INTERVAL 375906 SECOND),
    (48, 25, 16, 'Some keyboard software has a debounce setting. Raising it a little can hide the problem until you replace the switch.', 0, NOW() - INTERVAL 373506 SECOND),
    (50, 21, 24, 'Check the wattage printed on the charger. If it is lower than the original one, the laptop can use power faster than it charges.', 0, NOW() - INTERVAL 299106 SECOND),
    (51, 21, 16, 'Run powercfg /batteryreport in Command Prompt. It shows the battery\'s design capacity versus what it holds now. If it is under about 50 percent, the battery is worn out.', 1, NOW() - INTERVAL 296706 SECOND),
    (52, 26, 18, 'Shut it down and keep it upside down in a tent shape. Sweet drinks leave sticky residue, so it usually needs proper cleaning with isopropyl alcohol inside. Do not use a hair dryer.', 0, NOW() - INTERVAL 241506 SECOND),
    (53, 26, 20, 'Back up your files as soon as possible in case it gets worse. Liquid damage can spread over a few days.', 0, NOW() - INTERVAL 239106 SECOND),
    (54, 27, 17, 'Most clones use a CH340 USB chip that needs its own driver. Install the CH340 driver, replug the board, and the COM port should appear.', 1, NOW() - INTERVAL 222306 SECOND),
    (55, 27, 16, 'Also try a different USB cable. Some cheap cables only carry power, not data.', 0, NOW() - INTERVAL 219906 SECOND),
    (56, 18, 17, 'Use the university network login first. Personal VPN apps are often blocked on campus WiFi and some break the login page.', 0, NOW() - INTERVAL 94945 SECOND),
    (57, 18, 11, 'Also check if your faculty provides remote access to journals through the library website. That avoids the VPN question completely.', 0, NOW() - INTERVAL 90745 SECOND);

-- ---------- Reply votes ----------
-- Nobody votes on their own reply. Votes from the last 14 days also decide
-- the "Recently Helpful / Recently Not Helpful" labels.
INSERT INTO reply_votes (vote_id, reply_id, user_id, vote_type, created_at, updated_at) VALUES
    (1, 2, 7, 'upvote', NOW() - INTERVAL 1694218 SECOND, NOW() - INTERVAL 1694218 SECOND),
    (2, 2, 8, 'upvote', NOW() - INTERVAL 1658218 SECOND, NOW() - INTERVAL 1658218 SECOND),
    (3, 2, 5, 'upvote', NOW() - INTERVAL 1586218 SECOND, NOW() - INTERVAL 1586218 SECOND),
    (4, 2, 3, 'upvote', NOW() - INTERVAL 1514218 SECOND, NOW() - INTERVAL 1514218 SECOND),
    (5, 2, 11, 'upvote', NOW() - INTERVAL 1442218 SECOND, NOW() - INTERVAL 1442218 SECOND),
    (6, 15, 10, 'upvote', NOW() - INTERVAL 1028218 SECOND, NOW() - INTERVAL 1028218 SECOND),
    (7, 15, 6, 'upvote', NOW() - INTERVAL 1010218 SECOND, NOW() - INTERVAL 1010218 SECOND),
    (8, 20, 12, 'upvote', NOW() - INTERVAL 668218 SECOND, NOW() - INTERVAL 668218 SECOND),
    (9, 20, 9, 'upvote', NOW() - INTERVAL 650218 SECOND, NOW() - INTERVAL 650218 SECOND),
    (10, 25, 4, 'upvote', NOW() - INTERVAL 527818 SECOND, NOW() - INTERVAL 527818 SECOND),
    (11, 25, 11, 'upvote', NOW() - INTERVAL 506218 SECOND, NOW() - INTERVAL 506218 SECOND),
    (12, 32, 5, 'upvote', NOW() - INTERVAL 160618 SECOND, NOW() - INTERVAL 160618 SECOND),
    (13, 32, 8, 'upvote', NOW() - INTERVAL 146218 SECOND, NOW() - INTERVAL 146218 SECOND),
    (14, 32, 2, 'upvote', NOW() - INTERVAL 110218 SECOND, NOW() - INTERVAL 110218 SECOND),
    (15, 4, 3, 'upvote', NOW() - INTERVAL 1532218 SECOND, NOW() - INTERVAL 1532218 SECOND),
    (16, 4, 9, 'upvote', NOW() - INTERVAL 1514218 SECOND, NOW() - INTERVAL 1514218 SECOND),
    (18, 11, 8, 'upvote', NOW() - INTERVAL 1147018 SECOND, NOW() - INTERVAL 1147018 SECOND),
    (19, 18, 9, 'upvote', NOW() - INTERVAL 812218 SECOND, NOW() - INTERVAL 812218 SECOND),
    (20, 18, 5, 'upvote', NOW() - INTERVAL 794218 SECOND, NOW() - INTERVAL 794218 SECOND),
    (21, 18, 1, 'upvote', NOW() - INTERVAL 758218 SECOND, NOW() - INTERVAL 758218 SECOND),
    (22, 18, 11, 'upvote', NOW() - INTERVAL 722218 SECOND, NOW() - INTERVAL 722218 SECOND),
    (23, 6, 1, 'upvote', NOW() - INTERVAL 1424218 SECOND, NOW() - INTERVAL 1424218 SECOND),
    (24, 6, 6, 'upvote', NOW() - INTERVAL 1406218 SECOND, NOW() - INTERVAL 1406218 SECOND),
    (25, 6, 7, 'upvote', NOW() - INTERVAL 1370218 SECOND, NOW() - INTERVAL 1370218 SECOND),
    (26, 6, 12, 'upvote', NOW() - INTERVAL 1334218 SECOND, NOW() - INTERVAL 1334218 SECOND),
    (27, 6, 3, 'upvote', NOW() - INTERVAL 1262218 SECOND, NOW() - INTERVAL 1262218 SECOND),
    (28, 16, 6, 'upvote', NOW() - INTERVAL 884218 SECOND, NOW() - INTERVAL 884218 SECOND),
    (29, 16, 11, 'downvote', NOW() - INTERVAL 866218 SECOND, NOW() - INTERVAL 866218 SECOND),
    (30, 29, 3, 'upvote', NOW() - INTERVAL 319018 SECOND, NOW() - INTERVAL 319018 SECOND),
    (32, 24, 11, 'upvote', NOW() - INTERVAL 531418 SECOND, NOW() - INTERVAL 531418 SECOND),
    (33, 24, 9, 'downvote', NOW() - INTERVAL 527818 SECOND, NOW() - INTERVAL 527818 SECOND),
    (34, 27, 11, 'upvote', NOW() - INTERVAL 419818 SECOND, NOW() - INTERVAL 419818 SECOND),
    (35, 27, 9, 'upvote', NOW() - INTERVAL 405418 SECOND, NOW() - INTERVAL 405418 SECOND),
    (36, 27, 3, 'upvote', NOW() - INTERVAL 391018 SECOND, NOW() - INTERVAL 391018 SECOND),
    (37, 27, 7, 'upvote', NOW() - INTERVAL 376618 SECOND, NOW() - INTERVAL 376618 SECOND),
    (38, 27, 12, 'upvote', NOW() - INTERVAL 362218 SECOND, NOW() - INTERVAL 362218 SECOND),
    (39, 7, 1, 'upvote', NOW() - INTERVAL 1406218 SECOND, NOW() - INTERVAL 1406218 SECOND),
    (40, 7, 4, 'upvote', NOW() - INTERVAL 1388218 SECOND, NOW() - INTERVAL 1388218 SECOND),
    (41, 7, 9, 'upvote', NOW() - INTERVAL 1370218 SECOND, NOW() - INTERVAL 1370218 SECOND),
    (42, 7, 11, 'upvote', NOW() - INTERVAL 1352218 SECOND, NOW() - INTERVAL 1352218 SECOND),
    (43, 7, 2, 'upvote', NOW() - INTERVAL 1334218 SECOND, NOW() - INTERVAL 1334218 SECOND),
    (44, 7, 3, 'upvote', NOW() - INTERVAL 1316218 SECOND, NOW() - INTERVAL 1316218 SECOND),
    (45, 7, 8, 'upvote', NOW() - INTERVAL 1298218 SECOND, NOW() - INTERVAL 1298218 SECOND),
    (46, 14, 10, 'upvote', NOW() - INTERVAL 1060618 SECOND, NOW() - INTERVAL 1060618 SECOND),
    (47, 14, 1, 'upvote', NOW() - INTERVAL 1046218 SECOND, NOW() - INTERVAL 1046218 SECOND),
    (48, 19, 12, 'upvote', NOW() - INTERVAL 700618 SECOND, NOW() - INTERVAL 700618 SECOND),
    (49, 26, 4, 'upvote', NOW() - INTERVAL 520618 SECOND, NOW() - INTERVAL 520618 SECOND),
    (50, 26, 1, 'upvote', NOW() - INTERVAL 513418 SECOND, NOW() - INTERVAL 513418 SECOND),
    (51, 21, 12, 'downvote', NOW() - INTERVAL 664618 SECOND, NOW() - INTERVAL 664618 SECOND),
    (52, 21, 1, 'downvote', NOW() - INTERVAL 661018 SECOND, NOW() - INTERVAL 661018 SECOND),
    (53, 21, 4, 'downvote', NOW() - INTERVAL 657418 SECOND, NOW() - INTERVAL 657418 SECOND),
    (54, 21, 11, 'downvote', NOW() - INTERVAL 653818 SECOND, NOW() - INTERVAL 653818 SECOND),
    (55, 21, 2, 'downvote', NOW() - INTERVAL 614218 SECOND, NOW() - INTERVAL 614218 SECOND),
    (56, 21, 9, 'downvote', NOW() - INTERVAL 578218 SECOND, NOW() - INTERVAL 578218 SECOND),
    (57, 21, 8, 'downvote', NOW() - INTERVAL 542218 SECOND, NOW() - INTERVAL 542218 SECOND),
    (58, 21, 5, 'downvote', NOW() - INTERVAL 538618 SECOND, NOW() - INTERVAL 538618 SECOND),
    (59, 1, 5, 'upvote', NOW() - INTERVAL 1694218 SECOND, NOW() - INTERVAL 1694218 SECOND),
    (60, 9, 5, 'downvote', NOW() - INTERVAL 1276618 SECOND, NOW() - INTERVAL 1276618 SECOND),
    (61, 9, 3, 'upvote', NOW() - INTERVAL 1269418 SECOND, NOW() - INTERVAL 1269418 SECOND),
    (63, 23, 9, 'downvote', NOW() - INTERVAL 560218 SECOND, NOW() - INTERVAL 560218 SECOND),
    (64, 31, 2, 'upvote', NOW() - INTERVAL 250618 SECOND, NOW() - INTERVAL 250618 SECOND),
    (65, 33, 5, 'downvote', NOW() - INTERVAL 142618 SECOND, NOW() - INTERVAL 142618 SECOND),
    (66, 33, 10, 'downvote', NOW() - INTERVAL 139018 SECOND, NOW() - INTERVAL 139018 SECOND),
    (67, 5, 3, 'upvote', NOW() - INTERVAL 1525018 SECOND, NOW() - INTERVAL 1525018 SECOND),
    (68, 12, 8, 'upvote', NOW() - INTERVAL 1100218 SECOND, NOW() - INTERVAL 1100218 SECOND),
    (69, 28, 11, 'upvote', NOW() - INTERVAL 412618 SECOND, NOW() - INTERVAL 412618 SECOND),
    (70, 34, 7, 'upvote', NOW() - INTERVAL 95818 SECOND, NOW() - INTERVAL 95818 SECOND),
    (71, 34, 12, 'upvote', NOW() - INTERVAL 88618 SECOND, NOW() - INTERVAL 88618 SECOND),
    (72, 10, 5, 'upvote', NOW() - INTERVAL 1244218 SECOND, NOW() - INTERVAL 1244218 SECOND),
    (73, 10, 8, 'upvote', NOW() - INTERVAL 1226218 SECOND, NOW() - INTERVAL 1226218 SECOND),
    (74, 17, 6, 'upvote', NOW() - INTERVAL 859018 SECOND, NOW() - INTERVAL 859018 SECOND),
    (76, 22, 8, 'downvote', NOW() - INTERVAL 574618 SECOND, NOW() - INTERVAL 574618 SECOND),
    (77, 36, 7, 'upvote', NOW() - INTERVAL 70618 SECOND, NOW() - INTERVAL 70618 SECOND),
    (78, 37, 9, 'upvote', NOW() - INTERVAL 63418 SECOND, NOW() - INTERVAL 63418 SECOND),
    (79, 35, 7, 'upvote', NOW() - INTERVAL 88618 SECOND, NOW() - INTERVAL 88618 SECOND),
    (80, 35, 1, 'upvote', NOW() - INTERVAL 1702 SECOND, NOW() - INTERVAL 1695 SECOND),
    (91, 24, 1, 'upvote', NOW() - INTERVAL 973 SECOND, NOW() - INTERVAL 973 SECOND),
    (92, 22, 1, 'downvote', NOW() - INTERVAL 963 SECOND, NOW() - INTERVAL 963 SECOND),
    (94, 4, 1, 'upvote', NOW() - INTERVAL 845 SECOND, NOW() - INTERVAL 842 SECOND),
    (95, 38, 17, 'upvote', NOW() - INTERVAL 471906 SECOND, NOW() - INTERVAL 471906 SECOND),
    (96, 38, 19, 'upvote', NOW() - INTERVAL 471906 SECOND, NOW() - INTERVAL 471906 SECOND),
    (97, 39, 19, 'upvote', NOW() - INTERVAL 465366 SECOND, NOW() - INTERVAL 465366 SECOND),
    (98, 38, 1, 'upvote', NOW() - INTERVAL 471906 SECOND, NOW() - INTERVAL 471906 SECOND),
    (99, 41, 23, 'upvote', NOW() - INTERVAL 463266 SECOND, NOW() - INTERVAL 463266 SECOND),
    (100, 41, 16, 'upvote', NOW() - INTERVAL 452706 SECOND, NOW() - INTERVAL 452706 SECOND),
    (101, 42, 20, 'upvote', NOW() - INTERVAL 452706 SECOND, NOW() - INTERVAL 452706 SECOND),
    (102, 43, 16, 'upvote', NOW() - INTERVAL 395106 SECOND, NOW() - INTERVAL 395106 SECOND),
    (103, 43, 17, 'upvote', NOW() - INTERVAL 395106 SECOND, NOW() - INTERVAL 395106 SECOND),
    (104, 43, 28, 'upvote', NOW() - INTERVAL 395106 SECOND, NOW() - INTERVAL 395106 SECOND),
    (105, 44, 17, 'downvote', NOW() - INTERVAL 391806 SECOND, NOW() - INTERVAL 391806 SECOND),
    (106, 44, 18, 'downvote', NOW() - INTERVAL 391266 SECOND, NOW() - INTERVAL 391266 SECOND),
    (107, 44, 20, 'downvote', NOW() - INTERVAL 390726 SECOND, NOW() - INTERVAL 390726 SECOND),
    (108, 47, 22, 'upvote', NOW() - INTERVAL 375906 SECOND, NOW() - INTERVAL 375906 SECOND),
    (109, 47, 17, 'upvote', NOW() - INTERVAL 375906 SECOND, NOW() - INTERVAL 375906 SECOND),
    (110, 48, 22, 'upvote', NOW() - INTERVAL 369906 SECOND, NOW() - INTERVAL 369906 SECOND),
    (111, 51, 27, 'upvote', NOW() - INTERVAL 292566 SECOND, NOW() - INTERVAL 292566 SECOND),
    (112, 51, 17, 'upvote', NOW() - INTERVAL 295806 SECOND, NOW() - INTERVAL 295806 SECOND),
    (113, 50, 27, 'upvote', NOW() - INTERVAL 299106 SECOND, NOW() - INTERVAL 299106 SECOND),
    (114, 52, 21, 'upvote', NOW() - INTERVAL 241506 SECOND, NOW() - INTERVAL 241506 SECOND),
    (115, 53, 21, 'upvote', NOW() - INTERVAL 236586 SECOND, NOW() - INTERVAL 236586 SECOND),
    (116, 52, 16, 'upvote', NOW() - INTERVAL 241506 SECOND, NOW() - INTERVAL 241506 SECOND),
    (117, 54, 24, 'upvote', NOW() - INTERVAL 222306 SECOND, NOW() - INTERVAL 222306 SECOND),
    (118, 54, 20, 'upvote', NOW() - INTERVAL 222306 SECOND, NOW() - INTERVAL 222306 SECOND),
    (120, 56, 1, 'upvote', NOW() - INTERVAL 88345 SECOND, NOW() - INTERVAL 88345 SECOND),
    (121, 57, 1, 'upvote', NOW() - INTERVAL 88345 SECOND, NOW() - INTERVAL 88345 SECOND);

-- Stored reputation must equal the votes above (upvotes minus downvotes on each user's replies)
UPDATE users u
SET u.reputation = (
    SELECT COALESCE(SUM(IF(v.vote_type = 'upvote', 1, -1)), 0)
    FROM reply_votes v
    JOIN replies r ON r.reply_id = v.reply_id
    WHERE r.user_id = u.user_id
);

-- ---------- Reports ----------
-- Removed / Dismissed reports were handled by staff; Pending ones wait in the staff queue.
INSERT INTO reports (report_id, reporter_id, reported_post_id, reported_reply_id, reason, description, status, created_at) VALUES
    (1, 12, NULL, 21, 'Misinformation', 'Deleting System32 would break Windows completely. Please remove this before someone tries it.', 'Pending', NOW() - INTERVAL 725545 SECOND),
    (2, 27, NULL, NULL, 'Spam', 'He is just advertising his laptop on my question.', 'Removed', NOW() - INTERVAL 318306 SECOND),
    (3, 16, NULL, NULL, 'Spam', 'Selling suspicious software keys.', 'Removed', NOW() - INTERVAL 316506 SECOND),
    (4, 17, NULL, NULL, 'Spam', 'Looks like pirated keys.', 'Removed', NOW() - INTERVAL 314706 SECOND),
    (5, 28, NULL, 43, 'Misinformation', 'I do not think phones can get dangerous from charging.', 'Dismissed', NOW() - INTERVAL 312906 SECOND);

-- ---------- Meetup requests ----------
-- Proposed times are relative to the import date.
INSERT INTO meetup_requests (meetup_id, requester_id, recipient_id, proposed_datetime, location, message, status, created_at, updated_at) VALUES
    (1, 3, 1, CURDATE() + INTERVAL 1 DAY + INTERVAL '14:00:00' HOUR_SECOND, 'Library level 2, discussion room 3',
        'Could you help me check why my MacBook battery drains so fast? I will bring my charger.', 'Rejected', NOW() - INTERVAL 99145 SECOND, NOW() - INTERVAL 19848 SECOND),
    (2, 1, 4, CURDATE() + INTERVAL 2 DAY + INTERVAL '10:30:00' HOUR_SECOND, 'Block B computer lab',
        'Can you show me how to clone my old hard drive to the new SSD?', 'Accepted', NOW() - INTERVAL 221545 SECOND, NOW() - INTERVAL 207145 SECOND),
    (3, 8, 2, CURDATE() + INTERVAL 1 DAY + INTERVAL '16:00:00' HOUR_SECOND, 'Student centre, ground floor',
        'Need help fixing GRUB on my dual boot laptop.', 'Pending', NOW() - INTERVAL 113545 SECOND, NOW() - INTERVAL 113545 SECOND),
    (4, 5, 8, CURDATE() + INTERVAL 3 DAY + INTERVAL '11:00:00' HOUR_SECOND, 'Cafeteria near the main hall',
        'Could you look at my phone hotspot settings with me?', 'Accepted', NOW() - INTERVAL 185545 SECOND, NOW() - INTERVAL 171145 SECOND),
    (5, 6, 12, CURDATE() + INTERVAL 0 DAY + INTERVAL '15:00:00' HOUR_SECOND, 'Library level 1, near the printers',
        'Want me to look at the Premiere export crash in person?', 'Rejected', NOW() - INTERVAL 250345 SECOND, NOW() - INTERVAL 235945 SECOND),
    (6, 12, 1, CURDATE() + INTERVAL 16 DAY + INTERVAL '09:58:00' HOUR_SECOND, 'Student Centre',
        NULL, 'Accepted', NOW() - INTERVAL 1382 SECOND, NOW() - INTERVAL 1352 SECOND),
    (7, 20, 19, CURDATE() + INTERVAL 1 DAY + INTERVAL '15:00:00' HOUR_SECOND, 'Library level 1, group study area',
        'To check the screen cable on your laptop. Coffee is on you.', 'Accepted', NOW() - INTERVAL 395106 SECOND, NOW() - INTERVAL 395106 SECOND),
    (8, 21, 20, CURDATE() + INTERVAL 0 DAY + INTERVAL '11:00:00' HOUR_SECOND, 'Library level 2, discussion room 1',
        'Could you check my MacBook keyboard after the tea spill? Kopi on me.', 'Rejected', NOW() - INTERVAL 241506 SECOND, NOW() - INTERVAL 241506 SECOND),
    (9, 25, 16, CURDATE() + INTERVAL 2 DAY + INTERVAL '13:00:00' HOUR_SECOND, 'Block B computer lab',
        'For my laptop that got wet. It has been drying for two days.', 'Pending', NOW() - INTERVAL 164706 SECOND, NOW() - INTERVAL 164706 SECOND);

-- ---------- Direct messages ----------
-- sender_deleted / receiver_deleted = 1 means that student used "Delete chat".
INSERT INTO messages (message_id, sender_id, receiver_id, content, image_path, is_read, sender_deleted, receiver_deleted, created_at) VALUES
    (1, 1, 4, 'Hi Arul, I saw your reply about SSDs. Do you have a SATA to USB cable I can borrow for cloning?', NULL, 1, 0, 0, NOW() - INTERVAL 228745 SECOND),
    (2, 4, 1, 'Yes, I have one. Bring your laptop and the new SSD, cloning takes about 30 minutes.', NULL, 1, 0, 0, NOW() - INTERVAL 225145 SECOND),
    (3, 1, 4, 'Great, I just sent you a meetup request for the Block B lab.', NULL, 1, 0, 0, NOW() - INTERVAL 221545 SECOND),
    (4, 4, 1, 'Accepted. Remember to back up your important files first, just in case.', NULL, 1, 0, 0, NOW() - INTERVAL 207145 SECOND),
    (5, 3, 1, 'Hi Danial, is it okay if we meet about my MacBook battery? I sent you a meetup request.', NULL, 1, 0, 0, NOW() - INTERVAL 99145 SECOND),
    (6, 1, 3, 'Sure. Can you send me a screenshot of the Battery settings page first?', NULL, 1, 0, 0, NOW() - INTERVAL 95545 SECOND),
    (7, 3, 1, 'Spotlight and Photos are using the most battery. Is that normal after an update?', NULL, 1, 0, 0, NOW() - INTERVAL 84745 SECOND),
    (8, 8, 5, 'About your hotspot post, did switching to 2.4GHz work?', NULL, 1, 0, 0, NOW() - INTERVAL 214345 SECOND),
    (9, 5, 8, 'Not yet, the tablet still cannot see it. I will try your MAC filter idea.', NULL, 1, 0, 0, NOW() - INTERVAL 210745 SECOND),
    (10, 8, 5, 'Also restart the phone after changing the band. Sometimes the setting does not apply straight away.', NULL, 1, 0, 0, NOW() - INTERVAL 207145 SECOND),
    (11, 9, 2, 'Thanks for the venv tip. Do I need to activate it every time I open a new terminal?', NULL, 1, 0, 0, NOW() - INTERVAL 869545 SECOND),
    (12, 2, 9, 'Yes, every new terminal. VS Code can also pick the venv for you automatically.', NULL, 1, 0, 0, NOW() - INTERVAL 865945 SECOND),
    (17, 25, 16, 'Hi Haziq, my sister said you fixed her laptop last semester. Mine will not turn on after I spilled water on it yesterday. Can you take a look?', NULL, 0, 0, 0, NOW() - INTERVAL 452706 SECOND),
    (18, 16, 25, 'Do not try to charge it! Leave it switched off with the battery out if you can, and let it dry for at least 48 hours. Then we can check it.', NULL, 1, 0, 0, NOW() - INTERVAL 452346 SECOND),
    (19, 20, 19, 'Saw your hinge post. If it is the cable, I can reseat it in 10 minutes. You buy the coffee?', NULL, 1, 0, 0, NOW() - INTERVAL 395106 SECOND),
    (20, 19, 20, 'Deal! Iced latte from the cafe next to the library? I really need this laptop for my assignment.', NULL, 0, 0, 0, NOW() - INTERVAL 394746 SECOND),
    (21, 20, 19, 'Perfect. I will send you a meetup request for the library study area. Bring your charger and the small screwdriver set if you have one.', NULL, 0, 0, 0, NOW() - INTERVAL 394386 SECOND),
    (22, 26, 18, 'Hi Ravi, can you change a phone battery? Roughly how much would the parts cost?', NULL, 1, 1, 0, NOW() - INTERVAL 318306 SECOND),
    (23, 18, 26, 'Yes I can. Depends on the model, usually RM60 to RM90 for the battery. I can do it between classes at the student centre.', NULL, 1, 0, 1, NOW() - INTERVAL 317946 SECOND),
    (24, 26, 18, 'Okay thanks, let me ask my parents first and I will get back to you.', NULL, 1, 1, 0, NOW() - INTERVAL 317586 SECOND),
    (25, 30, 23, 'Hi, I saw your post about the hospital WiFi. Did you fix it? My phone has the same problem at my placement.', NULL, 1, 0, 0, NOW() - INTERVAL 299106 SECOND),
    (26, 23, 30, 'Yes! Turn off Private DNS, then open neverssl.com in the browser and the login page pops up.', NULL, 1, 0, 0, NOW() - INTERVAL 298746 SECOND),
    (27, 21, 20, 'Your advice was really helpful. Can I buy you a kopi and you take a look at it at the library?', NULL, 0, 0, 0, NOW() - INTERVAL 241506 SECOND),
    (28, 20, 21, 'Sorry, I have two exams this week so I had to decline. Ravi does liquid damage cleaning and is better at it than me anyway.', NULL, 0, 0, 0, NOW() - INTERVAL 241146 SECOND),
    (29, 17, 1, 'Hi Danial, about your campus VPN question: the library WiFi blocks some ports, but most research sites work if you use the university\'s own login page first.', NULL, 1, 0, 0, NOW() - INTERVAL 97345 SECOND);

-- ---------- Notifications ----------
INSERT INTO notifications (notification_id, user_id, type, message, link, is_read, created_at) VALUES
    (1, 1, 'reply', 'Arul_Subramanian replied to your question: Is it worth upgrading my old laptop to an SSD?', 'student/post.php?id=3#reply-6', 1, NOW() - INTERVAL 1510345 SECOND),
    (2, 1, 'reply', 'Wei_Jie_Tan replied to your question: Windows Update stuck at 0% for hours', 'student/post.php?id=10#reply-22', 1, NOW() - INTERVAL 671545 SECOND),
    (3, 1, 'reply', 'Kevin_Raj replied to your question: Windows Update stuck at 0% for hours', 'student/post.php?id=10#reply-23', 1, NOW() - INTERVAL 653545 SECOND),
    (4, 1, 'reply', 'Siti_Farah replied to your question: Windows Update stuck at 0% for hours', 'student/post.php?id=10#reply-24', 1, NOW() - INTERVAL 617545 SECOND),
    (5, 1, 'meetup_accepted', 'Arul_Subramanian accepted your meetup request', 'student/meetups.php?tab=sent#meetup-2', 1, NOW() - INTERVAL 207145 SECOND),
    (6, 1, 'message', 'Arul_Subramanian sent you a message', 'student/messages.php?user=4', 1, NOW() - INTERVAL 207145 SECOND),
    (7, 1, 'meetup_request', 'Mei_Liu sent you a meetup request', 'student/meetups.php#meetup-1', 1, NOW() - INTERVAL 99145 SECOND),
    (8, 1, 'message', 'Mei_Liu sent you a message', 'student/messages.php?user=3', 1, NOW() - INTERVAL 84745 SECOND),
    (9, 4, 'meetup_request', 'Danial_Fahimy sent you a meetup request', 'student/meetups.php#meetup-2', 1, NOW() - INTERVAL 221545 SECOND),
    (10, 2, 'meetup_request', 'Kevin_Raj sent you a meetup request', 'student/meetups.php#meetup-3', 1, NOW() - INTERVAL 113545 SECOND),
    (11, 5, 'meetup_accepted', 'Kevin_Raj accepted your meetup request', 'student/meetups.php?tab=sent#meetup-4', 1, NOW() - INTERVAL 171145 SECOND),
    (12, 6, 'meetup_rejected', 'Amirah_Zulkifli declined your meetup request', 'student/meetups.php?tab=sent#meetup-5', 1, NOW() - INTERVAL 235945 SECOND),
    (13, 8, 'reply', 'Aron_Paul replied to your question: Dual boot Ubuntu not showing in boot menu', 'student/post.php?id=5#reply-11', 1, NOW() - INTERVAL 1229545 SECOND),
    (14, 8, 'reply', 'Hafiz_Rahman replied to your question: Dual boot Ubuntu not showing in boot menu', 'student/post.php?id=5#reply-12', 1, NOW() - INTERVAL 1193545 SECOND),
    (15, 12, 'reply', 'Jason_Lim replied to your question: Premiere Pro crashes when exporting 1080p video', 'student/post.php?id=9#reply-19', 1, NOW() - INTERVAL 779545 SECOND),
    (16, 12, 'reply', 'Danial_Fahimy replied to your question: Premiere Pro crashes when exporting 1080p video', 'student/post.php?id=9#reply-20', 1, NOW() - INTERVAL 761545 SECOND),
    (17, 7, 'reply', 'Wei_Jie_Tan replied to your question: Accidentally deleted a folder, can I recover it?', 'student/post.php?id=16#reply-36', 0, NOW() - INTERVAL 149545 SECOND),
    (18, 9, 'reply', 'Mei_Liu replied to your question: iPhone not showing up in Windows File Explorer', 'student/post.php?id=17#reply-37', 0, NOW() - INTERVAL 142345 SECOND),
    (23, 3, 'meetup_rejected', 'Danial_Fahimy declined your meetup request', 'student/meetups.php?tab=sent#meetup-1', 1, NOW() - INTERVAL 19848 SECOND),
    (24, 1, 'meetup_request', 'Amirah_Zulkifli sent you a meetup request', 'student/meetups.php#meetup-6', 1, NOW() - INTERVAL 1382 SECOND),
    (25, 12, 'meetup_accepted', 'Danial_Fahimy accepted your meetup request', 'student/meetups.php?tab=sent#meetup-6', 1, NOW() - INTERVAL 1352 SECOND),
    (26, 19, 'reply', 'Haziq_Iskandar replied to your question: Laptop screen flickers when I move the hinge', 'student/post.php?id=20#reply-38', 1, NOW() - INTERVAL 471906 SECOND),
    (27, 19, 'reply', 'Ravi_Kumar replied to your question: Laptop screen flickers when I move the hinge', 'student/post.php?id=20#reply-39', 1, NOW() - INTERVAL 469506 SECOND),
    (28, 19, 'reply', 'Daniel_Wong replied to your question: Laptop screen flickers when I move the hinge', 'student/post.php?id=20#reply-40', 1, NOW() - INTERVAL 467106 SECOND),
    (29, 23, 'reply', 'Chloe_Tan replied to your question: Phone cannot open the hospital guest WiFi login page', 'student/post.php?id=22#reply-41', 0, NOW() - INTERVAL 464706 SECOND),
    (30, 23, 'reply', 'Siti_Farah replied to your question: Phone cannot open the hospital guest WiFi login page', 'student/post.php?id=22#reply-42', 0, NOW() - INTERVAL 462306 SECOND),
    (31, 16, 'message', 'Sarah_Ong sent you a message', 'student/messages.php?user=25', 1, NOW() - INTERVAL 452706 SECOND),
    (32, 25, 'message', 'Haziq_Iskandar sent you a message', 'student/messages.php?user=16', 1, NOW() - INTERVAL 452706 SECOND),
    (33, 19, 'message', 'Daniel_Wong sent you a message', 'student/messages.php?user=20', 1, NOW() - INTERVAL 395106 SECOND),
    (34, 20, 'message', 'Nurul_Izzah sent you a message', 'student/messages.php?user=19', 0, NOW() - INTERVAL 395106 SECOND),
    (35, 19, 'message', 'Daniel_Wong sent you a message', 'student/messages.php?user=20', 0, NOW() - INTERVAL 395106 SECOND),
    (36, 19, 'meetup_request', 'Daniel_Wong sent you a meetup request', 'student/meetups.php#meetup-7', 1, NOW() - INTERVAL 395106 SECOND),
    (37, 20, 'meetup_accepted', 'Nurul_Izzah accepted your meetup request', 'student/meetups.php?tab=sent#meetup-7', 1, NOW() - INTERVAL 395106 SECOND),
    (38, 28, 'reply', 'Ravi_Kumar replied to your question: Phone gets very hot while charging overnight', 'student/post.php?id=23#reply-43', 0, NOW() - INTERVAL 395106 SECOND),
    (39, 28, 'reply', 'Jun_Hao_Lim replied to your question: Phone gets very hot while charging overnight', 'student/post.php?id=23#reply-44', 0, NOW() - INTERVAL 392706 SECOND),
    (40, 28, 'reply', 'Danial_Fahimy replied to your question: Phone gets very hot while charging overnight', 'student/post.php?id=23#reply-45', 0, NOW() - INTERVAL 390306 SECOND),
    (42, 22, 'reply', 'Daniel_Wong replied to your question: Mechanical keyboard types some letters twice', 'student/post.php?id=25#reply-47', 0, NOW() - INTERVAL 375906 SECOND),
    (43, 22, 'reply', 'Haziq_Iskandar replied to your question: Mechanical keyboard types some letters twice', 'student/post.php?id=25#reply-48', 0, NOW() - INTERVAL 373506 SECOND),
    (45, 18, 'message', 'Muthu_Velu sent you a message', 'student/messages.php?user=26', 1, NOW() - INTERVAL 318306 SECOND),
    (46, 26, 'message', 'Ravi_Kumar sent you a message', 'student/messages.php?user=18', 1, NOW() - INTERVAL 318306 SECOND),
    (47, 23, 'message', 'Yasmin_Rahim sent you a message', 'student/messages.php?user=30', 1, NOW() - INTERVAL 299106 SECOND),
    (48, 30, 'message', 'Kavitha_Rao sent you a message', 'student/messages.php?user=23', 1, NOW() - INTERVAL 299106 SECOND),
    (49, 27, 'reply', 'Ahmad_Zaki replied to your question: How do I check if my laptop is actually charging?', 'student/post.php?id=21#reply-50', 0, NOW() - INTERVAL 299106 SECOND),
    (50, 27, 'reply', 'Haziq_Iskandar replied to your question: How do I check if my laptop is actually charging?', 'student/post.php?id=21#reply-51', 0, NOW() - INTERVAL 296706 SECOND),
    (51, 21, 'reply', 'Ravi_Kumar replied to your question: MacBook keyboard stopped working after I spilled tea on it', 'student/post.php?id=26#reply-52', 0, NOW() - INTERVAL 241506 SECOND),
    (52, 21, 'reply', 'Daniel_Wong replied to your question: MacBook keyboard stopped working after I spilled tea on it', 'student/post.php?id=26#reply-53', 0, NOW() - INTERVAL 239106 SECOND),
    (53, 20, 'message', 'Farah_Nadia sent you a message', 'student/messages.php?user=21', 0, NOW() - INTERVAL 241506 SECOND),
    (54, 20, 'meetup_request', 'Farah_Nadia sent you a meetup request', 'student/meetups.php#meetup-8', 1, NOW() - INTERVAL 241506 SECOND),
    (55, 21, 'meetup_rejected', 'Daniel_Wong declined your meetup request', 'student/meetups.php?tab=sent#meetup-8', 1, NOW() - INTERVAL 241506 SECOND),
    (56, 21, 'message', 'Daniel_Wong sent you a message', 'student/messages.php?user=20', 0, NOW() - INTERVAL 241506 SECOND),
    (57, 24, 'reply', 'Chloe_Tan replied to your question: Arduino board not showing up as a COM port', 'student/post.php?id=27#reply-54', 0, NOW() - INTERVAL 222306 SECOND),
    (58, 24, 'reply', 'Haziq_Iskandar replied to your question: Arduino board not showing up as a COM port', 'student/post.php?id=27#reply-55', 0, NOW() - INTERVAL 219906 SECOND),
    (59, 16, 'meetup_request', 'Sarah_Ong sent you a meetup request', 'student/meetups.php#meetup-9', 1, NOW() - INTERVAL 164706 SECOND),
    (60, 1, 'message', 'Chloe_Tan sent you a message', 'student/messages.php?user=17', 1, NOW() - INTERVAL 97345 SECOND),
    (61, 1, 'reply', 'Chloe_Tan replied to your question: Which VPN setting should I use for the campus WiFi?', 'student/post.php?id=18#reply-56', 0, NOW() - INTERVAL 94945 SECOND),
    (62, 1, 'reply', 'Wei_Jie_Tan replied to your question: Which VPN setting should I use for the campus WiFi?', 'student/post.php?id=18#reply-57', 0, NOW() - INTERVAL 90745 SECOND);
