import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

/*
 * Sincronización en vivo de una estancia vía Mercure.
 *
 * Se suscribe al topic de la estancia y, ante cualquier aviso, vuelve a renderizar
 * el Live Component anidado. El mensaje no transporta datos: el re-render se hace
 * en el servidor con los permisos del usuario actual.
 */
export default class extends Controller {
    static values = {
        url: String,
        topic: String,
    };

    connect() {
        if (!this.urlValue || !this.topicValue) {
            return;
        }

        const hub = new URL(this.urlValue, window.location.origin);
        hub.searchParams.append('topic', this.topicValue);

        this.eventSource = new EventSource(hub, { withCredentials: true });
        this.eventSource.onmessage = () => this.scheduleRefresh();
    }

    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
        if (this.refreshTimeout) {
            clearTimeout(this.refreshTimeout);
            this.refreshTimeout = null;
        }
    }

    scheduleRefresh() {
        // Agrupa ráfagas de avisos en un único re-render.
        if (this.refreshTimeout) {
            clearTimeout(this.refreshTimeout);
        }
        this.refreshTimeout = setTimeout(() => this.refresh(), 250);
    }

    refresh() {
        const element = this.element.querySelector('[data-controller~="live"]');
        if (!element) {
            return;
        }
        const before = this.snapshot();
        getComponent(element)
            .then((component) => component.render())
            .then(() => this.flashChanges(before))
            .catch(() => {});
    }

    // Mapa data-sync-key -> texto visible de la fila, para detectar qué cambió.
    snapshot() {
        const map = new Map();
        this.element.querySelectorAll('[data-sync-key]').forEach((node) => {
            map.set(node.dataset.syncKey, node.textContent.replace(/\s+/g, ' ').trim());
        });
        return map;
    }

    // Resalta solo las filas nuevas o cuyo texto difiere respecto al snapshot previo.
    flashChanges(before) {
        this.element.querySelectorAll('[data-sync-key]').forEach((node) => {
            const key = node.dataset.syncKey;
            const now = node.textContent.replace(/\s+/g, ' ').trim();
            if (before.has(key) && before.get(key) === now) {
                return;
            }
            node.classList.remove('js-sync-flash');
            void node.offsetWidth; // reinicia la animación si ya estaba aplicada
            node.classList.add('js-sync-flash');
            node.addEventListener(
                'animationend',
                () => node.classList.remove('js-sync-flash'),
                { once: true },
            );
        });
    }
}
