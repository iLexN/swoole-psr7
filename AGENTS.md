# AGENTS.md

## Project

PHP library (`ilexn/swoole-convert-psr7`) that converts Swoole HTTP request/response objects to PSR-7 interfaces using any PSR-17 factory. Requires PHP >=8.5 and `ext-swoole` or `ext-openswoole`.

## Commands

```bash
composer install
vendor/bin/phpunit                    # run tests (needs swoole or openswoole extension)
vendor/bin/phpstan analyse -l max src  # static analysis (level max + strict rules)
vendor/bin/rector process src --config rector.php --dry-run  # check rector refactorings
vendor/bin/rector process src --config rector.php            # apply rector refactorings
```

CI runs all checks (phpstan, rector) on PHP 8.5 with both `swoole` and `openswoole` extensions.

## Code style

- `declare(strict_types=1);` in every file
- All source classes are `final`
- PSR-4 autoloading: `Ilex\SwoolePsr7\` → `src/`, `Ilex\SwoolePsr7\Tests\` → `tests/`
- Rector sets: code-quality, dead-code, coding-style, php82, php83, php84, php85, early-return, naming

## Tests

- PHPUnit 13.2, single test suite from `./tests/`
- Tests require a Swoole or OpenSwoole extension — they won't run without one
- `SwooleRequestFactory` in `tests/` is a test helper for creating Swoole request mocks

## Gotchas

- The `composer.json` package name is `ilexn/swoole-convert-psr7` (note: `convert`, not `convent` — there was a rename from the old misspelled package)
- PHPStan runs at max level with strict-rules and phpunit extensions
- Rector is configured for PHP 8.2–8.5 feature upgrades; rector config is in `rector.php`
- Coverage uploaded to Coveralls only on PHP 8.5
