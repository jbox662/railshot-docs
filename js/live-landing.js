(function () {
    const headlineEl = document.getElementById('landingHeadline');
    const subtitleEl = document.getElementById('landingSubtitle');
    const bulletsEl = document.getElementById('landingBullets');
    const gridEl = document.getElementById('venueGrid');
    const emptyEl = document.getElementById('venueEmpty');

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderLanding(landing) {
        if (headlineEl && landing.headline) headlineEl.textContent = landing.headline;
        if (subtitleEl) subtitleEl.textContent = landing.subtitle || '';
        if (!bulletsEl) return;
        bulletsEl.innerHTML = (landing.bullets || []).map(function (bullet) {
            return '<li>' + escapeHtml(bullet) + '</li>';
        }).join('');
    }

    function renderVenues(venues) {
        if (!gridEl) return;
        if (!venues.length) {
            gridEl.innerHTML = '';
            if (emptyEl) emptyEl.classList.remove('hidden');
            return;
        }
        if (emptyEl) emptyEl.classList.add('hidden');

        gridEl.innerHTML = venues.map(function (venue) {
            const href = 'watch.html?venue=' + encodeURIComponent(venue.id);
            const liveBadge = venue.isLive
                ? '<span class="venue-card-live"><span class="venue-card-live-dot"></span> Live</span>'
                : '<span class="venue-card-offline">Offline</span>';
            const location = venue.location
                ? '<p class="venue-card-location">' + escapeHtml(venue.location) + '</p>'
                : '';
            const tableLabel = venue.tableCount === 1 ? '1 table' : venue.tableCount + ' tables';
            return (
                '<a class="venue-card shadow-pop" role="listitem" href="' + href + '">' +
                    '<div class="venue-card-image-wrap">' +
                        '<img class="venue-card-image" src="' + escapeHtml(venue.image || '/images/logo.png') + '" alt="">' +
                        liveBadge +
                    '</div>' +
                    '<div class="venue-card-body">' +
                        '<h3 class="venue-card-name">' + escapeHtml(venue.name) + '</h3>' +
                        location +
                        '<p class="venue-card-desc">' + escapeHtml(venue.description || venue.tagline || '') + '</p>' +
                        '<p class="venue-card-meta">' + escapeHtml(tableLabel) + ' · Watch now →</p>' +
                    '</div>' +
                '</a>'
            );
        }).join('');
    }

    function showSkeletons(count) {
        if (!gridEl) return;
        var html = '';
        for (var i = 0; i < count; i++) {
            html +=
                '<div class="venue-card-skeleton" aria-hidden="true">' +
                    '<div class="sk-image skeleton"></div>' +
                    '<div class="sk-body">' +
                        '<div class="sk-title skeleton"></div>' +
                        '<div class="sk-sub skeleton"></div>' +
                        '<div class="sk-desc skeleton"></div>' +
                        '<div class="sk-link skeleton"></div>' +
                    '</div>' +
                '</div>';
        }
        gridEl.innerHTML = html;
    }

    async function init() {
        // Show 3 skeleton cards while loading
        showSkeletons(3);

        try {
            const response = await fetch('/api/venues-config.php', { cache: 'no-store' });
            if (!response.ok) throw new Error('Failed to load venues');
            const data = await response.json();
            renderLanding(data.landing || {});
            renderVenues(data.venues || []);
        } catch (err) {
            console.warn('Venues config unavailable:', err);
            if (gridEl) gridEl.innerHTML = '';
            if (subtitleEl) {
                subtitleEl.textContent = 'Choose a venue below to watch live billiard tables in your browser.';
            }
            if (emptyEl) emptyEl.classList.remove('hidden');
        }
    }

    init();
})();
