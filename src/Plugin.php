<?php

namespace miranj\autotranslator;

use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use miranj\autotranslator\models\Settings;
use miranj\autotranslator\services\Translator;
use miranj\autotranslator\translators\DeepLTranslator;
use miranj\autotranslator\translators\GoogleTranslator;
use miranj\autotranslator\translators\TranslatorInterface;

/**
 * Auto Translator plugin
 *
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 * @author Miranj Design LLP <hello@miranj.in>
 * @copyright Miranj Design LLP
 * @license MIT
 * @property-read Translator $translator
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;
    
    public const DEFAULT_TRANSLATORS = [
        DeepLTranslator::class,
        GoogleTranslator::class,
    ];

    public static function config(): array
    {
        return [
            'components' => ['translator' => Translator::class],
        ];
    }

    public function init(): void
    {
        parent::init();

        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function() {
            $this->attachEventHandlers();
            // ...
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        // only allow classes that implement TranslatorInterface
        $translatorOptions = array_filter(
            self::DEFAULT_TRANSLATORS,
            fn($item) => in_array(TranslatorInterface::class, class_implements($item))
        );
        $translatorOptions = array_map(fn($item) => [
            'value' => $item,
            'label' => $item::displayName(),
        ], $translatorOptions);
        
        $configOverrides = Craft::$app->getConfig()->getConfigFromFile(strtolower($this->handle));
        
        return Craft::$app->view->renderTemplate('auto-translator/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
            'overrides' => array_keys($configOverrides),
            'translatorOptions' => $translatorOptions,
        ]);
    }

    private function attachEventHandlers(): void
    {
        // Register event handlers here ...
        // (see https://craftcms.com/docs/4.x/extend/events.html to get started)
    }
}
