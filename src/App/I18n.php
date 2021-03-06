<?php

namespace App;

use Exception;
use ipinfo\ipinfo\IPinfo;

class I18n
{
    const IPINFO_ACCESS_TOKEN = 'e6a52824c686d7';
    const MY_IP_ADDRESS = '207.237.223.159';
    private $supported_locales;

    public function __construct(array $supported_locales)
    {
        $this->supported_locales = $supported_locales;
    }

    public function getBestMatch(string $lang = null)
    {
        if ($lang === null) {
            return null;
        }
        $lang = \Locale::canonicalize($lang);
        if (in_array($lang, $this->supported_locales)) {
            return $lang;
        } else {
            foreach ($this->supported_locales as $supported_locale) {
                if (substr($supported_locale, 0, 2) == $lang) {
                    return $supported_locale;
                }
            }
        }
        return null;
    }

    private function getDefault()
    {
        return substr($this->supported_locales[0], 0, 2);
    }

    private function getAcceptedLocales()
    {
        if ($_SERVER['HTTP_ACCEPT_LANGUAGE'] == '') {
            return [];
        }
        $accepted_locales = [];
        $parts = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);

        foreach ($parts as $part) {
            $locale_and_pref = explode(';q=', $part);
            $locale = trim($locale_and_pref[0]);
            $pref = $locale_and_pref[1] ?? 1.0;

            $accepted_locales[$locale] = $pref;
        }
        asort($accepted_locales);
        return array_keys($accepted_locales);
    }

    private function getBestMatchFromHeader()
    {
        $accepted_locales = $this->getAcceptedLocales();

        array_walk($accepted_locales, function (&$locale) {
            $locale = \Locale::canonicalize($locale);
        });

        foreach ($accepted_locales as $locale) {

            if (in_array($locale, $this->supported_locales)) {
                return $locale;
            }
        }

        foreach ($accepted_locales as $locale) {
            $lang = substr($locale, 0, 2);
            foreach ($this->supported_locales as $supported_locale) {
                if (substr($supported_locale, 0, 2) == $lang) {
                    return $supported_locale;
                }
            }
        }
        return null;
    }

    public function getLocaleForRedirect()
    {
        $locale = $this->getBestMatchFromCookie();
        if ($locale !== null) {
            return $locale;
        }

        $locale = $this->getBestMatchFromHeader();
        if ($locale !== null) {
            return $locale;
        }
        $locale = $this->getBestMatchFromIPAddress();
        if ($locale !== null) {
            return $locale;
        }
        return $this->getDefault();
    }

    private function getBestMatchFromCookie()
    {
        if (isset($_COOKIE['locale'])) {
            return $this->getBestMatch($_COOKIE['locale']);
        }
        return null;
    }

    private function getBestMatchFromIPAddress()
    {
        try {
            /**
             * Get access token from ipinfo website
             */
            $client = new IPinfo(self::IPINFO_ACCESS_TOKEN);
            /**
             * Get Client IP Address with: $_SERVER['REMOTE_ADDR']
             */
            $details = $client->getDetails(self::MY_IP_ADDRESS);

            if (isset($details->country)) {
                return $this->getBestMatch($details->country);
            }
        } catch (Exception $ex) {
            echo $ex->getMessage();
        }
    }

    public function getLinkData(array $languages)
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $port = $_SERVER['SERVER_PORT'] == "80" ? '' : ":${$_SERVER['SERVER_PORT']}";
        $hostname_parts = \explode('.', $_SERVER['HTTP_HOST'], 2);
        $page = substr($_SERVER['REQUEST_URI'], 3);
        $url = $protocol . '://' . $_SERVER['HTTP_HOST'] . $port . '/%s' .  $page;

        $data = [];
        foreach ($languages as $code => $label) {
            $data[] = [
                'url' => sprintf($url, $code),
                'label' => $label
            ];
        }
        return $data;
    }
}