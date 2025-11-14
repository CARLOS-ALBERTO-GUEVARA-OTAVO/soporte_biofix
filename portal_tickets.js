document.addEventListener('DOMContentLoaded', function() {
    const verRespuestaBtns = document.querySelectorAll('.ver-respuesta-btn');

    verRespuestaBtns.forEach(button => {
        button.addEventListener('click', function(event) {
            event.stopPropagation(); // Evita que el evento de clic en la fila se dispare también
            const ticketId = this.dataset.ticketId;
            const detailsRow = document.getElementById(`details-${ticketId}`);
            const parentRow = this.closest('.ticket-row');

            if (detailsRow) {
                detailsRow.classList.toggle('show');
                parentRow.classList.toggle('expanded');
                this.classList.toggle('expanded'); // Para rotar el icono del botón
                
                // Cambiar texto e icono del botón
                if (this.classList.contains('expanded')) {
                    this.innerHTML = 'Ocultar Respuesta <i class="fas fa-chevron-up ml-2"></i>';
                } else {
                    this.innerHTML = 'Ver Respuesta <i class="fas fa-chevron-down ml-2"></i>';
                }
            }
        });
    });

    // Opcional: Hacer que toda la fila sea clicable para expandir/colapsar, si tiene un botón de respuesta
    const ticketRows = document.querySelectorAll('.ticket-row');
    ticketRows.forEach(row => {
        const responseButton = row.querySelector('.ver-respuesta-btn');
        if (responseButton) { // Solo si la fila tiene un botón de respuesta
            row.style.cursor = 'pointer'; // Indicar que es clicable
            row.addEventListener('click', function() {
                // Simular un clic en el botón de respuesta para activar su lógica
                responseButton.click();
            });
        }
    });
});