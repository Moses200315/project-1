/**
 * MealKit – Main Application JavaScript  v1.0.0
 * ================================================
 * Pure Vanilla JS – no framework dependencies.
 * Requires Bootstrap 5 for toast/modal/collapse components.
 *
 * Globals injected by layout footer:
 *   CSRF_TOKEN  – current session CSRF token
 *   APP_URL     – base application URL
 */

'use strict';

/* ── Namespace ─────────────────────────────────────────────────────────── */
const MealKit = (function () {

    /* ── Private: CSRF & Fetch Helper ────────────────────────────────────── */
    function apiFetch(url, data = {}) {
        const fd = new FormData();
        fd.append('csrf_token', window.CSRF_TOKEN || '');
        Object.entries(data).forEach(([k, v]) => fd.append(k, v));
        return fetch(url, { method: 'POST', body: fd }).then(r => r.json());
    }

    /* ── Auto-dismiss Bootstrap Alerts ───────────────────────────────────── */
    function initAlerts() {
        document.querySelectorAll('.alert.alert-dismissible').forEach(el => {
            setTimeout(() => {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
                bsAlert && bsAlert.close();
            }, 5500);
        });
    }

    /* ── Prevent Double-Submit ───────────────────────────────────────────── */
    function initFormProtection() {
        document.querySelectorAll('form:not([data-no-protect])').forEach(form => {
            form.addEventListener('submit', function () {
                const btn = this.querySelector('[type="submit"]');
                if (!btn || btn.dataset.submitted) return;
                btn.dataset.submitted = '1';
                const original = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-2"></span>Please wait…';
                // Re-enable after 12s as a safety net
                setTimeout(() => {
                    btn.disabled = false;
                    btn.innerHTML = original;
                    delete btn.dataset.submitted;
                }, 12000);
            });
        });
    }

    /* ── Toast Notification System ───────────────────────────────────────── */
    const toastColors = {
        success: 'bg-success',
        error:   'bg-danger',
        warning: 'bg-warning text-dark',
        info:    'bg-info text-dark',
    };

    function showToast(message, type = 'info', duration = 4000) {
        let container = document.getElementById('mk-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'mk-toast-container';
            container.className = 'position-fixed bottom-0 end-0 p-3';
            container.style.cssText = 'z-index:11000;min-width:280px;';
            document.body.appendChild(container);
        }

        const el = document.createElement('div');
        el.className = `toast align-items-center text-white border-0 ${toastColors[type] || 'bg-secondary'}`;
        el.setAttribute('role', 'alert');
        el.innerHTML = `
            <div class="d-flex">
              <div class="toast-body fw-semibold">${escapeHtml(message)}</div>
              <button type="button" class="btn-close btn-close-white ms-auto me-2 mt-2"
                      onclick="this.closest('.toast').remove()"></button>
            </div>`;
        container.appendChild(el);

        const bsToast = new bootstrap.Toast(el, { delay: duration, autohide: true });
        bsToast.show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    }
    window.mkToast = showToast; // expose globally

    /* ── Favourite Toggle (AJAX) ─────────────────────────────────────────── */
    function initFavouriteToggle() {
        document.querySelectorAll('.fav-form').forEach(form => {
            form.addEventListener('submit', function (e) {
                e.preventDefault();

                const btn        = this.querySelector('.fav-btn');
                const icon       = btn?.querySelector('i');
                const label      = btn?.querySelector('.fav-label');
                const countBadge = btn?.querySelector('.fav-count');

                if (btn) {
                    btn.disabled = true;
                    if (icon) icon.className = 'bi bi-arrow-repeat spin';
                }

                const recipeId = this.querySelector('[name="recipe_id"]')?.value;

                apiFetch(this.action, { recipe_id: recipeId })
                    .then(d => {
                        if (btn) btn.disabled = false;
                        if (!d.success) {
                            showToast(d.error || 'Something went wrong.', 'error');
                            return;
                        }
                        if (icon) {
                            icon.className = d.added
                                ? 'bi bi-heart-fill text-danger'
                                : 'bi bi-heart text-muted';
                        }
                        if (label) label.textContent = d.added ? 'Saved' : 'Save';
                        if (countBadge && d.count !== undefined) {
                            countBadge.textContent = d.count;
                        }
                        showToast(d.message, d.added ? 'success' : 'info', 2500);
                    })
                    .catch(() => {
                        if (btn) btn.disabled = false;
                        // Fall back to normal form submit
                        this.removeEventListener('submit', arguments.callee);
                        this.submit();
                    });
            });
        });
    }

    /* ── Notification Unread Badge Poll ──────────────────────────────────── */
    function initNotificationPoll() {
        const badge = document.querySelector('.notif-badge');
        if (!badge || !window.APP_URL) return;

        setInterval(() => {
            fetch(`${window.APP_URL}/notifications/unreadCount`, { credentials: 'same-origin' })
                .then(r => r.json())
                .then(d => {
                    if (d.count !== undefined) {
                        badge.textContent = d.count;
                        badge.style.display = d.count > 0 ? '' : 'none';
                    }
                })
                .catch(() => {}); // silent fail
        }, 60000); // poll every 60 s
    }

    /* ── Image File Preview ──────────────────────────────────────────────── */
    function initImagePreviews() {
        document.querySelectorAll('input[type="file"][data-preview]').forEach(input => {
            input.addEventListener('change', function () {
                const previewEl = document.getElementById(this.dataset.preview);
                if (!previewEl || !this.files?.[0]) return;
                const reader = new FileReader();
                reader.onload = e => {
                    previewEl.src = e.target.result;
                    previewEl.style.display = '';
                };
                reader.readAsDataURL(this.files[0]);
            });
        });
    }

    /* ── Serving-Size Scaler (recipe view) ──────────────────────────────── */
    function initServingCalculator() {
        const counter = document.getElementById('servingCount');
        if (!counter) return; // not on recipe-view page

        const recipeId   = counter.dataset.recipeId;
        const origServ   = parseInt(counter.dataset.original, 10);
        const ingrItems  = document.querySelectorAll('.ing-qty');
        let   current    = origServ;

        // Store original values
        ingrItems.forEach(el => {
            el.dataset.original = el.textContent.trim();
        });

        function updateServings(delta) {
            current = Math.max(1, Math.min(50, current + delta));
            counter.textContent = current;

            if (current === origServ) {
                ingrItems.forEach(el => { el.textContent = el.dataset.original; });
                return;
            }

            fetch(`${window.APP_URL}/recipes/calculate?recipe_id=${recipeId}&servings=${current}`)
                .then(r => r.json())
                .then(d => {
                    if (!d.ingredients) return;
                    d.ingredients.forEach((ing, i) => {
                        if (ingrItems[i]) ingrItems[i].textContent = ing.scaled_quantity;
                    });
                })
                .catch(() => {});
        }

        document.getElementById('servIncrease')?.addEventListener('click', () => updateServings(1));
        document.getElementById('servDecrease')?.addEventListener('click', () => updateServings(-1));
    }

    /* ── Sidebar Toggle (admin) ──────────────────────────────────────────── */
    function initSidebar() {
        const toggle = document.querySelector('.sidebar-toggle');
        const wrapper = document.getElementById('wrapper');
        if (!toggle || !wrapper) return;

        const KEY = 'mk_sidebar_open';
        // Restore state
        if (localStorage.getItem(KEY) === '0') wrapper.classList.remove('sidebar-open');

        toggle.addEventListener('click', () => {
            wrapper.classList.toggle('sidebar-open');
            localStorage.setItem(KEY, wrapper.classList.contains('sidebar-open') ? '1' : '0');
        });
    }

    /* ── Recipe Search Debounce (admin recipe list) ──────────────────────── */
    function initSearchDebounce() {
        const input = document.querySelector('input[name="search"]');
        if (!input || !input.closest('form')) return;

        let timer;
        input.addEventListener('input', function () {
            clearTimeout(timer);
            // Auto-submit only if user typed ≥3 chars or cleared the field
            if (this.value.length >= 3 || this.value.length === 0) {
                timer = setTimeout(() => this.closest('form').submit(), 600);
            }
        });
    }

    /* ── Featured Toggle (admin recipe table) ───────────────────────────── */
    function initFeaturedToggle() {
        document.querySelectorAll('.toggle-featured').forEach(btn => {
            btn.addEventListener('click', function () {
                const recipeId = this.dataset.id;
                apiFetch(`${window.APP_URL}/recipes/toggleFeatured`, { recipe_id: recipeId })
                    .then(d => {
                        if (!d.success) return;
                        const icon = this.querySelector('i');
                        if (icon) {
                            icon.className = d.is_featured
                                ? 'bi bi-star-fill text-warning'
                                : 'bi bi-star';
                        }
                        showToast(
                            d.is_featured ? 'Recipe featured.' : 'Recipe unfeatured.',
                            d.is_featured ? 'success' : 'info',
                            2500
                        );
                    });
            });
        });
    }

    /* ── Dynamic Ingredient Rows (recipe form) ───────────────────────────── */
    function initDynamicRows() {
        // Attach remove handler to existing rows
        function attachRemove(container) {
            container.querySelectorAll('.remove-row').forEach(btn => {
                btn.replaceWith(btn.cloneNode(true)); // remove old listeners
            });
            container.querySelectorAll('.remove-row').forEach(btn => {
                btn.addEventListener('click', function () {
                    const row = this.closest('.ingredient-row, .procedure-row');
                    if (row) {
                        row.remove();
                        renumberSteps(container);
                    }
                });
            });
        }

        function renumberSteps(container) {
            container.querySelectorAll('.step-num').forEach((el, i) => {
                el.textContent = `Step ${i + 1}`;
            });
        }

        const ingList  = document.getElementById('ingredientList');
        const procList = document.getElementById('procedureList');
        if (ingList)  attachRemove(ingList);
        if (procList) attachRemove(procList);
    }

    /* ── Category Slug Auto-generate ────────────────────────────────────── */
    function initSlugGenerator() {
        const nameInput = document.querySelector('input[name="name"][data-autoslug]');
        const slugInput = document.querySelector('input[name="slug"]');
        if (!nameInput || !slugInput) return;

        nameInput.addEventListener('input', function () {
            if (slugInput.dataset.manual) return;
            slugInput.value = this.value
                .toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/[\s]+/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-|-$/g, '');
        });

        slugInput.addEventListener('input', function () {
            this.dataset.manual = this.value ? '1' : '';
        });
    }

    /* ── Confirm Button Enhancement ──────────────────────────────────────── */
    function initConfirmButtons() {
        document.querySelectorAll('[data-confirm]').forEach(el => {
            el.addEventListener('click', function (e) {
                if (!confirm(this.dataset.confirm)) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
        });
    }

    /* ── Responsive Table Wrapper ────────────────────────────────────────── */
    function initTableWrapper() {
        document.querySelectorAll('table:not(.table-nowrap)').forEach(table => {
            if (!table.closest('.table-responsive')) {
                const wrap = document.createElement('div');
                wrap.className = 'table-responsive';
                table.parentNode.insertBefore(wrap, table);
                wrap.appendChild(table);
            }
        });
    }

    /* ── Copy to Clipboard ───────────────────────────────────────────────── */
    function initClipboard() {
        document.querySelectorAll('[data-copy]').forEach(btn => {
            btn.addEventListener('click', function () {
                navigator.clipboard?.writeText(this.dataset.copy)
                    .then(() => showToast('Copied!', 'success', 2000));
            });
        });
    }

    /* ── Chart.js Global Defaults ────────────────────────────────────────── */
    function initChartDefaults() {
        if (typeof Chart === 'undefined') return;
        Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
        Chart.defaults.font.size   = 12;
        Chart.defaults.color       = '#6c757d';
        Chart.defaults.plugins.legend.labels.boxWidth = 14;
    }

    /* ── Escape HTML (XSS safe) ──────────────────────────────────────────── */
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /* ── Public API ──────────────────────────────────────────────────────── */
    return {
        init() {
            initAlerts();
            initFormProtection();
            initImagePreviews();
            initFavouriteToggle();
            initServingCalculator();
            initSidebar();
            initSearchDebounce();
            initFeaturedToggle();
            initDynamicRows();
            initSlugGenerator();
            initConfirmButtons();
            initTableWrapper();
            initClipboard();
            initChartDefaults();
            initNotificationPoll();
        },

        toast: showToast,
        fetch: apiFetch,
        escape: escapeHtml,
    };
})();

/* ── Boot ──────────────────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => MealKit.init());
