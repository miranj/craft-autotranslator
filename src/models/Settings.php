<?php

namespace miranj\autotranslator\models;

use craft\base\Model;
use miranj\autotranslator\translationproviders\TranslatorInterface;

/**
 * Auto Translator settings
 */
class Settings extends Model
{
    public string $sourceSiteHandle = '';
    public array $targetSiteHandles = [];
    public $translatorClass = null;
    public string $translatorAuthKey = '';
    public bool $cacheEnabled = true;
    public bool $preferStaticTranslations = true;
    
    /**
     * Used by the Twig filter when no source language
     * is explicitly specified using the `from` param
     *
     * @values  __auto__ | __source__ | <locale-id>
     */
    public string $defaultTemplateLanguage = '__auto__';
    
    protected function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            ['cacheEnabled', 'boolean'],
            ['cacheEnabled', 'required'],
            ['sourceSiteHandle', 'string'],
            ['targetSiteHandles', 'each', 'rule' => ['string']],
            ['targetSiteHandles', 'required', 'when' => function($model) {
                return !!trim($model->sourceSiteHandle);
            }],
            ['translatorAuthKey', 'string'],
            ['translatorAuthKey', 'required', 'when' => function($model) {
                return $model->isTranslatorCompatible();
            }],
        ]);
    }
    
    public function isTranslatorCompatible()
    {
        return $this->translatorClass !== null &&
            class_exists($this->translatorClass) &&
            in_array(
                TranslatorInterface::class,
                class_implements($this->translatorClass),
            );
    }
}
