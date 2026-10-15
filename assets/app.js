import './stimulus_bootstrap.js';
import './styles/app.css';

// Los desplegables de TomSelect se cuelgan de <body>: dentro de una tarjeta con `overflow-hidden`
// o de una tabla con scroll, el desplegable quedaba recortado por su contenedor. Se aplica a todos
// los autocompletados de Symfony UX; las instancias de TomSelect creadas a mano lo indican por su cuenta.
document.addEventListener('autocomplete:pre-connect', (event) => {
    event.detail.options.dropdownParent = 'body';
});

// When the user types in a remote autocomplete (ux-autocomplete), clear the existing
// options and the search cache so that new results replace the previous ones instead
// of being appended. This runs after Stimulus has connected all controllers.
document.addEventListener('DOMContentLoaded', () => {
    requestAnimationFrame(() => {
        document.querySelectorAll('[data-controller~="symfony--ux-autocomplete--autocomplete"]').forEach(el => {
            const ts = el.tomselect;
            if (!ts) return;
            ts.on('type', () => {
                ts.clearOptions();
                ts.loadedSearches = {};
            });
        });
    });
});
