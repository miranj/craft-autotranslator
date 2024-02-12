<?php

namespace miranj\autotranslator\translators;

use craft\base\Field;
use miranj\autotranslator\exceptions\AutoTranslatorException;

interface FieldTranslatorInterface
{    
    // Returns a user friendly name of the service provider
    public static function displayName(): string;
    
    // Returns the field type classes that it can translate
    public static function getFieldTypes(): array;
    
    // Validates if it can translate a particular field or not
    public static function canTranslate(Field $field): bool;
    
    // Returns the translated value of a supported field
    public static function translate(Field $field, $sourceElement, $targetElementOwner, $sourceElementOwner);
}
