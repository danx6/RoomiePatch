<?php
// Put this inside <head> on every page, before the stylesheet.
// It applies the saved theme straight away, so the page does not flash
// light before switching to dark.
?>
<script>
    (function () {
        var theme = null;
        try { theme = localStorage.getItem('rp-theme'); } catch (err) {}
        if (theme !== 'light' && theme !== 'dark') {
            theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-theme', theme);
    })();
</script>
