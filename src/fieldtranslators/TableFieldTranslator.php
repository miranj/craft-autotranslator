<?php

namespace miranj\autotranslator\fieldtranslators;

use Craft;
use craft\base\Field;
use craft\fields\Table;
use Illuminate\Support\Collection;
use miranj\autotranslator\Plugin;

/**
* Table Field Translator
*/
class TableFieldTranslator extends TextFieldTranslator
{
    public const FIELD_TYPES = [
        Table::class,
    ];
    public const COLUMN_TYPES = [
        'heading',
        'multiline',
        'singleline',
    ];
    
    public static function displayName(): string
    {
        return Craft::t('auto-translator', 'Table Field Translator');
    }
    
    // Returns the translated value of a supported field
    public static function translate(Field $field, $sourceElement, $targetElementOwner, $sourceElementOwner)
    {
        // sanity check
        $value = $sourceElement->getFieldValue($field->handle);
        if (!static::canTranslate($field)) {
            return $value;
        }
        
        // figure out all text columns
        $translatableColumns = Collection::make($field->columns)
            ->filter(fn($column) => in_array($column['type'], static::COLUMN_TYPES))
            ->keys()
            ->all();
        
        // copy and translate all data
        $translatedData = [];
        foreach ($field->serializeValue($value, $sourceElement) as $row) {
            $newRow = [];
            foreach ($row as $column => $data) {
                $newRow[$column] = in_array($column, $translatableColumns)
                    ? Plugin::getInstance()->translator->translate(
                        $data,
                        $targetElementOwner->site->language,
                        $sourceElementOwner->site->language,
                    )
                    : $data;
            }
            $translatedData[] = $newRow;
        }
        
        return $translatedData;
    }
}
