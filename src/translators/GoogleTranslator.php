<?php

namespace miranj\autotranslator\translators;

use Craft;
use craft\helpers\App;
use Google\Cloud\Core\Exception\ServiceException;
use Google\Cloud\Translate\V2\TranslateClient;
use miranj\autotranslator\exceptions\AutoTranslatorException;

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
    
    function __construct(array $config = []) {
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
    
    public function translate(string $input, string $targetLanguage, string $sourceLanguage = ''): ?string
    {
        // convert into ISO-639 language codes
        // https://wikipedia.org/wiki/ISO_639
        $targetLanguage = explode('-', $targetLanguage, 2)[0];
        $sourceLanguage = explode('-', $sourceLanguage, 2)[0];
        
        // query the Google API
        try {
            Craft::info(
                "API request: translate from {$sourceLanguage} to {$targetLanguage} (" .
                    strlen($input) .
                    ") {$input}",
                __METHOD__,
            );
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
