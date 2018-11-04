<?php
namespace PSFS\base\config;

use PSFS\base\exception\ConfigException;
use PSFS\base\Logger;
use PSFS\base\Request;
use PSFS\base\types\traits\SingletonTrait;
use PSFS\base\types\traits\TestTrait;

/**
 * Class Config
 * @package PSFS\base\config
 */
class Config
{
    use SingletonTrait;
    use TestTrait;

    const DEFAULT_LANGUAGE = 'es';
    const DEFAULT_ENCODE = 'UTF-8';
    const DEFAULT_CTYPE = 'text/html';
    const DEFAULT_DATETIMEZONE = 'Europe/Madrid';

    const CONFIG_FILE = 'config.json';

    private static $config = array();
    static public $defaults = array(
        'db.host' => 'localhost',
        'db.port' => '3306',
        'default.language' => 'es_ES',
        'debug' => true,
        'front.version' => 'v1',
        'version' => 'v1',
    );
    static public $required = array('db.host', 'db.port', 'db.name', 'db.user', 'db.password', 'home.action', 'default.language', 'debug');
    static public $encrypted = array('db.password');
    static public $optional = [
        'platform.name', // Platform name
        'restricted', // Restrict the web access
        'admin.login', // Enable web login for admin
        'logger.phpFire', // Enable phpFire to trace the logs in the browser
        'logger.memory', // Enable log memory usage un traces
        'poweredBy', // Show PoweredBy header customized
        'author', // Author for auto generated files
        'author.email', // Author email for auto generated files
        'version', // Platform version(for cache purposes)
        'front.version', // Static resources version
        'cors.enabled', // Enable CORS (regex with the domains, * for all)
        'pagination.limit', // Pagination limit for autogenerated api admin
        'api.secret', // Secret passphrase to securize the api
        'api.admin', // Enable de autogenerated api admin(wok)
        'log.level', // Max log level(default INFO)
        'admin_action', // Default admin url when access to /admin
        'cache.var', // Static cache var
        'twig.autoreload', // Enable or disable auto reload templates for twig
        'modules.extend', // Variable for extending the current functionality
        'psfs.auth', // Variable for extending PSFS with the AUTH module
        'errors.strict', // Variable to trace all strict errors
        'psfs.memcache', // Add Memcache to prod cache process, ONLY for PROD environments
        'angular.protection', // Add an angular suggested prefix in order to avoid JSONP injections
        'cors.headers', // Add extra headers to the CORS check
        'json.encodeUTF8', // Encode the json response
        'cache.data.enable', // Enable data caching with PSFS
        'profiling.enable', // Enable the profiling headers
        'api.extrafields.compat', // Disbale retro compatibility with extra field mapping in api
        'output.json.strict_numbers', // Enable strict numbers in json responses
        'admin.version', // Determines the version for the admin ui
        'api.block.limit', // Determine the number of rows for bulk insert
        'api.field.types', // Extract __fields from api with their types
        'i18n.locales', // Default locales for any project
        'log.slack.hook', // Hook for slack traces
        'i18n.autogenerate', // Set PSFS auto generate i18n mode
        'resources.cdn.url', // CDN URL base path
        'api.field.type', // Field type for API dtos (phpName|camelName|camelName|fieldName) @see Propel TableMap calss
    ];
    protected $debug = false;

    /**
     * Method that load the configuration data into the system
     * @return Config
     */
    protected function init()
    {
        if (file_exists(CONFIG_DIR . DIRECTORY_SEPARATOR . self::CONFIG_FILE)) {
            $this->loadConfigData();
        }
        return $this;
    }

    /**
     * @return bool
     */
    public function isLoaded() {
        return !empty(self::$config);
    }

    /**
     * Method that saves the configuration
     * @param array $data
     * @param array $extra
     * @return array
     */
    protected static function saveConfigParams(array $data, array $extra)
    {
        Logger::log('Saving required config parameters');
        //En caso de tener parámetros nuevos los guardamos
        if (array_key_exists('label', $extra) && is_array($extra['label'])) {
            foreach ($extra['label'] as $index => $field) {
                if (array_key_exists($index, $extra['value']) && !empty($extra['value'][$index])) {
                    /** @var $data array */
                    $data[$field] = $extra['value'][$index];
                }
            }
        }
        return $data;
    }

