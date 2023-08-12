<?php

namespace miranj\autotranslator\translators;

use Craft;
use DeepL\Translator as Translator;

/**
* DeepL
* 
* https://www.deepl.com/docs-api/translate-text
* https://github.com/DeepLcom/deepl-php
*/
class DeepLTranslator implements TranslatorInterface
{
    protected $_apiClient = null;
    
    public static function displayName(): string
    {
        return Craft::t('auto-translator', 'DeepL');
    }
    
    public function getApiClient()
    {
        if (!$this->_apiClient) {
            $authKey = '';
            $this->_apiClient = new Translator($authKey);
        }
        return $this->_apiClient;
    }
    
    public function translate(string $input, string $targetLanguage, string $sourceLanguage = ''): ?string
    {
        // query the DeepL API
        Craft::info(
            "API request: translate from {$sourceLanguage} to {$targetLanguage} (" .
                strlen($input) .
                ") {$input}",
            __METHOD__,
        );
        $result = $this->getApiClient()->translateText(
            $input,
            $sourceLanguage ?: null,
            $targetLanguage,
        );
        
        // fallback to returning the input as-is
        if (!$result) {
            $result = ['text' => $input];
        }

        return html_entity_decode($result['text'], ENT_QUOTES);
    }
}
