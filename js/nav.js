/**
 * RailShot TV — Shared mobile navigation hamburger menu
 * Loaded on live.html and watch.html (pages that don't include app.js)
 */
(function () {
    function initMobileNav() {
        var hamburger = document.getElementById('navHamburger');
        var mobileOverlay = document.getElementById('navMobileOverlay');

        if (!hamburger || !mobileOverlay) return;

        function openMenu() {
            hamburger.classList.add('open');
            hamburger.setAttribute('aria-expanded', 'true');
            mobileOverlay.classList.add('open');
            mobileOverlay.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeMenu() {
            hamburger.classList.remove('open');
            hamburger.setAttribute('aria-expanded', 'false');
            mobileOverlay.classList.remove('open');
            mobileOverlay.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        hamburger.addEventListener('click', function () {
            if (hamburger.classList.contains('open')) {
                closeMenu();
            } else {
                openMenu();
            }
        });

        mobileOverlay.querySelectorAll('.nav-mobile-link').forEach(function (link) {
            link.addEventListener('click', closeMenu);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeMenu();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initMobileNav);
    } else {
        initMobileNav();
    }
})();
