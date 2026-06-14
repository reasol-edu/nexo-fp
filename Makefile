.PHONY: fixtures migrate setup test slides docs docs-pdf docs-web docs-serve

test:
	php bin/phpunit

## Carga los fixtures de demostración.
##
## Se usa --append para que el ORM Purger no intervenga: el esquema contiene
## una FK circular (educational_centre <-> academic_year) que el purger no
## puede resolver, y setting_definition contiene datos de referencia sembrados
## por las migraciones que no deben borrarse.
## La limpieza real la realiza wipeDatabase() dentro del propio fixture.
fixtures:
	php bin/console doctrine:fixtures:load --no-interaction --append

migrate:
	php bin/console doctrine:migrations:migrate --no-interaction

setup:
	php bin/console app:setup --no-interaction


## Genera la presentación en PDF (docs/slides/nexo-fp.pdf).
##
## Requiere Node.js: usa "npx @marp-team/marp-cli" sin instalación global.
## --allow-local-files permite incrustar las capturas de docs/slides/img.
## Cambia la extensión de salida a .pptx o .html para otros formatos.
slides:
	@command -v npx >/dev/null 2>&1 || { echo "Necesitas Node.js/npx para generar la presentación. Instala Node y reintenta."; exit 1; }
	npx --yes @marp-team/marp-cli docs/slides/nexo-fp.md --allow-local-files -o docs/slides/nexo-fp.pdf

## Genera el manual completo: PDF y web.
docs: docs-pdf docs-web

## Genera el manual en PDF (docs/manual/nexo-fp-manual.pdf).
##
## Usa pandoc (Markdown -> HTML) y "npx pagedjs-cli" (Chromium headless, el
## mismo motor que el PDF de las slides) para imprimir el HTML con el tema CSS.
## El HTML intermedio (_build.html) se genera en docs/manual/ para que las
## rutas relativas a assets/ e img/ resuelvan igual que en la web.
##
## pagedjs-cli usa Puppeteer; reutilizamos el Chrome del sistema (el mismo que
## genera el PDF de las slides) vía PUPPETEER_EXECUTABLE_PATH para no descargar
## un Chromium aparte. Si no se encuentra, instala uno con "npx puppeteer
## browsers install chrome".
docs-pdf:
	@command -v pandoc >/dev/null 2>&1 || { echo "Necesitas pandoc. Instálalo (p. ej. brew install pandoc) y reintenta."; exit 1; }
	@command -v npx >/dev/null 2>&1 || { echo "Necesitas Node.js/npx para generar el PDF. Instala Node y reintenta."; exit 1; }
	pandoc -s --toc --toc-depth=2 \
		--metadata title="Manual de usuario de Nexo FP" \
		--metadata subtitle="Gestión de la Fase de Formación en Empresa u Organismo Equiparado (FFEOE)" \
		--metadata lang=es \
		-c assets/theme.css -c assets/print.css \
		-o docs/manual/_build.html \
		docs/manual/index.md docs/manual/0*.md docs/manual/10-*.md
	cd docs/manual && CHROME="$${PUPPETEER_EXECUTABLE_PATH:-$$(for c in \
		"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" \
		"/Applications/Chromium.app/Contents/MacOS/Chromium" \
		"$$(command -v google-chrome)" "$$(command -v chromium)" "$$(command -v chromium-browser)"; do \
		[ -x "$$c" ] && echo "$$c" && break; done)}"; \
		PUPPETEER_EXECUTABLE_PATH="$$CHROME" npx --yes pagedjs-cli _build.html -o nexo-fp-manual.pdf

## Construye la web del manual (docs/manual-site/) con MkDocs Material.
##
## Requiere MkDocs Material: pip install -r docs/manual/requirements.txt
docs-web:
	@command -v mkdocs >/dev/null 2>&1 || { echo "Necesitas MkDocs Material: pip install -r docs/manual/requirements.txt"; exit 1; }
	mkdocs build -f docs/manual/mkdocs.yml

## Previsualiza la web del manual en local (http://127.0.0.1:8000).
docs-serve:
	@command -v mkdocs >/dev/null 2>&1 || { echo "Necesitas MkDocs Material: pip install -r docs/manual/requirements.txt"; exit 1; }
	mkdocs serve -f docs/manual/mkdocs.yml
