import { Controller } from '@hotwired/stimulus';

// Autodescarta un aviso flash tras durationValue ms, pausando la cuenta atrás
// mientras el usuario tiene el ratón o el foco sobre él (p. ej. mientras lee un
// mensaje de error largo) y reanudándola al salir.
export default class extends Controller {
    static values = { duration: Number };

    #timer = null;
    #remaining = 0;
    #startedAt = 0;

    connect() {
        this.#remaining = this.durationValue;
        this.#schedule();
    }

    disconnect() {
        this.#clear();
    }

    pause() {
        if (this.#timer === null) return;
        this.#clear();
        this.#remaining -= Date.now() - this.#startedAt;
    }

    resume() {
        if (this.#timer !== null || this.#remaining <= 0) return;
        this.#schedule();
    }

    close() {
        this.#clear();
        this.element.style.opacity = '0';
        setTimeout(() => this.element.remove(), 500);
    }

    #schedule() {
        this.#startedAt = Date.now();
        this.#timer = setTimeout(() => this.close(), Math.max(this.#remaining, 0));
    }

    #clear() {
        clearTimeout(this.#timer);
        this.#timer = null;
    }
}
