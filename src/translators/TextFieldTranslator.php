<?php

namespace miranj\autotranslator\translators;

use Craft;
use craft\base\Field;
use craft\fields\PlainText;
use craft\fields\BaseRelationField;
use craft\helpers\ArrayHelper;
use miranj\autotranslator\Plugin;

/**
* Base Field Translator
*/
class TextFieldTranslator implements FieldTranslatorInterface
{
    public const FIELD_TYPES = [
        PlainText::class,
    ];
    
    public static function displayName(): string
    {
        return Craft::t('auto-translator', 'Text Field Translator');
    }
    
    // Returns the field type classes that it can translate
    public static function getFieldTypes(): array
    {
        return static::FIELD_TYPES;
    }
    
    // Validates if it can translate a particular field or not
    public static function canTranslate(Field $field): bool
    {
        return $field &&
            !($field instanceof BaseRelationField) &&
            ArrayHelper::firstWhere(
                static::getFieldTypes(),
                fn($fieldTypeClass) => $field instanceof $fieldTypeClass
            );
    }
    
    // Returns the translated value of a supported field
    public static function translate(Field $field, $sourceElement, $targetElementOwner, $sourceElementOwner)
    {
        $value = $sourceElement->getFieldValue($field->handle);
        
        if (!static::canTranslate($field)) {
            return $value;
        }
        
        return Plugin::getInstance()->translator->translate(
            $value,
            $targetElementOwner->site->language,
            $sourceElementOwner->site->language,
        );
    }
}
