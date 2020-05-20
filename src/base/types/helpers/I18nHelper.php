<?php

namespace PSFS\base\types\helpers;

use PSFS\base\Cache;
use PSFS\base\config\Config;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\Router;
use PSFS\base\Security;

/**
 * Class I18nHelper
 * @package PSFS\base\types\helpers
 */
class I18nHelper
{
    const PSFS_SESSION_LANGUAGE_KEY = '__PSFS_SESSION_LANG_SELECTED__';

    static $langs = ['es_ES', 'en_GB', 'fr_FR', 'pt_PT', 'de_DE'];

    /**
     * @param string $default
     * @return string
     */
    public static function extractLocale($default = null)
    {
        $locale = Request::header('X-API-LANG', $default);
        if (empty($locale)) {
            $locale = Security::getInstance()->getSessionKey(self::PSFS_SESSION_LANGUAGE_KEY);
            if (empty($locale) && array_key_exists('HTTP_ACCEPT_LANGUAGE', $_SERVER)) {
                $browserLocales = explode(",", str_replace("-", "_", $_SERVER["HTTP_ACCEPT_LANGUAGE"])); // brosers use en-US, Linux uses en_US
                for ($i = 0, $ct = count($browserLocales); $i < $ct; $i++) {
                    list($browserLocales[$i]) = explode(";", $browserLocales[$i]); //trick for "en;q=0.8"
                }
                $locale = array_shift($browserLocales);
            }
        }
        $locale = strtolower($locale);
        if (false !== strpos($locale, '_')) {
            $locale = explode('_', $locale);
            $locale = $locale[0];
        }
        // TODO check more en locales
        if (strtolower($locale) === 'en') {
            $locale = 'en_GB';
        } else {
            $locale = $locale . '_' . strtoupper($locale);
        }
        $defaultLocales = explode(',', Config::getParam('i18n.locales', ''));
        if (!in_array($locale, array_merge($defaultLocales, self::$langs))) {
            $locale = Config::getParam('default.language', $default);
        }
        return $locale;
    }

    /**
     * @param string $absoluteFileName
     * @return array
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function generateTranslationsFile($absoluteFileName)
    {
        $translations = array();
        if (file_exists($absoluteFileName)) {
            @include($absoluteFileName);
        } else {
            Cache::getInstance()->storeData($absoluteFileName, "<?php \$translations = array();\n", Cache::TEXT, TRUE);
        }

        return $translations;
    }

    /**
     * Method to set the locale
     * @param string $default
     * @throws \Exception
     */
    public static function setLocale($default = null)
    {
        $locale = self::extractLocale($default);
        Inspector::stats('[i18NHelper] Set locale to project [' . $locale . ']', Inspector::SCOPE_DEBUG);
        // Load translations
        putenv("LC_ALL=" . $locale);
        setlocale(LC_ALL, $locale);
        // Load the locale path
        $localePath = BASE_DIR . DIRECTORY_SEPARATOR . 'locale';
        Logger::log('Set locale dir ' . $localePath);
        GeneratorHelper::createDir($localePath);
        bindtextdomain('translations', $localePath);
        textdomain('translations');
        bind_textdomain_codeset('translations', 'UTF-8');
        Security::getInstance()->setSessionKey(I18nHelper::PSFS_SESSION_LANGUAGE_KEY, substr($locale, 0, 2));
    }

    /**
     * @param $data
     * @return string
     */
    public static function utf8Encode($data)
    {
        if (is_array($data)) {
            foreach ($data as &$field) {
                $field = self::utf8Encode($field);
            }
        } elseif (is_object($data)) {
            $properties = get_class_vars($data);
            if(is_array($properties)) {
                foreach (array_keys($properties) as $property) {
                    $data->$property = self::utf8Encode($data->$property);
                }
            }
        } elseif (is_string($data)) {
            $data = utf8_encode($data);
        }
        return $data;
    }

    /**
     * @param string $namespace
     * @return bool
     */
    public static function checkI18Class($namespace)
    {
        $isI18n = false;
        if (preg_match('/I18n$/i', $namespace)) {
            $parentClass = preg_replace('/I18n$/i', '', $namespace);
            if (Router::exists($parentClass)) {
                $isI18n = true;
            }
        }
        return $isI18n;
    }

    /**
     * @param $string
     * @return string
     */
    public static function sanitize($string)
    {
        $from = [
            ['á', 'à', 'ä', 'â', 'ª', 'Á', 'À', 'Â', 'Ä'],
            ['é', 'è', 'ë', 'ê', 'É', 'È', 'Ê', 'Ë'],
            ['í', 'ì', 'ï', 'î', 'Í', 'Ì', 'Ï', 'Î'],
            ['ó', 'ò', 'ö', 'ô', 'Ó', 'Ò', 'Ö', 'Ô'],
            ['ú', 'ù', 'ü', 'û', 'Ú', 'Ù', 'Û', 'Ü'],
            ['ñ', 'Ñ', 'ç', 'Ç'],
        ];
        $to = [
            ['a', 'a', 'a', 'a', 'a', 'A', 'A', 'A', 'A'],
            ['e', 'e', 'e', 'e', 'E', 'E', 'E', 'E'],
            ['i', 'i', 'i', 'i', 'I', 'I', 'I', 'I'],
            ['o', 'o', 'o', 'o', 'O', 'O', 'O', 'O'],
            ['u', 'u', 'u', 'u', 'U', 'U', 'U', 'U'],
            ['n', 'N', 'c', 'C',],
        ];

        $text = filter_var($string, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
        for ($i = 0, $total = count($from); $i < $total; $i++) {
            $text = str_replace($from[$i], $to[$i], $text);
        }

        return $text;
    }

    /**
     * Método que revisa las traducciones directorio a directorio
     * @param string $path
     * @param string $locale
     * @return array
     * @throws \PSFS\base\exception\GeneratorException
     */
    public static function findTranslations($path, $locale)
    {
        $localePath = realpath(BASE_DIR . DIRECTORY_SEPARATOR . 'locale');
        $localePath .= DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . 'LC_MESSAGES' . DIRECTORY_SEPARATOR;

        $translations = array();
        if (file_exists($path)) {
            $directory = dir($path);
            while (false !== ($fileName = $directory->read())) {
                GeneratorHelper::createDir($localePath);
                if (!file_exists($localePath . 'translations.po')) {
                    file_put_contents($localePath . 'translations.po', '');
                }
                $inspectPath = realpath($path . DIRECTORY_SEPARATOR . $fileName);
                $cmdPhp = "export PATH=\$PATH:/opt/local/bin; xgettext " .
                    $inspectPath . DIRECTORY_SEPARATOR .
                    "*.php --from-code=UTF-8 -j -L PHP --debug --force-po -o {$localePath}translations.po";
                if (is_dir($path . DIRECTORY_SEPARATOR . $fileName) && preg_match('/^\./', $fileName) == 0) {
                    $res = t('Revisando directorio: ') . $inspectPath;
                    $res .= t('Comando ejecutado: ') . $cmdPhp;
                    $res .= shell_exec($cmdPhp);
                    usleep(10);
                    $translations[] = $res;
                    $translations = array_merge($translations, self::findTranslations($inspectPath, $locale));
                }
            }
        }
        return $translations;
    }
}
