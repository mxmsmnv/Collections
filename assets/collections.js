/**
 * Collections module frontend JS
 */
(function() {
    'use strict';

    const cfg = window.CollectionsConfig || {};

    // ── Sidebar toggle ────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function() {
        var toggle  = document.getElementById('sidenav-toggle');
        var sidenav = document.getElementById('collections-sidenav');
        if (!toggle || !sidenav) return;

        toggle.addEventListener('click', function() {
            sidenav.classList.toggle('collapsed');
            localStorage.setItem(
                'collections_sidenav_collapsed',
                sidenav.classList.contains('collapsed') ? '1' : '0'
            );
        });
    });

    // ── Restore configure tab from URL hash ───────────────────────────────────

    document.addEventListener('DOMContentLoaded', function() {
        var hash = location.hash; // e.g. #tab-global
        if (!hash) return;
        var tabList = document.getElementById('collections-configure-tabs');
        if (!tabList) return;
        var link = tabList.querySelector('a[href="' + hash + '"]');
        if (!link) return;
        // Use UIkit tab API to switch to correct tab
        if (window.UIkit) {
            var tabEl = tabList.closest('[uk-tab]') || tabList;
            var links = tabList.querySelectorAll('li > a');
            var idx = Array.from(links).indexOf(link);
            if (idx >= 0) UIkit.tab(tabList).show(idx);
        }
        // Clear hash from URL without reload
        history.replaceState(null, '', location.pathname + location.search);
    });

    // ── Live search ───────────────────────────────────────────────────────────

    let searchTimer = null;
    const searchInput = document.getElementById('collections-search-input');

    if (searchInput && cfg.liveSearch) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimer);
            const val = this.value.trim();

            if (val.length > 0 && val.length < (cfg.minSearchLength || 2)) return;

            searchTimer = setTimeout(() => {
                const p = getCurrentParams();
                p.q = val;
                p.page = 1;
                fetchTable(p);
            }, 300);
        });
    }

    // ── Filter dropdowns ──────────────────────────────────────────────────────

    document.addEventListener('change', function(e) {
        const sel = e.target.closest('.collections-filter');
        if (!sel) return;
        const params = getCurrentParams();
        const field  = sel.dataset.field;
        params[`filter[${field}]`] = sel.value;
        params['page'] = 1;
        fetchTable(params);
    });

    // ── Sort links (async) ────────────────────────────────────────────────────

    document.addEventListener('click', function(e) {
        const link = e.target.closest('.collections-sort-link');
        if (!link) return;
        e.preventDefault();
        const url    = new URL(link.href, location.href);
        const params = {};
        url.searchParams.forEach((v, k) => params[k] = v);
        fetchTable(params);
    });

    // ── Pagination (async) ────────────────────────────────────────────────────

    document.addEventListener('click', function(e) {
        const link = e.target.closest('.collections-pagination a');
        if (!link) return;
        e.preventDefault();
        const url    = new URL(link.href, location.href);
        const params = {};
        url.searchParams.forEach((v, k) => params[k] = v);
        fetchTable(params);
    });

    // ── Fetch table (AJAX partial update) ────────────────────────────────────

    function getCurrentParams() {
        const params = {};
        new URLSearchParams(location.search).forEach((v, k) => params[k] = v);
        return params;
    }

    // Always preserve 'col' param so AJAX calls stay on correct collection
    function getColParam() {
        return new URLSearchParams(location.search).get('col') || '';
    }

    function fetchTable(params) {
        const resultEl = document.getElementById('collections-result');
        if (!resultEl) return;

        // Ensure col param is always preserved
        const col = getColParam();
        if (col && !params.col) params.col = col;

        resultEl.style.opacity = '0.5';

        const qs  = new URLSearchParams(params).toString();
        const url = location.pathname + '?' + qs;

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.html) {
                resultEl.innerHTML = data.html;
            }
            // Update pagination container if present
            const paginationEl = document.getElementById('collections-pagination');
            if (paginationEl && data.pagination !== undefined) {
                paginationEl.innerHTML = data.pagination;
            }
            // Update statusbar if present
            const statusbarEl = document.querySelector('.collections-statusbar');
            if (statusbarEl && data.statusbar !== undefined) {
                statusbarEl.innerHTML = data.statusbar;
            }
            resultEl.style.opacity = '1';
            history.replaceState(null, '', url);
            // Update export links to reflect current filters/search
            const exportBase = url.replace(/&export=(csv|json)/, '');
            document.querySelectorAll('.collections-export').forEach(function(a) {
                const fmt = a.dataset.format;
                a.href = exportBase + '&export=' + fmt;
            });
            // Reset checkboxes and bulk bar after table reload
            const checkAll = resultEl.querySelector('.collections-check-all');
            if (checkAll) checkAll.checked = false;
            bindRowEvents();
        })
        .catch(() => { resultEl.style.opacity = '1'; });
    }

    // ── Check all / bulk bar ──────────────────────────────────────────────────

    function setRowSelected(cb) {
        var row = cb.closest('tr');
        if (row) row.classList.toggle('row-selected', cb.checked);
    }

    // Click anywhere on a row to toggle its checkbox
    document.addEventListener('click', function(e) {
        var row = e.target.closest('tr.collections-row');
        if (!row) return;
        // Ignore clicks on interactive elements
        if (e.target.closest('a, button, input, label, .collections-actions')) return;
        var cb = row.querySelector('.collections-check');
        if (!cb) return;
        cb.checked = !cb.checked;
        setRowSelected(cb);
        updateBulkBar();
    });

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('collections-check-all')) {
            document.querySelectorAll('.collections-check').forEach(cb => {
                cb.checked = e.target.checked;
                setRowSelected(cb);
            });
            updateBulkBar();
        }
        if (e.target.classList.contains('collections-check')) {
            setRowSelected(e.target);
            updateBulkBar();
        }
    });

    function updateBulkBar() {
        const checked = document.querySelectorAll('.collections-check:checked').length;
        const bar     = document.getElementById('collections-bulk-bar');
        const counter = document.getElementById('bulk-count-num');
        if (bar) bar.style.display = checked > 0 ? 'flex' : 'none';
        if (counter) counter.textContent = checked;
    }

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-bulk-action]');
        if (!btn) return;

        const action  = btn.dataset.bulkAction;
        const ids     = [...document.querySelectorAll('.collections-check:checked')].map(cb => cb.value);
        if (!ids.length) return;

        if (action === 'delete' && cfg.confirmBatchDelete) {
            if (!confirm(`Delete ${ids.length} pages? This cannot be undone.`)) return;
        }

        submitBulk(action, ids);
    });

    const cancelBtn = document.getElementById('collections-bulk-cancel');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            document.querySelectorAll('.collections-check, .collections-check-all').forEach(cb => cb.checked = false);
            document.querySelectorAll('tr.row-selected').forEach(r => r.classList.remove('row-selected'));
            updateBulkBar();
        });
    }

    function getCsrf() {
        // Read from our own #collections-csrf div — rendered server-side with renderInput()
        var input = document.querySelector('#collections-csrf input[type="hidden"]');
        if (input) return { name: input.name, value: input.value };
        return { name: cfg.csrfName, value: cfg.csrfValue };
    }

    function submitBulk(action, ids) {
        const form   = document.createElement('form');
        form.method  = 'post';
        // Preserve ?col=key so server resolves the correct collection
        form.action  = location.pathname + location.search;

        const addInput = (name, value) => {
            const inp   = document.createElement('input');
            inp.type    = 'hidden';
            inp.name    = name;
            inp.value   = value;
            form.appendChild(inp);
        };

        const csrf = getCsrf();
        if (csrf.name && csrf.value) {
            addInput(csrf.name, csrf.value);
        }

        addInput('bulk_action', action);
        ids.forEach(id => addInput('ids[]', id));

        document.body.appendChild(form);
        form.submit();
    }

    // ── Inline status toggle ──────────────────────────────────────────────────

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.collections-toggle-status');
        if (!btn) return;

        const id     = btn.dataset.id;
        const status = btn.dataset.status;
        const action = status === 'published' ? 'unpublish' : 'publish';

        btn.disabled = true;
        btn.style.opacity = '0.6';

        // Use simple form POST to toggle status
        const params = getCurrentParams();
        const url = location.pathname + '?col=' + encodeURIComponent(params.col || '') + '&toggle_status=1';
        
        const csrf = getCsrf();
        const body = new URLSearchParams();
        body.append('page_id', id);
        body.append('action', action);
        if (csrf.name) body.append(csrf.name, csrf.value);

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: body.toString()
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                const newStatus = action === 'publish' ? 'published' : 'unpublished';
                btn.dataset.status = newStatus;
                // Swap icon
                btn.innerHTML = newStatus === 'published'
                    ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#555" style="width:14px;height:14px;display:inline-block;vertical-align:middle;flex-shrink:0"><path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd"/></svg>'
                    : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#aaa" style="width:14px;height:14px;display:inline-block;vertical-align:middle;flex-shrink:0"><path fill-rule="evenodd" d="M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm-1.72 6.97a.75.75 0 1 0-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 1 0 1.06 1.06L12 13.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L13.06 12l1.72-1.72a.75.75 0 1 0-1.06-1.06L12 10.94l-1.72-1.72Z" clip-rule="evenodd"/></svg>';
                btn.title = newStatus === 'published' ? 'Unpub' : 'Pub';
                // Swap toggle classes
                btn.classList.toggle('toggle-published',  newStatus === 'published');
                btn.classList.toggle('toggle-unpublished', newStatus === 'unpublished');
                // Update status dot in same row (renderer uses .collections-dot, not .collections-badge)
                const row = btn.closest('tr');
                if (row) {
                    row.className = row.className.replace(/\bstatus-\S+/, 'status-' + newStatus);
                    const dot = row.querySelector('.collections-dot');
                    if (dot) {
                        dot.className = 'collections-dot dot-' + newStatus;
                        dot.title = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                    }
                }
            }
            btn.disabled = false;
            btn.style.opacity = '1';
        })
        .catch(() => { btn.disabled = false; btn.style.opacity = '1'; });
    });

    // ── Configure: collection edit modal ─────────────────────────────────────

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-edit-collection');
        if (!btn) return;

        const data  = JSON.parse(btn.dataset.collection || '{}');
        const modal = document.getElementById('modal-collection-edit');
        if (!modal) return;

        // Populate form
        setField('field-original-key', data.key || '');
        setField('field-key', data.key || '');
        setField('field-label', data.label || '');
        setField('field-template', data.template || '');
        // parent field removed
        setField('field-selector', data.selector || '');
        setField('field-icon', data.icon || 'fa-list');
        setField('field-group', data.group || 'content');
        setField('field-columns', (data.columns || []).join(', '));
        setField('field-searchFields', (data.searchFields || []).join(', '));
        setField('field-sortBy', data.sortBy || 'title');
        setField('field-sortDir', data.sortDir || 'asc');
        setField('field-perPage', data.perPage || 0);
        setField('field-order', data.order || 0);

        const expCheck = document.getElementById('field-exportEnabled');
        if (expCheck) expCheck.checked = data.exportEnabled !== false;

        const relCheck = document.getElementById('field-searchRelated');
        if (relCheck) relCheck.checked = data.searchRelated !== false;

        const title = document.getElementById('modal-collection-title');
        if (title) title.textContent = `Edit: ${data.label || data.key}`;

        UIkit.modal(modal).show();
    });

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('#btn-add-collection');
        if (!btn) return;

        const modal = document.getElementById('modal-collection-edit');
        if (!modal) return;

        // Clear form
        ['field-original-key', 'field-key', 'field-label', 'field-selector'].forEach(id => setField(id, ''));
        setField('field-icon', 'fa-list');
        setField('field-group', 'content');
        setField('field-columns', 'title');
        setField('field-searchFields', 'title');
        setField('field-sortBy', 'title');
        setField('field-sortDir', 'asc');
        setField('field-perPage', '0');
        setField('field-order', '0');

        const expCheck = document.getElementById('field-exportEnabled');
        if (expCheck) expCheck.checked = true;

        const relCheck = document.getElementById('field-searchRelated');
        if (relCheck) relCheck.checked = true;

        const title = document.getElementById('modal-collection-title');
        if (title) title.textContent = 'Add Collection';

        UIkit.modal(modal).show();
    });

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-delete-collection');
        if (!btn) return;

        const key   = btn.dataset.key;
        const modal = document.getElementById('modal-delete-confirm');
        const input = document.getElementById('delete-key');

        if (modal && input) {
            input.value = key;
            UIkit.modal(modal).show();
        }
    });

    function setField(id, value) {
        const el = document.getElementById(id);
        if (!el) return;
        if (el.tagName === 'SELECT') {
            [...el.options].forEach(o => o.selected = o.value === String(value));
        } else {
            el.value = value;
        }
    }

    // ── Quick delete ──────────────────────────────────────────────────────────

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.collections-delete');
        if (!btn) return;

        if (!confirm('Delete this page? This cannot be undone.')) return;

        const id  = btn.dataset.id;
        const row = btn.closest('tr');

        // Use bulk POST (same as bulk bar delete) — avoids API auth issues
        const form = document.createElement('form');
        form.method = 'post';
        form.action = location.pathname + location.search;

        const addInput = (name, value) => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = name;
            inp.value = value;
            form.appendChild(inp);
        };

        const csrf = getCsrf();
        if (csrf.name && csrf.value) addInput(csrf.name, csrf.value);
        addInput('bulk_action', 'delete');
        addInput('ids[]', id);

        document.body.appendChild(form);
        form.submit();
    });

    // ── Bind row events after AJAX update ────────────────────────────────────

    function bindRowEvents() {
        updateBulkBar();
    }

    bindRowEvents();

    // ── Icon Picker (event delegation) ─────────────────────────────────────

    var FA_ICONS = [
        'fa-adjust','fa-anchor','fa-archive','fa-area-chart','fa-arrows','fa-arrows-h','fa-arrows-v',
        'fa-asterisk','fa-at','fa-balance-scale','fa-ban','fa-bar-chart','fa-barcode','fa-bars',
        'fa-bed','fa-beer','fa-bell','fa-bell-o','fa-bicycle','fa-binoculars','fa-birthday-cake',
        'fa-bolt','fa-bomb','fa-book','fa-bookmark','fa-bookmark-o','fa-briefcase','fa-bug',
        'fa-building','fa-building-o','fa-bullhorn','fa-bullseye','fa-bus','fa-calculator',
        'fa-calendar','fa-calendar-o','fa-camera','fa-camera-retro','fa-car','fa-cart-plus',
        'fa-certificate','fa-check','fa-check-circle','fa-check-circle-o','fa-check-square',
        'fa-child','fa-circle','fa-circle-o','fa-clipboard','fa-clock-o','fa-clone','fa-cloud',
        'fa-cloud-download','fa-cloud-upload','fa-code','fa-code-fork','fa-coffee','fa-cog','fa-cogs',
        'fa-columns','fa-comment','fa-comment-o','fa-comments','fa-comments-o','fa-compass',
        'fa-copy','fa-copyright','fa-credit-card','fa-crop','fa-crosshairs','fa-cube','fa-cubes',
        'fa-cutlery','fa-dashboard','fa-database','fa-desktop','fa-diamond','fa-download','fa-edit',
        'fa-ellipsis-h','fa-ellipsis-v','fa-envelope','fa-envelope-o','fa-envelope-open','fa-eraser',
        'fa-exchange','fa-exclamation','fa-exclamation-circle','fa-exclamation-triangle','fa-expand',
        'fa-external-link','fa-eye','fa-eye-slash','fa-eyedropper','fa-f','fa-fax','fa-feed',
        'fa-female','fa-fighter-jet','fa-file','fa-file-o','fa-file-archive-o','fa-file-audio-o',
        'fa-file-code-o','fa-file-excel-o','fa-file-image-o','fa-file-pdf-o','fa-file-text',
        'fa-file-text-o','fa-file-video-o','fa-file-word-o','fa-film','fa-filter','fa-fire',
        'fa-flag','fa-flag-o','fa-flask','fa-folder','fa-folder-o','fa-folder-open','fa-font',
        'fa-gamepad','fa-gavel','fa-gift','fa-glass','fa-globe','fa-graduation-cap','fa-group',
        'fa-hand-o-right','fa-hand-o-up','fa-handshake-o','fa-hashtag','fa-hdd-o','fa-header',
        'fa-headphones','fa-heart','fa-heart-o','fa-heartbeat','fa-history','fa-home','fa-hourglass',
        'fa-i-cursor','fa-id-badge','fa-id-card','fa-image','fa-inbox','fa-industry','fa-info',
        'fa-info-circle','fa-key','fa-keyboard-o','fa-language','fa-laptop','fa-leaf','fa-lemon-o',
        'fa-level-down','fa-level-up','fa-life-ring','fa-lightbulb-o','fa-line-chart','fa-link',
        'fa-list','fa-list-alt','fa-list-ol','fa-list-ul','fa-location-arrow','fa-lock','fa-magic',
        'fa-magnet','fa-male','fa-map','fa-map-marker','fa-map-o','fa-map-pin','fa-map-signs',
        'fa-medkit','fa-meh-o','fa-microphone','fa-minus','fa-minus-circle','fa-minus-square',
        'fa-mobile','fa-money','fa-moon-o','fa-music','fa-newspaper-o','fa-paint-brush',
        'fa-paper-plane','fa-paperclip','fa-paste','fa-pause','fa-paw','fa-pencil','fa-pencil-square',
        'fa-pencil-square-o','fa-percent','fa-phone','fa-picture-o','fa-pie-chart','fa-plane',
        'fa-play','fa-play-circle','fa-plug','fa-plus','fa-plus-circle','fa-plus-square','fa-podcast',
        'fa-power-off','fa-print','fa-puzzle-piece','fa-qrcode','fa-question','fa-question-circle',
        'fa-quote-left','fa-random','fa-recycle','fa-refresh','fa-remove','fa-reply','fa-reply-all',
        'fa-retweet','fa-road','fa-rocket','fa-rss','fa-save','fa-search','fa-search-minus',
        'fa-search-plus','fa-server','fa-share','fa-share-alt','fa-share-square','fa-shield',
        'fa-ship','fa-shopping-bag','fa-shopping-basket','fa-shopping-cart','fa-sign-in','fa-sign-out',
        'fa-signal','fa-sitemap','fa-sliders','fa-smile-o','fa-sort','fa-space-shuttle','fa-spinner',
        'fa-spoon','fa-square','fa-square-o','fa-star','fa-star-half','fa-star-half-o','fa-star-o',
        'fa-sticky-note','fa-stop','fa-street-view','fa-suitcase','fa-sun-o','fa-table','fa-tablet',
        'fa-tachometer','fa-tag','fa-tags','fa-tasks','fa-television','fa-terminal','fa-th',
        'fa-th-large','fa-th-list','fa-thumb-tack','fa-thumbs-down','fa-thumbs-up','fa-ticket',
        'fa-times','fa-times-circle','fa-tint','fa-toggle-off','fa-toggle-on','fa-trash','fa-trash-o',
        'fa-tree','fa-trophy','fa-truck','fa-umbrella','fa-undo','fa-university','fa-unlock',
        'fa-upload','fa-user','fa-user-circle','fa-user-o','fa-user-plus','fa-user-secret',
        'fa-user-times','fa-users','fa-video-camera','fa-volume-up','fa-warning','fa-wheelchair',
        'fa-wifi','fa-wrench'
    ];

    var iconPickerBuilt = false;

    document.addEventListener('click', function(e) {
        // Toggle link
        var toggle = e.target.closest('#icon-picker-toggle');
        if (toggle) {
            e.preventDefault();
            var picker = document.getElementById('icon-picker');
            if (!picker) return;

            if (!iconPickerBuilt) {
                picker.innerHTML = FA_ICONS.map(function(ic) {
                    return '<span class="icon-pick" data-icon="' + ic + '" title="' + ic + '" '
                        + 'style="display:inline-block;width:32px;height:32px;text-align:center;line-height:32px;'
                        + 'cursor:pointer;border-radius:3px;font-size:14px;">'
                        + '<i class="fa ' + ic + '"></i></span>';
                }).join('');
                iconPickerBuilt = true;
            }

            var show = picker.style.display === 'none';
            picker.style.display = show ? 'block' : 'none';
            toggle.textContent = show ? 'Hide Icons' : 'Show All Icons';
            return;
        }

        // Icon pick
        var pick = e.target.closest('.icon-pick');
        if (pick) {
            var input = document.getElementById('field-icon');
            var preview = document.getElementById('icon-preview');
            var picker = document.getElementById('icon-picker');
            var toggle = document.getElementById('icon-picker-toggle');
            if (input) input.value = pick.dataset.icon;
            if (preview) preview.innerHTML = '<i class="fa ' + pick.dataset.icon + '"></i>';
            if (picker) picker.style.display = 'none';
            if (toggle) toggle.textContent = 'Show All Icons';
        }
    });

    // Update preview on icon input change
    document.addEventListener('input', function(e) {
        if (e.target.id === 'field-icon') {
            var preview = document.getElementById('icon-preview');
            if (preview) {
                var cls = e.target.value.trim() || 'fa-list';
                preview.innerHTML = '<i class="fa ' + cls + '"></i>';
            }
        }
    });

})();