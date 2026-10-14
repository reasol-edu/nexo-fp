import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['backdrop', 'dialog', 'input', 'results', 'itemTemplate', 'groupTemplate'];
    static values  = { url: String, groupLabels: Object, noResults: String };

    #debounce = null;
    #abort    = null;
    #selected = -1;

    connect() {
        this._onKeydown = this.#handleGlobalKeydown.bind(this);
        document.addEventListener('keydown', this._onKeydown);
    }

    disconnect() {
        document.removeEventListener('keydown', this._onKeydown);
        clearTimeout(this.#debounce);
        this.#abort?.abort();
    }

    open() {
        this.backdropTarget.classList.remove('hidden');
        this.dialogTarget.classList.remove('hidden');
        this.inputTarget.value = '';
        this.resultsTarget.innerHTML = '';
        this.#selected = -1;
        this.inputTarget.focus();
        // Sin texto se ofrecen los elementos recientes y las acciones rápidas.
        this.#fetchResults();
    }

    // Guarda en el navegador el elemento elegido para ofrecerlo como «reciente» (hasta 5).
    remember(event) {
        const link = event.target.closest('a[data-item]');
        if (link) this.#saveRecent(link);
    }

    close() {
        this.backdropTarget.classList.add('hidden');
        this.dialogTarget.classList.add('hidden');
        clearTimeout(this.#debounce);
        this.#abort?.abort();
    }

    onInput() {
        clearTimeout(this.#debounce);
        this.#debounce = setTimeout(() => this.#fetchResults(), 250);
    }

    onKeydown(event) {
        const items = Array.from(this.resultsTarget.querySelectorAll('a[data-item]'));
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            this.#selected = Math.min(this.#selected + 1, items.length - 1);
            this.#highlightItem(items);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            this.#selected = Math.max(this.#selected - 1, -1);
            this.#highlightItem(items);
        } else if (event.key === 'Enter' && this.#selected >= 0 && items[this.#selected]) {
            event.preventDefault();
            this.#saveRecent(items[this.#selected]);
            window.location.href = items[this.#selected].href;
        } else if (event.key === 'Escape') {
            this.close();
        }
    }

    // ── Private ───────────────────────────────────────────────────────────────

    #handleGlobalKeydown(event) {
        const isOpen = !this.dialogTarget.classList.contains('hidden');

        if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
            event.preventDefault();
            isOpen ? this.close() : this.open();
        } else if (event.key === 'Escape' && isOpen) {
            this.close();
        }
    }

    async #fetchResults() {
        const q = this.inputTarget.value.trim();
        if (q.length === 1) {
            this.resultsTarget.innerHTML = '';
            return;
        }

        this.#abort?.abort();
        this.#abort = new AbortController();

        try {
            const res = await fetch(
                this.urlValue + '?q=' + encodeURIComponent(q),
                { signal: this.#abort.signal },
            );
            if (!res.ok) return;
            const data = await res.json();
            const groups = data.groups ?? {};
            if (q === '') {
                const recent = this.#loadRecent();
                this.#renderResults(recent.length > 0 ? { recent, ...groups } : groups, true);
            } else {
                this.#renderResults(groups);
            }
        } catch (err) {
            if (err.name !== 'AbortError') console.error(err);
        }
    }

    #loadRecent() {
        try {
            const list = JSON.parse(window.localStorage.getItem('nexo.palette.recent') ?? '[]');
            return Array.isArray(list) ? list.filter((i) => i && typeof i.url === 'string' && typeof i.label === 'string') : [];
        } catch (e) {
            return [];
        }
    }

    #saveRecent(link) {
        const item = {
            label: link.querySelector('[data-slot="label"]')?.textContent ?? '',
            sublabel: link.querySelector('[data-slot="sublabel"]')?.textContent ?? '',
            url: link.getAttribute('href') ?? '',
        };
        if (item.label === '' || item.url === '') return;
        try {
            const list = this.#loadRecent().filter((i) => i.url !== item.url);
            window.localStorage.setItem('nexo.palette.recent', JSON.stringify([item, ...list].slice(0, 5)));
        } catch (e) {
            // Sin almacenamiento disponible: no se recuerdan elementos recientes.
        }
    }

    #renderResults(groups, emptyQuery = false) {
        this.resultsTarget.innerHTML = '';
        this.#selected = -1;
        const keys = Object.keys(groups);

        if (keys.length === 0 && emptyQuery) {
            return;
        }

        if (keys.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'px-4 py-6 text-center text-sm text-gray-400';
            empty.textContent = this.noResultsValue;
            this.resultsTarget.appendChild(empty);
            return;
        }

        for (const key of keys) {
            const items = groups[key];
            if (!items || items.length === 0) continue;

            // Group header
            const header = this.groupTemplateTarget.content.cloneNode(true);
            header.querySelector('[data-slot="name"]').textContent = this.groupLabelsValue[key] ?? key;
            this.resultsTarget.appendChild(header);

            for (const item of items) {
                const node = this.itemTemplateTarget.content.cloneNode(true);
                node.querySelector('[data-slot="label"]').textContent    = item.label;
                node.querySelector('[data-slot="sublabel"]').textContent = item.sublabel ?? '';
                const link = node.querySelector('a');
                link.href            = item.url;
                link.dataset.item    = '1';
                this.resultsTarget.appendChild(node);
            }
        }
    }

    #highlightItem(items) {
        items.forEach((el, i) => {
            el.classList.toggle('bg-gray-50', i === this.#selected);
        });
        if (this.#selected >= 0) {
            items[this.#selected]?.scrollIntoView({ block: 'nearest' });
        }
    }
}
