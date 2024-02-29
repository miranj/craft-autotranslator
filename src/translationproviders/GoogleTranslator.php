<?php

namespace miranj\autotranslator\translationproviders;

use Craft;
use craft\helpers\App;
use Google\Cloud\Core\Exception\ServiceException;
use Google\Cloud\Translate\V2\TranslateClient;
use miranj\autotranslator\exceptions\AutoTranslatorException;
use miranj\autotranslator\helpers\LanguageHelper;
use miranj\autotranslator\Plugin;

/**
* Google Cloud Translations
* v2 (Basic)
*
* https://cloud.google.com/translate/docs/basic/translating-text
* https://cloud.google.com/php/docs/reference/cloud-translate/latest
*/
class GoogleTranslator implements TranslatorInterface
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
        return Craft::t('auto-translator', 'Google Translate');
    }
    
    public function getApiClient()
    {
        if (!$this->_apiClient) {
            $this->_apiClient = new TranslateClient(['key' => $this->_apiKey]);
        }
        return $this->_apiClient;
    }
    
    protected function setSourceAndTargetLanguages()
    {
        $apiCall = fn() => LanguageHelper::prepLanguageList(
            $this->getApiClient()->languages()
        );
        $cacheKey = [self::class, __METHOD__];
        $this->_sourceLanguages = Plugin::getInstance()->settings->cacheEnabled
            ? Craft::$app->cache->getOrSet($cacheKey, $apiCall)
            : $apiCall();
        $this->_targetLanguages = $this->_sourceLanguages;
    }
    
    public function getSourceLanguages()
    {
        if (!$this->_sourceLanguages) {
            $this->setSourceAndTargetLanguages();
        }
        return $this->_sourceLanguages;
    }
    
    public function getTargetLanguages()
    {
        if (!$this->_targetLanguages) {
            $this->setSourceAndTargetLanguages();
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
        // query the Google API
        try {
            Craft::info(
                "Google API request: translate from {$sourceLanguage} to {$targetLanguage} (" .
                    strlen($input) .
                    ") {$input}",
                __METHOD__,
            );
            
            $targetLanguage = $this->findMatchingTargetLanguage($targetLanguage);
            $sourceLanguage = $this->findMatchingSourceLanguage($sourceLanguage);
            
            if (!$targetLanguage) {
                throw new AutoTranslatorException('Target language not supported by '.static::displayName());
            }
            
            $result = $this->getApiClient()->translate($input, [
                'target' => $targetLanguage,
                'source' => $sourceLanguage ?: null,
            ]);
        } catch (ServiceException $e) {
            throw new AutoTranslatorException(previous: $e);
        }
        
        // fallback to returning the input as-is
        if (!$result) {
            $result = ['text' => $input];
        }

        return html_entity_decode($result['text'], ENT_QUOTES);
    }
}
