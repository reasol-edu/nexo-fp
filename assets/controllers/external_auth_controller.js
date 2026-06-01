import { Controller } from '@hotwired/stimulus';

/**
 * Oculta/muestra el bloque de contraseña según el estado del checkbox
 * de autenticación externa. Si el docente es externo, la contraseña
 * no es necesaria y el campo se deshabilita para evitar envíos accidentales.
 */
export default class extends Controller {
    static targets = ['passwordBlock', 'passwordInput', 'checkbox'];

    connect() {
        this.#update(false);
    }

    toggle() {
        this.#update(true);
    }

    #update(animate) {
        const isExternal = this.checkboxTarget.checked;

        if (isExternal) {
            if (animate) {
                this.passwordBlockTarget.style.maxHeight = this.passwordBlockTarget.scrollHeight + 'px';
                requestAnimationFrame(() => {
                    this.passwordBlockTarget.style.maxHeight = '0';
                    this.passwordBlockTarget.style.opacity = '0';
                });
            } else {
                this.passwordBlockTarget.style.maxHeight = '0';
                this.passwordBlockTarget.style.opacity = '0';
            }
            this.passwordBlockTarget.style.overflow = 'hidden';
            this.passwordInputTarget.disabled = true;
            this.passwordInputTarget.value = '';
        } else {
            this.passwordBlockTarget.style.overflow = 'hidden';
            this.passwordBlockTarget.style.maxHeight = this.passwordBlockTarget.scrollHeight + 'px';
            this.passwordBlockTarget.style.opacity = '1';
            this.passwordInputTarget.disabled = false;
            // Cuando se vuelve a mostrar, quitar max-height tras la transición para no
            // bloquear el layout en caso de redimensionado
            if (animate) {
                this.passwordBlockTarget.addEventListener(
                    'transitionend',
                    () => { this.passwordBlockTarget.style.maxHeight = 'none'; },
                    { once: true }
                );
            } else {
                this.passwordBlockTarget.style.maxHeight = 'none';
            }
        }
    }
}

