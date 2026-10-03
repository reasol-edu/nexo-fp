#!/usr/bin/env bash
# Comprueba las migraciones recientes (2.9.0: estancias con varias enseñanzas) contra bases de datos
# reales en Docker: PostgreSQL 16, MySQL 8 y MariaDB 11. SQLite se prueba aparte (no necesita Docker).
#
# Para cada motor: parte de la migración anterior con datos reales, sube, comprueba que los datos se
# han trasladado, baja, vuelve a subir, comprueba que no se puede borrar una enseñanza en uso (FK RESTRICT)
# y muestra las diferencias entre el esquema migrado y el mapeo del ORM.
#
# Además carga las fixtures de demostración y comprueba que los contadores de la oferta formativa
# (consultas con `IN (:entidades)`) devuelven datos: con ids binarios (MySQL, SQLite con migraciones)
# devolvían 0 y los tests en memoria no lo detectaban.
#
# USO:   scripts/check-migrations.sh [pg|mysql|mariadb|sqlite|all]      (necesita Docker; cada motor tarda ~1 min)
# Variables: FROM=<versión anterior> (por defecto 20260702000000), KEEP=1 para no parar los contenedores.
set -uo pipefail
cd "$(dirname "$0")/.."

FROM="${FROM:-20260702000000}"
ONLY="${1:-all}"
FAILS=0
export APP_ENV=dev APP_DEBUG=0 APP_LOG=false MAILER_DSN=null://null

ok()   { echo "  ✅ $*"; }
fail() { echo "  ❌ $*"; FAILS=$((FAILS+1)); }
mig()  { php -d memory_limit=1G bin/console doctrine:migrations:migrate "$@" --no-interaction 2>&1 | grep -E "OK|rror|xception" | head -3; }

# id fijos para los datos de prueba
C=11111111-1111-4111-8111-111111111111; Y=22222222-2222-4222-8222-222222222222; F=33333333-3333-4333-8333-333333333333
P1=44444444-4444-4444-8444-444444444441; P2=44444444-4444-4444-8444-444444444442
S1=55555555-5555-4555-8555-555555555551; S2=55555555-5555-4555-8555-555555555552

