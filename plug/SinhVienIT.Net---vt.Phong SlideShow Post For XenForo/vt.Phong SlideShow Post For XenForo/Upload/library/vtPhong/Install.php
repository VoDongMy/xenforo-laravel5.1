<?php

class vtPhong_Install
{

    private static $_instance;
    private $_addOnData;

    public static function getInstance()
    {
        if (!self::$_instance)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    public static function install($existingAddOn, $addOnData)
    {
        self::getInstance()->_addOnData = $addOnData;
        $install = self::getInstance();
        $installedVersion = ($existingAddOn) ? $existingAddOn['version_id'] : false;

        if ($installedVersion === false)
        { // The first install
            $nextStep = 0;
        }
        else
        {
            $nextStep = $installedVersion + 1;
        }

        $endStep = $addOnData['version_id'];
        for ($i = $nextStep; $i <= $endStep; $i++)
        {
            $method = '_installStep' . $i;
            if (method_exists($install, $method))
            {
                $install->$method();
            }
        }
    }

    public static function uninstall($existingAddOn)
    {
        $uninstall = self::getInstance();
        $installedVersion = $existingAddOn['version_id'];

        for ($i = $installedVersion; $i >= 0; $i--)
        {
            $method = '_uninstallStep' . $i;
            if (method_exists($uninstall, $method))
            {
                $uninstall->$method();
            }
        }
    }

    private function _installStep0()
    {
        $db = XenForo_Application::get('db');
        $db->query("
            CREATE TABLE `xf_thread_slide` (
              `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `thread_id` int(10) NOT NULL,
              `url_slide` varchar(500) NOT NULL DEFAULT '',
              `title_slide` varchar(150) NOT NULL,
              `des_slide` text NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8
        ");
        
        $db->query('
            ALTER TABLE `xf_forum`
            ADD COLUMN `can_add_slide`  tinyint(2) UNSIGNED NOT NULL DEFAULT 0 AFTER `allowed_watch_notifications`
        ');
    }

    private function _uninstallStep0()
    {
        $db = XenForo_Application::get('db');
        $db->query("
            DROP TABLE IF EXISTS `xf_thread_slide`
        ");
        
        $db->query('
            ALTER TABLE `xf_forum`
            DROP COLUMN `can_add_slide`
        ');
    }

}