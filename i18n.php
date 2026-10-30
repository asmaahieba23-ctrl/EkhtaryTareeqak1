<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$requestedLanguage = $_GET['lang'] ?? null;
if (is_string($requestedLanguage) && in_array($requestedLanguage, ['ar', 'en'], true)) {
    $_SESSION['language'] = $requestedLanguage;
}

$language = $_SESSION['language'] ?? 'ar';
if (!in_array($language, ['ar', 'en'], true)) {
    $language = 'ar';
    $_SESSION['language'] = $language;
}

function tr(string $arabic, string $english): string
{
    global $language;
    return $language === 'en' ? $english : $arabic;
}

function language_url(string $targetLanguage): string
{
    $query = [];
    if (!empty($_SERVER['QUERY_STRING'])) {
        parse_str($_SERVER['QUERY_STRING'], $query);
    }

    $query['lang'] = $targetLanguage;
    return $_SERVER['SCRIPT_NAME'] . '?' . http_build_query($query);
}

function page_language(): string
{
    global $language;
    return $language;
}

function page_direction(): string
{
    return page_language() === 'ar' ? 'rtl' : 'ltr';
}
