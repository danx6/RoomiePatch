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

### Reputation

Students can upvote or downvote any reply except their own (one active vote per reply;
clicking the same arrow again removes it). A student's **reputation** is the total upvotes
minus downvotes on all their replies. It is stored in the database, can go negative, and is
shown beside their name and on their profile.

A **Recently Helpful** (green) or **Recently Not Helpful** (red) label reflects only the votes
from the last 14 days: at least 3 recent votes are needed, then 60% or more upvotes counts as
helpful and 40% or less as not helpful. With too few or mixed votes, no label is shown.

### Resolved posts and the IT Knowledge Repository

When the original poster accepts a reply, the post becomes **Resolved** and read-only: the reply
box is replaced by "Submissions are closed as this issue has been resolved." and the server
refuses new replies. The accepted reply is stored in `posts.accepted_reply_id`, and the time it
was resolved in `posts.resolved_at`.

The **Resolved** tab in Browse Posts only shows posts resolved in the last 7 days. Older posts are
not deleted; they stay in the database and can still be opened by link.

Useful solutions can be kept permanently in the **IT Knowledge Repository** (last item in the
sidebar):

1. The original poster, the student whose reply was accepted, or staff click
   **Submit for evaluation** on a resolved post. Each post can be submitted only once.
2. A **certified university technician** (staff with `users.is_certified_technician = 1`) reviews
   the submission under Staff, then Knowledge Repository. They can edit the title, category,
   summary, problem, solution and keywords, and then approve or reject it. Rejecting needs a
   reason. The reviewer, the date and any rejection reason are recorded.
3. Approved entries appear in the repository, where students can search them and filter by
   category (Hardware, Software, Operating System, Networking; the same as post categories, and
   posts in Other are filed under Software).
   They can sort by newest, oldest, A–Z or Z–A. Each entry shows the original question with
   **only** the accepted reply.

Staff can unpublish an approved entry, and a technician can publish it again. Only approved,
published entries are visible to students; this is checked on the server, not only in the page.

### Meetup Liability & Safety Notice

Before sending or accepting a meetup request, students must tick an acknowledgement in a popup
covering liability for personal belongings and devices, and that sessions take place only in
university-designated areas, which the university monitors. The server rejects the request
without this acknowledgement.

## Setup

1. Make the project available in the web server's root folder as `roomiepatch`:
   - Laragon: `C:\laragon\www\roomiepatch` (on this machine it is a junction pointing to the project folder)
   - XAMPP: `...\xampp\htdocs\roomiepatch`
2. Start **Apache** and **MySQL**.
3. Open phpMyAdmin (or HeidiSQL), and import `database/roomiepatch.sql`.
   This one file creates the `roomiepatch` database, all tables, and the demo data
   (27 students and 3 staff with a few weeks of simulated activity: 34 posts, 17 of them
   resolved and 6 with screenshots, 76 replies, 126 votes, 25 direct messages, 9 meetups,
   5 reports with one still waiting for staff, and 14 repository submissions: 9 approved,
   3 pending and 2 rejected). The demo screenshots in `uploads/posts/demo-*.png` are original images
   drawn for this project.
4. Open `http://localhost/roomiepatch` and log in.

**Already have an older RoomiePatch database?** Import these migrations instead, in order, to add
the newer features without losing your data:

1. `database/migration_reputation.sql`: reply voting and reputation.
2. `database/migration_repository.sql`: accepted reply and resolved time on posts, certified
   technicians, and the `repository_entries` table. Existing resolved posts get their accepted
   reply from `replies.is_resolved`. Their resolved time is estimated from the latest reply,
   because it was not recorded before.

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
| Student | `MC240937205`  | Haziq_Iskandar      | `stud123`  |
| Student | `MC241021846`  | Chloe_Tan           | `stud123`  |
| Student | `MC250115390`  | Ravi_Kumar          | `stud123`  |
| Student | `MC240931168`  | Nurul_Izzah         | `stud123`  |
| Student | `MC241027734`  | Daniel_Wong         | `stud123`  |
| Student | `MC250118023`  | Farah_Nadia         | `stud123`  |
| Student | `MC240935591`  | Jun_Hao_Lim         | `stud123`  |
| Student | `MC241024417`  | Kavitha_Rao         | `stud123`  |
| Student | `MC250112689`  | Ahmad_Zaki          | `stud123`  |
| Student | `MC240939312`  | Sarah_Ong           | `stud123`  |
| Student | `MC241020578`  | Muthu_Velu          | `stud123`  |
| Student | `MC250116744`  | Aina_Sofea          | `stud123`  |
| Student | `MC240932950`  | Brandon_Lee         | `stud123`  |
| Student | `MC241028361`  | Irfan_Hakim         | `stud123`  |
| Student | `MC250110925`  | Yasmin_Rahim        | `stud123`  |
| Staff   | `SD234792`     | Admin_Farah (certified technician)        | `admin123` |
| Staff   | `SD231058`     | Admin_Rutheshwarar (certified technician) | `admin123` |
| Staff   | `SD238417`     | Admin_Nadia         | `admin123` |

Certified technicians can approve or reject repository submissions. Other staff can view them and
unpublish entries. To make another staff account a technician, set `is_certified_technician = 1`
for it in the `users` table.

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
| `database/`         | `roomiepatch.sql`: all tables plus the demo data; `migration_*.sql`: upgrades for older databases |

## Licence

Code: MIT (see `LICENSE`). The Figtree font in `assets/fonts/` is under the SIL Open Font License (`assets/fonts/OFL.txt`).
