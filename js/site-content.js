/**
 * Applies editable site content from /api/site-config.php
 */
(function () {
    const map = {
        heroTitle: document.querySelector('.hero-title'),
        heroSubtitle: document.querySelector('.hero-subtitle'),
        downloadNote: document.querySelector('.download-note')
    };

    function applySiteConfig(data) {
        if (!data) return;
        if (map.heroTitle && data.heroTitle) map.heroTitle.textContent = data.heroTitle;
        if (map.heroSubtitle && data.heroSubtitle) map.heroSubtitle.textContent = data.heroSubtitle;
        if (map.downloadNote && data.downloadNote) map.downloadNote.textContent = data.downloadNote;

        if (data.contactEmail) {
            document.querySelectorAll('[data-site-email]').forEach(function (el) {
                if (el.tagName === 'A') {
                    el.href = 'mailto:' + data.contactEmail;
                    el.textContent = data.contactEmail;
                }
            });
        }
    }

    fetch('/api/site-config.php', { cache: 'no-store' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(applySiteConfig)
        .catch(function () {});
})();
