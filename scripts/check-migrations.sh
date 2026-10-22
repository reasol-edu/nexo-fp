#!/usr/bin/env bash
# Comprueba la última migración de datos (la coordinación dual y las estancias pasan de la enseñanza al
# nivel: programme_coordinator → programme_year_coordinator y stay_programme → stay_programme_year) contra
# bases de datos reales: PostgreSQL 16, MySQL 8 y MariaDB 11 en Docker, y SQLite (sin Docker).
#
# Para cada motor: parte de la migración anterior con datos reales, comprueba que se rechaza una
# enseñanza sin niveles con coordinadores (en vez de perderlos), sube, comprueba que los datos se han
# copiado a todos los niveles y que las claves foráneas (RESTRICT / CASCADE) se comportan, baja
# conservando lo que se puede, vuelve a subir y muestra las diferencias entre el esquema migrado y el
# mapeo del ORM.
#
# Además carga las fixtures de demostración y comprueba que los contadores de la oferta formativa
# (consultas con `IN (:entidades)`) devuelven datos: con ids binarios (MySQL, SQLite con migraciones)
# devolvían 0 y los tests en memoria no lo detectaban. Y elimina un curso con datos (AcademicYearEraser).
#
# USO:   scripts/check-migrations.sh [pg|mysql|mariadb|sqlite|all]      (Docker para pg, mysql y mariadb; cada uno ~1 min)
# Variables: FROM=<versión anterior> (por defecto 20261020000000), KEEP=1 para no parar los contenedores.
set -uo pipefail
cd "$(dirname "$0")/.."

FROM="${FROM:-20261020000000}"
# Primera migración de la serie (coordinación y estancias por nivel); la siguiente pasa la preferencia del puesto a nivel.
TARGET="${TARGET:-20261022000000}"
ONLY="${1:-all}"
FAILS=0
export APP_ENV=dev APP_DEBUG=0 APP_LOG=false MAILER_DSN=null://null

ok()   { echo "  ✅ $*"; }
fail() { echo "  ❌ $*"; FAILS=$((FAILS+1)); }
mig()  { php -d memory_limit=1G bin/console doctrine:migrations:migrate "$@" --no-interaction 2>&1 | grep -E "OK|rror|xception|sin niveles" | head -3; }

# id fijos para los datos de prueba
C=11111111-1111-4111-8111-111111111111; Y=22222222-2222-4222-8222-222222222222; F=33333333-3333-4333-8333-333333333333
P1=44444444-4444-4444-8444-444444444441; P2=44444444-4444-4444-8444-444444444442; P3=44444444-4444-4444-8444-444444444443
L11=77777777-7777-4777-8777-777777777711; L12=77777777-7777-4777-8777-777777777712
L21=77777777-7777-4777-8777-777777777721; L22=77777777-7777-4777-8777-777777777722
T1=66666666-6666-4666-8666-666666666661; T2=66666666-6666-4666-8666-666666666662
S1=55555555-5555-4555-8555-555555555551; S2=55555555-5555-4555-8555-555555555552
# puestos con preferencia de enseñanza (migración de la preferencia a nivel)
X1=88888888-8888-4888-8888-888888888881; X2=88888888-8888-4888-8888-888888888882
X3=88888888-8888-4888-8888-888888888883; X4=88888888-8888-4888-8888-888888888884

