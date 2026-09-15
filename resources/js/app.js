import './bootstrap';
import 'bootstrap';
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.bootstrap5.css';

window.TomSelect = TomSelect;

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.js-searchable-select').forEach((element) => {
        if (element.dataset.tomselectInitialized === 'true') {
            return;
        }

        if (element.closest('.modal') && element.closest('.modal').dataset.tomselectPending === 'true') {
            return;
        }

        new TomSelect(element, {
            plugins: ['clear_button'],
            persist: false,
            create: false,
            sortField: { field: 'text', direction: 'asc' },
            placeholder: 'Buscar...',
            hideSelected: false,
            closeAfterSelect: true,
        });

        element.dataset.tomselectInitialized = 'true';
    });
});