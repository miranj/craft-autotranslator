<?php

namespace miranj\autotranslator\services;

use Craft;
use craft\base\Element;
use craft\base\Field;
use craft\elements\Entry;
use craft\events\ElementEvent;
use craft\events\ModelEvent;
use craft\fields\BaseRelationField;
use craft\helpers\ArrayHelper;
use craft\helpers\ElementHelper;
use craft\services\Elements;
use Illuminate\Support\Collection;
use miranj\autotranslator\Plugin;
use yii\base\Component;
use yii\base\Event;

/**
 * Site Sync service
 */
class SiteSync extends Component
{
    protected $settings;
    protected array $queuedSourceElements = [];
    protected Collection $fieldTranslators;
    
    public const DEFAULT_ELEMENTS = [
        \craft\elements\Entry::class,
        \craft\elements\MatrixBlock::class,
        \craft\elements\Category::class,
        \craft\elements\Asset::class,
        \verbb\navigation\elements\Node::class,
    ];
    
    public function init(): void
    {
        parent::init();
        $this->settings = Plugin::getInstance()->settings;
        $this->fieldTranslators = Collection::make(Plugin::DEFAULT_FIELD_TRANSLATORS)->reverse();
    }
    
    public function isActive(): bool
    {
        return
            Craft::$app->getIsMultiSite()
            && $this->settings->sourceSiteHandle
            && !empty($this->settings->targetSiteHandles)
            && Plugin::getInstance()->translator->isActive();
    }
    
    public function attachEventHandlers()
    {
        // sanity check
        if (!$this->isActive()) {
            Craft::warning("Auto-translation sync not configured", __METHOD__);
            return;
        }
        
        // Track elements to be synced during propagation
        Event::on(
            Elements::class,
            Elements::EVENT_BEFORE_SAVE_ELEMENT,
            [$this, 'queueSyncDuringPropagate'],
        );
        
        // Translate tracked elements during propagation
        Event::on(
            Elements::class,
            Elements::EVENT_BEFORE_SAVE_ELEMENT,
            [$this, 'syncElementIfQueued'],
        );
        
        // Un-track synced elements after propagation
        Event::on(
            Element::class,
            Element::EVENT_AFTER_PROPAGATE,
            [$this, 'unqueueSyncDuringPropagate'],
        );
    }
    
    public function queueSyncDuringPropagate(ElementEvent $event)
    {
        $element = $event->element;
        
        // ignore unsupported elements
        if (!ArrayHelper::firstWhere(
            static::DEFAULT_ELEMENTS,
            fn($elementClass) => $element instanceof $elementClass
        )) {
            return;
        }
        
        // ignore non-localized elements
        // ignore propagating elements
        // ignore queued elements
        // ignore elements without content 
        if (
            !$element::isLocalized() ||
            !$element::hasContent() ||
            $element->propagating ||
            $this->isQueued($element)
        ) {
            return;
        }
        
        // ignore drafts, revisions, provisional drafts, etc
        if (
            !ElementHelper::isCanonical($element) ||
            ElementHelper::isDraftOrRevision($element) ||
            $element->isRevision
        ) {
            Craft::debug("Ignore non-canonical element: $element", __METHOD__);
            return;
        }
        
        // ignore non-source site elements
        if ($element->site->handle !== $this->settings->sourceSiteHandle) {
            Craft::debug("Ignoring non-source site: {$element->site->handle}", __METHOD__);
            return;
        }
        
        // translate propagated versions of this element
        $this->queueElement($element);
    }
    
    public function unqueueSyncDuringPropagate(ModelEvent $event)
    {
        $this->unqueueElement($event->sender);
    }
    
    public function syncElementIfQueued(ElementEvent $event)
    {
        $element = $event->element;
        
        // only act on queued source elements
        if (!$this->isQueued($element)) {
            return;
        }
        
        // ignore non-target site elements
        if (!in_array($element->site->handle, $this->settings->targetSiteHandles)) {
            Craft::debug("Ignoring non-target site: {$element->site->handle}", __METHOD__);
            return;
        }
        
        // proceed
        $sourceElement = $this->getQueued($element);
        $this->syncElementTranslations($sourceElement, $element);
    }
    