# q <pg|my|ma|sq> "<sql>": ejecuta SQL y devuelve el valor (sin cabeceras ni espacios)
q() {
  case "$1" in
    pg) docker exec -i nx-pg psql -U app -d app -tA -v ON_ERROR_STOP=1 -c "$2" ;;
    my) docker exec -i nx-mysql mysql -uroot -ppw app -N -s -e "$2" 2>/dev/null ;;
    ma) docker exec -i nx-mariadb mariadb -uroot -ppw app -N -s -e "$2" 2>/dev/null ;;
    sq) sqlite3 -cmd 'PRAGMA foreign_keys = ON' "$SQLITE_DB" "$2" ;;
  esac | tr -d '[:space:]'
}
# Ejecuta SQL y devuelve su código de salida (para comprobar que algo se rechaza)
qx() {
  case "$1" in
    pg) docker exec -i nx-pg psql -U app -d app -v ON_ERROR_STOP=1 -c "$2" >/dev/null 2>&1 ;;
    my) docker exec -i nx-mysql mysql -uroot -ppw app -e "$2" >/dev/null 2>&1 ;;
    ma) docker exec -i nx-mariadb mariadb -uroot -ppw app -e "$2" >/dev/null 2>&1 ;;
    sq) sqlite3 -cmd 'PRAGMA foreign_keys = ON' "$SQLITE_DB" "$2" >/dev/null 2>&1 ;;
  esac
}
# ¿Cuántas columnas con ese nombre tiene la tabla? (en cualquier motor)
colcount_any() {
  case "$1" in
    sq) q sq "SELECT COUNT(*) FROM pragma_table_info('$2') WHERE name = '$3'" ;;
    pg) q pg "SELECT COUNT(*) FROM information_schema.columns WHERE table_name = '$2' AND column_name = '$3' AND table_schema = current_schema()" ;;
    *)  q "$1" "SELECT COUNT(*) FROM information_schema.columns WHERE table_name = '$2' AND column_name = '$3' AND table_schema = DATABASE()" ;;
  esac
}
tablecount() {
  case "$1" in
    sq) q sq "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = '$2'" ;;
    pg) q pg "SELECT COUNT(*) FROM information_schema.tables WHERE table_name = '$2' AND table_schema = current_schema()" ;;
    *)  q "$1" "SELECT COUNT(*) FROM information_schema.tables WHERE table_name = '$2' AND table_schema = DATABASE()" ;;
  esac
}

