/**
 * c:/xampp/htdocs/soporte_biofix/js/portal_tickets.js
 * 
 * Lógica para la tabla de tickets en el portal de usuario.
 */
document.addEventListener('DOMContentLoaded', function() {

    // 1. Para tickets pendientes o en proceso (toda la fila es clickeable)
    document.querySelectorAll('.ticket-row-expandable').forEach(row => {
        row.addEventListener('click', () => {
            const detailsRow = row.nextElementSibling;
            if (detailsRow) {
                detailsRow.style.display = detailsRow.style.display === 'table-row' ? 'none' : 'table-row';
            }
        });
    });

    // 2. Para tickets resueltos (solo el botón "Ver Respuesta" es clickeable)
    // Esta es la lógica corregida y robusta.
    document.querySelectorAll('.ver-respuesta-btn').forEach(button => {
        button.addEventListener('click', (e) => {
            e.stopPropagation(); // Previene otros eventos de clic
            const detailsRow = button.closest('tr').nextElementSibling;
            if (detailsRow) {
                detailsRow.style.display = detailsRow.style.display === 'table-row' ? 'none' : 'table-row';
            }
        });
    });
});