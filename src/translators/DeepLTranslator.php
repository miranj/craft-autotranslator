<?php

namespace miranj\autotranslator\translators;

use Craft;
use craft\helpers\App;
use DeepL\Translator as Translator;
use DeepL\DeepLException;
use miranj\autotranslator\exceptions\AutoTranslatorException;

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
    
    function __construct(array $config = []) {
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
    
    public function translate(string $input, string $targetLanguage, string $sourceLanguage = ''): ?string
    {
        // query the DeepL API
        try {
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