run_engine() {
  local name="$1" image="$2" port="$3" mpath="$4" url="$5" kind="$6"; shift 6
  echo; echo "══ $name ══"
  docker rm -f "nx-$name" >/dev/null 2>&1
  docker run -d --rm --name "nx-$name" "$@" -p "$port" "$image" >/dev/null || { fail "no arranca $image"; return; }
  # esperar a que acepte consultas
  for _ in $(seq 1 60); do q "$kind" "SELECT 1" >/dev/null 2>&1 && break; sleep 2; done
  q "$kind" "SELECT 1" >/dev/null 2>&1 || { fail "la base de datos no responde"; return; }

  export MIGRATIONS_PATH="$mpath" DATABASE_URL="$url"
  echo "▸ migrando hasta $FROM"; mig "DoctrineMigrations\\Version$FROM"

  echo "▸ datos de prueba (2 estancias, 2 enseñanzas)"
  if [ "$kind" = pg ]; then U() { echo "'$1'"; }; else U() { echo "UNHEX(REPLACE('$1','-',''))"; }; fi
  q "$kind" "INSERT INTO educational_centre (id, code, name, city) VALUES ($(U $C), '41000001', 'IES', 'Sevilla');
    INSERT INTO academic_year (id, educational_centre_id, name) VALUES ($(U $Y), $(U $C), '2025-2026');
    INSERT INTO professional_family (id, academic_year_id, name) VALUES ($(U $F), $(U $Y), 'Informática');
    INSERT INTO programme (id, academic_year_id, professional_family_id, name) VALUES ($(U $P1), $(U $Y), $(U $F), 'DAW'), ($(U $P2), $(U $Y), $(U $F), 'DAM');
    INSERT INTO stay (id, academic_year_id, programme_id, name, start_date, end_date) VALUES
      ($(U $S1), $(U $Y), $(U $P1), 'E1', '2026-03-01', '2026-06-30'), ($(U $S2), $(U $Y), $(U $P2), 'E2', '2026-03-01', '2026-06-30');" >/dev/null \
    && ok "datos insertados" || { fail "no se pudieron insertar los datos"; return; }

  echo "▸ subiendo a la última versión"; mig
  [ "$(q "$kind" "SELECT COUNT(*) FROM stay_programme")" = 2 ] && ok "stay_programme copiada (2 filas)" || fail "stay_programme no tiene 2 filas"
  [ "$(q "$kind" "SELECT COUNT(*) FROM stay WHERE id = $(U $S1)")" = 1 ] && ok "las estancias siguen ahí" || fail "faltan estancias"
  colcount "$kind" stay programme_id | grep -qx 0 && ok "stay.programme_id eliminada" || fail "stay.programme_id sigue existiendo"
  colcount "$kind" training_position priority_until | grep -qx 1 && ok "training_position.priority_until creada" || fail "falta priority_until"
  [ "$(q "$kind" "SELECT COUNT(*) FROM setting_definition WHERE $(kq "$kind") = 'email.notification.shared_position'")" = 1 ] && ok "ajuste shared_position creado" || fail "falta el ajuste shared_position"

  echo "▸ una enseñanza en uso no se puede borrar (RESTRICT)"
  if q "$kind" "DELETE FROM programme WHERE id = $(U $P1)" >/dev/null 2>&1; then fail "se borró una enseñanza en uso"; else ok "el borrado se rechaza"; fi

  echo "▸ añadiendo una 2.ª enseñanza a E1 y bajando 3 migraciones"
  q "$kind" "INSERT INTO stay_programme (stay_id, programme_id) VALUES ($(U $S1), $(U $P2))" >/dev/null
  for _ in 1 2 3; do mig prev; done
  [ "$(q "$kind" "SELECT COUNT(*) FROM stay WHERE programme_id IS NOT NULL")" = 2 ] && ok "programme_id restaurada en las 2 estancias" || fail "programme_id no restaurada"
  [ "$(q "$kind" "SELECT p.name FROM stay s JOIN programme p ON p.id = s.programme_id WHERE s.id = $(U $S1)")" = DAM ] && ok "E1 conserva una enseñanza (la 1.ª por nombre: DAM)" || fail "E1 no conserva la enseñanza esperada"
  colcount "$kind" training_position priority_until | grep -qx 0 && ok "columnas de preferencia eliminadas" || fail "siguen las columnas de preferencia"

  echo "▸ volviendo a subir"; mig
  [ "$(q "$kind" "SELECT COUNT(*) FROM stay_programme")" = 2 ] && ok "stay_programme recreada con los datos" || fail "stay_programme incorrecta tras volver a subir"

  check_counts

  echo "▸ diferencias esquema migrado ↔ mapeo del ORM (relativas a esta versión):"
  php -d memory_limit=1G bin/console doctrine:schema:update --dump-sql 2>&1 | grep -iE "stay_programme|priority|training_position|shared" | sed 's/^/    /' || true
  echo "    (vacío = sin diferencias en las tablas tocadas)"
  [ -n "${KEEP:-}" ] || docker stop "nx-$name" >/dev/null 2>&1
}

