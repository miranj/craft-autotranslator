<?php

namespace miranj\autotranslator\translationproviders;

use Craft;
use craft\helpers\App;
use DeepL\DeepLException;
use DeepL\Translator as Translator;
use miranj\autotranslator\exceptions\AutoTranslatorException;
use miranj\autotranslator\helpers\LanguageHelper;
use miranj\autotranslator\Plugin;

/**
* DeepL
*
* https://www.deepl.com/docs-api/translate-text
* https://github.com/DeepLcom/deepl-php
*/
class DeepLTranslator implements TranslatorInterface
{
    public static array $defaultConfig = [
        'translatorAuthKey' => '',
    ];
    
    protected $_apiClient = null;
    protected $_apiKey = '';
    protected $_sourceLanguages = [];
    protected $_targetLanguages = [];
    
    public function __construct(array $config = [])
    {
        $config = array_merge(self::$defaultConfig, $config);
        $this->_apiKey = App::parseEnv($config['translatorAuthKey']);
    }
    
    public static function displayName(): string
    {
        return Craft::t('auto-translator', 'DeepL');
    }
    
    public function getApiClient()
    {
        if (!$this->_apiClient) {
            $this->_apiClient = new Translator($this->_apiKey);
        }
        return $this->_apiClient;
    }
    
    public function getSourceLanguages()
    {
        if (!$this->_sourceLanguages) {
            $apiCall = fn() => LanguageHelper::prepLanguageList(
                array_column($this->getApiClient()->getSourceLanguages(), 'code')
            );
            $cacheKey = [self::class, __METHOD__];
            $this->_sourceLanguages = Plugin::getInstance()->settings->cacheEnabled
                ? Craft::$app->cache->getOrSet($cacheKey, $apiCall)
                : $apiCall();
        }
        return $this->_sourceLanguages;
    }
    
    public function getTargetLanguages()
    {
        if (!$this->_targetLanguages) {
            $apiCall = fn() => LanguageHelper::prepLanguageList(
                array_column($this->getApiClient()->getTargetLanguages(), 'code')
            );
            $cacheKey = [self::class, __METHOD__];
            $this->_targetLanguages = Plugin::getInstance()->settings->cacheEnabled
                ? Craft::$app->cache->getOrSet($cacheKey, $apiCall)
                : $apiCall();
        }
        return $this->_targetLanguages;
    }
    
    public function findMatchingSourceLanguage(string $sourceLanguage)
    {
        return LanguageHelper::getBestMatchingLanguge($this->getSourceLanguages(), $sourceLanguage);
    }
    
    public function findMatchingTargetLanguage(string $targetLanguage)
    {
        return LanguageHelper::getBestMatchingLanguge($this->getTargetLanguages(), $targetLanguage);
    }
    
    public function translate(string $input, string $targetLanguage, string $sourceLanguage = ''): ?string
    {
        // query the DeepL API
        try {
            Craft::info(
                "DeepL API request: translate from {$sourceLanguage} to {$targetLanguage} (" .
                    strlen($input) .
                    ") {$input}",
                __METHOD__,
            );
            
            $targetLanguage = $this->findMatchingTargetLanguage($targetLanguage);
            $sourceLanguage = $this->findMatchingSourceLanguage($sourceLanguage);
            
            $result = $this->getApiClient()->translateText(
                $input,
                $sourceLanguage ?: null,
                $targetLanguage,
            );
        } catch (DeepLException $e) {
            throw new AutoTranslatorException(previous: $e);
        }
        
        // fallback to returning the input as-is
        if (!$result) {
            $result = ['text' => $input];
        } else {
            $result = ['text' => $result->text];
        }

        return html_entity_decode($result['text'], ENT_QUOTES);
    }
}
