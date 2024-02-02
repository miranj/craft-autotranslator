<?php

namespace miranj\autotranslator\services;

use Craft;
use miranj\autotranslator\exceptions\AutoTranslatorException;
use miranj\autotranslator\Plugin;
use yii\base\Component;

/**
 * Translator service
 */
class Translator extends Component
{
    protected $_translator = null;
    
    public function getTranslator()
    {
        if (!$this->_translator) {
            $settings = Plugin::getInstance()->getSettings();
            if ($settings->isTranslatorCompatible()) {
                $this->_translator = new $settings->translatorClass($settings->toArray());
            }
        }
        return $this->_translator;
    }
    
    public function translate($input, $targetLanguage, $sourceLanguage = '')
    {
        // fetch active translator
        $translator = $this->getTranslator();
        if (!$translator) {
            Craft::debug("No translator found", __METHOD__);
            return $input;
        }
        
        // sanity check
        if (!trim($input)) {
            Craft::debug("Ignore empty string:$input", __METHOD__);
            return $input;
        }
        
        // log
        if ($sourceLanguage) {
            Craft::debug("Translate from $sourceLanguage to $targetLanguage: $input", __METHOD__);
        } else {
            Craft::debug("Translate to $targetLanguage: $input", __METHOD__);
        }

        // attempt translation
        $result = null;
        try {
            if (Plugin::getInstance()->settings->cacheEnabled) {
                $cacheKey = [$translator::class, $input, $targetLanguage, $sourceLanguage];
                $result = Craft::$app->cache->getOrSet(
                    $cacheKey,
                    fn() => $translator->translate($input, $targetLanguage, $sourceLanguage),
                );
            } else {
                $result = $translator->translate($input, $targetLanguage, $sourceLanguage);
            }
        } catch (AutoTranslatorException $e) {
            Craft::error("Translation service error: $e", __METHOD__);
        }
        
        return $result ?: $input;
    }
}
