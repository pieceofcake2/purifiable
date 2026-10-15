<?php

App::uses('ModelBehavior', 'Model');

/**
 * Purifiable Model Behavior
 *
 * Sanitizes HTML in model fields with [HTML Purifier](http://htmlpurifier.org/) before they are
 * saved: removes malicious code (XSS) and makes the HTML standards compliant.
 *
 * ```
 * public $actsAs = [
 *     'Purifiable.Purifiable' => [
 *         'fields' => ['body'],
 *         'overwrite' => true,
 *     ],
 * ];
 * ```
 *
 * Settings:
 *
 * - `fields`: Fields to purify.
 * - `overwrite`: Replace the field value with the purified HTML (default false). When false, the
 *   purified HTML is stored in another field named with `affix` (e.g. `body_clean`).
 * - `affix`: Affix of the field with the purified HTML (default `_clean`).
 * - `affix_position`: `suffix` (default, `body_clean`) or `prefix` (`_cleanbody`).
 * - `config`: HTML Purifier configuration by namespace, e.g. `['HTML' => ['Allowed' => 'p,a[href]']]`.
 *   See http://htmlpurifier.org/live/configdoc/plain.html
 * - `customFilters`: Class names of `HTMLPurifier_Filter` subclasses to use.
 *
 * @author Jose Diaz-Gonzalez
 */
class PurifiableBehavior extends ModelBehavior
{
    /**
     * Default settings.
     *
     * @var array<string, mixed>
     */
    protected array $_defaults = [
        'fields' => [],
        'overwrite' => false,
        'affix' => '_clean',
        'affix_position' => 'suffix',
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
        ],
        'customFilters' => [],
    ];

    /**
     * HTML Purifier instances by model alias.
     *
     * @var array<string, HTMLPurifier>
     */
    protected array $_purifiers = [];

    /**
     * Setup the behavior.
     *
     * @param Model $model Model using the behavior.
     * @param array $config Settings.
     * @return void
     */
    public function setup(Model $model, $config = [])
    {
        $settings = Hash::merge($this->_defaults, (array)$config);
        $settings['fields'] = (array)$settings['fields'];
        if (!isset($settings['config']['Cache']['SerializerPath']) && defined('CACHE')) {
            $settings['config']['Cache']['SerializerPath'] = CACHE . 'htmlpurifier';
        }
        $this->settings[$model->alias] = $settings;
        unset($this->_purifiers[$model->alias]);
    }

    /**
     * Purify the configured fields before saving.
     *
     * @param Model $model Model instance.
     * @param array $options Options passed from Model::save().
     * @return bool
     */
    public function beforeSave(Model $model, $options = [])
    {
        $settings = $this->settings[$model->alias];
        foreach ($settings['fields'] as $field) {
            if (!isset($model->data[$model->alias][$field])) {
                continue;
            }
            $value = $model->data[$model->alias][$field];
            if (!is_string($value) || $value === '') {
                continue;
            }

            $target = $field;
            if (!$settings['overwrite']) {
                $target = $settings['affix_position'] === 'prefix'
                    ? $settings['affix'] . $field
                    : $field . $settings['affix'];
            }
            $model->data[$model->alias][$target] = $this->clean($model, $value);
        }

        return true;
    }

    /**
     * Purify HTML with the settings of the model.
     *
     * Also available as a model method: `$this->Post->clean($html)`.
     *
     * @param Model $model Model instance.
     * @param string|null $value HTML to purify.
     * @return string
     */
    public function clean(Model $model, $value)
    {
        return $this->getPurifier($model)->purify((string)$value);
    }

    /**
     * The HTML Purifier instance of the model, created once.
     *
     * @param Model $model Model instance.
     * @return HTMLPurifier
     */
    public function getPurifier(Model $model): HTMLPurifier
    {
        if (!isset($this->_purifiers[$model->alias])) {
            $this->_purifiers[$model->alias] = new HTMLPurifier($this->_config($this->settings[$model->alias]));
        }

        return $this->_purifiers[$model->alias];
    }

    /**
     * Build the HTML Purifier configuration.
     *
     * @param array $settings Settings of the model.
     * @return HTMLPurifier_Config
     */
    protected function _config(array $settings): HTMLPurifier_Config
    {
        $config = HTMLPurifier_Config::createDefault();
        foreach ($settings['config'] as $namespace => $values) {
            foreach ((array)$values as $key => $value) {
                $config->set($namespace . '.' . $key, $value);
            }
        }

        $path = $settings['config']['Cache']['SerializerPath'] ?? null;
        if (is_string($path) && $path !== '' && !is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            throw new CakeException(sprintf('Cannot create the HTML Purifier cache directory %s.', $path));
        }

        if ($settings['customFilters']) {
            $filters = [];
            foreach ((array)$settings['customFilters'] as $filter) {
                $filters[] = is_object($filter) ? $filter : new $filter();
            }
            $config->set('Filter.Custom', $filters);
        }

        return $config;
    }
}
