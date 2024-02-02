<?php

namespace miranj\autotranslator\web\twig;

use Craft;
use miranj\autotranslator\Plugin;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;


/**
 * Twig extension
 */
class AutoTranslateTwigExtension extends AbstractExtension
{
    public function getName()
    {
        return 'AutoTranslate';
    }
    
    public function getFilters()
    {
        return [
            new TwigFilter('autotranslate', [$this, 'autotranslate']),
            new TwigFilter('at', [$this, 'autotranslate']),
        ];
    }

    public function autotranslate($string, $language = '', $from = '')
    {
        if ($language === '') {
            $language = Craft::$app->language;
        }
        $result = Plugin::getInstance()->translator->translate($string, $language, $from);
        return $result;
    }
}
