<?php

function translation_filter($context, $translationId, $params = [])
{
    /** @var i18n $i18n */
    $i18n = $context[TemplateVars::I18N];

    if (array_key_exists('default', $params)) {
        $default = $params['default'];
        unset($params['default']);
    } else {
        $default = '';
    }

    if (array_key_exists('id', $params))
        unset($params['id']);

    $translated_string = $i18n->get($translationId, $default, $params, $i18n->get_lang());

    return $translated_string;
}

function translated_duration_filter($context, $dateTimeString, $full = false)
{
    /** @var i18n $i18n */
    $i18n = $context[TemplateVars::I18N];

    return duration_string($i18n, $dateTimeString, $full);
}