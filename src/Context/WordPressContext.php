<?php
namespace MattKendon\WordPressExtension\Context;

use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;

use Behat\MinkExtension\Context\MinkContext;

/**
 * Class WordPressContext
 *
 * @package MattKendon\WordPressExtension\Context
 */
class WordPressContext extends MinkContext
{
    /**
     * Create a new WordPress website from scratch
     *
     * @Given /^\w+ have|has a vanilla wordpress installation$/
     */
    public function installWordPress(TableNode $table = null)
    {
        global $wp_rewrite;

        $name = "admin";
        $email = "an@example.com";
        $password = "test";
        $username = "admin";

        if ($table) {
            $hash = $table->getHash();
            $row = $hash[0];
            $name = $row["name"];
            $username = $row["username"];
            $email = $row["email"];
            $password = $row["password"];
        }

        $host = getenv('DB_HOST');
        $user = getenv('DB_USER');
        $password = getenv('DB_PASSWORD');
        $database = getenv('DB_NAME');

        $dsn = "mysql:dbname={$database};host={$host}";

        $db = new \PDO($dsn, $user, $password);

//        $mysqli = new \Mysqli($host, $user, $password, $database);

        $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $db->exec("SET GROUP_CONCAT_MAX_LEN=32768;");
        $db->exec("SET @tables = NULL;");
        $db->exec("SELECT GROUP_CONCAT('`', table_name, '`') INTO @tables
                    FROM information_schema.tables
                    WHERE table_schema = (SELECT DATABASE());");
        $db->exec("SELECT IFNULL(@tables,'dummy') INTO @tables;");
        $db->exec("SET @tables = CONCAT('DROP TABLE IF EXISTS ', @tables);");
        $db->exec("PREPARE stmt FROM @tables;");
        $db->exec("EXECUTE stmt;");
        $db->exec("DEALLOCATE PREPARE stmt;");
        $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
//        assertTrue($value);
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        wp_install($name, $username, $email, true, '', $password);

        $wp_rewrite->init();
        $wp_rewrite->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

    }

    /**
     * Add these users to this wordpress installation
     *
     * @see wp_insert_user
     *
     * @Given /^there are users$/
     */
    public function thereAreUsers(TableNode $table)
    {
        foreach ($table->getHash() as $userData) {
            if (!is_int(wp_insert_user($userData))) {
                throw new \InvalidArgumentException("Invalid user information schema.");
            }
        }
    }

    /**
     * Add these posts to this wordpress installation
     *
     * @see wp_insert_post
     *
     * @Given /^there are posts$/
     */
    public function thereArePosts(TableNode $table)
    {
        foreach ($table->getHash() as $postData) {
            if (!is_int(wp_insert_post($postData))) {
                throw new \InvalidArgumentException("Invalid post information schema.");
            }
        }
    }

    /**
     * Activate/Deactivate plugins
     * | plugin          | status  |
     * | plugin/name.php | enabled |
     *
     * @Given /^there are plugins$/
     */
    public function thereArePlugins(TableNode $table)
    {
        foreach ($table->getHash() as $row) {
            if ($row["status"] == "enabled") {
                activate_plugin( $row["plugin"] );
            } else {
                deactivate_plugins( $row["plugin"] );
            }
        }
    }


    /**
     * Login into the reserved area of this wordpress
     *
     * @Given /^I am logged in as "([^"]*)" with password "([^"]*)"$/
     */
    public function login($username, $password)
    {
    	$this->getSession()->reset();
        $this->visit("wp-login.php");
        $currentPage = $this->getSession()->getPage();

        $currentPage->fillField('user_login', $username);
        $currentPage->fillField('user_pass', $password);
        $currentPage->findButton('wp-submit')->click();

        assertTrue($this->getSession()->getPage()->hasContent('Dashboard'));
    }

}
