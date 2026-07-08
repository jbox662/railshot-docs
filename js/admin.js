(function () {
    const messageEl = document.getElementById('adminMessage');
    const tablesContainer = document.getElementById('tablesContainer');
    const mediamtxYamlEl = document.getElementById('mediamtxYaml');
    const activeTableSelect = document.getElementById('activeTableId');
    let tables = Array.isArray(window.RAILSHOT_ADMIN_TABLES) ? window.RAILSHOT_ADMIN_TABLES : [];
    let activeTableId = String(window.RAILSHOT_ACTIVE_TABLE_ID || '');

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

    function renderActiveTableSelect() {
        if (!activeTableSelect) return;
        const validIds = tables.map(function (t) { return t.id; }).filter(Boolean);
        if (activeTableId === '' || validIds.indexOf(activeTableId) === -1) {
            activeTableId = validIds[0] || '';
        }
        activeTableSelect.innerHTML = tables.map(function (table) {
            const id = table.id || '';
            const label = (table.name || id) + (id ? ' (' + id + ')' : '');
            const selected = id === activeTableId ? ' selected' : '';
            return '<option value="' + escapeHtml(id) + '"' + selected + '>' + escapeHtml(label) + '</option>';
        }).join('');
        if (!tables.length) {
            activeTableSelect.innerHTML = '<option value="">No tables configured</option>';
        }
    }

    function renderTables() {
        if (!tablesContainer) return;
        tablesContainer.innerHTML = tables.map(function (table, index) {
            return (
                '<div class="admin-table-card" data-index="' + index + '">' +
                    '<div class="admin-table-card-header">' +
                        '<strong>Table ' + (index + 1) + '</strong>' +
                        '<button type="button" class="admin-remove-btn" data-remove="' + index + '">Remove</button>' +
                    '</div>' +
                    '<div class="admin-table-card-grid">' +
                        '<label>Path ID (MediaMTX) <input data-field="id" value="' + escapeHtml(table.id || '') + '" placeholder="table1"></label>' +
                        '<label>Display name <input data-field="name" value="' + escapeHtml(table.name || '') + '" placeholder="Table 1"></label>' +
                        '<label class="full-width">Description <input data-field="description" value="' + escapeHtml(table.description || '') + '"></label>' +
                        '<label class="full-width">RTSP URL <input data-field="rtspUrl" value="' + escapeHtml(table.rtspUrl || '') + '" placeholder="rtsp://user:pass@host:554/..."></label>' +
                    '</div>' +
                '</div>'
            );
        }).join('');

        tablesContainer.querySelectorAll('[data-remove]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const idx = Number(btn.getAttribute('data-remove'));
                tables.splice(idx, 1);
                renderTables();
            });
        });

        tablesContainer.querySelectorAll('.admin-table-card').forEach(function (card) {
            const index = Number(card.getAttribute('data-index'));
            card.querySelectorAll('[data-field]').forEach(function (input) {
                input.addEventListener('input', function () {
                    const field = input.getAttribute('data-field');
                    tables[index][field] = input.value;
                });
            });
        });

        renderActiveTableSelect();
    }

    function readLiveForm() {
        const form = document.getElementById('liveForm');
        const data = new FormData(form);
        return {
            section: 'live',
            mediamtxHost: data.get('mediamtxHost'),
            preferredProtocol: data.get('preferredProtocol'),
            useHttpsProxy: form.querySelector('[name="useHttpsProxy"]').checked,
            activeTableId: activeTableSelect ? activeTableSelect.value : activeTableId,
            tables: tables
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

    activeTableSelect?.addEventListener('change', function () {
        activeTableId = activeTableSelect.value;
    });

    document.getElementById('addTableBtn')?.addEventListener('click', function () {
        const next = tables.length + 1;
        tables.push({
            id: 'table' + next,
            name: 'Table ' + next,
            description: '',
            rtspUrl: ''
        });
        renderTables();
    });

    document.getElementById('saveLiveBtn')?.addEventListener('click', async function () {
        try {
            const result = await postSave(readLiveForm());
            if (result.mediamtxYaml && mediamtxYamlEl) {
                mediamtxYamlEl.value = result.mediamtxYaml;
            }
            if (activeTableSelect) {
                activeTableId = activeTableSelect.value;
            }
            showMessage('Live camera settings saved. Viewers now see: ' + (activeTableSelect?.selectedOptions[0]?.text || 'on-air table') + '.');
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

    renderTables();
    renderActiveTableSelect();
})();
