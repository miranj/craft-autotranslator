<?php

namespace miranj\autotranslator\translators;

use miranj\autotranslator\exceptions\AutoTranslatorException;

interface TranslatorInterface
{
    public static function displayName(): string;
    
    /**
     * @throws AutoTranslatorException
     */
    public function translate(string $input, string $targetLanguage, string $sourceLanguage = ''): ?string;
}