# Con el motor listo y la base en $FROM: datos de prueba, migración y comprobaciones.
check_levels_migration() {
  local kind="$1" S=
  if [ "$kind" = my ] || [ "$kind" = ma ]; then U() { echo "UNHEX(REPLACE('$1','-',''))"; }; else U() { echo "'$1'"; }; fi

  echo "▸ datos de prueba (3 enseñanzas, 4 niveles, 2 coordinadores, 2 estancias)"
  qx "$kind" "INSERT INTO educational_centre (id, code, name, city) VALUES ($(U $C), '41000001', 'IES', 'Sevilla');
    INSERT INTO academic_year (id, educational_centre_id, name) VALUES ($(U $Y), $(U $C), '2025-2026');
    INSERT INTO professional_family (id, academic_year_id, name) VALUES ($(U $F), $(U $Y), 'Informática');
    INSERT INTO programme (id, academic_year_id, professional_family_id, name) VALUES ($(U $P1), $(U $Y), $(U $F), 'DAW'), ($(U $P2), $(U $Y), $(U $F), 'DAM'), ($(U $P3), $(U $Y), $(U $F), 'SinNiveles');
    INSERT INTO programme_year (id, programme_id, name) VALUES ($(U $L11), $(U $P1), '1.º DAW'), ($(U $L12), $(U $P1), '2.º DAW'), ($(U $L21), $(U $P2), '1.º DAM'), ($(U $L22), $(U $P2), '2.º DAM');
    INSERT INTO teacher (id, name_first_name, name_last_name, username, admin, external, active) VALUES
      ($(U $T1), 'Ana', 'Uno', 'ana.uno', FALSE, FALSE, TRUE), ($(U $T2), 'Beto', 'Dos', 'beto.dos', FALSE, FALSE, TRUE);
    INSERT INTO programme_coordinator (programme_id, teacher_id) VALUES ($(U $P1), $(U $T1)), ($(U $P1), $(U $T2)), ($(U $P2), $(U $T2));
    INSERT INTO stay (id, academic_year_id, name, start_date, end_date) VALUES
      ($(U $S1), $(U $Y), 'E1', '2026-03-01', '2026-06-30'), ($(U $S2), $(U $Y), 'E2', '2026-03-01', '2026-06-30');
    INSERT INTO stay_programme (stay_id, programme_id) VALUES ($(U $S1), $(U $P1)), ($(U $S2), $(U $P1)), ($(U $S2), $(U $P2));
    INSERT INTO training_position (id, stay_id, signed, state, priority_programme_id, priority_until) VALUES
      ($(U $X1), $(U $S1), FALSE, 'DRAFT', $(U $P1), '2026-12-31'),
      ($(U $X2), $(U $S2), FALSE, 'DRAFT', $(U $P2), '2026-12-31'),
      ($(U $X3), $(U $S2), FALSE, 'DRAFT', $(U $P3), '2026-12-31'),
      ($(U $X4), $(U $S2), FALSE, 'DRAFT', $(U $P1), '2026-12-31');
    INSERT INTO training_position_programme_year (training_position_id, programme_year_id) VALUES
      ($(U $X1), $(U $L12)), ($(U $X1), $(U $L11)), ($(U $X2), $(U $L21)), ($(U $X4), $(U $L21));" \
    && ok "datos insertados" || { fail "no se pudieron insertar los datos"; return 1; }

  echo "▸ una enseñanza sin niveles con coordinadores aborta la migración (no se pierde nada en silencio)"
  qx "$kind" "INSERT INTO programme_coordinator (programme_id, teacher_id) VALUES ($(U $P3), $(U $T1))"
  local out; out="$(mig "DoctrineMigrations\\Version$TARGET")"
  if echo "$out" | grep -q "sin niveles"; then ok "migración rechazada con aviso"; else fail "no avisó de la enseñanza sin niveles: «$out»"; fi
  [ "$(tablecount "$kind" programme_coordinator)" = 1 ] && ok "las tablas antiguas siguen intactas" || fail "se tocaron las tablas antiguas tras abortar"
  qx "$kind" "DELETE FROM programme_coordinator WHERE programme_id = $(U $P3)"

  echo "▸ subiendo a la última versión"; mig
  [ "$(q "$kind" "SELECT COUNT(*) FROM programme_year_coordinator")" = 6 ] && ok "coordinación copiada a todos los niveles (6 filas: 2×2 + 1×2)" || fail "programme_year_coordinator no tiene 6 filas"
  [ "$(q "$kind" "SELECT COUNT(*) FROM stay_programme_year")" = 6 ] && ok "estancias copiadas a todos los niveles (6 filas: 2 + 2 + 2)" || fail "stay_programme_year no tiene 6 filas"
  [ "$(q "$kind" "SELECT COUNT(*) FROM stay WHERE id = $(U $S1)")" = 1 ] && ok "las estancias siguen ahí" || fail "faltan estancias"
  [ "$(tablecount "$kind" programme_coordinator)" = 0 ] && [ "$(tablecount "$kind" stay_programme)" = 0 ] && ok "tablas antiguas eliminadas" || fail "siguen las tablas antiguas"

  echo "▸ la preferencia de enseñanza de un puesto pasa a ser de un nivel"
  has() { [ "$(q "$kind" "SELECT COUNT(*) FROM training_position WHERE id = $(U $1) AND priority_programme_year_id = $(U $2)")" = 1 ]; }
  has "$X1" "$L11" && ok "X1: de entre los niveles que ofrece (1.º y 2.º DAW), el primero por nombre (1.º DAW)" || fail "X1 no tiene como preferente 1.º DAW"
  has "$X2" "$L21" && ok "X2: su único nivel ofrecido (1.º DAM)" || fail "X2 no tiene como preferente 1.º DAM"
  has "$X4" "$L11" && ok "X4: no ofrece ningún nivel de DAW, se toma el primero de DAW (1.º DAW)" || fail "X4 no tiene como preferente 1.º DAW"
  [ "$(q "$kind" "SELECT COUNT(*) FROM training_position WHERE id = $(U $X3) AND priority_programme_year_id IS NULL AND priority_until IS NULL")" = 1 ] && ok "X3: una enseñanza sin niveles descarta la preferencia" || fail "X3 conserva una preferencia sin nivel"
  [ "$(colcount_any "$kind" training_position priority_programme_id)" = 0 ] && ok "priority_programme_id eliminada" || fail "sigue existiendo priority_programme_id"

  echo "▸ un nivel en uso por una estancia no se puede borrar (RESTRICT)"
  if qx "$kind" "DELETE FROM programme_year WHERE id = $(U $L11)"; then fail "se borró un nivel en uso"; else ok "el borrado se rechaza"; fi

  echo "▸ borrar una estancia limpia las filas de unión (CASCADE)"
  local S3=55555555-5555-4555-8555-555555555553
  qx "$kind" "INSERT INTO stay (id, academic_year_id, name, start_date, end_date) VALUES ($(U $S3), $(U $Y), 'E3', '2026-03-01', '2026-06-30'); INSERT INTO stay_programme_year (stay_id, programme_year_id) VALUES ($(U $S3), $(U $L11)), ($(U $S3), $(U $L12))"
  [ "$(q "$kind" "SELECT COUNT(*) FROM stay_programme_year")" = 8 ] || fail "la estancia E3 no se ha creado con sus 2 niveles"
  qx "$kind" "DELETE FROM stay WHERE id = $(U $S3)"
  [ "$(q "$kind" "SELECT COUNT(*) FROM stay_programme_year")" = 6 ] && ok "al borrar E3 desaparecen sus 2 niveles (quedan 6)" || fail "no se limpió stay_programme_year al borrar la estancia"

  echo "▸ bajando: la coordinación de un solo nivel vuelve a ser de la enseñanza"
  qx "$kind" "INSERT INTO programme_year_coordinator (programme_year_id, teacher_id) VALUES ($(U $L22), $(U $T1))"
  mig "DoctrineMigrations\\Version$FROM"
  [ "$(q "$kind" "SELECT COUNT(*) FROM programme_coordinator")" = 4 ] && ok "programme_coordinator recreada (T1 y T2 en DAW, T2 y T1 en DAM: 4 filas sin repetir)" || fail "programme_coordinator incorrecta tras bajar"
  [ "$(q "$kind" "SELECT COUNT(*) FROM stay_programme")" = 3 ] && ok "stay_programme recreada (E1: DAW; E2: DAW y DAM)" || fail "stay_programme incorrecta tras bajar"
  [ "$(q "$kind" "SELECT COUNT(*) FROM training_position WHERE id = $(U $X1) AND priority_programme_id = $(U $P1)")" = 1 ] && ok "la preferencia vuelve a ser de la enseñanza del nivel (X1: DAW)" || fail "preferencia de X1 incorrecta tras bajar"

  echo "▸ volviendo a subir"; mig
  [ "$(q "$kind" "SELECT COUNT(*) FROM programme_year_coordinator")" = 8 ] && ok "coordinación de nuevo por niveles (8 filas)" || fail "programme_year_coordinator incorrecta tras volver a subir"
  [ "$(q "$kind" "SELECT COUNT(*) FROM stay_programme_year")" = 6 ] && ok "estancias de nuevo por niveles (6 filas)" || fail "stay_programme_year incorrecta tras volver a subir"

  check_counts
  check_erase

  # SQLite guarda los ids como CHAR(36) y el mapeo del ORM espera BLOB: siempre hay diferencias de tipo, sin interés.
  [ "$kind" = sq ] && return
  echo "▸ diferencias esquema migrado ↔ mapeo del ORM (en las tablas de esta versión):"
  local diff; diff="$(php -d memory_limit=1G bin/console doctrine:schema:update --dump-sql 2>&1 | grep -iE "programme_year_coordinator|stay_programme_year|stay_programme\b|programme_coordinator" || true)"
  if [ -z "$diff" ]; then ok "sin diferencias"; else fail "diferencias de esquema:"; echo "$diff" | sed 's/^/    /'; fi
}

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
  check_levels_migration "$kind"
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

