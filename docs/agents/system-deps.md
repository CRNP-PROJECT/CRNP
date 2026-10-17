# System dependencies

The toolchain this repo needs, installed from the system package
manager. Everything here is **system-wide**, not project-local, so
remove it with `pacman -R` (see [Uninstall](#uninstall)) rather than by
deleting the project.

The project's own PHP dependencies live in `composer.json` / `vendor/`
and are managed separately — `composer install`, not `pacman`.

## Installed

| Package | Version | Why |
|---|---|---|
| `php` | 8.5.10 | Laravel 12 runtime. The plan requires PHP 8.2+; 8.5 is the mainline build in Arch `extra`. Ships the bundled extensions (`ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pcre`, `pdo`, `session`, `tokenizer`, `xml`). |
| `php-gd` | 8.5.10 | Image handling: admin product resize (~800 px) and GCash-proof compression (adaptive JPEG ≤300 KB, ≥720 px floor). |
| `php-sqlite` | 8.5.10 | SQLite driver for the test suite and any local tooling. |
| `composer` | 2.10.3 | Dependency manager for `laravel/framework`, `laravel/socialite`, `pestphp/pest`, `pint`, `larastan`. |

```sh
sudo pacman -S --needed php php-gd php-sqlite composer
```

### Extension config

Arch keeps `extension=` lines **commented out** in `/etc/php/php.ini`, so
`php-gd` / `php-sqlite` install but do not load by themselves. They are
enabled via `conf.d`, which the ArchWiki recommends over editing
`php.ini`:

`/etc/php/conf.d/00-crates-n-plates.ini`

```ini
extension=gd
extension=sqlite3
extension=pdo_sqlite
extension=bcmath
extension=intl
```

`bcmath` and `intl` ship in `/usr/lib/php/modules` as part of the base
`php` package; they are enabled for convenience (money maths and
formatting), not because Laravel requires them.

### Pulled in transitively (not top-level)

`argon2`, `gd`, `libxpm`, `libyuv`, `libavif` — pulled in by the packages
above. Listed so the uninstall below accounts for them; leave them if
another project needs them.

## Compatibility (verified, not assumed)

- **PHP 8.5.10 satisfies Laravel 12.** `laravel/framework` v12.69.3
  declares `"php": "^8.2"`, which composer resolves as `>=8.2 <9.0` — so
  8.5.x is in range. Check with
  `curl -s https://repo.packagist.org/p2/laravel/framework.json`.
- **All 13 Laravel-required extensions load:** `ctype`, `curl`, `dom`,
  `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pcre`, `pdo`,
  `session`, `tokenizer`, `xml`.
- **GD and SQLite were smoke-tested**, not just flag-checked: a
  `imagejpeg` / `imagepng` round-trip and an in-memory
  `PDO("sqlite::memory:")` query both succeed.
- Note the plan pins **Laravel 12** even though 13.x is current; that is a
  deliberate project decision (`docs/PLAN.md` §2), not an oversight.

## Not installed (checked, unnecessary)

`re2c`, `freetype2`, `libxml2`, `oniguruma`, `sqlite`, `openssl`, `curl`,
`zip`, `icu`, `readline`, `libzip`, `zlib` — all already present in the
base image or unused by the pacman `php` build. `re2c` was installed and
then removed again during setup: it is only needed to *compile* PHP from
source, and this toolchain uses the pacman binary.

There is no `php-intl` / `php-bcmath` / `php-mbstring` / `php-xml`
package — those extensions are compiled into Arch's `php` package
(only `gd` and `sqlite` ship separately).

## Still to do (ticket #17)

Ticket **#17 — Foundation** runs `composer install` for the project-level
packages (`laravel/framework`, `laravel/socialite`, `pestphp/pest`,
`pint`, `larastan`). Composer itself is installed here; the lockfile and
`vendor/` are #17's job.

## Uninstall

Remove only what this repo added. Check `Required By` first — several of
these are shared across the system.

```sh
# Top-level (what this repo requested):
sudo pacman -R php php-gd php-sqlite composer

# Config this repo added:
sudo rm /etc/php/conf.d/00-crates-n-plates.ini

# Transitive deps, ONLY if nothing else needs them:
sudo pacman -R argon2 gd libxpm libyuv libavif
```

Inspect before removing:

```sh
pacman -Qi <package>   # shows "Required By"
```