    public function syncElementTranslations($sourceElement, $targetElement)
    {
        Craft::info("Syncing ". get_class($targetElement) .": $targetElement from {$sourceElement->site->language} to {$targetElement->site->language}", __METHOD__);
        
        $this->translateAttributes($sourceElement, $targetElement);
        $translatedValues = $this->translateFields($sourceElement, $targetElement);
        $targetElement->setFieldValues($translatedValues);
    }
    
    public function translateAttributes($sourceElement, $element)
    {
        // translate the title
        if (
            $element::hasTitles() &&
            $sourceElement->getTitleTranslationKey() !== $element->getTitleTranslationKey()
        ) {
            Craft::debug("Translating native title field", __METHOD__);
            $element->title = Plugin::getInstance()->translator->translate(
                $sourceElement->title,
                $element->site->language,
                $sourceElement->site->language,
            );
        }
        
        // translate the slug
        if (
            $sourceElement->slug &&
            $sourceElement->getSlugTranslationKey() !== $element->getSlugTranslationKey()
        ) {
            Craft::debug("Translating native slug field", __METHOD__);
            
            // de-slugify, translate, re-slugify
            $sourceValue = str_replace(
                Craft::$app->getConfig()->getGeneral()->slugWordSeparator,
                ' ',
                $sourceElement->slug,
            );
            $newValue = Plugin::getInstance()->translator->translate(
                $sourceValue,
                $element->site->language,
                $sourceElement->site->language,
            );
            $newValue = ElementHelper::generateSlug(
                $newValue,
                Craft::$app->getConfig()->getGeneral()->limitAutoSlugsToAscii,
                $element->site->language,
            );
            
            // update if needed, force URI refresh
            if ($element->slug !== $newValue) {
                $element->slug = $newValue;
                if ($element::hasUris()) {
                    Craft::debug("Updating element uri", __METHOD__);
                    Craft::$app->elements->setElementUri($element);
                }
            }
        }
    }
    
    public function translateFields($sourceElement, $element, $sourceElementOwner = null, $elementOwner = null)
    {
        // defaults
        $sourceElementOwner = $sourceElementOwner ?: $sourceElement;
        $elementOwner = $elementOwner ?: $element;
        
        $translatedFields = [];
        
        // remove relation fields
        // remove fields already synced by Craft
        $fields = Collection::make($element->getFieldLayout()->getCustomFields())
            ->reject(fn($field) => $field instanceof BaseRelationField)
            ->filter(fn($field) => $field->getTranslationKey($sourceElementOwner) !== $field->getTranslationKey($elementOwner));
        
        // locate translators for each field
        $fieldsWithTranslators = $fields
            ->map(fn($field) => [
                $field,
                $this->fieldTranslators->first(
                    fn($fieldTranslator) => $fieldTranslator::canTranslate($field)
                ),
            ])
            ->where('1', '!=', []);

        foreach ($fieldsWithTranslators->all() as list($field, $translator)) {
            // translate value
            $newValue = $translator::translate(
                $field,
                $sourceElement,
                $elementOwner,
                $sourceElementOwner,
            );
            
            // queue for saving
            $translatedFields[$field->handle] = $newValue;
        }
        
        return $translatedFields;
    }
    
    protected function isQueued($element): bool
    {
        return isset($this->queuedSourceElements[$element->uid]);
    }
    
    protected function getQueued($element)
    {
        return $this->queuedSourceElements[$element->uid] ?? null;
    }
    
    protected function queueElement($element)
    {
        Craft::debug("--> Queuing element for translations: $element", __METHOD__);
        $this->queuedSourceElements[$element->uid] = $element;
    }
    
    protected function unqueueElement($element)
    {
        if ($this->isQueued($element)) {
            Craft::debug("<-- Unqueuing element for translations: $element", __METHOD__);
            unset($this->queuedSourceElements[$element->uid]);
        }
    }
}
