<?php

namespace miranj\autotranslator\helpers;

use craft\helpers\ArrayHelper;
use craft\helpers\StringHelper;

/**
* Utility functions for working with language codes
*/
class LanguageHelper
{
    public static function prepLanguageList($supportedLanguages)
    {
        $supportedLanguages = array_map(fn($lang) => [
            'language' => $lang,
            'full' => StringHelper::slugify($lang),
            'code' => StringHelper::slugify(explode('-', $lang, 2)[0]),
        ], $supportedLanguages);
        
        return ArrayHelper::index($supportedLanguages, 'language');
    }
    
    public static function getBestMatchingLanguge(array $supportedLanguages, string $targetLanguage)
    {
        // sanity check
        if (!$targetLanguage) {
            return $targetLanguage;
        }
        
        // prep needles
        $targetLanguageCode = StringHelper::slugify(explode('-', $targetLanguage, 2)[0]);
        $targetLanguage = StringHelper::slugify($targetLanguage);
        
        // first look for full language match
        // eg: supported:en-us == target:en-us
        $match = ArrayHelper::firstWhere(
            $supportedLanguages,
            'full',
            $targetLanguage,
        );
        if ($match) {
            return $match['language'];
        }
        
        // otherwise look for languge code only match
        // supported:en == target:en-us
        $match = ArrayHelper::firstWhere(
            $supportedLanguages,
            'full',
            $targetLanguageCode,
        );
        if ($match) {
            return $match['language'];
        }
        
        // otherwise look for first languge code
        // supported:en-us == target:en
        $match = ArrayHelper::firstWhere(
            $supportedLanguages,
            'code',
            $targetLanguageCode,
        );
        if ($match) {
            return $match['language'];
        }
    }
}