    /**
     * Method that saves the extra parameters into the configuration
     * @param array $data
     * @return array
     */
    protected static function saveExtraParams(array $data)
    {
        $final_data = array();
        if (count($data) > 0) {
            Logger::log('Saving extra configuration parameters');
            foreach ($data as $key => $value) {
                if (null !== $value || $value !== '') {
                    $final_data[$key] = $value;
                }
            }
        }
        return $final_data;
    }

    /**
     * Method that returns if the system is in debug mode
     * @return boolean
     */
    public function getDebugMode()
    {
        return $this->debug;
    }

    /**
     * @param bool $debug
     */
    public function setDebugMode($debug = true) {
        $this->debug = $debug;
        self::$config['debug'] = $this->debug;
    }

    /**
     * Method that checks if the platform is proper configured
     * @return boolean
     */
    public function isConfigured()
    {
        Logger::log('Checking configuration');
        $configured = (count(self::$config) > 0);
        if ($configured) {
            foreach (static::$required as $required) {
                if (!array_key_exists($required, self::$config)) {
                    $configured = false;
                    break;
                }
            }
        }
        return $configured || $this->checkTryToSaveConfig() || self::isTest();
    }

    /**
     * Method that check if the user is trying to save the config
     * @return bool
     */
    public function checkTryToSaveConfig()
    {
        $uri = Request::getInstance()->getRequestUri();
        $method = Request::getInstance()->getMethod();
        return (preg_match('/^\/admin\/(config|setup)$/', $uri) !== false && strtoupper($method) === 'POST');
    }

    /**
     * Method that saves all the configuration in the system
     *
     * @param array $data
     * @param array|null $extra
     * @return boolean
     */
    public static function save(array $data, array $extra = null)
    {
        $data = self::saveConfigParams($data, $extra);
        $final_data = self::saveExtraParams($data);
        $saved = false;
        try {
            $final_data = array_filter($final_data, function($key, $value) {
                return in_array($key, self::$required, true) || !empty($value);
            }, ARRAY_FILTER_USE_BOTH);
            $saved = (false !== file_put_contents(CONFIG_DIR . DIRECTORY_SEPARATOR . self::CONFIG_FILE, json_encode($final_data, JSON_PRETTY_PRINT)));
            self::getInstance()->loadConfigData();
            $saved = true;
        } catch (ConfigException $e) {
            Logger::log($e->getMessage(), LOG_ERR);
        }
        return $saved;
    }

    /**
     * Method that returns a config value
     * @param string $param
     * @param mixed $defaultValue
     *
     * @return mixed|null
     */
    public function get($param, $defaultValue = null)
    {
        return array_key_exists($param, self::$config) ? self::$config[$param] : $defaultValue;
    }

    /**
     * Method that returns all the configuration
     * @return array
     */
    public function dumpConfig()
    {
        return self::$config ?: [];
    }

    /**
     * Method that reloads config file
     */
    public function loadConfigData()
    {
        self::$config = json_decode(file_get_contents(CONFIG_DIR . DIRECTORY_SEPARATOR . self::CONFIG_FILE), true) ?: [];
        $this->debug = array_key_exists('debug', self::$config) ? (bool)self::$config['debug'] : FALSE;
    }

    /**
     * Clear configuration set
     */
    public function clearConfig()
    {
        self::$config = [];
    }

    /**
     * Static wrapper for extracting params
     * @param string $key
     * @param mixed|null $defaultValue
     * @param string $module
     * @return mixed|null
     */
    public static function getParam($key, $defaultValue = null, $module = null)
    {
        if(null !== $module) {
            return self::getParam(strtolower($module) . '.' . $key, self::getParam($key, $defaultValue));
        }
        return array_key_exists($key, self::$config) ? self::$config[$key] : $defaultValue;
    }
}
