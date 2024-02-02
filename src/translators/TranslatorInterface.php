<?php

namespace miranj\autotranslator\translators;

use miranj\autotranslator\exceptions\AutoTranslatorException;

interface TranslatorInterface
{
    // Constructor takes an array of configuration options
    public function __construct(array $config = []);
    
    // Returns a user friendly name of the service provider
    public static function displayName(): string;
    
    /**
     * @throws AutoTranslatorException
     */
    public function translate(string $input, string $targetLanguage, string $sourceLanguage = ''): ?string;
}
