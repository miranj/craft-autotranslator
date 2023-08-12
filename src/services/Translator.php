<?php

namespace miranj\autotranslator\services;

use Craft;
use miranj\autotranslator\Plugin;
use miranj\autotranslator\exceptions\AutoTranslatorException;
use miranj\autotranslator\translators\TranslatorInterface;
use yii\base\Component;

/**
 * Translator service
 */
class Translator extends Component
{
    protected $_translator = null;
    
    public function getTranslator()
    {
        if (!$this->_translator) {
            $translatorClass = Plugin::getInstance()->settings->translatorClass;
            $translatorIsCompatible = in_array(
                TranslatorInterface::class,
                class_implements($translatorClass),
            );
            if ($translatorClass && $translatorIsCompatible) {
                $this->_translator = new $translatorClass();
            }
        }
        return $this->_translator;
    }
}
