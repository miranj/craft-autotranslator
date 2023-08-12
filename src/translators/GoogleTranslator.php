<?php

namespace miranj\autotranslator\translators;

use Craft;
use Google\Cloud\Core\Exception\ServiceException;
use Google\Cloud\Translate\TranslateClient;
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
    protected $_apiClient = null;
    
    public static function displayName(): string
    {
        return Craft::t('auto-translator', 'Google Translate');
    }
    
    public function getApiClient()
    {
        if (!$this->_apiClient) {
            $authKey = '';
            $this->_apiClient = new TranslateClient(['key' => $authKey]);
        }
        return $this->_apiClient;
    }
    
    public function translate(string $input, string $targetLanguage, string $sourceLanguage = ''): ?string
    {
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
