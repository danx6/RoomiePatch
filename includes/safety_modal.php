<?php
// Meetup Liability & Safety Notice popup.
// Include this once on any page that has a form with the attribute data-safety-notice
// (sending a meetup request, accepting a meetup request).
// When that form is submitted, main.js shows this popup first. The form is only sent after
// the student ticks the checkbox and clicks "I Agree & Continue", and the server checks
// the acknowledgement again. Nothing is saved: the popup appears again for every new send or accept.
?>
<div class="modal-overlay" id="safety-modal" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="safety-modal-title" aria-describedby="safety-modal-text">
        <h2 class="modal-title" id="safety-modal-title">Meetup Liability &amp; Safety Notice</h2>

        <div class="modal-body" id="safety-modal-text">
            <h3 class="modal-section">Liability</h3>
            <ul class="modal-list">
                <li>Any damage, loss or issues involving personal belongings or devices during troubleshooting are entirely the responsibility of the students involved.</li>
                <li>Students take part in troubleshooting sessions voluntarily and at their own responsibility.</li>
                <li>The university does not take responsibility for damage resulting from voluntary student-to-student assistance.</li>
            </ul>

            <h3 class="modal-section">Safety &amp; designated areas</h3>
            <ul class="modal-list">
                <li>All face-to-face assistance sessions must take place strictly within university-designated areas.</li>
                <li>All students must acknowledge these conditions before going ahead with the meetup.</li>
            </ul>
            <p class="modal-highlight">The university strictly monitors all registered sessions for safety purposes.</p>
        </div>

        <label class="modal-check">
            <input type="checkbox" id="safety-modal-check">
            <span>I have read and accept the liability and safety conditions above.</span>
        </label>

        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" id="safety-modal-cancel">Cancel</button>
            <button type="button" class="btn btn-primary" id="safety-modal-agree" disabled>I Agree &amp; Continue</button>
        </div>
    </div>
</div>
