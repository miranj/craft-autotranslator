<?php

namespace miranj\autotranslator\services;

use Craft;
use craft\base\Element;
use craft\base\Field;
use craft\elements\Entry;
use craft\events\ElementEvent;
use craft\events\ModelEvent;
use craft\fields\BaseRelationField;
use craft\helpers\ElementHelper;
use craft\services\Elements;
use miranj\autotranslator\Plugin;
use yii\base\Component;
use yii\base\Event;
use yii\helpers\ArrayHelper;

/**
 * Site Sync service
 */
class SiteSync extends Component
{
    protected $settings;
    protected array $queuedSourceElements = [];
    
    public function init(): void
    {
        parent::init();
        $this->settings = Plugin::getInstance()->settings;
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
            Craft::debug("Auto-translation sync not configured", __METHOD__);
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
            [$this, 'syncElementTranslations'],
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
        if (!ElementHelper::isCanonical($element)) {
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
    
    public function syncElementTranslations(ElementEvent $event)
    {
        $element = $event->element;
        
        // ignore non-propagating elements
        // only act on queued source elements
        if (!$element->propagating || !$this->isQueued($element)) {
            return;
        }
        
        // ignore non-target site elements
        if (!in_array($element->site->handle, $this->settings->targetSiteHandles)) {
            Craft::debug("Ignoring non-target site: {$element->site->handle}", __METHOD__);
            return;
        }
        
        // proceed
        $sourceElement = $this->getQueued($element);
        Craft::info("Syncing ". get_class($element) .": $element from {$sourceElement->site->language} to {$element->site->language}", __METHOD__);
        $this->translateAttributes($sourceElement, $element);
        $this->translateFields($sourceElement, $element);
    }
    
    public function translateAttributes($sourceElement, $element)
    {
        # code...
    }
    
    public function translateFields($sourceElement, $element)
    {
    }
    
    public function translateField($field, $fieldValue, $sourceLanguage, $targetLanguage)
    {
        return Plugin::getInstance()->translator->translate(
            $fieldValue,
            $targetLanguage,
            $sourceLanguage,
        );
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
