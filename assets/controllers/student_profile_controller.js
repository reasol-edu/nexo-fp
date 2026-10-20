import { Controller } from '@hotwired/stimulus';

/*
 * Ficha del estudiante en un diálogo. El disparador (botón o insignia) lleva la URL de la ficha en
 * «data-student-profile-url-value»; al pulsarlo se descarga el formulario, se muestra en un <dialog>
 * nativo (foco atrapado, Escape y clic fuera lo cierran) y se envía por fetch. Si hay errores de
 * validación el servidor devuelve el formulario con ellos (422); al guardar se recarga la página.
 * Sin JavaScript, el enlace del disparador abre la ficha como página completa.
 */
/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static values = { url: String };

    open(event) {
        event.preventDefault();
        event.stopPropagation();
        this.trigger = event.currentTarget;
        this.dialog = document.createElement('dialog');
        this.dialog.className = 'm-auto max-h-[90vh] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-2xl border border-gray-200 bg-white p-6 shadow-xl backdrop:bg-gray-900/40';
        this.dialog.setAttribute('aria-labelledby', 'student-profile-title');
        this.dialog.addEventListener('close', () => this.teardown());
        this.dialog.addEventListener('click', (e) => {
            if (e.target === this.dialog) {
                this.dialog.close();
            }
        });
        document.body.appendChild(this.dialog);

        this.load(this.urlValue).then((ok) => {
            if (ok) {
                this.dialog.showModal();
            } else {
                this.teardown();
                window.location.href = this.urlValue;
            }
        });
    }

    disconnect() {
        this.teardown();
    }

    async load(url, options = {}) {
        try {
            const response = await fetch(url, {
                ...options,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (response.ok && (response.headers.get('Content-Type') || '').includes('json')) {
                window.location.reload();
                return true;
            }
            if (response.ok || response.status === 422) {
                this.dialog.innerHTML = await response.text();
                this.bind();
                return true;
            }
        } catch (error) {
            // Sin red o respuesta inesperada: se abre la página completa.
        }
        return false;
    }

    bind() {
        const form = this.dialog.querySelector('[data-student-profile-form]');
        if (!form) {
            return;
        }
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            this.load(form.action, { method: 'POST', body: new FormData(form) });
        });
        this.dialog.querySelector('[data-student-profile-close]')?.addEventListener('click', () => this.dialog.close());
        (this.dialog.querySelector('[aria-invalid="true"], input:checked, input, textarea'))?.focus({ preventScroll: true });
    }

    teardown() {
        if (this.dialog) {
            const dialog = this.dialog;
            this.dialog = null;
            if (dialog.open) {
                dialog.close();
            }
            dialog.remove();
            this.trigger?.focus?.();
        }
    }
}
