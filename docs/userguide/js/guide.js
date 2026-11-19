// The User Guide's scripts, run at the end of the page and before MkDocs' search script. No inline scripts anywhere, so
// the guide needs no 'unsafe-inline' in its CSP.
(function () {
    'use strict';

    // MkDocs' search script (search/main.js) reads a global base_url, the path from this page to the guide's root. The
    // theme puts it on <html data-base-url="..."> rather than in an inline script.
    window.base_url = document.documentElement.getAttribute('data-base-url') || '.';

    // Plausible only on domainmod.org itself, never in the copy of the guide inside the DomainMOD app or on a dev copy.
    // The same script and settings as the site's page-bottom include.
    if (window.location.hostname === 'domainmod.org') {
        // Plausible's queue, so events sent before its script loads aren't lost
        window.plausible = window.plausible || function () {
            (window.plausible.q = window.plausible.q || []).push(arguments);
        };
        var analytics = document.createElement('script');
        analytics.defer = true;
        analytics.setAttribute('data-api', 'https://api.h1ggs.net/v1/content');
        analytics.setAttribute('data-domain', 'domainmod.org');
        analytics.src = 'https://api.h1ggs.net/request.js';
        document.head.appendChild(analytics);
    }

    // MkDocs' search refreshes its results on keyup, so clearing the field with the browser's own clear button (which
    // fires input and search, but no keyup) would leave the old results showing. This passes those on as a keyup.
    var query = document.getElementById('mkdocs-search-query');
    if (query) {
        ['input', 'search'].forEach(function (type) {
            query.addEventListener(type, function () {
                if (query.value === '') query.dispatchEvent(new KeyboardEvent('keyup'));
            });
        });
    }

    // MkDocs' search lists a page and each of its sections as separate results, so a word in one section matches twice
    // (the section, and the whole page). A whole-page result is dropped when a section of that page ranks above it (a
    // page that ranks first stays, as the best match), and each section result shows the page it's on above its title,
    // such as "Getting Started" over "Downloading". The page names come from the sidebar's page list. Then a screen
    // reader hears how many results there are, once they settle, rather than every result.
    var results = document.getElementById('mkdocs-search-results');
    var status = document.getElementById('dm-search-status');
    if (results && window.MutationObserver) {
        var pageNames = {};
        Array.prototype.forEach.call(document.querySelectorAll('#dm-guide-pages a[href]'), function (link) {
            pageNames[new URL(link.getAttribute('href'), window.location.href).pathname] = link.textContent.trim();
        });
        var tidying = false;
        var timer;
        var refill;
        var announced = null;
        var tidy = function () {
            var articles = Array.prototype.slice.call(results.querySelectorAll('article'));
            // MkDocs stores some titles with their HTML entities already escaped, then escapes them again, so a heading such as
            // "Questions, Problems & Feature Requests" shows as "&amp;". Each title's text is put back as plain text.
            Array.prototype.forEach.call(results.querySelectorAll('article h3 a'), function (link) {
                if (link.childNodes.length === 1 && link.firstChild.nodeType === 3 && /&(amp|lt|gt|quot|#39);/.test(link.textContent)) {
                    var decoder = document.createElement('textarea');
                    decoder.innerHTML = link.textContent;
                    link.textContent = decoder.value;
                }
            });
            // Each title's text goes in its own span, so only the title is underlined on hover, not the page name above it
            Array.prototype.forEach.call(results.querySelectorAll('article h3 a'), function (link) {
                if (link.querySelector('.dm-guide-result-title')) return;
                var title = document.createElement('span');
                title.className = 'dm-guide-result-title';
                while (link.firstChild) title.appendChild(link.firstChild);
                link.appendChild(title);
            });
            // Results are in rank order, so a section seen before its page's own result outranks it
            var sectionSeen = {};
            var pageSeen = {};
            articles.forEach(function (article) {
                var link = article.querySelector('h3 a');
                if (!link) return;
                var url = new URL(link.getAttribute('href'), window.location.href);
                if (url.hash === '') {
                    if (sectionSeen[url.pathname]) {
                        article.remove();
                    } else {
                        pageSeen[url.pathname] = true;
                    }
                    return;
                }
                // A page's top heading (named like the page) is the page itself: shown once, with no page name before it
                if (link.querySelector('.dm-guide-result-page')) return;
                if (link.textContent.trim() === pageNames[url.pathname]) {
                    // Dropped if the page already showed, or if a higher-ranked section of it displaced the page result
                    if (pageSeen[url.pathname] || sectionSeen[url.pathname]) {
                        article.remove();
                    } else {
                        sectionSeen[url.pathname] = true;
                        pageSeen[url.pathname] = true;
                    }
                    return;
                }
                sectionSeen[url.pathname] = true;
                if (!pageNames[url.pathname]) return;
                // The page name sits on its own line above the title, inside the link, so the link's accessible name
                // includes the page. A visually hidden comma separates the two, so they're read as a pause, not run together.
                var label = document.createElement('span');
                label.className = 'dm-guide-result-page';
                label.appendChild(document.createTextNode(pageNames[url.pathname]));
                var comma = document.createElement('span');
                comma.className = 'visually-hidden';
                comma.textContent = ', ';
                label.appendChild(comma);
                link.insertBefore(label, link.firstChild);
            });
        };
        new MutationObserver(function () {
            if (tidying) return;
            tidying = true;
            tidy();
            tidying = false;
            if (!status) return;
            clearTimeout(timer);
            clearTimeout(refill);
            timer = setTimeout(function () {
                // What's read out is the query and its result together, so a new query or a changed result (such as a
                // late worker reply) is announced, while a redraw of the same results for the same query (the arrow keys
                // make MkDocs redraw) isn't read out again
                var field = document.getElementById('mkdocs-search-query');
                var count = results.querySelectorAll('article').length;
                var message = count === 1 ? '1 result.' : count > 1 ? count + ' results.' : results.textContent.trim();
                var key = (field ? field.value : '') + '\n' + message;
                if (key === announced) return;
                // Emptied first, so a new query with the same count is still heard
                status.textContent = '';
                refill = setTimeout(function () {
                    status.textContent = message;
                    announced = key;
                }, 100);
            }, 600);
        }).observe(results, { childList: true, subtree: true });
    }

    // Escape closes the phone menu and puts focus back on its toggle button, as on the site (assets/js/site.js). It
    // listens on window in the capture phase, since Bootstrap's own delegated handlers listen on document in the capture
    // phase and would close an open colour-mode menu before this could see it.
    window.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape' || !window.bootstrap) return;
        var menu = document.getElementById('dm-menu');
        // An open colour-mode menu closes first, on its own
        if (!menu || !menu.classList.contains('show') || menu.querySelector('.dropdown-menu.show')) return;
        // Only while focus is in the header, so Escape never pulls focus back from the page, and only at phone widths,
        // where the toggle shows (the menu keeps its open state when the window widens)
        var header = menu.closest('header');
        var toggle = document.querySelector('[aria-controls="dm-menu"]');
        if (!header || !header.contains(document.activeElement) || !toggle || !toggle.offsetParent) return;
        window.bootstrap.Collapse.getOrCreateInstance(menu, { toggle: false }).hide();
        toggle.focus();
    }, true);
})();
