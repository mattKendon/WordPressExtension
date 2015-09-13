<?php

namespace MattKendon\WordPressExtension\Context\Initializer;

use Behat\Behat\Context\Context,
    Behat\Behat\Context\Initializer\ContextInitializer;

use Symfony\Component\Finder\Finder,
    Symfony\Component\Filesystem\Filesystem;

use MattKendon\WordPressExtension\Context\WordPressContext;

/**
 * Class FeatureListener
 *
 * @package MattKendon\WordPressExtension\Listener
 */
class WordPressContextInitializer implements ContextInitializer
{
    private $wordpressParams;
    private $minkParams;
    private $basePath;

    /**
     * inject the wordpress extension parameters and the mink parameters
     *
     * @param array  $wordpressParams
     * @param array  $minkParams
     * @param string $basePath
     */
    public function __construct($wordpressParams, $minkParams, $basePath)
    {
        $this->wordpressParams = $wordpressParams;
        $this->minkParams = $minkParams;
        $this->basePath = $basePath;
    }

    /**
     * setup the wordpress environment / stack if the context is a wordpress context
     *
     * @param Context $context
     */
    public function initializeContext(Context $context)
    {
        if (!$context instanceof WordPressContext) {
            return;
        }
        $this->prepareEnvironment();
//        $this->installFileFixtures();
        $this->flushDatabase();
        $this->loadStack();
    }

    /**
     * prepare environment variables
     */
    private function prepareEnvironment()
    {
        // wordpress uses these superglobal fields everywhere...
        $urlComponents = parse_url($this->minkParams['base_url']);
        $_SERVER['HTTP_HOST'] = $urlComponents['host'] . (isset($urlComponents['port']) ? ':' . $urlComponents['port'] : '');
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

        //set ENV_FILE which is used by bedrock to determine the environment file to load
        if (!defined('ENV_FILE')) {
            define('ENV_FILE', '.env.behat');
        }


        // we don't have a request uri in headless scenarios:
        // wordpress will try to "fix" php_self variable based on the request uri, if not present
        $PHP_SELF = $GLOBALS['PHP_SELF'] = $_SERVER['PHP_SELF'] = '/index.php';
    }

    /**
     * actually load the wordpress stack
     */
    private function loadStack()
    {
        // prevent wordpress from calling home to api.wordpress.org
        if (!defined('WP_INSTALLING') || !WP_INSTALLING) {
            define('WP_INSTALLING', true);
        }

        $finder = new Finder();

        // load the wordpress "stack"
        $finder->files()->in($this->wordpressParams['path'])->depth('== 0')->name('wp-load.php');

        foreach ($finder as $bootstrapFile) {
            require_once $bootstrapFile->getRealpath();
        }
    }

    /**
     * flush the database if specified by flush_database parameter
     */
    public function flushDatabase()
    {
        if ($this->wordpressParams['flush_database']) {

            $host = getenv('DB_HOST');
            $user = getenv('DB_USER');
            $password = getenv('DB_PASSWORD');
            $database = getenv('DB_NAME');

            $mysqli = new \Mysqli($host, $user, $password, $database);

            //drop database doesn't always work. Truncate and/or drop tables instead
            $mysqli->multi_query("DROP DATABASE IF EXISTS {$database}; CREATE DATABASE {$database};");
        }
    }
}
