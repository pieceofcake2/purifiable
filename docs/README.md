# Purifiable Plugin Documentation

The Purifiable plugin provides a model behavior that sanitizes HTML fields with
[HTML Purifier](http://htmlpurifier.org/) before they are saved. HTML Purifier removes malicious
code (XSS) such as `<script>` tags, event handler attributes and `javascript:` URLs, and makes the
HTML standards compliant, while keeping the allowed formatting.

Use it for fields where users may enter HTML, for example with a WYSIWYG editor. For fields that
must not contain HTML at all, escape the output with `h()` instead.

- [Installation](#installation)
- [Usage](#usage)
- [Settings](#settings)
- [HTML Purifier Configuration](#html-purifier-configuration)
- [Custom Filters](#custom-filters)
- [Purifying Values Directly](#purifying-values-directly)
- [Definition Cache](#definition-cache)
- [Migrating from josegonzalez/cakephp-purifiable](#migrating-from-josegonzalez-cakephp-purifiable)
- [Origin](#origin)

## Installation

Requirements: PHP 8.0+, [pieceofcake2/cakephp](https://github.com/pieceofcake2/cakephp) 2.12+.
[ezyang/htmlpurifier](https://github.com/ezyang/htmlpurifier) 4.17+ is installed with the plugin.

```bash
composer require pieceofcake2/purifiable
```

The plugin has the `cakephp-plugin` type, so it is installed into your plugins directory by
`composer/installers`. Set `installer-paths` in your application's `composer.json`:

| Layout | `installer-paths` |
| --- | --- |
| Modern (`config/`, `src/`, `templates/`, `plugins/`) | `"plugins/{$name}/": ["type:cakephp-plugin"]` |
| Legacy (`app/Config/`, `app/Plugin/`) | `"app/Plugin/{$name}/": ["type:cakephp-plugin"]` |

Load the plugin in `config/bootstrap.php` (legacy layout: `app/Config/bootstrap.php`). If you
already use `CakePlugin::loadAll()`, this is not necessary:

```php
CakePlugin::load('Purifiable');
```

## Usage

Attach the behavior to a model and list the fields to purify:

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

When a post is saved, `body` is replaced with the purified HTML:

```php
$this->Post->save(['Post' => [
    'body' => '<p onclick="steal()">Hello <script>alert(1)</script><b>world</b></p>',
]]);
// body: <p>Hello <b>world</b></p>
```

By default (`overwrite` false), the original value is kept and the purified HTML is saved to another
field, `body_clean`. Add that column to the table, and output `body_clean` in the views:

```php
public $actsAs = [
    'Purifiable.Purifiable' => ['fields' => ['body']],
];
// body: the original HTML, body_clean: the purified HTML
```

Fields that are not in the data, empty strings and non-string values are not changed.

## Settings

| Setting | Default | Description |
| --- | --- | --- |
| `fields` | `[]` | Fields to purify (a string or an array). |
| `overwrite` | `false` | Replace the field with the purified HTML. When false, the purified HTML is saved to the field named with `affix`. |
| `affix` | `_clean` | Affix of the field for the purified HTML. |
| `affix_position` | `suffix` | `suffix` (`body_clean`) or `prefix` (with `'affix' => 'clean_'`: `clean_body`). |
| `config` | See below | HTML Purifier configuration by namespace. |
| `customFilters` | `[]` | `HTMLPurifier_Filter` class names or instances. |

## HTML Purifier Configuration

`config` sets [HTML Purifier configuration directives](http://htmlpurifier.org/live/configdoc/plain.html),
grouped by namespace. It is merged with the default:

```php
'config' => [
    'HTML' => [
        'DefinitionID' => 'purifiable',
        'DefinitionRev' => 1,
        'TidyLevel' => 'heavy',
        'Doctype' => 'XHTML 1.0 Transitional',
    ],
    'Core' => [
        'Encoding' => 'UTF-8',
    ],
    'AutoFormat' => [
        'RemoveSpansWithoutAttributes' => true,
        'RemoveEmpty' => true,
    ],
    'Cache' => [
        'SerializerPath' => CACHE . 'htmlpurifier',
    ],
],
```

For example, allow only some elements and attributes, and open links in a new window:

```php
public $actsAs = [
    'Purifiable.Purifiable' => [
        'fields' => ['body'],
        'overwrite' => true,
        'config' => [
            'HTML' => [
                'Allowed' => 'p,br,b,strong,i,em,ul,ol,li,a[href|title],span[style]',
                'TargetBlank' => true,
            ],
            'CSS' => [
                'AllowedProperties' => 'color,font-weight,text-decoration',
            ],
        ],
    ],
];
```

`AutoFormat.RemoveSpansWithoutAttributes` requires `span` to be allowed. If `HTML.Allowed` does not
include `span`, disable it, or HTML Purifier throws an exception:

```php
'config' => [
    'HTML' => ['Allowed' => 'p,b,i,a[href]'],
    'AutoFormat' => ['RemoveSpansWithoutAttributes' => false],
],
```

When you change the HTML definition (for example `HTML.Allowed`), increase `HTML.DefinitionRev` or
clear the [definition cache](#definition-cache).

## Custom Filters

`customFilters` adds `HTMLPurifier_Filter` subclasses (`Filter.Custom`), for example to convert
markers before purifying:

```php
class SmileyFilter extends HTMLPurifier_Filter
{
    public $name = 'SmileyFilter';

    /**
     * Replace the markers before purifying.
     *
     * @param string $html HTML.
     * @param HTMLPurifier_Config $config Configuration.
     * @param HTMLPurifier_Context $context Context.
     * @return string
     */
    public function preFilter($html, $config, $context)
    {
        return str_replace('[smile]', ':-)', $html);
    }
}
```

```php
'customFilters' => ['SmileyFilter'],
```

The class must be loaded (by Composer or `App::uses()`) before the behavior purifies a value. An
instance can be given instead of the class name.

## Purifying Values Directly

The behavior adds the `clean()` method to the model, to purify a value with the settings of the
model, for example for data that is not saved:

```php
$this->set('preview', $this->Post->clean($this->request->data('Post.preview')));
```

`$this->Post->Behaviors->Purifiable->getPurifier($this->Post)` returns the `HTMLPurifier` instance
of the model (created once per model and reused).

## Definition Cache

HTML Purifier caches the definitions built from the configuration. The plugin stores them in
`CACHE . 'htmlpurifier'` (`tmp/cache/htmlpurifier`) and creates the directory if needed; HTML
Purifier's own default is a directory inside the library in `vendor/`, which is often not
writable. Set another directory with `Cache.SerializerPath`, or disable the cache with
`'Cache' => ['DefinitionImpl' => null]`.

Delete the directory after changing the HTML definition if you do not increase
`HTML.DefinitionRev`.

## Migrating from josegonzalez/cakephp-purifiable

This package replaces `josegonzalez/cakephp-purifiable`. The plugin name (`Purifiable`), the
behavior name (`Purifiable.Purifiable`) and the settings are the same.

```bash
composer remove josegonzalez/cakephp-purifiable
composer require pieceofcake2/purifiable
```

Changes in behavior:

- HTML Purifier is loaded by Composer. Remove a copy in `Vendor/htmlpurifier` if you added one.
- The definition cache is stored in `tmp/cache/htmlpurifier` instead of the library directory.
- A field with the value `'0'` is purified (it was skipped as empty).

## Origin

This package is a maintained fork of
[josegonzalez/cakephp-purifiable](https://github.com/josegonzalez/cakephp-purifiable)
(`josegonzalez/cakephp-purifiable` on Packagist, archived), originally by Jose Diaz-Gonzalez. It is
maintained for CakePHP 2 on modern PHP by [pieceofcake2](https://github.com/pieceofcake2). Changes
from the original are listed in the
[commit history since the fork](https://github.com/pieceofcake2/purifiable/compare/548a479...1.x).
