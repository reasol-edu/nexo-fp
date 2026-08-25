# Doctrine repositories: the only door to the database

## The rule

Never call generic Doctrine retrieval methods — `find()`, `findOneBy()`, `findAll()`, `findBy()`,
`getRepository()` — from services, controllers, commands, or anywhere outside `App\Repository\*`.

This is enforced mechanically: `src/PHPStan/Rules/ForbidGenericDoctrineMethodsRule.php` fails
PHPStan analysis (`vendor/bin/phpstan analyse`) whenever `find()`/`getRepository()` is called on an
`EntityManagerInterface`, or `getRepository()` on a `ManagerRegistry`, from a class outside
`App\Repository\`. The error message it raises:

> Calling `<method>()` on EntityManager/Registry is forbidden outside `App\Repository\`. Inject the
> concrete repository and use a named method instead.

## Why

Calling generic Doctrine methods (or `$em->getRepository(Entity::class)`) from application code
couples that code to Doctrine's internal API and hides the actual intent of the data access behind
a generic call. A named repository method documents what's actually being fetched and why.

## How to apply

- **Inject the concrete repository** via constructor injection (e.g. `TeacherRepository
  $teacherRepository`), never obtain it with `$em->getRepository(Entity::class)`.
- **Add named methods** on the repository that express intent, e.g. `findByUsername()`,
  `findActiveByCentre()`, `findByCentreOrderedByName()` — instead of exposing `findOneBy()`/
  `findBy()` with criteria arrays to callers outside the repository.
- Generic Doctrine methods (`findOneBy`, `createQueryBuilder`, etc.) are perfectly fine **inside**
  the repository class itself, as the implementation detail behind a named method — they're only
  banned from being called *from outside* `App\Repository\`.
- Tenant-scoped lookups should take the centre (or other scope) as an explicit parameter rather
  than reading a global — see `TenantContext` in
  [`skills/architecture.md`](architecture.md#multi-tenancy).

## Example pattern (from the test suite)

`tests/Integration/Repository/*Test.php` extend `RepositoryTestCase` and exercise the named
methods directly, e.g. `AcademicYearRepositoryTest` covers `findByCentreOrderedByName()` and
`findByCentreAndId()` with cases for correct ordering, centre isolation, and empty results. When
adding a repository method, add an integration test in this style alongside it.
