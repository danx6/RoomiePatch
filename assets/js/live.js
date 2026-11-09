// RoomiePatch live updates (no page refresh).
// Plain JavaScript "polling": every few seconds we ask a PHP endpoint in api/ for
// anything NEW since last time (using the last id or a timestamp), and update the page.
//
// To keep the server load low:
//   - a poller never starts a new request while the previous one is still running
//   - polling pauses while the browser tab is hidden, and resumes when you come back
//   - after a network error it waits longer each time (up to 30 seconds)

(function () {
    var BASE = document.body.getAttribute('data-base-url') || '/';

    // ---------- Small helpers ----------

    // GET an api/ endpoint and return the JSON. Throws on network or server errors.
    function getJSON(path) {
        return fetch(BASE + path, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store'
        }).then(readJSON);
    }

    // POST a FormData object to an api/ endpoint and return the JSON.
    function postForm(path, formData) {
        return fetch(BASE + path, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(readJSON);
    }

    function readJSON(response) {
        return response.json().catch(function () {
            // Not JSON (e.g. a PHP error page): treat it like a network error so the poller backs off
            throw new Error('Unexpected response from the server.');
        }).then(function (data) {
            // HTTP code kept separately, so it never overwrites a "status" field sent by the API
            data.httpStatus = response.status;
            if (response.status === 401) {
                // Logged out (e.g. session ended): stop all live updates
                stopAll();
                showStatus('You have been logged out. Refresh the page to log in again.');
            }
            return data;
        });
    }

    // Turn an HTML string from the server into real elements
    function htmlToElements(html) {
        var template = document.createElement('template');
        template.innerHTML = html.trim();
        return template.content;
    }

    // Small message at the bottom of the screen when the connection has trouble
    var statusBox = null;
    function showStatus(text) {
        if (!statusBox) {
            statusBox = document.createElement('div');
            statusBox.className = 'live-status';
            statusBox.setAttribute('role', 'status');
            document.body.appendChild(statusBox);
        }
        statusBox.textContent = text;
        statusBox.hidden = false;
    }
    function hideStatus() {
        if (statusBox) {
            statusBox.hidden = true;
        }
    }

    // Update a sidebar badge, e.g. setBadge('messages', 3)
    function setBadge(key, count) {
        document.querySelectorAll('[data-badge="' + key + '"]').forEach(function (badge) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.hidden = !(count > 0);
        });
    }

    // ---------- The poller ----------
    // poller(task, every) runs task() every `every` milliseconds.
    // task must return a Promise. Returns an object with .now() to poll immediately.
    var allPollers = [];

    function poller(task, every) {
        var timer = null;
        var running = false;
        var stopped = false;
        var failures = 0;

        function schedule(delay) {
            clearTimeout(timer);
            if (!stopped) {
                timer = setTimeout(run, delay);
            }
        }

        function run() {
            if (stopped || running) {
                return;
            }
            if (document.hidden) {
                return; // paused; visibilitychange restarts it
            }
            running = true;
            task().then(function () {
                failures = 0;
                hideStatus();
                running = false;
                schedule(every);
            }).catch(function (err) {
                // Network problem: try again later, waiting longer each time
                if (window.console) {
                    console.warn('RoomiePatch live update failed:', err);
                }
                failures++;
                running = false;
                if (failures >= 2) {
                    showStatus('Connection lost. Trying again...');
                }
                schedule(Math.min(every * Math.pow(2, failures), 30000));
            });
        }

        var p = {
            now: function () { schedule(0); },
            stop: function () { stopped = true; clearTimeout(timer); }
        };
        allPollers.push(p);
        schedule(every);
        return p;
    }

    function stopAll() {
        allPollers.forEach(function (p) { p.stop(); });
    }

    // Coming back to the tab: check straight away
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            allPollers.forEach(function (p) { p.now(); });
        }
    });

    // =====================================================================
    // 1. Direct messages (student/messages.php)
    // =====================================================================
    var inbox = document.querySelector('[data-inbox]');

    if (inbox) {
        var chatUser = parseInt(inbox.getAttribute('data-user'), 10) || 0;
        var lastId = parseInt(inbox.getAttribute('data-last-id'), 10) || 0;
        var listVersion = inbox.getAttribute('data-list-version') || '';
        var chatBox = document.getElementById('chat-messages');
        var listBox = document.getElementById('conversation-list');
        var form = document.getElementById('chat-compose');

        // Is the chat scrolled to (or near) the bottom?
        function nearBottom() {
            return chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight < 80;
        }

        // Add new messages to the chat, skipping any that are already on screen
        function appendMessages(messages, forceScroll) {
            if (!chatBox || !messages.length) {
                return;
            }
            var stick = forceScroll || nearBottom();
            var start = chatBox.querySelector('.chat-start');

            messages.forEach(function (m) {
                if (chatBox.querySelector('[data-message-id="' + m.id + '"]')) {
                    return; // already shown: no duplicates
                }
                chatBox.appendChild(htmlToElements(m.html));
                if (start) {
                    start.remove();
                    start = null;
                }
            });

            // Only jump to the newest message if the student was already at the bottom
            // (or just sent it), so reading older messages is not interrupted
            if (stick) {
                chatBox.scrollTop = chatBox.scrollHeight;
            }
        }

        function checkMessages(forceScroll) {
            var url = 'api/messages.php?user=' + chatUser + '&after=' + lastId + '&list=' + encodeURIComponent(listVersion);
            return getJSON(url).then(function (data) {
                if (!data.ok) {
                    if (data.httpStatus >= 500) {
                        throw new Error(data.error);
                    }
                    return;
                }
                appendMessages(data.messages, forceScroll);
                lastId = Math.max(lastId, data.last_id);
                if (data.list_html !== undefined) {
                    listBox.innerHTML = data.list_html;
                }
                listVersion = data.list_version;
                setBadge('messages', data.unread_messages);
            });
        }

        // Faster checks while a chat is open, slower when only the list is shown
        var chatPoller = poller(function () { return checkMessages(false); }, chatUser ? 2500 : 5000);

        // ---------- Sending without reloading ----------
        if (form) {
            var sendButton = document.getElementById('chat-send');
            var textBox = document.getElementById('chat-text');
            var fileInput = document.getElementById('chat-image');
            var preview = document.getElementById('chat-preview');
            var errorBox = document.getElementById('chat-error');
            var sending = false;

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                if (sending) {
                    return; // stop double sends from double clicks / Enter presses
                }
                if (textBox.value.trim() === '' && fileInput.files.length === 0) {
                    return;
                }

                sending = true;
                sendButton.disabled = true;
                errorBox.hidden = true;

                postForm('api/messages.php', new FormData(form)).then(function (data) {
                    if (!data.ok) {
                        errorBox.textContent = data.error || 'The message could not be sent.';
                        errorBox.hidden = false;
                        return;
                    }
                    // Clear the box, then fetch the new message right away
                    textBox.value = '';
                    textBox.style.height = '';
                    fileInput.value = '';
                    preview.innerHTML = '';
                    return checkMessages(true);
                }).catch(function () {
                    errorBox.textContent = 'Could not reach the server. Check your connection and try again.';
                    errorBox.hidden = false;
                }).then(function () {
                    sending = false;
                    sendButton.disabled = false;
                    textBox.focus();
                });
            });
        }
    }

    // =====================================================================
    // 2. Notification bell (every student page) + sidebar badges
    // =====================================================================
    var bell = document.querySelector('[data-notifications]');

    if (bell) {
        var since = bell.getAttribute('data-since');
        var countBox = bell.querySelector('.notif-count');
        var list = bell.querySelector('.notif-list');
        var emptyText = bell.querySelector('.notif-empty');
        var readAllForm = document.getElementById('notif-read-all');

        function setUnread(count) {
            countBox.textContent = count > 9 ? '9+' : count;
            countBox.hidden = !(count > 0);
            readAllForm.hidden = !(count > 0);
        }

        function shownIds() {
            return Array.prototype.map.call(list.querySelectorAll('[data-notification-id]'), function (li) {
                return li.getAttribute('data-notification-id');
            }).join(',');
        }

        function checkNotifications() {
            var url = 'api/notifications.php?since=' + encodeURIComponent(since) + '&shown=' + shownIds();
            return getJSON(url).then(function (data) {
                if (!data.ok) {
                    if (data.httpStatus >= 500) {
                        throw new Error(data.error);
                    }
                    return;
                }
                since = data.server_time;

                // New (or moved-to-the-top) notifications go to the top of the list.
                // If one is already shown, the old copy is removed first: no duplicates.
                data.items.forEach(function (item) {
                    var old = list.querySelector('[data-notification-id="' + item.id + '"]');
                    if (old) {
                        old.remove();
                    }
                    list.insertBefore(htmlToElements(item.html), list.firstChild);
                });
                // Keep only the 10 newest
                var all = list.querySelectorAll('[data-notification-id]');
                for (var i = 10; i < all.length; i++) {
                    all[i].remove();
                }
                list.hidden = list.children.length === 0;
                emptyText.hidden = list.children.length > 0;

                // Items that were read somewhere else lose their unread highlight
                list.querySelectorAll('[data-notification-id]').forEach(function (li) {
                    var stillUnread = data.still_unread.indexOf(parseInt(li.getAttribute('data-notification-id'), 10)) !== -1;
                    var isNew = data.items.some(function (item) { return String(item.id) === li.getAttribute('data-notification-id'); });
                    if (!stillUnread && !isNew) {
                        li.querySelector('.notif-item').classList.remove('unread');
                    }
                });

                setUnread(data.unread);
                setBadge('messages', data.badges.messages);
                setBadge('meetups', data.badges.meetups);
            });
        }

        var bellPoller = poller(checkNotifications, 4000);

        // "Mark all as read" without leaving the page
        readAllForm.addEventListener('submit', function (event) {
            event.preventDefault();
            postForm('api/notifications.php', new FormData(readAllForm)).then(function (data) {
                if (data.ok) {
                    list.querySelectorAll('.notif-item.unread').forEach(function (a) {
                        a.classList.remove('unread');
                    });
                    setUnread(0);
                }
            }).catch(function () {
                readAllForm.submit(); // network trouble: fall back to the normal form
            });
        });
    }

    // =====================================================================
    // 3. Meetup Requests (student/meetups.php)
    // =====================================================================
    var meetupPage = document.querySelector('[data-meetups]');

    if (meetupPage) {
        var meetupTab = meetupPage.getAttribute('data-tab');
        var meetupSince = meetupPage.getAttribute('data-since');
        var meetupList = document.getElementById('meetup-list');
        var meetupEmpty = meetupPage.querySelector('[data-meetups-empty]');
        var pendingLabel = meetupPage.querySelector('[data-pending-label]');

        function checkMeetups() {
            var url = 'api/meetups.php?tab=' + meetupTab + '&since=' + encodeURIComponent(meetupSince);
            return getJSON(url).then(function (data) {
                if (!data.ok) {
                    if (data.httpStatus >= 500) {
                        throw new Error(data.error);
                    }
                    return;
                }
                meetupSince = data.server_time;

                data.cards.forEach(function (card) {
                    var old = meetupList.querySelector('[data-meetup-id="' + card.id + '"]');
                    var fresh = htmlToElements(card.html).firstElementChild;

                    if (old) {
                        // Status changed (e.g. Accepted): swap the card in place.
                        // But never replace a card while its Accept popup is open.
                        var modal = document.getElementById('safety-modal');
                        if (modal && !modal.hidden && old.contains(document.activeElement)) {
                            return;
                        }
                        old.replaceWith(fresh);
                    } else {
                        // A new request: show it at the top
                        meetupList.insertBefore(fresh, meetupList.firstChild);
                    }
                    fresh.classList.add('just-updated');
                });

                if (data.cards.length) {
                    meetupEmpty.hidden = true;
                }

                // Tab counts and "N waiting for your reply"
                Object.keys(data.counts).forEach(function (key) {
                    meetupPage.querySelectorAll('[data-count="' + key + '"]').forEach(function (el) {
                        el.textContent = data.counts[key];
                    });
                });
                if (pendingLabel) {
                    pendingLabel.hidden = !(data.counts.received_pending > 0);
                }
                setBadge('meetups', data.counts.received_pending);
            });
        }

        poller(checkMeetups, 5000);
    }

    // =====================================================================
    // 4. Replies on a post page (student/post.php)
    // =====================================================================
    var thread = document.querySelector('[data-thread]');

    if (thread) {
        var postId = thread.getAttribute('data-post-id');
        var lastReplyId = parseInt(thread.getAttribute('data-last-reply-id'), 10) || 0;
        var pageStatus = thread.getAttribute('data-status');
        var replyList = document.getElementById('reply-list');
        var repliesEmpty = thread.querySelector('[data-replies-empty]');
        var threadNotice = thread.querySelector('[data-thread-notice]');
        var replyPoller = null;

        function showThreadNotice(text, withRefresh) {
            threadNotice.textContent = text + ' ';
            if (withRefresh) {
                var link = document.createElement('a');
                link.href = window.location.pathname + window.location.search;
                link.textContent = 'Refresh to see it';
                threadNotice.appendChild(link);
            }
            threadNotice.hidden = false;
        }

        function checkReplies() {
            return getJSON('api/replies.php?post=' + postId + '&after=' + lastReplyId).then(function (data) {
                if (data.removed) {
                    showThreadNotice('This post has been removed.', false);
                    replyPoller.stop();
                    return;
                }
                if (!data.ok) {
                    if (data.httpStatus >= 500) {
                        throw new Error(data.error);
                    }
                    return;
                }

                // New replies go at the end, like the rest of the conversation
                data.replies.forEach(function (r) {
                    if (replyList.querySelector('[data-reply-id="' + r.id + '"]')) {
                        return; // already shown
                    }
                    var el = htmlToElements(r.html).firstElementChild;
                    el.classList.add('just-updated');
                    replyList.appendChild(el);
                });
                lastReplyId = Math.max(lastReplyId, data.last_id);

                replyList.hidden = data.count === 0;
                repliesEmpty.hidden = data.count > 0;
                document.querySelectorAll('[data-reply-count]').forEach(function (el) {
                    el.textContent = data.count;
                });
                thread.querySelector('[data-reply-word]').textContent = data.count === 1 ? 'Reply' : 'Replies';

                // The author marked a reply as the solution while this page was open.
                // The solution moves to the top and buttons change, so a refresh shows it best.
                if (data.status !== pageStatus) {
                    pageStatus = data.status;
                    if (data.status === 'Resolved') {
                        showThreadNotice('This question was just marked as resolved.', true);
                    }
                }
            });
        }

        replyPoller = poller(checkReplies, 5000);
    }

    // =====================================================================
    // 5. Voting on replies (no page reload, no confirmation popup)
    // =====================================================================
    // Listens on the whole page, so replies that arrive through live updates work too.
    // The server decides everything (one vote per reply, no voting on your own reply,
    // the reputation change); this code only shows the result it sends back.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form.matches('form[data-vote-form]')) {
            return;
        }
        event.preventDefault();

        var button = event.submitter;
        if (!button || form.classList.contains('is-loading')) {
            return; // a vote for this reply is already being saved
        }

        var reply = form.closest('[data-reply-id]');
        var errorBox = reply.querySelector('[data-vote-error]');
        var data = new FormData(form);
        data.append('vote', button.value);

        form.classList.add('is-loading');
        errorBox.hidden = true;

        postForm('api/vote.php', data).then(function (result) {
            if (!result.ok) {
                errorBox.textContent = result.error || 'Your vote could not be saved.';
                errorBox.hidden = false;
                return;
            }

            // Arrows: highlight the active vote (or none after removing it)
            form.querySelectorAll('.vote-btn').forEach(function (btn) {
                var active = btn.value === result.my_vote;
                btn.classList.toggle('active', active);
                btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            // This reply's score
            var score = form.querySelector('[data-vote-score]');
            score.textContent = result.score;
            score.classList.toggle('positive', result.score > 0);
            score.classList.toggle('negative', result.score < 0);

            // Every reputation label of the reply's author on this page
            document.querySelectorAll('[data-rep-user="' + result.author_id + '"]').forEach(function (label) {
                label.replaceWith(htmlToElements(result.rep_html));
            });
        }).catch(function () {
            errorBox.textContent = 'Could not reach the server. Check your connection and try again.';
            errorBox.hidden = false;
        }).then(function () {
            form.classList.remove('is-loading');
        });
    });

    // Export a little so other sections below can use the helpers
    window.RoomiePatchLive = {
        getJSON: getJSON,
        postForm: postForm,
        poller: poller,
        setBadge: setBadge,
        htmlToElements: htmlToElements
    };
})();
