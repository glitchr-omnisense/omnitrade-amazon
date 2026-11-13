# Changelog

## [Unreleased]

## [1.0.0] - 2026-11-13

The first stable release: it installs with Composer's default minimum-stability (stable).

### Release
- Requires `omnisense/omnitrade` `^1.0`, a stable version, where it required `^1.0@dev`;
  `extra.branch-alias` `dev-1.x` → `1.x-dev`.
- CI: `.github/workflows/ci.yml` (glitchr-studio/ci's php-package.yml: the package installed alone,
  PHPUnit, PHPStan against its baseline, a consumer install) and `release.yml` (that CI on the tag,
  then glitchr-studio/ci's php-release.yml); `phpunit.xml.dist` runs the package's own tests.
- `phpstan.neon` at level max, as its siblings, and `phpstan-baseline.neon`: what was there when
  PHPStan came in (2026-11-13), which may only shrink.
- README: the install line and a first call: an affiliate link, built locally (no account, no
  request).

### Fixed
- A `ProviderException` no longer carries the Creators API's client secret and the token. The
  message quoted the HTTP client's (the whole URL), the previous was the client's own exception, and
  every trace of the chain kept the arguments of the calls under it (the request's options, the
  headers with the credentials) when `zend.exception_ignore_args` is off: a log of the whole chain
  wrote them. The message masks them as `***`, the previous is a copy of the client's exception (the
  same class and code), and no exception `Api` throws keeps its trace's arguments
  (`Omnitrade\Http\Redaction`).
- `Product::$description` is HTML, as the family's contract has it: the item's features as a list
  (`<ul><li>`, each escaped), where they came as plain text lines.
