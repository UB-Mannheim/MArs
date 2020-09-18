<?php

// Copyright (C) 2020 Universitätsbibliothek Mannheim
// See file LICENSE for license details.

require_once( realpath('./lib/php-gettext/gettext.inc'));

    define('LOCALE_DIR', realpath('./locale'));

    if (!defined('DEFAULT_LOCALE')) {
        define('DEFAULT_LOCALE', 'de_DE');
    };

    $supported_locales  = array('de_DE', 'en_US');
    $encoding           = 'UTF-8';

if (isset($_REQUEST['L'])) {
    // user request language by URL parameter
    if ($_REQUEST['L'] === '0') {
        $locale = 'de_DE';
        $_SESSION[ 'language' ]  = 'de';
    } else if ($_REQUEST['L'] === '1') {
        $locale = 'en_US';
        $_SESSION[ 'language' ]  = 'en';
    } else {
        $locale = 'de_DE';
        $_SESSION[ 'language' ]  = 'de';
    }
} elseif (isset($_SESSION[ 'language' ])) {
    // Get language from session data.
    if ($_SESSION['language'] === 'de') {
        $locale = 'de_DE';
    } else if ($_SESSION['language'] === 'en') {
        $locale = 'en_US';
    }
} elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    // Get language from browser settings.
    $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
    $acceptLang = ['de', 'en'];
    $lang = in_array($lang, $acceptLang) ? $lang : 'de';

    if ($lang === 'de') {
        $locale = 'de_DE';
        $_SESSION[ 'language' ] = 'de';
    } else if ($lang === 'en') {
        $locale = 'en_US';
        $_SESSION[ 'language' ] = 'en';
    } else {
        $locale = 'de_DE';
        $_SESSION[ 'language' ] = 'de';
    }
} else {
    $locale = 'de_DE';
    $_SESSION[ 'language' ]  = 'de';
}

putenv("LANG=$locale");
putenv("LANGUAGE=$locale");
_setlocale(LC_MESSAGES, $locale);
// Set the text domain as 'messages'
$domain = 'MArs';

_bindtextdomain($domain, LOCALE_DIR);
_bind_textdomain_codeset($domain, $encoding);
_textdomain($domain);

// look for translation now in ./locale/de_DE/LC_MESSAGES/a.mo
