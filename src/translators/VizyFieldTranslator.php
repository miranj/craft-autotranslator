<?php

namespace miranj\autotranslator\translators;

use Craft;
use craft\base\Field;
use Illuminate\Support\Collection;
use miranj\autotranslator\Plugin;
use verbb\vizy\fields\VizyField;
use verbb\vizy\models\NodeCollection;
use verbb\vizy\nodes as VizyNodes;

/**
* Base Field Translator
*/
class VizyFieldTranslator extends TextFieldTranslator
{
    public const FIELD_TYPES = [
        VizyField::class,
    ];
    
    // node types that should be translated
    public const VIZY_NODES = [
        VizyNodes\Blockquote::class,
        VizyNodes\BulletList::class,
        VizyNodes\Heading::class,
        VizyNodes\Image::class,
        VizyNodes\ListItem::class,
        VizyNodes\OrderedList::class,
        VizyNodes\Paragraph::class,
        VizyNodes\TableCell::class,
        VizyNodes\Text::class,
    ];
    
    protected static array $translatableNodes = [];
    
    public static function displayName(): string
    {
        return Craft::t('auto-translator', 'Vizy Field Translator');
    }
    
    // Returns the translated value of a supported field
    public static function translate(Field $field, $sourceElement, $targetElementOwner, $sourceElementOwner)
    {
        // sanity check
        $value = $sourceElement->getFieldValue($field->handle);
        if (!static::canTranslate($field)) {
            return $value;
        }
        
        if (empty(static::$translatableNodes)) {
            static::$translatableNodes = Collection::make(static::VIZY_NODES)
                ->map(fn($node) => $node::$type)
                ->all();
        }
        
        Craft::debug("Translating Vizy field: $field->handle", __METHOD__);
        
        // recursively translate all vizy nodes
        $translatedData = static::translateNodes(
            $value->getNodes(),
            $sourceElement,
            $targetElementOwner,
            $sourceElementOwner,
        );
                
        return $translatedData;
    }
    
    protected static function translateNodes(array $nodes, $sourceElement, $targetElementOwner, $sourceElementOwner): array
    {
        $translatedBlocks = [];
        foreach ($nodes as $node) {
            $newBlock = $node->rawNode;
            
            // translate text content
            if ($node->type === 'text' && isset($newBlock['text'])) {
                $newBlock['text'] = Plugin::getInstance()->translator->translate(
                    $newBlock['text'],
                    $targetElementOwner->site->language,
                    $sourceElementOwner->site->language,
                );
            }

            // treat VizyBlock nodes as elements & attempt translating all fields
            if ($node instanceof VizyNodes\VizyBlock) {
                $newFieldValues = Plugin::getInstance()->siteSync->translateFields(
                    $node,
                    clone $node,
                    $sourceElementOwner,
                    $targetElementOwner,
                );
                foreach ($newFieldValues as $fieldHandle => $fieldValue) {
                    if (isset($newBlock['attrs']['values']['content']['fields'][$fieldHandle])) {
                        $newBlock['attrs']['values']['content']['fields'][$fieldHandle] = $fieldValue;
                    }
                }
            }
            
            // recursively translate any nested nodes
            if (
                in_array($node->type, static::$translatableNodes) &&
                !empty($node->content)
            ) {
                $newBlock['content'] = static::translateNodes(
                    $node->content,
                    $sourceElement,
                    $targetElementOwner,
                    $sourceElementOwner,
                );
            }
            
            $translatedBlocks[] = $newBlock;
        }
        
        return $translatedBlocks;
    }
}