# Con la base ya migrada: fixtures de demo y contadores de la oferta formativa.
check_counts() {
  echo "▸ fixtures de demostración y contadores de la oferta formativa"
  if ! php -d memory_limit=1G bin/console doctrine:fixtures:load --no-interaction --append >/tmp/nx-fixtures.log 2>&1; then
    fail "las fixtures no se cargan ($(tail -1 /tmp/nx-fixtures.log | cut -c1-120))"; return
  fi
  local snippet; snippet="$(mktemp)"
  cat >"$snippet" <<'PHP'
<?php
require getcwd().'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(getcwd().'/.env');
$k = new App\Kernel('dev', true); $k->boot();
$em = $k->getContainer()->get('doctrine')->getManager();
$families   = $em->getRepository(App\Entity\ProfessionalFamily::class)->findAll();
$programmes = $em->getRepository(App\Entity\Programme::class)->findAll();
$levels     = $em->getRepository(App\Entity\ProgrammeYear::class)->findAll();
echo count($em->getRepository(App\Entity\Programme::class)->countByFamily($families)), ' ',
     count($em->getRepository(App\Entity\ProgrammeYear::class)->countByProgramme($programmes)), ' ',
     count($em->getRepository(App\Entity\Group::class)->countByLevel($levels)), ' ', count($families), "\n";
PHP
  local out; out="$(php "$snippet" 2>&1 | tail -1)"; rm -f "$snippet"
  read -r f p l total <<<"$out"
  if [ -n "${total:-}" ] && [ "$total" -gt 0 ] && [ "$f" = "$total" ] && [ "$p" -gt 0 ] && [ "$l" -gt 0 ]; then
    ok "contadores correctos (familias $f/$total, enseñanzas $p, niveles $l)"
  else
    fail "contadores a cero o error: «${out}»"
  fi
}

# q <pg|my> "<sql>": ejecuta SQL y devuelve el valor (sin cabeceras ni espacios)
q() {
  case "$1" in
    pg) docker exec -i nx-pg psql -U app -d app -tA -v ON_ERROR_STOP=1 -c "$2" ;;
    my) docker exec -i nx-mysql mysql -uroot -ppw app -N -s -e "$2" 2>/dev/null ;;
    ma) docker exec -i nx-mariadb mariadb -uroot -ppw app -N -s -e "$2" 2>/dev/null ;;
  esac | tr -d '[:space:]'
}
kq() { [ "$1" = pg ] && echo '"key"' || echo '`key`'; }
colcount() { q "$1" "SELECT COUNT(*) FROM information_schema.columns WHERE table_name = '$2' AND column_name = '$3' AND table_schema = $( [ "$1" = pg ] && echo "current_schema()" || echo "DATABASE()" )"; echo; }

run_sqlite() {
  echo; echo "══ sqlite ══"
  local db; db="$(mktemp -u).db"
  export MIGRATIONS_PATH=migrations/sqlite DATABASE_URL="sqlite:///$db"
  echo "▸ migrando"; mig
  check_counts
  rm -f "$db"
}

[[ "$ONLY" = all || "$ONLY" = sqlite ]] && run_sqlite
[[ "$ONLY" = all || "$ONLY" = pg ]]      && run_engine pg      postgres:16 "54320:5432" migrations/postgresql "postgresql://app:pw@127.0.0.1:54320/app?serverVersion=16&charset=utf8" pg -e POSTGRES_PASSWORD=pw -e POSTGRES_USER=app -e POSTGRES_DB=app
[[ "$ONLY" = all || "$ONLY" = mysql ]]   && run_engine mysql   mysql:8.0    "33060:3306" migrations/mysql      "mysql://root:pw@127.0.0.1:33060/app?serverVersion=8.0.32&charset=utf8mb4"      my -e MYSQL_ROOT_PASSWORD=pw -e MYSQL_DATABASE=app
[[ "$ONLY" = all || "$ONLY" = mariadb ]] && run_engine mariadb mariadb:11   "33061:3306" migrations/mysql      "mysql://root:pw@127.0.0.1:33061/app?serverVersion=11.4.0-MariaDB&charset=utf8mb4" ma -e MARIADB_ROOT_PASSWORD=pw -e MARIADB_DATABASE=app

echo; [ "$FAILS" = 0 ] && echo "✅ Migraciones correctas" || { echo "❌ $FAILS comprobaciones fallidas"; exit 1; }
