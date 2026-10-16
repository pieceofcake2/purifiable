# CakePHP 2 Purifiable Plugin

[![GitHub License](https://img.shields.io/github/license/pieceofcake2/purifiable?label=License)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/pieceofcake2/purifiable?label=Packagist)](https://packagist.org/packages/pieceofcake2/purifiable)
![PHP](https://img.shields.io/packagist/dependency-v/pieceofcake2/purifiable/php?logo=php&logoColor=%23FFFFFF&label=PHP&labelColor=%23777BB4&color=%23FFFFFF)
![CakePHP](https://img.shields.io/packagist/dependency-v/pieceofcake2/purifiable/pieceofcake2/cakephp?logo=cakephp&logoColor=%23FFFFFF&label=CakePHP&labelColor=%23D33C43&color=%23FFFFFF)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%205-brightgreen?label=PHPStan)](https://github.com/pieceofcake2/purifiable/actions/workflows/CI.yml)
[![CI](https://img.shields.io/github/actions/workflow/status/pieceofcake2/purifiable/CI.yml?label=CI)](https://github.com/pieceofcake2/purifiable/actions/workflows/CI.yml)
[![Codecov](https://img.shields.io/codecov/c/gh/pieceofcake2/purifiable?label=Coverage)](https://codecov.io/gh/pieceofcake2/purifiable)

__This is forked for CakePHP2.__

A model behavior for [pieceofcake2/cakephp](https://github.com/pieceofcake2/cakephp) applications
that sanitizes HTML fields with [HTML Purifier](http://htmlpurifier.org/) before they are saved:
it removes malicious code (XSS) and keeps the allowed formatting.

## Documentation

- Website: https://pieceofcake2.github.io/purifiable/ (日本語: https://pieceofcake2.github.io/purifiable/ja/)
- Markdown: [docs/README.md](docs/README.md) (日本語: [docs/ja/README.md](docs/ja/README.md)) (for AI tools and offline reading)

It covers the settings, the HTML Purifier configuration, custom filters, the definition cache and
the migration from `josegonzalez/cakephp-purifiable`.

The Markdown documentation is also included in the Composer package, in the plugin's directory: `plugins/Purifiable/docs/README.md` (the location depends on your `installer-paths`).

## Requirements

* PHP 8.0+
* pieceofcake2/cakephp 2.12+
* ezyang/htmlpurifier 4.17+ (installed with the plugin)

## Installation

```bash
composer require pieceofcake2/purifiable
```

Load the plugin in `config/bootstrap.php` (legacy layout: `app/Config/bootstrap.php`):

```php
CakePlugin::load('Purifiable');
```

See [Installation](docs/README.md#installation) for the `installer-paths` setting.

## Usage

```php
class Post extends AppModel
{
    public $actsAs = [
        'Purifiable.Purifiable' => [
            'fields' => ['body'],
            'overwrite' => true,
        ],
    ];
}
```

`body` is purified when the post is saved. Without `overwrite`, the original value is kept and the
purified HTML is saved to `body_clean`. See [Settings](docs/README.md#settings).

## Contributing

See the [contribution guidelines](https://github.com/pieceofcake2/.github/blob/master/CONTRIBUTING.md).

## Origin

This package is a maintained fork of
[josegonzalez/cakephp-purifiable](https://github.com/josegonzalez/cakephp-purifiable)
(`josegonzalez/cakephp-purifiable` on Packagist, archived), originally by Jose Diaz-Gonzalez. It is
maintained for CakePHP 2 on modern PHP by [pieceofcake2](https://github.com/pieceofcake2). Changes
from the original are listed in the
[commit history since the fork](https://github.com/pieceofcake2/purifiable/compare/548a479...1.x).

## License

Licensed under the MIT License. See [LICENSE](LICENSE).
