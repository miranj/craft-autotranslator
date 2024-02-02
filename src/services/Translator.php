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
    
    protected function sanityCheck(mixed $input, string $targetLanguage, string $sourceLanguage = '')
    {
        // blank input
        if (!trim($input)) {
            Craft::debug("Ignore empty string:$input", __METHOD__);
            return false;
        }
        
        // no target language
        if (!trim($targetLanguage)) {
            Craft::debug("Target translation language not specified: $input", __METHOD__);
            return false;
        }
        
        // same source and target
        if (trim($targetLanguage) == trim($sourceLanguage)) {
            Craft::debug("Same source & target language, skipping translation: $input", __METHOD__);
            return false;
        }
        
        return true;
    }
    
    public function translate(mixed $input, string $targetLanguage, string $sourceLanguage = '')
    {
        $result = null;
        
        // sanity check
        if (!$this->sanityCheck($input, $targetLanguage, $sourceLanguage)) {
            return $input;
        }
        
        // check for static translations
        if (Plugin::getInstance()->settings->preferStaticTranslations) {
            $result = $this->getStaticTranslation($input, $targetLanguage);
            if ($result !== null) {
                Craft::debug("Static translation found $input: $result", __METHOD__);
                return $result;
            }
        }
        
        // fetch active translator
        $translator = $this->getTranslator();
        if (!$translator) {
            Craft::debug("No translator found", __METHOD__);
            return $input;
        }
        
        // log
        if ($sourceLanguage) {
            Craft::debug("Translate from $sourceLanguage to $targetLanguage: $input", __METHOD__);
        } else {
            Craft::debug("Translate to $targetLanguage: $input", __METHOD__);
        }

        // attempt automatic translation
        try {
            $getTranslation = fn() => $translator->translate(
                (string)$input,
                $targetLanguage,
                $sourceLanguage,
            );
            if (Plugin::getInstance()->settings->cacheEnabled) {
                $cacheKey = [$translator::class, $input, $targetLanguage, $sourceLanguage];
                $result = Craft::$app->cache->getOrSet($cacheKey, $getTranslation);
            } else {
                $result = $getTranslation();
            }
        } catch (AutoTranslatorException $e) {
            Craft::error("Translation service error: $e", __METHOD__);
        }
        
        return $result ?: $input;
    }
    
    public function getStaticTranslation(mixed $message, string $language, string $category = '')
    {
        if ($category === '') {
            $category = Craft::$app->getRequest()->getIsSiteRequest() ? 'site' : 'app';
        }
        
        $messageSource = Craft::$app->i18n->getMessageSource($category);
        $translation = $messageSource->translate($category, (string)$message, $language);
        if ($translation === false) {
            return null;
        }
        
        return Craft::t($category, (string)$message, [], $language);
    }
}
