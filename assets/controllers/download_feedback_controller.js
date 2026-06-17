import { Controller } from '@hotwired/stimulus';

/*
 * Muestra un aviso breve no bloqueante al iniciar una descarga (p. ej. la
 * exportación CSV). La descarga llega como adjunto (Content-Disposition), así
 * que la página no navega y el aviso se puede mostrar y retirar solo.
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = {
        message: String,
        duration: { type: Number, default: 4000 },
    };

    show() {
        const toast = document.createElement('div');
        toast.setAttribute('role', 'status');
        toast.className = 'fixed bottom-4 right-4 z-[70] flex items-center gap-2 rounded-xl border ' +
            'border-plum-200 bg-white px-4 py-3 text-sm text-plum-800 shadow-lg transition-opacity duration-300';
        toast.textContent = this.messageValue;
        document.body.appendChild(toast);

        window.setTimeout(() => {
            toast.style.opacity = '0';
            window.setTimeout(() => toast.remove(), 300);
        }, this.durationValue);
    }
}
