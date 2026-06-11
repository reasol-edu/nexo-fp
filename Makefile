.PHONY: fixtures migrate setup test

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

test:
	php bin/phpunit
