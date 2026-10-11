// Confirmation avant les actions sensibles : <button data-confirm="Message ?">
document.addEventListener('submit', function (event) {
    const submitter = event.submitter;
    const message = (submitter && submitter.dataset.confirm) || event.target.dataset.confirm;
    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

// Fermeture automatique des messages de succès
document.querySelectorAll('.alert-success').forEach(function (el) {
    setTimeout(function () {
        bootstrap.Alert.getOrCreateInstance(el).close();
    }, 5000);
});

// Filtre instantané des tableaux : <input data-table-filter="#idTable">
document.querySelectorAll('[data-table-filter]').forEach(function (input) {
    const table = document.querySelector(input.dataset.tableFilter);
    if (!table) return;
    input.addEventListener('input', function () {
        const term = input.value.toLowerCase();
        table.querySelectorAll('tbody tr').forEach(function (row) {
            row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
        });
    });
});
