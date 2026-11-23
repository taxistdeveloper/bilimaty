/**
 * Practice schedule editor: add / remove rows, live hours (end − start), rows on weekends and public
 * holidays are marked and not counted, the total is compared with the assigned hours.
 * The server repeats every check; this only helps while typing.
 */
(() => {
    'use strict';

    const form = document.querySelector('[data-practice-form]');
    if (!form) return;

    const body = form.querySelector('[data-rows]');
    const template = form.querySelector('[data-row-template]');
    const actionInput = form.querySelector('[data-action]');
    const summary = document.querySelector('[data-practice-summary]');
    const limit = Number(summary?.dataset.limit || 0);
    const holidays = new Set(JSON.parse(form.dataset.holidays || '[]'));

    const minutes = (value) => {
        const m = /^(\d{1,2}):(\d{2})/.exec(value || '');
        return m ? Number(m[1]) * 60 + Number(m[2]) : null;
    };
    const fmt = (h) => String(Math.round(h * 10) / 10).replace('.', ',');

    const exclusion = (date) => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return null;
        const day = new Date(`${date}T00:00:00`).getDay();
        if (day === 0 || day === 6) return 'weekend';
        return holidays.has(date) ? 'holiday' : null;
    };

    const renumber = () => {
        body.querySelectorAll('[data-row]').forEach((row, index) => {
            row.querySelector('[data-row-number]').textContent = String(index + 1);
            row.querySelectorAll('[name^="entries["]').forEach((input) => {
                input.name = input.name.replace(/^entries\[[^\]]*\]/, `entries[${index}]`);
            });
        });
    };

    const recalc = () => {
        let counted = 0;
        let excluded = 0;
        body.querySelectorAll('[data-row]').forEach((row) => {
            const start = minutes(row.querySelector('[data-start]').value);
            const end = minutes(row.querySelector('[data-end]').value);
            const date = row.querySelector('[data-date]').value;
            const hours = start !== null && end !== null && end > start ? Math.round((end - start) / 6) / 10 : 0;
            const reason = exclusion(date);
            const note = row.querySelector('[data-hours-note]');
            row.querySelector('[data-hours]').textContent = fmt(hours);
            note.textContent = reason ? form.dataset[reason === 'weekend' ? 'labelWeekend' : 'labelHoliday'] : '';
            row.classList.toggle('table-warning', reason !== null && date !== '');
            if (hours > 0 && date) {
                if (reason) excluded += hours; else counted += hours;
            }
        });
        if (!summary) return;
        const percent = limit > 0 ? Math.min(100, Math.round((counted / limit) * 100)) : 0;
        summary.querySelector('[data-sum-counted]').textContent = fmt(counted);
        summary.querySelector('[data-sum-remaining]').textContent = fmt(Math.max(0, limit - counted));
        summary.querySelector('[data-sum-over]').textContent = fmt(Math.max(0, counted - limit));
        summary.querySelector('[data-sum-excluded]').textContent = fmt(excluded);
        const bar = summary.querySelector('[data-sum-bar]');
        bar.style.width = `${percent}%`;
        bar.classList.toggle('bg-danger', counted > limit + 0.05);
        bar.classList.toggle('bg-success', Math.abs(counted - limit) <= 0.05);
    };

    form.querySelector('[data-add-row]').addEventListener('click', () => {
        const html = template.innerHTML.replaceAll('__i__', String(body.children.length));
        body.insertAdjacentHTML('beforeend', html);
        renumber();
        body.lastElementChild.querySelector('textarea')?.focus();
    });

    body.addEventListener('click', (event) => {
        const button = event.target.closest('[data-remove-row]');
        if (!button) return;
        const rows = body.querySelectorAll('[data-row]');
        if (rows.length === 1) {
            rows[0].querySelectorAll('input:not([type=checkbox]), textarea').forEach((input) => { input.value = ''; });
            rows[0].querySelector('input[type=checkbox]').checked = false;
        } else {
            button.closest('[data-row]').remove();
        }
        renumber();
        recalc();
    });

    body.addEventListener('input', recalc);
    body.addEventListener('change', recalc);

    // Two buttons, one form: "save" goes straight, "submit" asks for confirmation (app.js modal).
    form.querySelector('[data-save]').addEventListener('click', () => {
        actionInput.value = 'save';
        delete form.dataset.confirm;
    });
    form.querySelector('[data-submit]').addEventListener('click', () => {
        actionInput.value = 'submit';
        form.dataset.confirm = form.dataset.submitConfirm;
        delete form.dataset.confirmed;
    });

    renumber();
    recalc();
})();
