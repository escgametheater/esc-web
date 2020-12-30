<?php
/**
 * Class for loading modules
 *
 */

class ModuleFailureException extends Exception {

}
class Modules
{
    // Modules Name Constant Wrappers
    const HTTP = 'http';
    const BETA = 'beta';
    const ERROR_HANDLING = 'error-handling';
    const LOGS = 'logs';
    const PROFILING = 'profiling';
    const GEOIP = 'geoip';
    const CACHE = 'cache';
    const REDIRECTS = 'redirects';
    const DB = 'db';
    const PERMISSIONS = 'permissions';
    const ENTITIES = 'entities';
    const MANAGERS = 'managers';
    const AUTH = 'auth';
    const TRACKING = 'tracking';
    const IDENTITIES = 'identities';
    const CSRF = 'csrf';
    const I18N = 'i18n';
    const DEBUG = 'debug';
    const ANONYMOUS_CACHE = 'anonymous-cache';
    const FORMS = 'forms';
    const TASKS = 'tasks';
    const TWIG = 'twig';
    const SENTRY = 'sentry';
    const ELASTICSEARCH = 'elasticsearch';
    const S3 = 's3';

    /**
     * List of loaded modules
     */
    private static $modules = [];

    /**
     * List of loaded forms
     */
    private static $forms = [];

    /**
     * List of loaded managers
     */
    private static $managers = [];

    /**
     * List of loaded classes
     */
    private static $classes = [];

    /**
     * List of loaded helpers
     */
    private static $helpers = [];

    /**
     * Load a module
     * returns true on success
     */
    public static function load($name)
    {
        global $FRAMEWORK_DIR;

        $file = "${FRAMEWORK_DIR}/modules/${name}/init.php";
        $r = is_file($file) && include($file);

        if ($r)
            self::$modules[] = $name;
        else
            throw new ESCFrameworkException("Failed to load module: $name");
        return $r;
    }

    /**
     * Load a module
     * checks if the module is not already loaded
     */
    public static function uses($name)
    {
        if (!self::is_loaded($name))
            self::load($name);
    }

    /**
     * Checks if a module is loaded
     */
    public static function is_loaded($name)
    {
        return in_array($name, self::$modules);
    }

    /**
     * Load a manager
     * checks if the module is not already loaded
     */
    public static function load_manager($name)
    {
        if (!in_array($name, self::$managers)) {
            global $PROJECT_DIR, $FRAMEWORK_DIR;
            $p = "${PROJECT_DIR}/core/domain/managers/${name}.php";
            $f = "${FRAMEWORK_DIR}/managers/${name}.php";
            if (is_file($p))
                $r = include($p);
            elseif (is_file($f))
                $r = include($f);
            else
                $r = 0;

            if ($r)
                self::$managers[] = $name;
            else
                throw new ESCFrameworkException("Failed to load manager $name");
        }
    }

    /**
     * Load a form
     * checks if the module is not already loaded
     */
    public static function load_form($name)
    {
        if (!in_array($name, self::$forms)) {
            global $PROJECT_DIR;
            $r = include("${PROJECT_DIR}/core/domain/forms/${name}.php");
            if ($r)
                self::$forms[] = $name;
            else
                throw new ESCFrameworkException("Failed to load form $name");
        }
    }

    /**
     * @param $name
     * @throws Exception
     */
    public static function load_forms($name)
    {
        self::load_form($name);
    }


    /**
     * Load a helper
     * checks if the module is not already loaded
     */
    public static function load_helper($name)
    {
        if (!in_array($name, self::$helpers)) {
            global $PROJECT_DIR, $FRAMEWORK_DIR;

            $p = "${PROJECT_DIR}/core/domain/helpers/${name}.php";
            $f = "${FRAMEWORK_DIR}/helpers/${name}.php";

            if (is_file($p))
                $r = include($p);
            elseif (is_file($f))
                $r = include($f);
            else
                $r = 0;

            if ($r)
                self::$helpers[] = $name;
            else
                throw new ESCFrameworkException("Failed to load helper $name");
        }
    }
}

class Helpers {

    // Framework
    const ASSETS = 'assets';
    const EMAIL = 'email';
    const CONTENT = 'content';
    const COMMENTS = 'comments';
    const UPLOADS = 'uploads';
    const BITFIELD = 'bitfield';
    const FILE = 'file';
    const IMAGE = 'image';
    const T = 't';
    const THUMBNAILS = 'thumbnails';
    const PAGINATOR = 'paginator';
    const ACTIVITYFEEDS = 'activityfeeds';
    const PAGEREAD = 'pageread';

    // Domain
    const ZIP_UPLOAD = 'zip-upload';
    const PUBNUB = 'pubnub';
    const SLACK = 'slack';

    const ORDER = 'order';


}