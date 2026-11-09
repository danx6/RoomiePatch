// RoomiePatch shared JavaScript.

// ---------- Theme toggle ----------
// Any button with the attribute data-theme-toggle switches light/dark.
// The choice is saved in localStorage so it stays after a refresh.
document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
    button.addEventListener('click', function () {
        var root = document.documentElement;
        var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-theme', next);
        try { localStorage.setItem('rp-theme', next); } catch (err) {}
    });
});

// ---------- Image preview for file inputs ----------
// <input type="file" data-image-preview="box-id" data-max-files="3">
// Shows small thumbnails of the chosen images before the form is sent.
// (The server still checks the files; this is only a preview.)
document.querySelectorAll('[data-image-preview]').forEach(function (input) {
    var box = document.getElementById(input.getAttribute('data-image-preview'));
    var max = parseInt(input.getAttribute('data-max-files'), 10) || 1;

    input.addEventListener('change', function () {
        box.innerHTML = '';
        var files = Array.prototype.slice.call(input.files);

        files.forEach(function (file) {
            var item = document.createElement('figure');
            item.className = 'image-preview-item';
            var img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.alt = '';
            var caption = document.createElement('figcaption');
            caption.textContent = file.name;
            item.appendChild(img);
            item.appendChild(caption);
            box.appendChild(item);
        });

        if (files.length > max) {
            var warning = document.createElement('p');
            warning.className = 'field-error';
            warning.textContent = 'You chose ' + files.length + ' images. Only ' + max + ' are allowed.';
            box.appendChild(warning);
        }
    });
});

// ---------- Image viewer ----------
// Any link with data-lightbox opens its image enlarged on top of the page,
// with an X button to close it. Clicking the dark background or pressing Escape also closes it.
// (Without JavaScript the link still works and simply opens the image.)
var viewer = null;

function openViewer(src) {
    if (!viewer) {
        viewer = document.createElement('div');
        viewer.className = 'lightbox';
        viewer.setAttribute('role', 'dialog');
        viewer.setAttribute('aria-modal', 'true');
        viewer.setAttribute('aria-label', 'Enlarged image');
        viewer.innerHTML =
            '<button type="button" class="lightbox-close" aria-label="Close image">' +
            '<svg class="icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" ' +
            'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
            '<path d="M6 6l12 12M18 6L6 18"/></svg></button>' +
            '<img class="lightbox-img" alt="Enlarged image">';
        document.body.appendChild(viewer);

        viewer.addEventListener('click', function (event) {
            // Close when clicking the X or the dark background, but not the image itself
            if (event.target === viewer || event.target.closest('.lightbox-close')) {
                closeViewer();
            }
        });
    }
    viewer.querySelector('.lightbox-img').src = src;
    viewer.classList.add('open');
    document.body.classList.add('lightbox-open');
    viewer.querySelector('.lightbox-close').focus();
}

function closeViewer() {
    if (viewer && viewer.classList.contains('open')) {
        viewer.classList.remove('open');
        document.body.classList.remove('lightbox-open');
        viewer.querySelector('.lightbox-img').removeAttribute('src');
    }
}

document.addEventListener('click', function (event) {
    var link = event.target.closest('a[data-lightbox]');
    if (link) {
        event.preventDefault();
        openViewer(link.getAttribute('href'));
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeViewer();
    }
});

// ---------- Meetup Liability & Safety Notice ----------
// Forms with data-safety-notice (send a meetup request, accept a request) show the
// safety popup first. The form is only sent after the checkbox is ticked and
// "I Agree & Continue" is clicked. The acknowledgement is never saved.
var safetyModal = document.getElementById('safety-modal');

if (safetyModal) {
    var safetyCheck = document.getElementById('safety-modal-check');
    var safetyAgree = document.getElementById('safety-modal-agree');
    var safetyCancel = document.getElementById('safety-modal-cancel');
    var pendingForm = null;      // the form waiting for the student's answer
    var lastFocused = null;      // where to put the keyboard focus back after closing

    function openSafetyModal(form) {
        pendingForm = form;
        form.querySelector('input[name="safety_ack"]').value = '';   // not acknowledged yet
        lastFocused = document.activeElement;
        safetyCheck.checked = false;          // always starts unticked
        safetyAgree.disabled = true;
        safetyModal.hidden = false;
        document.body.classList.add('lightbox-open');
        safetyCheck.focus();
    }

    function closeSafetyModal() {
        safetyModal.hidden = true;
        document.body.classList.remove('lightbox-open');
        pendingForm = null;
        if (lastFocused) {
            lastFocused.focus();
        }
    }

    // Listen on the whole page (not on each form), so Accept buttons on meetup cards
    // that arrive later through live updates also get the safety notice.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form.matches('form[data-safety-notice]')) {
            return;
        }
        // Second time round (after agreeing) the form is allowed through
        if (form.dataset.safetyAgreed === 'yes') {
            form.dataset.safetyAgreed = '';
            return;
        }
        event.preventDefault();
        // Point out empty required fields first, so the notice is not shown for a form that cannot be sent
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        openSafetyModal(form);
    });

    // The Agree button only works once the checkbox is ticked
    safetyCheck.addEventListener('change', function () {
        safetyAgree.disabled = !safetyCheck.checked;
    });

    safetyAgree.addEventListener('click', function () {
        if (!safetyCheck.checked || !pendingForm) {
            return;
        }
        var form = pendingForm;
        // Tell the server the notice was acknowledged for THIS action only
        form.querySelector('input[name="safety_ack"]').value = '1';
        form.dataset.safetyAgreed = 'yes';
        closeSafetyModal();
        form.requestSubmit();
    });

    safetyCancel.addEventListener('click', closeSafetyModal);

    // Clicking the dark background or pressing Escape also cancels
    safetyModal.addEventListener('click', function (event) {
        if (event.target === safetyModal) {
            closeSafetyModal();
        }
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !safetyModal.hidden) {
            closeSafetyModal();
        }
    });
}

// ---------- Chat ----------
// Start the message list scrolled to the newest message.
var chatMessages = document.getElementById('chat-messages');
if (chatMessages) {
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

// In the chat box: Enter sends, Shift+Enter adds a new line, and the box grows as you type.
document.querySelectorAll('[data-enter-submit]').forEach(function (textarea) {
    textarea.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            textarea.form.requestSubmit();
        }
    });
    textarea.addEventListener('input', function () {
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
    });
});

// ---------- Mobile menu ----------
// The menu button, the close button and the dark overlay all toggle the sidebar.
document.querySelectorAll('[data-menu-toggle]').forEach(function (el) {
    el.addEventListener('click', function () {
        document.body.classList.toggle('menu-open');
    });
});

// ---------- Dropdowns (user menu) ----------
function closeDropdowns(except) {
    document.querySelectorAll('.dropdown.open').forEach(function (dropdown) {
        if (dropdown !== except) {
            dropdown.classList.remove('open');
            dropdown.querySelector('[data-dropdown-toggle]').setAttribute('aria-expanded', 'false');
        }
    });
}

document.querySelectorAll('[data-dropdown-toggle]').forEach(function (button) {
    button.addEventListener('click', function (event) {
        event.stopPropagation();
        var dropdown = button.closest('.dropdown');
        closeDropdowns(dropdown);
        var isOpen = dropdown.classList.toggle('open');
        button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
});

// Clicking anywhere else, or pressing Escape, closes open dropdowns and the mobile menu.
document.addEventListener('click', function (event) {
    if (!event.target.closest('.dropdown-panel')) {
        closeDropdowns(null);
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeDropdowns(null);
        document.body.classList.remove('menu-open');
    }
});
