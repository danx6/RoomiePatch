<?php
// Meetup Safety Notice popup.
// Include this once on any page that has a form with the attribute data-safety-notice.
// When that form is submitted, main.js shows this popup first. The form is only sent after
// the student ticks the checkbox and clicks "I Agree & Continue".
// Nothing is saved: the popup appears again for every new send or accept.
?>
<div class="modal-overlay" id="safety-modal" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="safety-modal-title" aria-describedby="safety-modal-text">
        <h2 class="modal-title" id="safety-modal-title">Meetup Safety Notice</h2>

        <div class="modal-body" id="safety-modal-text">
            <p>The university is only responsible for safety and supervision within designated campus areas. Meetups outside the university campus are not recommended and are arranged at the students’ own responsibility.</p>
            <p>RoomiePatch does not guarantee the safety of meetups. Students are responsible for exercising caution and making their own safety decisions.</p>
            <p class="modal-highlight">Every meetup request on this platform is strictly monitored for students’ safety.</p>
        </div>

        <label class="modal-check">
            <input type="checkbox" id="safety-modal-check">
            <span>I have read and understood this safety notice.</span>
        </label>

        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" id="safety-modal-cancel">Cancel</button>
            <button type="button" class="btn btn-primary" id="safety-modal-agree" disabled>I Agree &amp; Continue</button>
        </div>
    </div>
</div>
