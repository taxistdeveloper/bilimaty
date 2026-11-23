/**
 * Electronic journal grid: autosave of cells via Fetch API, keyboard navigation,
 * live recalculation of the student's results, comments and the lesson modal.
 *
 * Keys: Enter / ↓ — save and go down, ↑ — up, Alt+Enter or right click — comment.
 */
(() => {
    'use strict';
    const { api, toast } = window.CollegeOS || {};
    const grid = document.getElementById('journalGrid');
    const i18n = JSON.parse(document.getElementById('journalI18n')?.textContent || '{}');
    const journalId = grid?.dataset.journal;

    const fmt = (v) => {
        if (v === null || v === undefined || v === '') return '';
        return String(Number(Number(v).toFixed(2)));
    };

    const updateRow = (row, result) => {
        Object.entries(result.components || {}).forEach(([typeId, value]) => {
            const el = row.querySelector(`[data-result-type="${typeId}"]`);
            if (!el) return;
            if (el.tagName === 'INPUT') {
                if (document.activeElement !== el) el.value = fmt(value);
            } else {
                el.textContent = fmt(value);
            }
        });
        const finalCell = row.querySelector('[data-result="final"]');
        const final = result.fixed_final !== null && result.fixed_final !== undefined && grid.dataset.approved === '1' ? result.fixed_final : result.final;
        if (finalCell) {
            finalCell.textContent = fmt(final) || '—';
            finalCell.classList.toggle('is-pass', result.passed === true);
            finalCell.classList.toggle('is-fail', result.passed === false);
        }
        const absences = row.querySelector('[data-result="absences"]');
        if (absences) absences.textContent = result.absent + result.excused > 0 ? `${result.absent}/${result.excused}` : '';
    };

    const save = async (input, extra = {}) => {
        const row = input.closest('tr');
        const body = { kind: input.dataset.kind, student_id: row.dataset.student, value: input.value, ...extra };
        if (input.dataset.kind === 'lesson') body.lesson_id = input.dataset.lesson;
        if (input.dataset.kind === 'assessment') body.type_id = input.dataset.type;
        input.classList.remove('is-invalid');
        input.classList.add('is-saving');
        try {
            const { data } = await api(`/api/journals/${journalId}/grades`, { method: 'POST', body });
            input.dataset.prev = input.value;
            input.classList.add('is-saved');
            setTimeout(() => input.classList.remove('is-saved'), 900);
            if (input.dataset.kind === 'lesson') {
                const v = input.value.trim().toLowerCase();
                input.closest('td').classList.toggle('is-absent', ['н', 'n', 'у', 'y'].includes(v));
                if (v === 'n') input.value = 'н';
                if (v === 'y') input.value = 'у';
                if (v !== '' && !['н', 'n', 'у', 'y'].includes(v)) input.value = fmt(v.replace(',', '.'));
                input.dataset.prev = input.value;
            }
            updateRow(row, data.result);
            return true;
        } catch (error) {
            input.classList.add('is-invalid');
            const message = error.errors && Object.values(error.errors)[0] ? Object.values(error.errors)[0][0] : error.message;
            toast(message, 'error');
            return false;
        } finally {
            input.classList.remove('is-saving');
        }
    };

    const move = (input, delta) => {
        const td = input.closest('td');
        const index = [...td.parentElement.children].indexOf(td);
        let row = td.parentElement;
        do {
            row = delta > 0 ? row.nextElementSibling : row.previousElementSibling;
        } while (row && row.children[index]?.querySelector('input[readonly]'));
        row?.children[index]?.querySelector('input')?.focus();
    };

    if (grid && grid.dataset.editable === '1') {
        grid.querySelectorAll('input.app-cell:not([readonly])').forEach((input) => {
            input.dataset.prev = input.value;
            input.addEventListener('focus', () => input.select());
            input.addEventListener('blur', () => {
                if (input.value !== input.dataset.prev) save(input);
            });
            input.addEventListener('keydown', async (event) => {
                if (event.key === 'Enter' && event.altKey && input.dataset.kind === 'lesson') {
                    event.preventDefault();
                    openComment(input);
                    return;
                }
                if (event.key === 'Enter' || event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    if (input.value !== input.dataset.prev && !(await save(input))) return;
                    move(input, event.key === 'ArrowUp' ? -1 : 1);
                }
                if (event.key === 'Escape') {
                    input.value = input.dataset.prev;
                    input.classList.remove('is-invalid');
                }
            });
            if (input.dataset.kind === 'lesson') {
                input.addEventListener('contextmenu', (event) => {
                    event.preventDefault();
                    openComment(input);
                });
            }
        });
    }

    /* ---- Comments ---- */
    const commentModalEl = document.getElementById('commentModal');
    const commentModal = commentModalEl && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(commentModalEl) : null;
    let commentInput = null;
    const openComment = (input) => {
        if (!commentModal) return;
        commentInput = input;
        commentModalEl.querySelector('[data-comment-target]').textContent = `${i18n.comment_for || ''} ${input.getAttribute('aria-label')}`;
        commentModalEl.querySelector('textarea').value = input.dataset.comment || '';
        commentModal.show();
    };
    commentModalEl?.addEventListener('shown.bs.modal', () => commentModalEl.querySelector('textarea').focus());
    document.getElementById('commentForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!commentInput) return;
        const text = commentModalEl.querySelector('textarea').value;
        const row = commentInput.closest('tr');
        try {
            const { data } = await api(`/api/journals/${journalId}/grades`, {
                method: 'POST',
                body: { kind: 'comment', student_id: row.dataset.student, lesson_id: commentInput.dataset.lesson, comment: text },
            });
            commentInput.dataset.comment = text.trim();
            commentInput.title = text.trim();
            commentInput.closest('td').classList.toggle('has-comment', text.trim() !== '');
            updateRow(row, data.result);
            commentModal.hide();
            toast(i18n.saved, 'success');
        } catch (error) {
            toast(error.message, 'error');
        }
    });

    /* ---- Lesson modal ---- */
    const lessonModalEl = document.getElementById('lessonModal');
    const lessonForm = document.getElementById('lessonForm');
    const lessonModal = lessonModalEl && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(lessonModalEl) : null;
    const openLesson = (data) => {
        const title = lessonModalEl.querySelector('#lessonTitle');
        title.textContent = data.lesson_id ? title.dataset.titleEdit : title.dataset.titleNew;
        lessonForm.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        lessonForm.querySelectorAll('.invalid-feedback').forEach((el) => el.remove());
        ['lesson_id', 'lesson_date', 'lesson_period_id', 'lesson_type', 'topic', 'homework', 'note', 'hours', 'conducted_by'].forEach((name) => {
            const field = lessonForm.querySelector(`[name="${name}"]`);
            if (field && data[name] !== undefined) field.value = data[name] ?? '';
        });
        const del = lessonForm.querySelector('[data-lesson-delete]');
        del.classList.toggle('d-none', !data.lesson_id);
        del.dataset.url = data.delete_url || '';
        lessonModal.show();
    };
    document.querySelector('[data-lesson-new]')?.addEventListener('click', () => openLesson({
        lesson_id: '', topic: '', homework: '', note: '', hours: 2,
        lesson_date: lessonForm.querySelector('[name="lesson_date"]').defaultValue,
    }));
    document.querySelectorAll('[data-lesson-edit]').forEach((button) => {
        button.addEventListener('click', () => openLesson(JSON.parse(button.dataset.lessonEdit)));
    });
    lessonForm?.querySelector('[data-lesson-delete]')?.addEventListener('click', (event) => {
        const button = event.currentTarget;
        if (!button.dataset.url || !window.confirm(button.dataset.confirmText)) return;
        const form = document.getElementById('lessonDeleteForm');
        form.action = button.dataset.url;
        form.submit();
    });
})();
