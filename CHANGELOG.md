# CHANGELOG

## Unreleased

First release of `pieceofcake2/purifiable`, a maintained fork of
[josegonzalez/cakephp-purifiable](https://github.com/josegonzalez/cakephp-purifiable).

- Requires PHP 8.0+, pieceofcake2/cakephp 2.12+ and ezyang/htmlpurifier 4.17+ (loaded by Composer
  instead of `App::import('Vendor', ...)`)
- The HTML Purifier definition cache is stored in `tmp/cache/htmlpurifier` (`CACHE`) by default
  instead of the library directory in `vendor/`, which is often not writable
- One HTML Purifier instance per model is reused instead of creating one for every value
- A field with the value `'0'` is purified (it was skipped as empty); non-string values are skipped
- `customFilters` accepts filter instances as well as class names
- New `getPurifier()` behavior method
- PHPUnit tests, PHP_CodeSniffer and PHPStan (level 5) in CI
- Documentation in English and Japanese
