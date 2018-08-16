<?php

namespace Tuleap\Git\GitPHP;

/**
 * GitPHP Resource
 *
 * Resource factory
 *
 * @author Christopher Han <xiphux@gmail.com>
 * @copyright Copyright (c) 2010 Christopher Han
 * @package GitPHP
 */

/**
 * Resource factory class
 *
 * @package GitPHP
 */
class Resource
{

    /**
     * instance
     *
     * Stores the singleton instance of the resource provider
     *
     * @access protected
     * @static
     */
    protected static $instance = null;

    /**
     * currentLocale
     *
     * Stores the currently instantiated locale identifier
     *
     * @access protected
     * @static
     */
    protected static $currentLocale = '';

    /**
     * GetInstance
     *
     * Returns the singleton instance
     *
     * @access public
     * @static
     * @return mixed instance of resource class
     */
    public static function GetInstance() // @codingStandardsIgnoreLine
    {
        return self::$instance;
    }

    /**
     * Instantiated
     *
     * Tests if the resource provider has been instantiated
     *
     * @access public
     * @static
     * @return boolean true if resource provider is instantiated
     */
    public static function Instantiated() // @codingStandardsIgnoreLine
    {
        return (self::$instance !== null);
    }

    /**
     * Instantiate
     *
     * Instantiates the singleton instance
     *
     * @access public
     * @static
     * @param string $locale locale to instantiate
     * @return boolean true if resource provider was instantiated successfully
     */
    public static function Instantiate($locale) // @codingStandardsIgnoreLine
    {
        self::$instance = null;
        self::$currentLocale = '';

        $reader = null;
        if (!(($locale == 'en_US') || ($locale == 'en'))) {
            $reader = new \FileReader(__DIR__ . '/../../site-content/gitphp_locale/' . $locale . '/gitphp.mo');
            if (!$reader) {
                return false;
            }
        }

        self::$instance = new \gettext_reader($reader);
        self::$currentLocale = $locale;
        return true;
    }
}


/**
 * Gettext wrapper function for readability, single string
 *
 * @param string $str string to translate
 * @return string translated string
 */
function __($str)
{
    if (Resource::Instantiated()) {
        return Resource::GetInstance()->translate($str);
    }
    return $str;
}

/**
 * Gettext wrapper function for readability, plural form
 *
 * @param string $singular singular form of string
 * @param string $plural plural form of string
 * @param int $count number of items
 * @return string translated string
 */
function __n($singular, $plural, $count)
{
    if (Resource::Instantiated()) {
        return Resource::GetInstance()->ngettext($singular, $plural, $count);
    }
    if ($count > 1) {
        return $plural;
    }
    return $singular;
}
