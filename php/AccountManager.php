<?php

require_once dirname(__FILE__) . '/VCSFactory.php';
require_once dirname(__FILE__) . '/DBConnection.php';

class AccountManager
{
    private static $instance;

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c;
        }
        return self::$instance;
    }

    public $userID;
    public $vcsLogin;
    public $vcsPasswd;
    public $vcsLang;
    public $userConf;

    private function __construct()
    {
    }

    /**
     * Update the date/time about the lastConnexion for this user, in DB
     */
    public function updateLastConnect()
    {
        $s = sprintf(
            'UPDATE `users` SET `last_connect`=now() WHERE `userID`="%s"',
            $this->userID
        );
        DBConnection::getInstance()->query($s);
    }

    /**
     * Check if there is an authentificated session or not
     * Update the last connexion's date in DB for this user
     *
     * @return TRUE if there is an authentificated session, FALSE otherwise.
     */
    public function isLogged()
    {
        if (!isset($_SESSION['userID'])) {
            return false;
        }

        $this->userID    = $_SESSION['userID'];
        $this->vcsLogin  = $_SESSION['vcsLogin'];
        $this->vcsPasswd = $_SESSION['vcsPasswd'];
        $this->vcsLang   = $_SESSION['lang'];

        $this->userConf = isset($_SESSION['userConf'])
            ? $_SESSION['userConf']
            : array(
                "conf_needupdate_diff"       => 'using-exec',
                "conf_needupdate_scrollbars" => 'true',
                "conf_needupdate_displaylog" => 'false',

                "conf_error_skipnbliteraltag" => 'true',
                "conf_error_scrollbars"       => 'true',
                "conf_error_displaylog"       => 'false',

                "conf_reviewed_scrollbars" => 'true',
                "conf_reviewed_displaylog" => 'false',

                "conf_allfiles_displaylog" => 'false',

                "conf_patch_scrollbars" => 'true',
                "conf_patch_displaylog" => 'false',

                "conf_theme" => 'themes/empty.css'
            );
        $this->updateLastConnect();

        return true;
    }

    /**
     * Log into this application.
     *
     * @param $vcsLogin  The login use to identify this user into PHP VCS server.
     * @param $vcsPasswd The password, in plain text, to identify this user into PHP VCS server.
     * @param $lang      The language we want to access.
     * @return An associated array.
     */
    public function login($vcsLogin, $vcsPasswd, $lang='en')
    {

        // Var to return into ExtJs
        $return = array();

        // We try to authenticate this user to VCS server.
        $r = VCSFactory::getInstance()->authenticate($vcsLogin, $vcsPasswd);

        if( $r === true ) {

           $this->vcsLogin  = $vcsLogin;
           $this->vcsPasswd = $vcsPasswd;
           $this->vcsLang   = $lang;

           // Is this user already exist on this server ? database check
           $s = sprintf(
               'SELECT * FROM `users` WHERE `vcs_login`="%s"',
               $vcsLogin
           );
           $r = DBConnection::getInstance()->query($s);

           if ($r->num_rows == 1) {

              //This user exist into DB. We store his configuration into ...
              $a = $r->fetch_object();

              // ... object's property ...
              $this->userConf = array(
                  "conf_needupdate_diff"       => $a->conf_needupdate_diff,
                  "conf_needupdate_scrollbars" => $a->conf_needupdate_scrollbars,
                  "conf_needupdate_displaylog" => $a->conf_needupdate_displaylog,

                  "conf_error_skipnbliteraltag" => $a->conf_error_skipnbliteraltag,
                  "conf_error_scrollbars"       => $a->conf_error_scrollbars,
                  "conf_error_displaylog"       => $a->conf_error_displaylog,

                  "conf_reviewed_scrollbars" => $a->conf_reviewed_scrollbars,
                  "conf_reviewed_displaylog" => $a->conf_reviewed_displaylog,

                  "conf_allfiles_displaylog" => $a->conf_allfiles_displaylog,

                  "conf_patch_scrollbars" => $a->conf_patch_scrollbars,
                  "conf_patch_displaylog" => $a->conf_patch_displaylog,

                  "conf_theme" => $a->conf_theme
              );

              // ... and into the php's session
              $_SESSION['userID']    = $a->userID;
              $_SESSION['vcsLogin']  = $this->vcsLogin;
              $_SESSION['vcsPasswd'] = $this->vcsPasswd;
              $_SESSION['lang']      = $this->vcsLang;
              $_SESSION['userConf']  = $this->userConf;

              // We construct the return's var for ExtJs
              $return['state'] = true;
              $return['msg']   = 'Welcome !';


           } else {

              // We register this new valid user
              $userID = $this->register();

              //We store his configuration into object's property
              $_SESSION['userID']    = $userID;
              $_SESSION['vcsLogin']  = $this->vcsLogin;
              $_SESSION['vcsPasswd'] = $this->vcsPasswd;
              $_SESSION['lang']      = $this->vcsLang;
              $_SESSION['userConf']  = array(
                  "conf_needupdate_diff"       => 'using-exec',
                  "conf_needupdate_scrollbars" => 'true',
                  "conf_needupdate_displaylog" => 'false',

                  "conf_error_skipnbliteraltag" => 'true',
                  "conf_error_scrollbars"       => 'true',
                  "conf_error_displaylog"       => 'false',

                  "conf_reviewed_scrollbars" => 'true',
                  "conf_reviewed_displaylog" => 'false',

                  "conf_allfiles_displaylog" => 'false',

                  "conf_patch_scrollbars" => 'true',
                  "conf_patch_displaylog" => 'false',

                  "conf_theme" => 'themes/empty.css'
              );

              // We construct the return's var for ExtJs
              $return['state'] = true;

           }
        } elseif ($r == 'Bad password') {

            // Authentication failed from the VCS server : bad password return
            $return['state'] = false;
            $return['msg']   = 'Bad vcs password';

        } else {

            //Authentication failed from the VCS server : others errors
            $return['state'] = false;
            $return['msg']   = 'unknow from vcs';
        }

        return $return;
    }

    /**
     * Register a new valid user on the application.
     *
     * @todo The VCS password is stored in plain text into the database for later use. We need to find something better
     * @return int The database insert id
     */
    private function register()
    {
        $s = sprintf(
            'INSERT INTO `users` (`vcs_login`) VALUES ("%s")',
            $this->vcsLogin
        );
        $db = DBConnection::getInstance();
        $db->query($s);
        return $db->insert_id();
    }

    /**
     * Update an option in user configuration database
     *
     * @param $item The name of the option.
     * @param $value The value of the option.
     */
    public function updateConf($item, $value)
    {
        $s = sprintf(
            'UPDATE `users` SET `%s`="%s" WHERE `vcs_login`="%s"',
            $item, $value, AccountManager::getInstance()->vcsLogin
        );
        DBConnection::getInstance()->query($s);

        // In session
        AccountManager::getInstance()->userConf[$item] = $value;
        $_SESSION['userConf'][$item] = $value;
    }

    /**
     * Erase personal data. Delete all reference into the DB for this user.
     */
    public function eraseData()
    {
        $uid = AccountManager::getInstance()->userID;
        $s = sprintf(
            'DELETE FROM `commitMessage` WHERE `userID`="%s"',
            $uid
        );
        DBConnection::getInstance()->query($s);

        $s = sprintf(
            'DELETE FROM `users` WHERE `userID`="%s"',
            $uid
        );
        DBConnection::getInstance()->query($s);
    }


    /**
     * Send an email.
     *
     * @param $to The Receiver.
     * @param $subject The subject of the email.
     * @param $msg The content of the email. Don't use HTML here ; only plain text.
     */
    public function email($to, $subject, $msg)
    {
        $headers = 'From: '.$this->vcsLogin.'@php.net' . "\r\n" .
                   'X-Mailer: PhpDocumentation Online Editor' ."\r\n" .
                   'Content-Type: text/plain; charset="utf-8"'."\n";

        mail($to, stripslashes($subject), stripslashes(trim($msg)), $headers);
    }
}

?>