# Con las fixtures cargadas: elimina el curso con más estancias con AcademicYearEraser y comprueba que
# se borra todo lo suyo (estancias, puestos, oferta formativa) y se conserva el alumnado. Las claves
# foráneas de SQLite son más permisivas, por eso conviene probarlo en los motores reales.
check_erase() {
  echo "▸ eliminación de un curso con datos (AcademicYearEraser)"
  local snippet; snippet="$(mktemp)"
  cat >"$snippet" <<'PHP'
<?php
require getcwd().'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(getcwd().'/.env');
$k = new App\Kernel('dev', true); $k->boot();
$em = $k->getContainer()->get('doctrine')->getManager();
$stays = $em->getRepository(App\Entity\Stay::class);
$best = null; $bestCount = 0;
foreach ($em->getRepository(App\Entity\AcademicYear::class)->findAll() as $y) {
    $n = count($stays->findByYear($y));
    if ($n > $bestCount) { $best = $y; $bestCount = $n; }
}
if ($best === null) { echo "sin curso con estancias\n"; exit; }
$students = (int) $em->createQuery('SELECT COUNT(s.id) FROM App\Entity\Student s')->getSingleScalarResult();
$id = $best->getId();
// Un curso activo no se puede eliminar: se activa antes otro curso nuevo (como hace el cambio de curso).
$centre = $best->getEducationalCentre();
if ($centre->getActiveAcademicYear() === $best) {
    $next = (new App\Entity\AcademicYear())->setName('curso-siguiente')->setEducationalCentre($centre);
    $em->persist($next);
    $centre->setActiveAcademicYear($next);
    $em->flush();
}
$eraser = new App\Service\AcademicYearEraser(
    $em, $stays,
    $em->getRepository(App\Entity\ProfessionalFamily::class),
    $em->getRepository(App\Entity\Programme::class),
    $em->getRepository(App\Entity\ProgrammeYear::class),
    $em->getRepository(App\Entity\Group::class),
);
try {
    $eraser->erase($best);
    $em->clear();
    $left = (int) $em->createQuery('SELECT COUNT(s.id) FROM App\Entity\Stay s WHERE s.academicYear = :y')->setParameter('y', $id, 'uuid')->getSingleScalarResult();
    $gone = $em->getRepository(App\Entity\AcademicYear::class)->find($id) === null;
    $studentsAfter = (int) $em->createQuery('SELECT COUNT(s.id) FROM App\Entity\Student s')->getSingleScalarResult();
    echo ($gone && $left === 0 && $studentsAfter === $students) ? "OK $bestCount $students\n" : "FALLO gone=$gone left=$left students=$students/$studentsAfter\n";
} catch (Throwable $e) {
    echo 'ERROR ', get_class($e), ': ', substr($e->getMessage(), 0, 160), "\n";
}
PHP
  local out; out="$(php -d memory_limit=1G "$snippet" 2>&1 | tail -1)"; rm -f "$snippet"
  case "$out" in
    OK*) ok "curso eliminado con sus estancias y alumnado intacto (${out#OK })" ;;
    *)   fail "la eliminación del curso falla: «${out}»" ;;
  esac
}

