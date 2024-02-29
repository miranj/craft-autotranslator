<?php

namespace miranj\autotranslator\fieldtranslators;

use Craft;
use craft\base\Field;
use craft\elements\MatrixBlock;
use craft\fields\Matrix;
use craft\helpers\ArrayHelper;
use Illuminate\Support\Collection;
use miranj\autotranslator\Plugin;

/**
* Matrix Field Translator
*/
class MatrixFieldTranslator extends TextFieldTranslator
{
    public const FIELD_TYPES = [
        Matrix::class,
    ];
    public static $forceMatrixFieldSync = true;
    
    public static function displayName(): string
    {
        return Craft::t('auto-translator', 'Matrix Field Translator');
    }
    
    // Returns the translated value of a supported field
    public static function translate(Field $field, $sourceElement, $targetElementOwner, $sourceElementOwner)
    {
        // sanity check
        $value = $sourceElement->getFieldValue($field->handle);
        if (!static::canTranslate($field)) {
            return $value;
        }
        
        // set parent field to be dirty
        // (this will force all Matrix blocks in this field to be re-saved
        // in the afterElementPropagate() method and the existing element sync
        // workflow will ensure translateable fields get synced automatically,
        // no extra steps needed because each Matrix block is an element)
        if (static::$forceMatrixFieldSync && !$sourceElement->isFieldDirty($field->handle)) {
            Craft::debug("Marking Matrix field as dirty: $field->handle", __METHOD__);
            $sourceElement->setDirtyFields([$field->handle]);
        }
        
        if ($sourceElement->isFieldDirty($field->handle)) {
            Craft::debug("Deferring dirty Matrix field translation as an element: $field->handle", __METHOD__);
            return [];
        }
        
        // otherwise, proceed with custom translation
        Craft::debug("Translating Matrix field: $field->handle", __METHOD__);
        
        // translate all matrix blocks
        $translatedData = static::translateBlocks(
            $value instanceof Collection
                ? $value->all()
                : ($value->getCachedResult() ?? (clone $value)->status(null)->all()),
            $field->serializeValue($value, $sourceElement),
            $sourceElement,
            $targetElementOwner,
            $sourceElementOwner,
        );
        
        return $translatedData;
    }
    
    protected static function translateBlocks(
        $blocks,
        $serializedBlocks,
        $sourceElement,
        $targetElementOwner,
        $sourceElementOwner
    ): array
    {
        // existing blocks
        $translatedBlocks = $serializedBlocks;
        
        foreach ($blocks as $block) {

            // treat MatrixBlocks as elements & attempt translating all fields
            if ($block instanceof MatrixBlock) {
                $newFieldValues = Plugin::getInstance()->siteSync->translateFields(
                    $block,
                    clone $block,
                    $sourceElementOwner,
                    $targetElementOwner,
                );
                $translatedBlocks[$block->id]['fields'] = ArrayHelper::merge(
                    $translatedBlocks[$block->id]['fields'],
                    $newFieldValues,
                );
                $translatedBlocks[$block->id]['dirty'] = true;
            }
        }
                
        return $translatedBlocks;
    }
}
