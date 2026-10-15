<?php

class PurifiablePostFixture extends CakeTestFixture
{
    /**
     * Fields.
     *
     * @var array
     */
    public $fields = [
        'id' => ['type' => 'integer', 'key' => 'primary'],
        'title' => ['type' => 'string', 'null' => true],
        'body' => ['type' => 'text', 'null' => true],
        'body_clean' => ['type' => 'text', 'null' => true],
        'clean_body' => ['type' => 'text', 'null' => true],
        'excerpt' => ['type' => 'text', 'null' => true],
    ];

    /**
     * Records.
     *
     * @var array
     */
    public $records = [];
}
