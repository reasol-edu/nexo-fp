import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

const DEFAULTS = {
    search: '',
    familyId: '',
    programmeId: '',
    showCurrent: true,
    showFuture: true,
    showPast: true,
};

export default class extends Controller {
    static values = {
        centreId: String,
        active: Boolean,
    };

    async connect() {
        this.onRender = () => this.persist();
        this.element.addEventListener('live:render', this.onRender);

        if (this.activeValue) {
            return;
        }

        const saved = this.read();
        if (saved === null) {
            return;
        }

        const component = await getComponent(this.element);
        let changed = false;
        for (const [prop, value] of Object.entries(saved)) {
            if (value !== DEFAULTS[prop]) {
                component.set(prop, value, false);
                changed = true;
            }
        }
        if (changed) {
            component.render();
        }
    }

    disconnect() {
        this.element.removeEventListener('live:render', this.onRender);
    }

    persist() {
        const state = this.currentState();
        if (state === null) {
            return;
        }

        const isClean = Object.entries(DEFAULTS).every(([prop, def]) => state[prop] === def);
        try {
            if (isClean) {
                window.localStorage.removeItem(this.storageKey());
            } else {
                window.localStorage.setItem(this.storageKey(), JSON.stringify(state));
            }
        } catch {
            // localStorage no disponible (modo privado, cuota...): se ignora
        }
    }

    currentState() {
        let props;
        try {
            props = JSON.parse(this.element.dataset.livePropsValue ?? '{}');
        } catch {
            return null;
        }

        return this.sanitize(props);
    }

    read() {
        let raw;
        try {
            raw = window.localStorage.getItem(this.storageKey());
        } catch {
            return null;
        }
        if (raw === null) {
            return null;
        }

        let parsed;
        try {
            parsed = JSON.parse(raw);
        } catch {
            return null;
        }
        if (typeof parsed !== 'object' || parsed === null) {
            return null;
        }

        return this.sanitize(parsed);
    }

    sanitize(data) {
        const state = { ...DEFAULTS };

        if (typeof data.search === 'string') {
            state.search = data.search.slice(0, 255);
        }
        for (const prop of ['familyId', 'programmeId']) {
            if (typeof data[prop] === 'string' && UUID_PATTERN.test(data[prop])) {
                state[prop] = data[prop];
            }
        }
        for (const prop of ['showCurrent', 'showFuture', 'showPast']) {
            if (typeof data[prop] === 'boolean') {
                state[prop] = data[prop];
            }
        }

        return state;
    }

    storageKey() {
        return `nexofp:stay-filters:${this.centreIdValue}`;
    }
}
