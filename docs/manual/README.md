# Manual de usuario de Nexo FP

Manual de usuario y administración de Nexo FP. La **fuente única** son los ficheros Markdown de este
directorio (`index.md` + capítulos `NN-*.md`), versionables en Git, de los que se generan dos salidas:

- un **PDF** con estilo elegante (`nexo-fp-manual.pdf`), y
- una **web navegable** con buscador.

Ambas comparten la identidad visual de la presentación a través de `assets/theme.css` (paleta
`--nx-accent: #6d5b97`).

## Ficheros

- `index.md`, `01-*.md` … `10-*.md` — capítulos del manual (fuente única).
- `assets/theme.css` — tokens de diseño compartidos (PDF y web).
- `assets/print.css` — maquetación paginada del PDF (portada, índice, cabeceras/pies).
- `mkdocs.yml` — configuración de la web (MkDocs Material).
- `requirements.txt` — dependencia de la web (`mkdocs-material`).
- `img/` — capturas incrustadas en el manual.

## Generar el PDF

Desde la raíz del repositorio:

```bash
make docs-pdf
```

Genera `docs/manual/nexo-fp-manual.pdf`. Requiere **pandoc** y **Node.js** (usa `npx pagedjs-cli`,
Chromium headless: el mismo motor que el PDF de las diapositivas). No necesita instalación global.

> El PDF y la web no se versionan en el repositorio: los genera CI en cada release y se publican como
> `nexo-fp-manual-vX.Y.Z.pdf` y `nexo-fp-manual-web-vX.Y.Z.zip` en los activos del
> [GitHub Release](https://github.com/reasol-edu/nexo-fp/releases). Estos comandos son para
> previsualización local.

## Generar / previsualizar la web

```bash
pip install -r docs/manual/requirements.txt   # una sola vez
make docs-web                                 # construye docs/manual-site/
make docs-serve                               # previsualización en http://127.0.0.1:8000
```

## Generar ambas salidas

```bash
make docs
```
