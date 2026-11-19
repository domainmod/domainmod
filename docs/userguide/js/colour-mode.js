// Colour mode (Light, Dark, or Auto), set on <html> before the first paint. Loaded in the <head>, not deferred.
(function () {
    'use strict';
    var KEY = 'domainmod-colour-mode';
    var media = window.matchMedia('(prefers-color-scheme: dark)');

    function stored() {
        try {
            var v = window.localStorage.getItem(KEY);
            return v === 'light' || v === 'dark' ? v : 'auto';
        } catch (e) {
            return 'auto';
        }
    }

    var choice = stored();

    function apply() {
        var mode = choice === 'auto' ? (media.matches ? 'dark' : 'light') : choice;
        document.documentElement.setAttribute('data-bs-theme', mode);
    }

    function mark() {
        var buttons = document.querySelectorAll('[data-dm-mode]');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].setAttribute('aria-pressed', buttons[i].getAttribute('data-dm-mode') === choice ? 'true' : 'false');
        }
    }

    apply();
    // Older Safari (iOS 13 and earlier) only has addListener, and calling addEventListener there would stop the menu
    function follow() {
        if (choice === 'auto') apply();
    }
    if (media.addEventListener) {
        media.addEventListener('change', follow);
    } else if (media.addListener) {
        media.addListener(follow);
    }

    document.addEventListener('DOMContentLoaded', function () {
        mark();
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-dm-mode]');
            if (!button) return;
            choice = button.getAttribute('data-dm-mode');
            try {
                window.localStorage.setItem(KEY, choice);
            } catch (e) {
                // No storage: the choice still applies to this page
            }
            apply();
            mark();
            // The menu closes after a choice, so focus goes back to its toggle rather than staying on a hidden item
            var dropdown = button.closest('.dropdown');
            var toggle = dropdown ? dropdown.querySelector('[data-bs-toggle="dropdown"]') : null;
            if (toggle) toggle.focus();
        });
    });
})();
