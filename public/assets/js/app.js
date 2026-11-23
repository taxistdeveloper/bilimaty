/**
 * BILIMATY core front-end helpers (vanilla ES6+, no build step).
 */
(() => {
    'use strict';

    const meta = (name) => document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') ?? '';
    const i18n = (() => {
        try {
            return JSON.parse(document.getElementById('app-i18n')?.textContent || '{}');
        } catch {
            return {};
        }
    })();

    /**
     * fetch() wrapper for the internal JSON API: sends CSRF token and session cookie,
     * parses the unified {success, message, data, errors} envelope.
     */
    const api = async (path, { method = 'GET', body = null, signal } = {}) => {
        const base = meta('base-url').replace(/\/$/, '');
        const response = await fetch(base + path, {
            method,
            signal,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': meta('csrf-token'),
                ...(body !== null ? { 'Content-Type': 'application/json' } : {}),
            },
            body: body !== null ? JSON.stringify(body) : undefined,
        });

        let payload;
        try {
            payload = await response.json();
        } catch {
            payload = { success: false, message: response.statusText, data: null };
        }
        if (!response.ok || payload.success === false) {
            const error = new Error(payload.message || `HTTP ${response.status}`);
            error.status = response.status;
            error.errors = payload.errors || {};
            throw error;
        }
        return payload;
    };

    const toast = (message, type = 'info') => {
        const container = document.getElementById('toastContainer');
        if (!container || !window.bootstrap) return;
        const icons = { success: 'bi-check-circle-fill text-success', error: 'bi-x-octagon-fill text-danger', warning: 'bi-exclamation-triangle-fill text-warning', info: 'bi-info-circle-fill text-primary' };
        const el = document.createElement('div');
        el.className = 'toast align-items-center border-0 shadow';
        el.setAttribute('role', 'status');
        el.innerHTML = '<div class="d-flex"><div class="toast-body d-flex align-items-start gap-2"><i class="bi" aria-hidden="true"></i><span></span></div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div>';
        el.querySelector('.bi').className = `bi ${icons[type] || icons.info}`;
        el.querySelector('span').textContent = message; // textContent: never inject HTML
        container.appendChild(el);
        el.addEventListener('hidden.bs.toast', () => el.remove());
        new bootstrap.Toast(el, { delay: 5000 }).show();
    };

    window.CollegeOS = { api, toast, i18n };

    /* Flash toasts rendered by the server */
    document.querySelectorAll('[data-flash-toast]').forEach((el) => {
        if (window.bootstrap) new bootstrap.Toast(el, { delay: 6000 }).show();
    });

    /* Show / hide password */
    document.querySelectorAll('[data-toggle-password]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.querySelector(button.dataset.togglePassword);
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(show));
            button.querySelector('.bi')?.classList.toggle('bi-eye', !show);
            button.querySelector('.bi')?.classList.toggle('bi-eye-slash', show);
        });
    });

    /*
     * Confirmation dialog for dangerous forms: <form data-confirm="Text" [data-confirm-danger]>.
     * Capture phase on document runs before the form's own submit listeners.
     */
    const confirmModalEl = document.getElementById('confirmModal');
    if (confirmModalEl && window.bootstrap) {
        const modal = bootstrap.Modal.getOrCreateInstance(confirmModalEl);
        const okButton = confirmModalEl.querySelector('[data-confirm-ok]');
        let pendingForm = null;

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed === '1') return;
            event.preventDefault();
            event.stopPropagation();
            pendingForm = form;
            confirmModalEl.querySelector('[data-confirm-text]').textContent = form.dataset.confirm;
            okButton.className = `btn ${'confirmDanger' in form.dataset ? 'btn-danger' : 'btn-primary'}`;
            modal.show();
        }, true);

        okButton.addEventListener('click', () => {
            if (!pendingForm) return;
            pendingForm.dataset.confirmed = '1';
            modal.hide();
            pendingForm.requestSubmit();
        });
    }

    /* Copy to clipboard: <button data-copy="#inputSelector" data-copied-text="..."> */
    document.querySelectorAll('[data-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            const input = document.querySelector(button.dataset.copy);
            if (!input) return;
            try {
                await navigator.clipboard.writeText(input.value);
            } catch {
                input.select();
                document.execCommand('copy');
            }
            toast(button.dataset.copiedText || 'OK', 'success');
        });
    });

    /* "Select all" checkbox inside a permission group */
    document.querySelectorAll('[data-permission-group]').forEach((group) => {
        const toggle = group.querySelector('[data-group-toggle]');
        const boxes = [...group.querySelectorAll('input[name="permissions[]"]:not(:disabled)')];
        if (!toggle) return;
        const sync = () => {
            const checked = boxes.filter((b) => b.checked).length;
            toggle.checked = boxes.length > 0 && checked === boxes.length;
            toggle.indeterminate = checked > 0 && checked < boxes.length;
            toggle.disabled = boxes.length === 0 || toggle.closest('fieldset')?.disabled === true;
        };
        toggle.addEventListener('change', () => {
            boxes.forEach((b) => { b.checked = toggle.checked; });
            sync();
        });
        boxes.forEach((b) => b.addEventListener('change', sync));
        sync();
    });

    /*
     * Dependent selects: <select data-specialty-select="#a, #b"> shows only the options
     * of the targets whose data-specialty matches the chosen value.
     */
    document.querySelectorAll('[data-specialty-select]').forEach((source) => {
        const targets = [...document.querySelectorAll(source.dataset.specialtySelect)];
        if (!targets.length) return;
        const sync = () => targets.forEach((target) => {
            [...target.options].forEach((option) => {
                if (!option.dataset.specialty) return;
                const visible = option.dataset.specialty === source.value;
                option.hidden = !visible;
                option.disabled = !visible;
                if (!visible && option.selected) target.value = '';
            });
        });
        source.addEventListener('change', sync);
        sync();
    });

    /* Live sum of percentage inputs: <div data-weights> … <input data-weight> … <span data-weights-sum> */
    document.querySelectorAll('[data-weights]').forEach((box) => {
        const out = box.parentElement.querySelector('[data-weights-sum]');
        const sum = () => {
            const total = [...box.querySelectorAll('[data-weight]')].reduce((acc, el) => acc + (parseFloat(el.value.replace(',', '.')) || 0), 0);
            if (out) {
                out.textContent = String(Number(total.toFixed(2)));
                out.classList.toggle('text-success', Math.abs(total - 100) < 0.001);
                out.classList.toggle('text-danger', Math.abs(total - 100) >= 0.001);
            }
        };
        box.addEventListener('input', sum);
        sum();
    });

    /* <select data-autosubmit-form> submits its form on change */
    document.querySelectorAll('[data-autosubmit-form]').forEach((select) => {
        select.addEventListener('change', () => select.form?.submit());
    });

    /* Buttons that open the retake modal for a given student */
    document.querySelectorAll('[data-retake-student]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelector('[data-retake-student-input]').value = button.dataset.retakeStudent;
            document.querySelector('[data-retake-name-output]').textContent = button.dataset.retakeName;
        });
    });

    /* Re-open a modal form after a failed server-side validation */
    document.querySelectorAll('.modal[data-show-on-load]').forEach((el) => {
        if (window.bootstrap) bootstrap.Modal.getOrCreateInstance(el).show();
    });

    /* Prevent double submit and show progress */
    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('[type="submit"][data-loading-text]');
            if (!button) return;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>';
            button.append(document.createTextNode(button.dataset.loadingText));
        });
    });

    /*
     * Language switch without losing typed data: form values (except passwords,
     * CSRF tokens and files) are stashed in sessionStorage and restored after reload.
     */
    const draftKey = `collegeos:draft:${location.pathname}`;
    const skipField = (el) => !el.name || el.type === 'password' || el.type === 'file' || el.type === 'hidden' || el.name.startsWith('_');

    document.querySelectorAll('[data-lang-switch]').forEach((link) => {
        link.addEventListener('click', () => {
            const forms = [...document.querySelectorAll('form')].map((form) =>
                [...form.elements].filter((el) => !skipField(el)).map((el) => ({
                    name: el.name,
                    value: el.type === 'checkbox' || el.type === 'radio' ? el.checked : el.value,
                }))
            );
            try {
                sessionStorage.setItem(draftKey, JSON.stringify(forms));
            } catch { /* storage may be disabled: switching still works */ }
        });
    });

    try {
        const saved = sessionStorage.getItem(draftKey);
        if (saved) {
            sessionStorage.removeItem(draftKey);
            const forms = document.querySelectorAll('form');
            JSON.parse(saved).forEach((fields, index) => {
                const form = forms[index];
                if (!form) return;
                const used = new Map();
                fields.forEach(({ name, value }) => {
                    const candidates = [...form.elements].filter((el) => el.name === name && !skipField(el));
                    const i = used.get(name) ?? 0;
                    used.set(name, i + 1);
                    const el = candidates[i];
                    if (!el) return;
                    if (el.type === 'checkbox' || el.type === 'radio') el.checked = Boolean(value);
                    else el.value = value; // the same page re-rendered in another language: the typed value wins
                });
            });
        }
    } catch { /* ignore corrupted drafts */ }

    /* Notifications dropdown */
    const dropdown = document.getElementById('notificationsDropdown');
    if (dropdown) {
        const list = dropdown.querySelector('[data-notifications-list]');
        const badge = dropdown.querySelector('[data-notifications-count]');

        const setCount = (count) => {
            badge.textContent = count > 99 ? '99+' : String(count);
            badge.classList.toggle('d-none', count === 0);
        };

        const render = (items) => {
            list.replaceChildren();
            if (!items.length) {
                const empty = document.createElement('div');
                empty.className = 'text-center text-muted small py-4';
                empty.textContent = i18n.notificationsEmpty || '';
                list.appendChild(empty);
                return;
            }
            items.forEach((item) => {
                const el = document.createElement(item.link ? 'a' : 'div');
                el.className = `app-notification${item.read_at ? '' : ' is-unread'}`;
                if (item.link && item.link.startsWith('/') && !item.link.startsWith('//')) {
                    el.href = meta('base-url').replace(/\/$/, '') + item.link;
                }
                const title = document.createElement('div');
                title.className = 'fw-semibold small';
                title.textContent = item.title;
                const body = document.createElement('div');
                body.className = 'small text-muted';
                body.textContent = item.body || '';
                const time = document.createElement('div');
                time.className = 'small text-muted mt-1';
                time.textContent = new Date(item.created_at.replace(' ', 'T')).toLocaleString(document.documentElement.lang === 'kk' ? 'kk-KZ' : 'ru-RU');
                el.append(title, body, time);
                list.appendChild(el);
            });
        };

        const load = async () => {
            try {
                const { data } = await api('/api/notifications');
                setCount(data.unread);
                render(data.items);
            } catch (error) {
                if (error.status === 401) return;
                list.innerHTML = '';
                const failed = document.createElement('div');
                failed.className = 'text-center text-danger small py-4';
                failed.textContent = i18n.notificationsError || error.message;
                list.appendChild(failed);
            }
        };

        dropdown.querySelector('[data-notifications-read]')?.addEventListener('click', async () => {
            try {
                const { message } = await api('/api/notifications/read', { method: 'POST', body: {} });
                await load();
                toast(message, 'success');
            } catch (error) {
                toast(error.message, 'error');
            }
        });

        load();
    }
})();
