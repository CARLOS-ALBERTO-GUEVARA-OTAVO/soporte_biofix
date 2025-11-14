/**
 * Clase para gestionar la inactividad del usuario y mostrar una advertencia antes de cerrar la sesión.
 */
class InactivityTimer {
    constructor({
        logoutUrl = 'login/logout.php',
        timeout = 20000, // 10 minutos por defecto
        warningTime = 10000, // 10 segundos de advertencia
        warningModalId = 'inactivity-warning-modal',
        countdownSpanId = 'countdown-timer'
    }) {
        this.logoutUrl = logoutUrl;
        this.timeout = timeout;
        this.warningTime = warningTime;
        this.warningModal = document.getElementById(warningModalId);
        this.countdownSpan = document.getElementById(countdownSpanId);

        this.timer = null;
        this.warningTimer = null;
        this.countdownInterval = null;

        this.events = ['mousemove', 'mousedown', 'keypress', 'scroll', 'touchstart'];
        this.resetTimer = this.resetTimer.bind(this);

        this.events.forEach(event => {
            window.addEventListener(event, this.resetTimer, true);
        });

        this.startTimer();
    }

    startTimer() {
        this.timer = setTimeout(() => this.showWarning(), this.timeout - this.warningTime);
    }

    resetTimer() {
        clearTimeout(this.timer);
        clearTimeout(this.warningTimer);
        clearInterval(this.countdownInterval);
        this.hideWarning();
        this.startTimer();
    }

    showWarning() {
        if (!this.warningModal) return;

        this.warningModal.style.display = 'flex';
        let countdown = this.warningTime / 1000;
        this.countdownSpan.textContent = countdown;

        this.countdownInterval = setInterval(() => {
            countdown--;
            this.countdownSpan.textContent = countdown;
        }, 1000);

        this.warningTimer = setTimeout(() => {
            window.location.href = this.logoutUrl;
        }, this.warningTime);
    }

    hideWarning() {
        if (this.warningModal) {
            this.warningModal.style.display = 'none';
        }
    }
}