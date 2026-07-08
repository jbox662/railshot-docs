(function () {
    const messageEl = document.getElementById('adminMessage');
    const venuesContainer = document.getElementById('venuesContainer');
    const mediamtxYamlEl = document.getElementById('mediamtxYaml');
    let venues = Array.isArray(window.RAILSHOT_ADMIN_VENUES) ? window.RAILSHOT_ADMIN_VENUES : [];

    function showMessage(text, isError) {
        if (!messageEl) return;
        messageEl.textContent = text;
        messageEl.classList.remove('hidden', 'error');
        if (isError) messageEl.classList.add('error');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function tableOptionsHtml(tables, selectedId) {
        return (tables || []).map(function (table) {
            const id = table.id || '';
            const label = (table.name || id) + (id ? ' (' + id + ')' : '');
            const selected = id === selectedId ? ' selected' : '';
            return '<option value="' + escapeHtml(id) + '"' + selected + '>' + escapeHtml(label) + '</option>';
        }).join('');
    }

    function renderVenues() {
        if (!venuesContainer) return;

        venuesContainer.innerHTML = venues.map(function (venue, venueIndex) {
            const tables = venue.tables || [];
            const activeTableId = venue.activeTableId || (tables[0] && tables[0].id) || '';

            const tablesHtml = tables.map(function (table, tableIndex) {
                return (
                    '<div class="admin-table-card admin-nested-table" data-venue="' + venueIndex + '" data-table="' + tableIndex + '">' +
                        '<div class="admin-table-card-header">' +
                            '<strong>Camera ' + (tableIndex + 1) + '</strong>' +
                            '<button type="button" class="admin-remove-btn" data-remove-table data-venue="' + venueIndex + '" data-table="' + tableIndex + '">Remove</button>' +
                        '</div>' +
                        '<div class="admin-table-card-grid">' +
                            '<label>Path ID <input data-venue-field="tables" data-table-field="id" data-venue="' + venueIndex + '" data-table="' + tableIndex + '" value="' + escapeHtml(table.id || '') + '" placeholder="table1"></label>' +
                            '<label>Display name <input data-venue-field="tables" data-table-field="name" data-venue="' + venueIndex + '" data-table="' + tableIndex + '" value="' + escapeHtml(table.name || '') + '"></label>' +
                            '<label class="full-width">Description <input data-venue-field="tables" data-table-field="description" data-venue="' + venueIndex + '" data-table="' + tableIndex + '" value="' + escapeHtml(table.description || '') + '"></label>' +
                            '<label class="full-width">RTSP URL <input data-venue-field="tables" data-table-field="rtspUrl" data-venue="' + venueIndex + '" data-table="' + tableIndex + '" value="' + escapeHtml(table.rtspUrl || '') + '"></label>' +
                        '</div>' +
                    '</div>'
                );
            }).join('');

            return (
                '<div class="admin-table-card admin-venue-card" data-venue-index="' + venueIndex + '">' +
                    '<div class="admin-table-card-header">' +
                        '<strong>Venue ' + (venueIndex + 1) + '</strong>' +
                        '<button type="button" class="admin-remove-btn" data-remove-venue="' + venueIndex + '">Remove venue</button>' +
                    '</div>' +
                    '<div class="admin-table-card-grid">' +
                        '<label>Venue ID (URL) <input data-venue-field="id" data-venue="' + venueIndex + '" value="' + escapeHtml(venue.id || '') + '" placeholder="main-hall"></label>' +
                        '<label>Venue name <input data-venue-field="name" data-venue="' + venueIndex + '" value="' + escapeHtml(venue.name || '') + '"></label>' +
                        '<label>Location <input data-venue-field="location" data-venue="' + venueIndex + '" value="' + escapeHtml(venue.location || '') + '"></label>' +
                        '<label>Tagline <input data-venue-field="tagline" data-venue="' + venueIndex + '" value="' + escapeHtml(venue.tagline || '') + '" placeholder="Live now"></label>' +
                        '<label class="full-width">Description <textarea data-venue-field="description" data-venue="' + venueIndex + '" rows="2">' + escapeHtml(venue.description || '') + '</textarea></label>' +
                        '<label class="full-width">Card image URL <input data-venue-field="image" data-venue="' + venueIndex + '" value="' + escapeHtml(venue.image || '/images/logo.png') + '"></label>' +
                        '<label class="full-width">On-air camera (viewers see this) ' +
                            '<select data-venue-field="activeTableId" data-venue="' + venueIndex + '">' + tableOptionsHtml(tables, activeTableId) + '</select>' +
                        '</label>' +
                    '</div>' +
                    '<div class="admin-nested-header">' +
                        '<h4>Cameras / tables</h4>' +
                        '<button type="button" class="btn btn-secondary btn-small" data-add-table="' + venueIndex + '">+ Add camera</button>' +
                    '</div>' +
                    tablesHtml +
                '</div>'
            );
        }).join('');

        venuesContainer.querySelectorAll('[data-remove-venue]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                venues.splice(Number(btn.getAttribute('data-remove-venue')), 1);
                renderVenues();
            });
        });

        venuesContainer.querySelectorAll('[data-remove-table]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const vi = Number(btn.getAttribute('data-venue'));
                const ti = Number(btn.getAttribute('data-table'));
                venues[vi].tables.splice(ti, 1);
                renderVenues();
            });
        });

        venuesContainer.querySelectorAll('[data-add-table]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const vi = Number(btn.getAttribute('data-add-table'));
                const next = (venues[vi].tables || []).length + 1;
                venues[vi].tables = venues[vi].tables || [];
                venues[vi].tables.push({
                    id: 'table' + next,
                    name: 'Table ' + next,
                    description: '',
                    rtspUrl: ''
                });
                renderVenues();
            });
        });

        venuesContainer.querySelectorAll('[data-venue-field]').forEach(function (input) {
            input.addEventListener('input', function () {
                const vi = Number(input.getAttribute('data-venue'));
                const field = input.getAttribute('data-venue-field');
                const tableField = input.getAttribute('data-table-field');
                if (field === 'tables') {
                    const ti = Number(input.getAttribute('data-table'));
                    venues[vi].tables[ti][tableField] = input.value;
                } else {
                    venues[vi][field] = input.value;
                }
            });
            input.addEventListener('change', function () {
                if (input.getAttribute('data-venue-field') === 'activeTableId') {
                    const vi = Number(input.getAttribute('data-venue'));
                    venues[vi].activeTableId = input.value;
                }
            });
        });
    }

    function readLandingForm() {
        const form = document.getElementById('landingForm');
        const data = new FormData(form);
        const bullets = String(data.get('bullets') || '')
            .split('\n')
            .map(function (line) { return line.trim(); })
            .filter(Boolean);
        return {
            headline: data.get('headline'),
            subtitle: data.get('subtitle'),
            bullets: bullets
        };
    }

    function readLiveForm() {
        const form = document.getElementById('liveForm');
        const data = new FormData(form);
        return {
            section: 'live',
            mediamtxHost: data.get('mediamtxHost'),
            preferredProtocol: data.get('preferredProtocol'),
            useHttpsProxy: form.querySelector('[name="useHttpsProxy"]').checked,
            landing: readLandingForm(),
            venues: venues
        };
    }

    function readSiteForm() {
        const form = document.getElementById('siteForm');
        const data = new FormData(form);
        return {
            section: 'site',
            heroTitle: data.get('heroTitle'),
            heroSubtitle: data.get('heroSubtitle'),
            contactEmail: data.get('contactEmail'),
            downloadNote: data.get('downloadNote')
        };
    }

    async function postSave(payload) {
        const response = await fetch('/admin/api/save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        });

        const text = await response.text();
        let result = null;
        try {
            result = text ? JSON.parse(text) : null;
        } catch (err) {
            throw new Error('Server returned an invalid response. Check that admin/api/save.php is uploaded and PHP is enabled.');
        }

        if (!response.ok || !result || !result.ok) {
            throw new Error((result && result.error) || 'Save failed (HTTP ' + response.status + ')');
        }
        return result;
    }

    document.getElementById('addVenueBtn')?.addEventListener('click', function () {
        const next = venues.length + 1;
        venues.push({
            id: 'venue-' + next,
            name: 'Venue ' + next,
            location: '',
            description: '',
            tagline: 'Live now',
            image: '/images/logo.png',
            activeTableId: 'table1',
            tables: [{
                id: 'table1',
                name: 'Table 1',
                description: '',
                rtspUrl: ''
            }]
        });
        renderVenues();
    });

    document.getElementById('saveLiveBtn')?.addEventListener('click', async function () {
        try {
            const result = await postSave(readLiveForm());
            if (result.mediamtxYaml && mediamtxYamlEl) {
                mediamtxYamlEl.value = result.mediamtxYaml;
            }
            showMessage('Live settings saved. Venues updated on live.html.');
        } catch (err) {
            showMessage(err.message, true);
        }
    });

    document.getElementById('saveSiteBtn')?.addEventListener('click', async function () {
        try {
            await postSave(readSiteForm());
            showMessage('Site content saved.');
        } catch (err) {
            showMessage(err.message, true);
        }
    });

    document.getElementById('savePasswordBtn')?.addEventListener('click', async function () {
        const form = document.getElementById('passwordForm');
        const data = new FormData(form);
        try {
            await postSave({
                section: 'password',
                currentPassword: data.get('currentPassword'),
                newPassword: data.get('newPassword'),
                confirmPassword: data.get('confirmPassword')
            });
            form.reset();
            showMessage('Admin password updated.');
        } catch (err) {
            showMessage(err.message, true);
        }
    });

    document.getElementById('copyYamlBtn')?.addEventListener('click', function () {
        if (!mediamtxYamlEl) return;
        navigator.clipboard.writeText(mediamtxYamlEl.value).then(function () {
            showMessage('MediaMTX YAML copied to clipboard.');
        });
    });

    renderVenues();
})();
