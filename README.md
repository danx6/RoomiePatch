# RoomiePatch

A student-powered peer-to-peer IT troubleshooting platform connecting university students to share technical knowledge and help resolve personal device issues.

Built with plain HTML, CSS, JavaScript, PHP and MySQL (developed on Laragon; XAMPP works the same way).
No frameworks or UI libraries.

## About the project

RoomiePatch is a peer-to-peer IT troubleshooting and support platform designed specifically for university students.

### The problem

IT support often relies mainly on remote troubleshooting and remote management. Many advanced
enterprise-grade remote management capabilities are designed for organisational environments and
may depend on specialised hardware, compatible devices or supporting infrastructure that typical
university students do not have access to.

Students commonly use personal laptops and devices that may lack these advanced remote management
capabilities. Some problems are also difficult to diagnose or resolve remotely, particularly
hardware issues, device configuration problems, and situations where physical inspection or a
helping hand makes the difference.

### What RoomiePatch offers

RoomiePatch provides a student-oriented community where students can:

- Share IT troubleshooting problems with their own devices.
- Receive advice and solutions from other students.
- Discuss hardware, software, operating system and networking issues.
- Arrange optional face-to-face assistance when it is appropriate.

### Approach

RoomiePatch combines online peer troubleshooting with optional physical assistance. Students first
discuss a problem in an open thread; when a problem is hard to solve online, they can request an
in-person meetup on campus, with a safety notice shown before every request.

The focus is on:

- **Accessibility**: it works with the personal devices students already own, with no special
  hardware or management software needed.
- **Community collaboration**: students help each other, and the reply that solved a problem is
  marked as resolved so others can find the fix later.
- **Practical IT support**: everyday problems with laptops, phones, software and networks.

RoomiePatch is not a replacement for professional remote support tools or the university IT
department; it is a complementary space for students to learn from and support one another.

The layout takes inspiration from community discussion platforms such as Stack Overflow and Reddit,
but RoomiePatch focuses specifically on IT troubleshooting for university students.

## Setup

1. Make the project available in the web server's root folder as `roomiepatch`:
   - Laragon: `C:\laragon\www\roomiepatch` (on this machine it is a junction pointing to the project folder)
   - XAMPP: `...\xampp\htdocs\roomiepatch`
2. Start **Apache** and **MySQL**.
3. Open phpMyAdmin (or HeidiSQL), and import `database/roomiepatch.sql`.
   This one file creates the `roomiepatch` database, all tables, and the demo data
   (accounts, posts, replies, meetups, messages, notifications and one pending report).
4. Open `http://localhost/roomiepatch` and log in.

To reset everything back to the demo data, import `roomiepatch.sql` again.
Demo times are relative to the moment you import, so the content always looks recent.

## Mock accounts

Demo accounts for the showcase only. They are not real university records.
Login IDs work in upper or lower case.

| Role    | Login ID       | Username            | Password   |
|---------|----------------|---------------------|------------|
| Student | `MC240930202`  | Danial_Fahimy (main)| `stud123`  |
| Student | `MC240934817`  | Aron_Paul           | `stud123`  |
| Student | `MC241022096`  | Mei_Liu             | `stud123`  |
| Student | `MC250117351`  | Arul_Subramanian    | `stud123`  |
| Student | `MC240930638`  | Siti_Farah          | `stud123`  |
| Student | `MC241025523`  | Jason_Lim           | `stud123`  |
| Student | `MC250119164`  | Nur_Aisyah          | `stud123`  |
| Student | `MC240933742`  | Kevin_Raj           | `stud123`  |
| Student | `MC241026085`  | Hafiz_Rahman        | `stud123`  |
| Student | `MC250111479`  | Priya_Nair          | `stud123`  |
| Student | `MC240938230`  | Wei_Jie_Tan         | `stud123`  |
| Student | `MC241022957`  | Amirah_Zulkifli     | `stud123`  |
| Staff   | `SD234792`     | Admin_Farah         | `admin123` |
| Staff   | `SD231058`     | Admin_Rutheshwarar  | `admin123` |
| Staff   | `SD238417`     | Admin_Nadia         | `admin123` |

## Folders

| Folder              | Purpose                                              |
|---------------------|------------------------------------------------------|
| `config/`           | Database connection settings (`db.php`)              |
| `includes/`         | Shared parts: login check, header, sidebar, icons    |
| `student/`          | Pages students see                                   |
| `admin/`            | Report review, meetup monitoring and moderation pages |
| `actions/`          | Form handlers that save data, then redirect back     |
| `api/`              | JSON endpoints for live updates (messages, notifications, meetups, replies), called by `assets/js/live.js` with `fetch()` |
| `assets/`           | `css/style.css`, `js/main.js`, `js/live.js`, `img/logo.png`, and `fonts/` (Figtree font, SIL Open Font License, works offline) |
| `uploads/posts/`    | Images attached to posts (up to 3 per post)          |
| `uploads/messages/` | Images attached to direct messages                   |
| `database/`         | `roomiepatch.sql`: all tables plus the demo data     |

## Licence

Code: MIT (see `LICENSE`). The Figtree font in `assets/fonts/` is under the SIL Open Font License (`assets/fonts/OFL.txt`).
