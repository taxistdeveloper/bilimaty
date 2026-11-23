/**
 * Timetable page: filters, lesson modal (JSON API via Fetch), occurrence modal.
 */
(() => {
    'use strict';
    const { api, toast } = window.CollegeOS || {};
    const i18n = JSON.parse(document.getElementById('scheduleI18n')?.textContent || '{}');
    const ruDate = (iso) => iso ? iso.split('-').reverse().join('.') : '';

    /* ---- Filters ---- */
    const filters = document.getElementById('scheduleFilters');
    filters?.querySelector('[data-autosubmit]')?.addEventListener('change', () => {
        const semester = filters.querySelector('[name="semester"]');
        if (semester) semester.disabled = true; // let the server pick the first semester of the year
        filters.submit();
    });
    filters?.querySelectorAll('[data-mode-switch]').forEach((radio) => {
        radio.addEventListener('change', () => {
            filters.querySelectorAll('[data-mode-select]').forEach((select) => {
                const active = select.dataset.modeSelect === radio.value;
                select.classList.toggle('d-none', !active);
                select.disabled = !active;
            });
        });
    });

    /* ---- Lesson (regular entry) modal ---- */
    const entryModalEl = document.getElementById('entryModal');
    const entryForm = document.getElementById('entryForm');
    const entryModal = entryModalEl && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(entryModalEl) : null;
    let editingId = null;

    const clearErrors = () => {
        entryForm.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        entryForm.querySelectorAll('[data-error-for]').forEach((el) => { el.textContent = ''; });
        const box = entryForm.querySelector('[data-conflicts]');
        box.classList.add('d-none');
        box.querySelector('ul').replaceChildren();
    };

    const showErrors = (errors) => {
        Object.entries(errors || {}).forEach(([field, messages]) => {
            if (field === 'conflicts') {
                const box = entryForm.querySelector('[data-conflicts]');
                messages.forEach((m) => {
                    const li = document.createElement('li');
                    li.textContent = m;
                    box.querySelector('ul').appendChild(li);
                });
                box.classList.remove('d-none');
                return;
            }
            const input = entryForm.querySelector(`[name="${field}"]`);
            const feedback = entryForm.querySelector(`[data-error-for="${field}"]`);
            input?.classList.add('is-invalid');
            if (feedback) feedback.textContent = messages[0];
        });
    };

    const openEntry = (values, id) => {
        if (!entryModal) return;
        editingId = id;
        entryForm.reset();
        clearErrors();
        Object.entries(values).forEach(([name, value]) => {
            const input = entryForm.querySelector(`[name="${name}"]`);
            if (input && value !== null && value !== undefined) input.value = String(value);
        });
        const title = entryModalEl.querySelector('#entryModalTitle');
        title.textContent = id ? title.dataset.titleEdit : title.dataset.titleNew;
        entryForm.querySelector('[data-entry-delete]').classList.toggle('d-none', !id);
        entryModal.show();
    };

    document.querySelectorAll('[data-entry-new]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            const values = { weekday: button.dataset.weekday || '', lesson_period_id: button.dataset.period || '' };
            const { mode, entity } = entryModalEl?.dataset || {};
            if (entity && mode === 'group') values.group_id = entity;
            if (entity && mode === 'teacher') values.teacher_id = entity;
            openEntry(values, null);
        });
    });

    entryForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearErrors();
        const body = Object.fromEntries(new FormData(entryForm).entries());
        body.semester_id = entryModalEl.dataset.semester;
        const submit = entryForm.querySelector('[type="submit"]');
        submit.disabled = true;
        try {
            await api(editingId ? `/api/schedule/${editingId}` : '/api/schedule', { method: editingId ? 'PUT' : 'POST', body });
            toast(i18n.saved, 'success');
            window.location.reload();
        } catch (error) {
            if (error.status === 422) showErrors(error.errors);
            else toast(error.message, 'error');
        } finally {
            submit.disabled = false;
        }
    });

    entryForm?.querySelector('[data-entry-delete]')?.addEventListener('click', async (event) => {
        if (!editingId || !window.confirm(event.currentTarget.dataset.confirmText)) return;
        try {
            await api(`/api/schedule/${editingId}`, { method: 'DELETE', body: {} });
            toast(i18n.deleted, 'success');
            window.location.reload();
        } catch (error) {
            toast(error.message, 'error');
        }
    });

    /* ---- Occurrence modal ---- */
    const occModalEl = document.getElementById('occurrenceModal');
    const occModal = occModalEl && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(occModalEl) : null;
    let current = null;

    document.querySelectorAll('[data-occurrence]').forEach((button) => {
        button.addEventListener('click', () => {
            current = JSON.parse(button.dataset.occurrence);
            occModalEl.querySelectorAll('[data-o]').forEach((el) => {
                const key = el.dataset.o;
                el.textContent = key === 'date' ? ruDate(current.date) : (current[key] ?? '');
            });
            const form = occModalEl.querySelector('[data-change-form]');
            if (form) {
                form.reset();
                form.action = form.dataset.actionTemplate.replace('__ID__', String(current.entry_id));
                form.querySelector('[name="lesson_date"]').value = current.original_date;
                toggleChangeFields(form);
            }
            const revert = occModalEl.querySelector('[data-change-revert]');
            if (revert) {
                revert.classList.toggle('d-none', !current.change_id);
                if (current.change_id) revert.action = i18n.revertAction.replace('__ID__', String(current.change_id));
            }
            occModal?.show();
        });
    });

    const toggleChangeFields = (form) => {
        const type = form.querySelector('[data-change-type]:checked')?.value || 'modify';
        form.querySelectorAll('[data-change-fields]').forEach((group) => {
            group.classList.toggle('d-none', !group.dataset.changeFields.split(' ').includes(type));
        });
    };
    occModalEl?.querySelectorAll('[data-change-type]').forEach((radio) => {
        radio.addEventListener('change', () => toggleChangeFields(radio.form));
    });

    occModalEl?.querySelector('[data-entry-edit]')?.addEventListener('click', async () => {
        if (!current) return;
        try {
            const { data } = await api(`/api/schedule/${current.entry_id}`);
            occModal.hide();
            openEntry(data, data.id);
        } catch (error) {
            toast(error.message, 'error');
        }
    });
})();
