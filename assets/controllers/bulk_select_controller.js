import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

/*
 * Selección múltiple de filas dentro de un Live Component y barra de acciones masivas.
 *
 * Estructura esperada (dentro del componente):
 *  - casillas de fila:   data-bulk-select-target="row" value="<id>" data-action="change->bulk-select#toggle"
 *  - casilla de cabecera: data-bulk-select-target="all" data-action="change->bulk-select#toggleAll"
 *  - barra de acciones:  data-bulk-select-target="bar" (oculta mientras no hay selección), con
 *    data-bulk-select-target="count" (texto con «%count%») y botones data-action="bulk-select#run"
 *    data-op="<operación>"; un select data-bulk-select-target="teacher" para la operación «tutor».
 *
 * La selección vive en este controlador (no en el DOM) porque el componente se vuelve a pintar
 * en cada cambio, también los de otras personas, y las casillas se reponen desde aquí.
 */
export default class extends Controller {
    static targets = ['row', 'all', 'bar', 'count', 'teacher'];
    static values = { action: { type: String, default: 'bulk' } };

    connect() {
        this.selected = new Set();
        this.observer = new MutationObserver(() => this.sync());
        this.observer.observe(this.element, { childList: true, subtree: true });
        // La lista avisa cuando un filtro, una búsqueda o un grupo plegado ocultan filas (no cambia el DOM, solo clases).
        this.onRefresh = () => this.sync();
        this.element.addEventListener('bulk-select:refresh', this.onRefresh);
        this.sync();
    }

    disconnect() {
        this.observer?.disconnect();
        this.element.removeEventListener('bulk-select:refresh', this.onRefresh);
    }

    // Casillas de las filas que se ven: las ocultas por un filtro o un grupo plegado no se marcan ni se envían.
    get visibleBoxes() {
        return this.rowTargets.filter((box) => !box.closest('tr.hidden'));
    }

    toggle(event) {
        const box = event.target;
        if (box.checked) this.selected.add(box.value); else this.selected.delete(box.value);
        this.sync();
    }

    toggleAll(event) {
        this.visibleBoxes.forEach((box) => {
            if (event.target.checked) this.selected.add(box.value); else this.selected.delete(box.value);
        });
        this.sync();
    }

    clear() {
        this.selected.clear();
        this.sync();
    }

    async run(event) {
        const op = event.currentTarget.dataset.op;
        const ids = [...this.selected].filter((id) => this.visibleBoxes.some((box) => box.value === id));
        if (ids.length === 0) return;

        const args = { op, positionIds: ids };
        if (op === 'tutor') {
            const teacherId = this.hasTeacherTarget ? this.teacherTarget.value : '';
            if (!teacherId) { this.teacherTarget?.focus(); return; }
            args.teacherId = teacherId;
        }

        const root = this.element.closest('[data-controller~="live"]') ?? this.element;
        const component = await getComponent(root);
        this.selected.clear();
        this.sync();
        component.action(this.actionValue, args);
    }

    // Repone las casillas desde la selección y actualiza la barra y la casilla de cabecera.
    sync() {
        if (this.syncing) return;
        this.syncing = true;
        const present = new Set(this.rowTargets.map((box) => box.value));
        [...this.selected].forEach((id) => { if (!present.has(id)) this.selected.delete(id); });
        this.rowTargets.forEach((box) => { box.checked = this.selected.has(box.value); });

        // Solo cuentan las filas visibles: lo marcado que queda oculto no se ve ni se envía.
        const visible         = this.visibleBoxes;
        const selectedVisible = visible.filter((box) => this.selected.has(box.value)).length;
        if (this.hasAllTarget) {
            this.allTarget.checked = visible.length > 0 && selectedVisible === visible.length;
            this.allTarget.indeterminate = selectedVisible > 0 && selectedVisible < visible.length;
        }
        if (this.hasBarTarget) {
            this.barTarget.classList.toggle('hidden', selectedVisible === 0);
        }
        if (this.hasCountTarget) {
            const template = this.countTarget.dataset.template ?? '%count%';
            this.countTarget.textContent = template.replace('%count%', String(selectedVisible));
        }
        // Las modificaciones anteriores disparan el observador: se ignoran en este turno.
        setTimeout(() => { this.syncing = false; }, 0);
    }
}
