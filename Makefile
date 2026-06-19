.PHONY: dev dev-stop fixtures migrate setup test slides docs docs-pdf docs-web docs-serve

# Versión publicada, leída de config/services.yaml (app.version). La portada del
# manual en PDF la muestra automáticamente, así que en cada release basta con
# actualizar services.yaml (paso 2 del procedimiento): no hay que tocar el manual.
VERSION := $(shell grep -E '^[[:space:]]*app\.version:' config/services.yaml | head -1 | sed -E 's/.*"([^"]+)".*/\1/')
# Fecha del release (app.pub_date), reformateada YYYY-MM-DD -> DD/MM/YYYY para
# mostrarla junto a la versión en la presentación y la portada del manual.
PUB_DATE := $(shell grep -E '^[[:space:]]*app\.pub_date:' config/services.yaml | head -1 | sed -E 's/.*"([0-9]+)-([0-9]+)-([0-9]+)".*/\3\/\2\/\1/')

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

## Arranca el entorno de desarrollo local.
##
## Levanta los contenedores de DESARROLLO (PostgreSQL + hub Mercure; los
## servicios «app» y «worker» quedan tras el perfil «production», así que NO se
## arrancan: el PHP lo sirve Symfony CLI) y, a continuación, «symfony serve».
## El Symfony CLI detecta el hub «dunglas/mercure» e inyecta MERCURE_URL y
## MERCURE_PUBLIC_URL, de modo que la sincronización en vivo funciona sin más.
##
## Se pasa --env-file .env.local porque compose.yaml interpola APP_SECRET y
## MERCURE_JWT_SECRET (con «:?») aunque «app»/«worker» estén desactivados.
## «symfony serve» queda en primer plano: Ctrl+C detiene el servidor (los
## contenedores siguen en marcha; usa «make dev-stop» para pararlos).
dev:
	@test -f .env.local || { echo "Falta .env.local. Cópialo de .env.example y ajusta APP_SECRET, DB_PASSWORD y MERCURE_JWT_SECRET."; exit 1; }
	@command -v symfony >/dev/null 2>&1 || { echo "Necesitas Symfony CLI. Instálalo desde https://symfony.com/download"; exit 1; }
	docker compose --env-file .env.local -f compose.yaml -f compose.dev.yaml up -d
	symfony server:start

## Detiene los contenedores de desarrollo (PostgreSQL + hub Mercure).
dev-stop:
	docker compose --env-file .env.local -f compose.yaml -f compose.dev.yaml down


## Genera la presentación en PDF (docs/slides/nexo-fp.pdf).
##
## Requiere Node.js: usa "npx @marp-team/marp-cli" sin instalación global.
## --allow-local-files permite incrustar las capturas de docs/slides/img.
## Cambia la extensión de salida a .pptx o .html para otros formatos.
##
## La versión y la fecha del release son fuente única (config/services.yaml):
## se sustituyen los marcadores {{VERSION}} y {{PUB_DATE}} del .md en un fichero
## temporal _build.md (en el mismo dir, para que las rutas a img/ resuelvan) y
## se compila ese. Por eso hay que generar siempre con "make slides", no
## directamente con marp sobre el .md (vería los marcadores sin sustituir).
slides:
	@command -v npx >/dev/null 2>&1 || { echo "Necesitas Node.js/npx para generar la presentación. Instala Node y reintenta."; exit 1; }
	sed -e 's/{{VERSION}}/$(VERSION)/g' -e 's#{{PUB_DATE}}#$(PUB_DATE)#g' docs/slides/nexo-fp.md > docs/slides/_build.md
	npx --yes @marp-team/marp-cli docs/slides/_build.md --allow-local-files -o docs/slides/nexo-fp.pdf
	rm -f docs/slides/_build.md

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
		--lua-filter=docs/pandoc-admonitions.lua \
		--lua-filter=docs/pandoc-internal-links.lua \
		--metadata title="Manual de usuario de Nexo FP" \
		--metadata subtitle="Preparación de la Fase de Formación en Empresa u Organismo Equiparado (FFEOE)" \
		--metadata date="Versión $(VERSION) · $(PUB_DATE)" \
		--metadata lang=es \
		-c assets/theme.css -c assets/print.css \
		-o docs/manual/_build.html \
		docs/manual/index.md docs/manual/[0-9][0-9]-*.md
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
	MANUAL_COPYRIGHT="Nexo FP · versión $(VERSION) · $(PUB_DATE)" mkdocs build -f docs/manual/mkdocs.yml

## Previsualiza la web del manual en local (http://127.0.0.1:8000).
docs-serve:
	@command -v mkdocs >/dev/null 2>&1 || { echo "Necesitas MkDocs Material: pip install -r docs/manual/requirements.txt"; exit 1; }
	MANUAL_COPYRIGHT="Nexo FP · versión $(VERSION) · $(PUB_DATE)" mkdocs serve -f docs/manual/mkdocs.yml
