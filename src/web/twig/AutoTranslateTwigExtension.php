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
        $settings = Plugin::getInstance()->settings;
        
        if ($language === '') {
            $language = Craft::$app->language;
        }
        
        if ($from === '' && $settings->defaultTemplateLanguage !== '__auto__') {
            $from = $settings->defaultTemplateLanguage;
            if ($from === '__source__') {
                $sourceSite = Craft::$app->sites->getSiteByHandle($settings->sourceSiteHandle);
                $from = $sourceSite ? $sourceSite->language : '';
            }
        }
        
        $result = Plugin::getInstance()->translator->translate($string, $language, $from);
        return $result;
    }
}
