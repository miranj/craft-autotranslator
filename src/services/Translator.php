<?php

namespace miranj\autotranslator\services;

use Craft;
use miranj\autotranslator\exceptions\AutoTranslatorException;
use miranj\autotranslator\Plugin;
use miranj\autotranslator\translators\TranslatorInterface;
use yii\base\Component;

/**
 * Translator service
 */
class Translator extends Component
{
    protected $_translator = null;
    protected $_translatorOffline = false;
    
    public function getTranslator()
    {
        if (!$this->_translator) {
            $translatorClass = Plugin::getInstance()->settings->translatorClass;
            $translatorIsCompatible = in_array(
                TranslatorInterface::class,
                class_implements($translatorClass),
            );
            if ($translatorClass && $translatorIsCompatible) {
                $this->_translator = new $translatorClass();
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
        
        if (!trim($input)) {
            Craft::debug("Ignore empty string:$input", __METHOD__);
            return $input;
        }
        
        // remove region codes from Craft language locales
        $targetLanguage = explode('-', $targetLanguage, 2)[0];
        if ($sourceLanguage) {
            $sourceLanguage = explode('-', $sourceLanguage, 2)[0];
            Craft::debug("Translate from $sourceLanguage to $targetLanguage: $input", __METHOD__);
        } else {
            Craft::debug("Translate to $targetLanguage: $input", __METHOD__);
        }

        $cacheKey = [$translator::class, $input, $targetLanguage, $sourceLanguage];
        $result = null;

        // if the service is offline, query data cache only
        if ($this->_translatorOffline) {
            $result = Craft::$app->cache->get($cacheKey);
        } else {
            // otherwise, attempt both: data cache &
            // translation service if cache miss
            try {
                $result = Craft::$app->cache->getOrSet(
                    $cacheKey,
                    fn() => $translator->translate($input, $targetLanguage, $sourceLanguage),
                );
            } catch (AutoTranslatorException $e) {
                Craft::error("Translation service offline: $e", __METHOD__);
                $this->_translatorOffline = true;
            }
        }
        
        return $result ?: $input;
    }
}
