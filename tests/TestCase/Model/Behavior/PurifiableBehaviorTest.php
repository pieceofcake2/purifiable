<?php

App::uses('Model', 'Model');
App::uses('PurifiableBehavior', 'Purifiable.Model/Behavior');

class PurifiableTestFilter extends HTMLPurifier_Filter
{
    public $name = 'PurifiableTestFilter';

    /**
     * Replace a marker before purifying.
     *
     * @param string $html HTML.
     * @param HTMLPurifier_Config $config Config.
     * @param HTMLPurifier_Context $context Context.
     * @return string
     */
    public function preFilter($html, $config, $context): string
    {
        return str_replace('[smile]', ':-)', $html);
    }
}

class PurifiableBehaviorTest extends CakeTestCase
{
    protected const XSS = '<p onclick="alert(1)">Hello <script>alert(1)</script><b>world</b></p>';

    protected const CLEAN = '<p>Hello <b>world</b></p>';

    protected const SIMPLE = '<p>x</p>';

    public $fixtures = ['plugin.purifiable.purifiable_post'];

    protected Model $Post;

    public function setUp(): void
    {
        parent::setUp();
        $this->Post = ClassRegistry::init(['class' => 'Model', 'alias' => 'PurifiablePost', 'table' => 'purifiable_posts']);
    }

    protected function attach(array $settings): void
    {
        $this->Post->Behaviors->unload('Purifiable');
        $this->Post->Behaviors->load('Purifiable.Purifiable', $settings);
    }

    protected function saveAndRead(array $data): array
    {
        $this->Post->create();
        $this->assertNotFalse($this->Post->save(['PurifiablePost' => $data]));

        return $this->Post->find('first', ['conditions' => ['id' => $this->Post->id]])['PurifiablePost'];
    }

    public function testDefaultSettings(): void
    {
        $this->attach(['fields' => 'body']);
        $settings = $this->Post->Behaviors->Purifiable->settings['PurifiablePost'];

        $this->assertSame(['body'], $settings['fields']);
        $this->assertFalse($settings['overwrite']);
        $this->assertSame('_clean', $settings['affix']);
        $this->assertSame('suffix', $settings['affix_position']);
        $this->assertSame('UTF-8', $settings['config']['Core']['Encoding']);
        $this->assertSame(CACHE . 'htmlpurifier', $settings['config']['Cache']['SerializerPath']);
    }

    public function testSaveToSuffixedField(): void
    {
        $this->attach(['fields' => ['body']]);
        $result = $this->saveAndRead(['body' => self::XSS]);

        $this->assertSame(self::XSS, $result['body'], 'The original value is kept.');
        $this->assertSame(self::CLEAN, $result['body_clean']);
    }

    public function testSaveToPrefixedField(): void
    {
        $this->attach(['fields' => ['body'], 'affix' => 'clean_', 'affix_position' => 'prefix']);
        $result = $this->saveAndRead(['body' => self::XSS]);

        $this->assertSame(self::CLEAN, $result['clean_body']);
        $this->assertNull($result['body_clean']);
    }

    public function testOverwrite(): void
    {
        $this->attach(['fields' => ['body', 'excerpt'], 'overwrite' => true]);
        $result = $this->saveAndRead(['body' => self::XSS, 'excerpt' => '<a href="javascript:alert(1)">x</a>']);

        $this->assertSame(self::CLEAN, $result['body']);
        $this->assertSame('<a>x</a>', $result['excerpt']);
    }

    public function testFieldsNotConfiguredAreKept(): void
    {
        $this->attach(['fields' => ['body'], 'overwrite' => true]);
        $result = $this->saveAndRead(['title' => '<b>title</b>', 'body' => 'text']);

        $this->assertSame('<b>title</b>', $result['title']);
    }

    public function testEmptyAndMissingValuesAreSkipped(): void
    {
        $this->attach(['fields' => ['body', 'excerpt']]);
        $result = $this->saveAndRead(['title' => 'x', 'body' => '']);

        $this->assertSame('', $result['body']);
        $this->assertNull($result['body_clean']);
        $this->assertNull($result['excerpt']);
    }

    public function testZeroIsPurified(): void
    {
        $this->attach(['fields' => ['body']]);
        $result = $this->saveAndRead(['body' => '0']);

        $this->assertSame('0', $result['body_clean']);
    }

    public function testCustomConfig(): void
    {
        $this->attach([
            'fields' => ['body'],
            'overwrite' => true,
            'config' => [
                'HTML' => ['Allowed' => 'p'],
                'AutoFormat' => ['RemoveSpansWithoutAttributes' => false],
            ],
        ]);
        $result = $this->saveAndRead(['body' => self::XSS]);

        $this->assertSame('<p>Hello world</p>', $result['body']);
    }

    public function testCustomFilters(): void
    {
        $this->attach(['fields' => ['body'], 'overwrite' => true, 'customFilters' => ['PurifiableTestFilter']]);

        $this->assertSame('<p>:-)</p>', $this->saveAndRead(['body' => '<p>[smile]</p>'])['body']);

        $this->attach(['fields' => ['body'], 'overwrite' => true, 'customFilters' => [new PurifiableTestFilter()]]);

        $this->assertSame('<p>:-)</p>', $this->saveAndRead(['body' => '<p>[smile]</p>'])['body']);
    }

    public function testCleanModelMethod(): void
    {
        $this->attach(['fields' => ['body']]);

        $this->assertSame(self::CLEAN, $this->Post->clean(self::XSS));
        $this->assertSame('', $this->Post->clean(null));
    }

    public function testPurifierIsReused(): void
    {
        $this->attach(['fields' => ['body']]);
        $purifier = $this->Post->Behaviors->Purifiable->getPurifier($this->Post);

        $this->assertInstanceOf(HTMLPurifier::class, $purifier);
        $this->assertSame($purifier, $this->Post->Behaviors->Purifiable->getPurifier($this->Post));

        $this->attach(['fields' => ['body'], 'overwrite' => true]);
        $this->assertNotSame($purifier, $this->Post->Behaviors->Purifiable->getPurifier($this->Post));
    }

    public function testSerializerPathIsCreated(): void
    {
        $path = TMP . 'tests' . DS . 'purifiable_' . uniqid();
        $this->attach(['fields' => ['body'], 'config' => ['Cache' => ['SerializerPath' => $path]]]);

        $this->assertSame(self::SIMPLE, $this->Post->clean(self::SIMPLE));
        $this->assertDirectoryExists($path);
        (new Folder($path))->delete();
    }

    public function testSerializerCanBeDisabled(): void
    {
        $this->attach(['fields' => ['body'], 'config' => ['Cache' => ['DefinitionImpl' => null]]]);

        $this->assertSame(self::SIMPLE, $this->Post->clean(self::SIMPLE));
    }
}