run_sqlite() {
  echo; echo "══ sqlite ══"
  SQLITE_DB="$(mktemp -u).db"
  export MIGRATIONS_PATH=migrations/sqlite DATABASE_URL="sqlite:///$SQLITE_DB"
  echo "▸ migrando hasta $FROM"; mig "DoctrineMigrations\\Version$FROM"
  check_levels_migration sq
  rm -f "$SQLITE_DB"
}

[[ "$ONLY" = all || "$ONLY" = sqlite ]] && run_sqlite
[[ "$ONLY" = all || "$ONLY" = pg ]]      && run_engine pg      postgres:16 "54320:5432" migrations/postgresql "postgresql://app:pw@127.0.0.1:54320/app?serverVersion=16&charset=utf8" pg -e POSTGRES_PASSWORD=pw -e POSTGRES_USER=app -e POSTGRES_DB=app
[[ "$ONLY" = all || "$ONLY" = mysql ]]   && run_engine mysql   mysql:8.0    "33060:3306" migrations/mysql      "mysql://root:pw@127.0.0.1:33060/app?serverVersion=8.0.32&charset=utf8mb4"      my -e MYSQL_ROOT_PASSWORD=pw -e MYSQL_DATABASE=app
[[ "$ONLY" = all || "$ONLY" = mariadb ]] && run_engine mariadb mariadb:11   "33061:3306" migrations/mysql      "mysql://root:pw@127.0.0.1:33061/app?serverVersion=11.4.0-MariaDB&charset=utf8mb4" ma -e MARIADB_ROOT_PASSWORD=pw -e MARIADB_DATABASE=app

echo; [ "$FAILS" = 0 ] && echo "✅ Migraciones correctas" || { echo "❌ $FAILS comprobaciones fallidas"; exit 1; }
