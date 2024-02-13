<?php

namespace miranj\autotranslator\fieldtranslators;

use Craft;
use craft\base\Field;
use miranj\autotranslator\Plugin;
use verbb\hyper\base\Link;
use verbb\hyper\fields\HyperField;

/**
* Hyper field translator
* https://github.com/verbb/hyper
*/
class HyperFieldTranslator extends TextFieldTranslator
{
    public const FIELD_TYPES = [
        HyperField::class,
    ];
    public const TEXT_FIELDS = [
        'ariaLabel',
        'linkText',
        'linkTitle',
    ];
    
    public static function displayName(): string
    {
        return Craft::t('auto-translator', 'Hyper Field Translator');
    }
    
    // Returns the translated value of a supported field
    public static function translate(Field $field, $sourceElement, $targetElementOwner, $sourceElementOwner)
    {
        // sanity check
        $value = $sourceElement->getFieldValue($field->handle);
        if (!static::canTranslate($field)) {
            return $value;
        }
        
        Craft::debug("Translating Hyper field: $field->handle", __METHOD__);
        
        $translatedLinks = [];
        foreach ($value as $link) {
            $newLink = $link->getSerializedValues();
            
            // translate all text fields
            foreach (static::TEXT_FIELDS as $linkField) {
                if (isset($newLink[$linkField])) {
                    $newLink[$linkField] = Plugin::getInstance()->translator->translate(
                        $newLink[$linkField],
                        $targetElementOwner->site->language,
                        $sourceElementOwner->site->language,
                    );
                }
                
            }
            
            // treat Link nodes as elements & attempt translating all fields
            if ($link instanceof Link && isset($newLink['fields'])) {
                $newLinkFieldValues = Plugin::getInstance()->siteSync->translateFields(
                    $link,
                    clone $link,
                    $sourceElementOwner,
                    $targetElementOwner,
                );
                foreach ($newLinkFieldValues as $fieldHandle => $fieldValue) {
                    if (isset($newLink['fields'][$fieldHandle])) {
                        $newLink['fields'][$fieldHandle] = $fieldValue;
                    }
                }
            }
            
            // ensure correct site ID is used
            $newLink['linkSiteId'] = $targetElementOwner->site->id;
            
            $translatedLinks[] = $newLink;
        }
                
        return $translatedLinks;
    }
}
