(function () {
    const messageEl = document.getElementById('adminMessage');
    const venuesContainer = document.getElementById('venuesContainer');
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
                            '<label class="full-width">YouTube Channel ID <input data-venue-field="tables" data-table-field="youtubeChannelId" data-venue="' + venueIndex + '" data-table="' + tableIndex + '" value="' + escapeHtml(table.youtubeChannelId || '') + '" placeholder="UCxH4xyXjhMNDR_fLuirDOtA">' +
                                '<span class="admin-field-hint">Enter the YouTube Channel ID (starts with UC...) — found in YouTube Studio &rarr; Settings &rarr; Channel &rarr; Advanced. The system auto-finds the live stream. <strong>Enter this once, never update it again.</strong></span>' +
                            '</label>' +
                            '<label class="full-width">YouTube URL (manual fallback) <input data-venue-field="tables" data-table-field="youtubeUrl" data-venue="' + venueIndex + '" data-table="' + tableIndex + '" value="' + escapeHtml(table.youtubeUrl || '') + '" placeholder="Leave blank if Channel ID is set above">' +
                                '<span class="admin-field-hint">Optional. Only needed if auto-detection is not working. Paste a youtube.com/live/ID URL here.</span>' +
                            '</label>' +
                            '<label class="full-width">Scoreholio Overlay URL (for this table)' +
                                '<input data-venue-field="tables" data-table-field="overlayUrl" data-venue="' + venueIndex + '" data-table="' + tableIndex + '" value="' + escapeHtml(table.overlayUrl || '') + '" placeholder="https://app.scoreholio.com/v2/billiards/overlay?type=widget-a&amp;account=...&amp;court=' + (tableIndex + 1) + '">' +
                                '<span class="admin-field-hint">Leave blank to use the venue-level overlay URL. Set this to override with a table-specific scoreboard (e.g. court=' + (tableIndex + 1) + ').</span>' +
                            '</label>' +
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
                        '<label class="full-width">Venue Card Image' +
                            '<div class="admin-image-upload-row">' +
                                '<input data-venue-field="image" data-venue="' + venueIndex + '" value="' + escapeHtml(venue.image || '/images/logo.png') + '" placeholder="/images/logo.png">' +
                                '<button type="button" class="btn btn-secondary btn-small admin-upload-btn" data-upload-venue="' + venueIndex + '">Upload Image</button>' +
                                '<input type="file" class="admin-upload-file-input" data-upload-file-venue="' + venueIndex + '" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none">' +
                            '</div>' +
                            '<div class="admin-image-preview-wrap" data-preview-venue="' + venueIndex + '">' +
                                (venue.image && venue.image !== '/images/logo.png' ? '<img class="admin-image-preview" src="' + escapeHtml(venue.image) + '" alt="Venue preview">' : '') +
                            '</div>' +
                        '</label>' +
                        '<label class="full-width">On-air camera (viewers see this) ' +
                            '<select data-venue-field="activeTableId" data-venue="' + venueIndex + '">' + tableOptionsHtml(tables, activeTableId) + '</select>' +
                        '</label>' +
                        '<div class="admin-overlay-section">' +
                            '<div class="admin-overlay-header">' +
                                '<strong>Scoreboard Overlay</strong>' +
                                '<label class="admin-toggle-label">' +
                                    '<input type="checkbox" class="admin-overlay-toggle" data-venue-field="overlayEnabled" data-venue="' + venueIndex + '"' + (venue.overlayEnabled ? ' checked' : '') + '>' +
                                    '<span class="admin-toggle-track"><span class="admin-toggle-thumb"></span></span>' +
                                    '<span>' + (venue.overlayEnabled ? 'Enabled' : 'Disabled') + '</span>' +
                                '</label>' +
                            '</div>' +
                            '<label class="full-width admin-overlay-url-label' + (venue.overlayEnabled ? '' : ' hidden') + '">' +
                                'Overlay URL (Scoreholio or custom browser source)' +
                                '<input data-venue-field="overlayUrl" data-venue="' + venueIndex + '" value="' + escapeHtml(venue.overlayUrl || '') + '" placeholder="https://app.scoreholio.com/v2/billiards/overlay?type=widget-a&amp;account=...">' +
                                '<span class="admin-field-hint">Paste your Scoreholio overlay URL here. Viewers will see it live on the stream.</span>' +
                            '</label>' +
                        '</div>' +
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
                    youtubeChannelId: '',
                    youtubeUrl: '',
                    overlayUrl: ''
                });
                renderVenues();
            });
        });

        // Upload button click → trigger hidden file input
        venuesContainer.querySelectorAll('.admin-upload-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var vi = Number(btn.getAttribute('data-upload-venue'));
                var fileInput = venuesContainer.querySelector('[data-upload-file-venue="' + vi + '"]');
                if (fileInput) fileInput.click();
            });
        });

        // File selected → upload to server
        venuesContainer.querySelectorAll('.admin-upload-file-input').forEach(function (fileInput) {
            fileInput.addEventListener('change', async function () {
                var vi = Number(fileInput.getAttribute('data-upload-file-venue'));
                var file = fileInput.files[0];
                if (!file) return;

                var uploadBtn = venuesContainer.querySelector('[data-upload-venue="' + vi + '"]');
                if (uploadBtn) {
                    uploadBtn.disabled = true;
                    uploadBtn.textContent = 'Uploading…';
                }

                try {
                    var formData = new FormData();
                    formData.append('image', file);
                    var response = await fetch('/admin/api/upload-image.php', {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData
                    });
                    var result = await response.json();
                    if (!response.ok || !result.ok) {
                        throw new Error(result.error || 'Upload failed');
                    }

                    // Update the URL input and in-memory venues array
                    var urlInput = venuesContainer.querySelector('[data-venue-field="image"][data-venue="' + vi + '"]');
                    if (urlInput) urlInput.value = result.url;
                    venues[vi].image = result.url;

                    // Update the preview image
                    var previewWrap = venuesContainer.querySelector('[data-preview-venue="' + vi + '"]');
                    if (previewWrap) {
                        previewWrap.innerHTML = '<img class="admin-image-preview" src="' + result.url + '" alt="Venue preview">';
                    }

                    showMessage('Image uploaded successfully. Click "Save live settings" to apply.');
                } catch (err) {
                    showMessage('Image upload failed: ' + err.message, true);
                } finally {
                    if (uploadBtn) {
                        uploadBtn.disabled = false;
                        uploadBtn.textContent = 'Upload Image';
                    }
                    fileInput.value = '';
                }
            });
        });

        // Overlay toggle — show/hide the URL input and update venues array
        venuesContainer.querySelectorAll('.admin-overlay-toggle').forEach(function (toggle) {
            toggle.addEventListener('change', function () {
                var vi = Number(toggle.getAttribute('data-venue'));
                venues[vi].overlayEnabled = toggle.checked;
                var section = toggle.closest('.admin-overlay-section');
                if (section) {
                    var lbl = section.querySelector('.admin-overlay-url-label');
                    if (lbl) lbl.classList.toggle('hidden', !toggle.checked);
                }
                var statusSpan = toggle.parentElement.querySelector('span:last-child');
                if (statusSpan) statusSpan.textContent = toggle.checked ? 'Enabled' : 'Disabled';
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
                } else if (input.type === 'checkbox') {
                    venues[vi][field] = input.checked;
                } else {
                    venues[vi][field] = input.value;
                }
            });
            input.addEventListener('change', function () {
                const vi = Number(input.getAttribute('data-venue'));
                const field = input.getAttribute('data-venue-field');
                if (field === 'activeTableId') {
                    venues[vi].activeTableId = input.value;
                } else if (input.type === 'checkbox') {
                    venues[vi][field] = input.checked;
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
        return {
            section: 'live',
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

    // Operator PIN save
    document.getElementById('saveOperatorPinBtn')?.addEventListener('click', async function () {
        var form = document.getElementById('operatorPinForm');
        var newPin = form.querySelector('[name="newPin"]').value.trim();
        var confirmPin = form.querySelector('[name="confirmPin"]').value.trim();
        if (!newPin) { showMessage('Please enter a PIN.', true); return; }
        if (newPin !== confirmPin) { showMessage('PINs do not match.', true); return; }
        if (newPin.length < 4) { showMessage('PIN must be at least 4 digits.', true); return; }
        try {
            var res = await fetch('/api/operator-set-pin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ newPin: newPin, confirmPin: confirmPin })
            });
            var result = await res.json();
            if (!res.ok || !result.ok) throw new Error(result.error || 'Save failed');
            showMessage('Operator PIN updated successfully.');
            form.reset();
        } catch (err) {
            showMessage('Error: ' + err.message, true);
        }
    });

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
                youtubeChannelId: '',
                youtubeUrl: '',
                overlayUrl: ''
            }]
        });
        renderVenues();
    });

    document.getElementById('saveLiveBtn')?.addEventListener('click', async function () {
        try {
            await postSave(readLiveForm());
            showMessage('Live settings saved. Venues updated on live.html.');
        } catch (err) {
            showMessage(err.message, true);
        }
    });

    // ── YouTube API Settings ─────────────────────────────────────────────────
    document.getElementById('saveYoutubeSettingsBtn')?.addEventListener('click', async function () {
        const apiKey = (document.getElementById('youtubeApiKeyInput')?.value || '').trim();
        try {
            await postSave({ section: 'youtube', apiKey: apiKey });
            showMessage('YouTube API key saved. Auto-detection is now active for cameras with a Channel ID.');
        } catch (err) {
            showMessage('Error: ' + err.message, true);
        }
    });

    document.getElementById('testYoutubeApiBtn')?.addEventListener('click', async function () {
        const resultEl = document.getElementById('youtubeApiTestResult');
        const apiKey = (document.getElementById('youtubeApiKeyInput')?.value || '').trim();
        if (!apiKey) {
            if (resultEl) resultEl.textContent = 'Enter an API key first.';
            return;
        }
        if (resultEl) resultEl.textContent = 'Testing…';
        try {
            const res = await fetch('/api/youtube-test.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ apiKey: apiKey })
            });
            const result = await res.json();
            if (result.ok) {
                if (resultEl) resultEl.innerHTML = '<span style="color:#22c55e">✓ API key is valid and working!</span>';
            } else {
                if (resultEl) resultEl.innerHTML = '<span style="color:#ef4444">✗ ' + (result.error || 'Invalid key') + '</span>';
            }
        } catch (err) {
            if (resultEl) resultEl.innerHTML = '<span style="color:#ef4444">✗ Test failed: ' + err.message + '</span>';
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

    // ── Stream Control Panel ───────────────────────────────────────────────────
    const streamControlEl = document.getElementById('streamControlVenues');

    async function renderStreamControl() {
        if (!streamControlEl) return;

        // Fetch current state from admin-live API for each venue
        const venueList = Array.isArray(window.RAILSHOT_ADMIN_VENUES) ? window.RAILSHOT_ADMIN_VENUES : [];

        if (!venueList.length) {
            streamControlEl.innerHTML = '<p class="admin-hint">No venues configured yet. Add a venue below first.</p>';
            return;
        }

        // Fetch live state for the first venue (or all, one at a time)
        streamControlEl.innerHTML = venueList.map(function (venue, vi) {
            const tables = venue.tables || [];
            const activeId = venue.activeTableId || '';
            const isLive = activeId !== '';
            const tableButtons = tables.map(function (t) {
                const isActive = t.id === activeId;
                return '<button type="button" class="stream-ctrl-table-btn' + (isActive ? ' active' : '') + '" data-venue-id="' + escapeHtml(venue.id || '') + '" data-table-id="' + escapeHtml(t.id) + '">' +
                    (isActive ? '\u25cf On Air: ' : '') + escapeHtml(t.name) +
                '</button>';
            }).join('');

            return '<div class="stream-ctrl-venue" data-venue-idx="' + vi + '">' +
                '<div class="stream-ctrl-venue-header">' +
                    '<span class="stream-ctrl-venue-name">' + escapeHtml(venue.name || venue.id || 'Venue ' + (vi + 1)) + '</span>' +
                    '<span class="stream-ctrl-badge ' + (isLive ? 'stream-ctrl-badge-live' : 'stream-ctrl-badge-off') + '">' +
                        (isLive ? '\u25cf On Air' : '\u25cb Off Air') +
                    '</span>' +
                '</div>' +
                '<div class="stream-ctrl-actions">' +
                    tableButtons +
                    '<button type="button" class="stream-ctrl-stop-btn' + (!isLive ? ' hidden' : '') + '" data-venue-id="' + escapeHtml(venue.id || '') + '" data-table-id="__none__">\u25a0 Stop Stream</button>' +
                '</div>' +
                '<span class="stream-ctrl-status" id="stream-ctrl-status-' + vi + '"></span>' +
            '</div>';
        }).join('');

        // Wire up buttons
        streamControlEl.querySelectorAll('[data-table-id]').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                const venueId = btn.getAttribute('data-venue-id');
                const tableId = btn.getAttribute('data-table-id');
                const vi = Number(btn.closest('[data-venue-idx]').getAttribute('data-venue-idx'));
                const statusEl = document.getElementById('stream-ctrl-status-' + vi);

                streamControlEl.querySelectorAll('[data-table-id]').forEach(function (b) { b.disabled = true; });
                if (statusEl) statusEl.textContent = 'Updating\u2026';

                try {
                    const res = await fetch('/api/admin-live.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ venue: venueId, tableId: tableId })
                    });
                    const result = await res.json();
                    if (!res.ok || !result.ok) throw new Error(result.error || 'Failed');

                    // Update local venues array so the UI reflects the change
                    const newActiveId = result.activeTableId || '';
                    if (window.RAILSHOT_ADMIN_VENUES) {
                        window.RAILSHOT_ADMIN_VENUES[vi].activeTableId = newActiveId;
                        venues[vi].activeTableId = newActiveId;
                    }

                    if (statusEl) {
                        statusEl.textContent = tableId === '__none__' ? 'Stream stopped.' : 'Now on air: ' + tableId;
                        setTimeout(function () { if (statusEl) statusEl.textContent = ''; }, 3000);
                    }
                    renderStreamControl();
                    renderVenues(); // keep the on-air dropdown in sync
                } catch (err) {
                    if (statusEl) statusEl.textContent = 'Error: ' + err.message;
                } finally {
                    streamControlEl.querySelectorAll('[data-table-id]').forEach(function (b) { b.disabled = false; });
                }
            });
        });
    }

    renderVenues();
    renderStreamControl();
})();
