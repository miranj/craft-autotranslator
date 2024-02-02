<?php

namespace miranj\autotranslator\models;

use craft\base\Model;
use miranj\autotranslator\translators\TranslatorInterface;

/**
 * Auto Translator settings
 */
class Settings extends Model
{
    public string $sourceSiteHandle = '';
    public array $targetSiteHandles = [];
    public $translatorClass = null;
    
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            ['sourceSiteHandle', 'string'],
            ['targetSiteHandles', 'each', 'rule' => ['string']],
            ['targetSiteHandles', 'required', 'when' => function($model) {
                return !!trim($model->sourceSiteHandle);
            }],
        ]);
    }
    
    public function isTranslatorCompatible()
    {
        return $this->translatorClass !== null && in_array(
            TranslatorInterface::class,
            class_implements($this->translatorClass),
        );
    }
}
