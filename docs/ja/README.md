# Purifiable プラグインドキュメント

Purifiable プラグインは、HTML のフィールドを保存する前に [HTML Purifier](http://htmlpurifier.org/) で
サニタイズするモデルのビヘイビアを提供します。HTML Purifier は `<script>` タグ、イベントハンドラの
属性、`javascript:` の URL などの悪意のあるコード (XSS) を取り除き、許可した書式は残したまま、
HTML を標準に準拠した形に整えます。

WYSIWYG エディタなどでユーザーが HTML を入力するフィールドに使います。HTML をまったく含めない
フィールドでは、代わりに出力時に `h()` でエスケープしてください。

- [インストール](#installation)
- [使い方](#usage)
- [設定](#settings)
- [HTML Purifier の設定](#html-purifier-configuration)
- [カスタムフィルター](#custom-filters)
- [値を直接サニタイズする](#purifying-values-directly)
- [定義のキャッシュ](#definition-cache)
- [josegonzalez/cakephp-purifiable からの移行](#migrating-from-josegonzalez-cakephp-purifiable)
- [由来](#origin)

<a id="installation"></a>

## インストール

動作要件: PHP 8.0 以上、[pieceofcake2/cakephp](https://github.com/pieceofcake2/cakephp) 2.12 以上。
[ezyang/htmlpurifier](https://github.com/ezyang/htmlpurifier) 4.17 以上はプラグインと一緒にインストールされます。

```bash
composer require pieceofcake2/purifiable
```

プラグインのタイプは `cakephp-plugin` なので、`composer/installers` によってプラグインディレクトリに
インストールされます。アプリケーションの `composer.json` で `installer-paths` を設定してください。

| レイアウト | `installer-paths` |
| --- | --- |
| モダン (`config/`、`src/`、`templates/`、`plugins/`) | `"plugins/{$name}/": ["type:cakephp-plugin"]` |
| レガシー (`app/Config/`、`app/Plugin/`) | `"app/Plugin/{$name}/": ["type:cakephp-plugin"]` |

`config/bootstrap.php` (レガシーレイアウトでは `app/Config/bootstrap.php`) でプラグインを読み込みます。
すでに `CakePlugin::loadAll()` を使っている場合は不要です。

```php
CakePlugin::load('Purifiable');
```

<a id="usage"></a>

## 使い方

モデルにビヘイビアを追加し、サニタイズするフィールドを指定します。

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

記事を保存すると、`body` がサニタイズされた HTML に置き換わります。

```php
$this->Post->save(['Post' => [
    'body' => '<p onclick="steal()">Hello <script>alert(1)</script><b>world</b></p>',
]]);
// body: <p>Hello <b>world</b></p>
```

デフォルト (`overwrite` が false) では元の値はそのまま残り、サニタイズされた HTML は別のフィールド
`body_clean` に保存されます。このカラムをテーブルに追加し、ビューでは `body_clean` を出力してください。

```php
public $actsAs = [
    'Purifiable.Purifiable' => ['fields' => ['body']],
];
// body: the original HTML, body_clean: the purified HTML
```

データに含まれないフィールド、空文字列、文字列以外の値は変更しません。

<a id="settings"></a>

## 設定

| 設定 | デフォルト | 説明 |
| --- | --- | --- |
| `fields` | `[]` | サニタイズするフィールド (文字列または配列)。 |
| `overwrite` | `false` | フィールドをサニタイズした HTML で置き換えます。false の場合は、`affix` を付けた名前のフィールドに保存します。 |
| `affix` | `_clean` | サニタイズした HTML を保存するフィールドに付ける文字列。 |
| `affix_position` | `suffix` | `suffix` (`body_clean`) または `prefix` (`'affix' => 'clean_'` の場合は `clean_body`)。 |
| `config` | 下記を参照 | 名前空間ごとの HTML Purifier の設定。 |
| `customFilters` | `[]` | `HTMLPurifier_Filter` のクラス名またはインスタンス。 |

<a id="html-purifier-configuration"></a>

## HTML Purifier の設定

`config` では、[HTML Purifier の設定ディレクティブ](http://htmlpurifier.org/live/configdoc/plain.html)を
名前空間ごとにまとめて指定します。デフォルトの設定とマージされます。

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

たとえば、一部の要素と属性だけを許可し、リンクを新しいウィンドウで開くようにするには次のようにします。

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

`AutoFormat.RemoveSpansWithoutAttributes` は `span` が許可されていることが前提です。`HTML.Allowed` に
`span` を含めない場合は、これを無効にしてください。無効にしないと HTML Purifier が例外を投げます。

```php
'config' => [
    'HTML' => ['Allowed' => 'p,b,i,a[href]'],
    'AutoFormat' => ['RemoveSpansWithoutAttributes' => false],
],
```

HTML の定義 (たとえば `HTML.Allowed`) を変えたときは、`HTML.DefinitionRev` を増やすか、
[定義のキャッシュ](#definition-cache)を削除してください。

<a id="custom-filters"></a>

## カスタムフィルター

`customFilters` で `HTMLPurifier_Filter` のサブクラスを追加できます (`Filter.Custom`)。たとえば、
サニタイズの前に目印を変換するには次のようにします。

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

ビヘイビアが値をサニタイズする前に、クラスが (Composer や `App::uses()` で) 読み込まれている必要が
あります。クラス名の代わりにインスタンスを渡すこともできます。

<a id="purifying-values-directly"></a>

## 値を直接サニタイズする

ビヘイビアはモデルに `clean()` メソッドを追加します。保存しないデータなどを、モデルの設定で
サニタイズできます。

```php
$this->set('preview', $this->Post->clean($this->request->data('Post.preview')));
```

`$this->Post->Behaviors->Purifiable->getPurifier($this->Post)` は、モデルの `HTMLPurifier` インスタンスを
返します (モデルごとに一度だけ作成して再利用します)。

<a id="definition-cache"></a>

## 定義のキャッシュ

HTML Purifier は、設定から作った定義をキャッシュします。このプラグインは定義を
`CACHE . 'htmlpurifier'` (`tmp/cache/htmlpurifier`) に保存し、必要ならディレクトリを作成します。
HTML Purifier 自体のデフォルトは `vendor/` 内のライブラリのディレクトリで、書き込めないことがよくあります。
`Cache.SerializerPath` で別のディレクトリを指定するか、`'Cache' => ['DefinitionImpl' => null]` で
キャッシュを無効にできます。

HTML の定義を変えて `HTML.DefinitionRev` を増やさない場合は、このディレクトリを削除してください。

<a id="migrating-from-josegonzalez-cakephp-purifiable"></a>

## josegonzalez/cakephp-purifiable からの移行

このパッケージは `josegonzalez/cakephp-purifiable` を置き換えます。プラグイン名 (`Purifiable`)、
ビヘイビア名 (`Purifiable.Purifiable`)、設定は同じです。

```bash
composer remove josegonzalez/cakephp-purifiable
composer require pieceofcake2/purifiable
```

動作の変更点:

- HTML Purifier は Composer で読み込まれます。`Vendor/htmlpurifier` にコピーを置いていた場合は削除してください。
- 定義のキャッシュは、ライブラリのディレクトリではなく `tmp/cache/htmlpurifier` に保存されます。
- 値が `'0'` のフィールドもサニタイズします (以前は空として扱われ、スキップされていました)。

<a id="origin"></a>

## 由来

このパッケージは
[josegonzalez/cakephp-purifiable](https://github.com/josegonzalez/cakephp-purifiable)
(Packagist では `josegonzalez/cakephp-purifiable`、アーカイブ済み) のメンテナンスされたフォークで、
元は Jose Diaz-Gonzalez によって作られました。
[pieceofcake2](https://github.com/pieceofcake2) が、モダンな PHP 上の CakePHP 2 向けにメンテナンスしています。
オリジナルからの変更点は
[フォーク以降のコミット履歴](https://github.com/pieceofcake2/purifiable/compare/548a479...1.x)
にあります。
