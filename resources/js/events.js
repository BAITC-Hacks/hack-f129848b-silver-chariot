document.querySelectorAll('[data-event-form]').forEach((form) => {
    form.querySelectorAll('[data-row-group]').forEach((group) => {
        const container = group.querySelector('[data-row-container]');
        const template = group.querySelector('template');
        let nextIndex = Math.max(-1, ...Array.from(container.children, (row) => Number(row.dataset.rowIndex))) + 1;

        group.querySelector('[data-add-row]').addEventListener('click', () => {
            const row = template.content.firstElementChild.cloneNode(true);
            const index = String(nextIndex++);
            row.dataset.rowIndex = index;
            row.querySelectorAll('*').forEach((element) => {
                ['id', 'name', 'for', 'aria-describedby'].forEach((attribute) => {
                    if (element.hasAttribute(attribute)) {
                        element.setAttribute(attribute, element.getAttribute(attribute).replaceAll('__INDEX__', index));
                    }
                });
            });
            container.append(row);
            row.querySelector('input, select')?.focus();
        });

        container.addEventListener('click', (event) => {
            const button = event.target.closest('[data-remove-row]');
            if (!button || button.disabled) {
                return;
            }

            const row = button.closest('[data-row-index]');
            const nextControl = row.nextElementSibling?.querySelector('input, select')
                ?? row.previousElementSibling?.querySelector('input, select')
                ?? group.querySelector('[data-add-row]');
            row.remove();
            nextControl.focus();
        });
    });

    const format = form.querySelector('[name="format"]');
    const schedule = form.querySelector('[data-schedule]');
    const selfPacedHint = form.querySelector('[data-self-paced-hint]');
    const updateSchedule = () => {
        const selfPaced = format.value === 'self_paced';
        schedule.hidden = selfPaced;
        schedule.disabled = selfPaced;
        selfPacedHint.hidden = !selfPaced;
    };

    format.addEventListener('change', updateSchedule);
    updateSchedule();
});

document.querySelectorAll('[data-event-delete]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm('Удалить эту активность из каталога? Восстановить её не получится.')) {
            event.preventDefault();
        }
    });
});
