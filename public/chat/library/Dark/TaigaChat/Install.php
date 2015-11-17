<?php

class Dark_TaigaChat_Install
{
	/**
	 * Instance manager.
	 *
	 * @var Dark_TaigaChat_Install
	 */
	private static $_instance;

	/**
	 * Database object
	 *
	 * @var Zend_Db_Adapter_Abstract
	 */
	protected $_db;

	/**
	 * Gets the installer instance.
	 *
	 * @return Dark_TaigaChat_Install
	 */
	public static final function getInstance()
	{
		if (!self::$_instance)
		{
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Helper method to get the database object.
	 *
	 * @return Zend_Db_Adapter_Abstract
	 */
	protected function _getDb()
	{
		if ($this->_db === null)
		{
			$this->_db = XenForo_Application::get('db');
		}

		return $this->_db;
	}

	/**
	 * Begins the installation process and picks the proper install routine.
	 *
	 * See see XenForo_Model_Addon::installAddOnXml() for more details about
	 * the arguments passed to this method.
	 *
	 * @param array Information about the existing version (if upgrading)
	 * @param array Information about the current version being installed
	 *
	 * @return void
	 */
	public static function install($existingAddOn, $addOnData)
	{
		// the version IDs from which we should start/end the install process
		$startVersionId = 1;
		$endVersionId = $addOnData['version_id'];

		if ($existingAddOn)
		{
			// we are upgrading, run every install method since last upgrade
			$startVersionId = $existingAddOn['version_id'] + 1;
		}

		// create our install object
		$install = self::getInstance();

		for ($i = $startVersionId; $i <= $endVersionId; $i++)
		{
			$method = '_installVersion' . $i;
			if (method_exists($install, $method) === false)
			{
				continue;
			}

			$install->$method();
		}

	}

	/**
	 * Install routine for version ID 1 
	 *
	 * @return void
	 */
	protected function _installVersion1()
	{
		$db = $this->_getDb();

		$db->query( "
			CREATE TABLE if not exists `dark_taigachat` (
				`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
				`user_id` INT(10) UNSIGNED NOT NULL,
				`username` VARCHAR(50) NOT NULL,
				`date` INT(10) UNSIGNED NOT NULL,
				`message` TEXT NOT NULL,
				PRIMARY KEY (`id`),
				INDEX `date` (`date`),
				INDEX `user_id` (`user_id`)
			)
			COLLATE='utf8_general_ci'
			ENGINE=InnoDB
		");
	}
	
	protected function _installVersion21()
	{
		$db = $this->_getDb();
	
		if(!$db->fetchOne("SHOW COLUMNS FROM `dark_taigachat` LIKE 'activity'"))
			$db->query( "
				ALTER TABLE `dark_taigachat`
				ADD COLUMN `activity` TINYINT(1) UNSIGNED NOT NULL AFTER `message`;
			");
		
		
		if(!$db->fetchOne("SHOW COLUMNS FROM `xf_user` LIKE 'taigachat_color'"))
			$db->query( "
				ALTER TABLE `xf_user`
				ADD COLUMN `taigachat_color` CHAR(6) NOT NULL AFTER `warning_points`;
			");
			
		$db->query( "
			CREATE TABLE if not exists `dark_taigachat_activity` (
				`user_id` INT(10) UNSIGNED NOT NULL,
				`date` INT(10) UNSIGNED NOT NULL,
				PRIMARY KEY (`user_id`),
				INDEX `date` (`date`)
			)
			COLLATE='utf8_general_ci'
			ENGINE=MEMORY;
		");
	}

	protected function _installVersion22()
	{
		
		
		// remove color button
		$options = XenForo_Application::get('options');
	
		$newButtons = str_ireplace("<span class='taigachat_bbcode_color'></span>:[color=][/color]\n", "", $options->dark_taigachat_toolbar_bbcode);
	
		$optionModel = XenForo_Model::create('XenForo_Model_Option');
		$optionModel->updateOptions(array('dark_taigachat_toolbar_bbcode' => $newButtons));
		
		$options->dark_taigachat_toolbar_bbcode = $newButtons;			
	
		
	}
	
	protected function _installVersion30()
	{
		$db = $this->_getDb();

		$db->query( "
			ALTER TABLE `xf_user` CHANGE COLUMN `taigachat_color` `taigachat_color` CHAR(6) NOT NULL DEFAULT '' AFTER `warning_points`;
		");
	}
	
	protected function _installVersion34()
	{
		$db = $this->_getDb();

		$db->query( "
			ALTER TABLE `dark_taigachat`
			ADD COLUMN `room_id` INT(10) UNSIGNED NOT NULL DEFAULT '1' AFTER `user_id`;
		");
			
		$db->query( "
			ALTER TABLE `dark_taigachat`
			ADD INDEX `room_id` (`room_id`);
		");
			
		$db->query( "
			ALTER TABLE `dark_taigachat`
			ADD INDEX `activity` (`activity`);
		");
	}
	
	protected function _installVersion35()
	{
		$db = $this->_getDb();

		$db->query( "
			ALTER TABLE `dark_taigachat`
				ADD COLUMN `last_update` INT(10) UNSIGNED NOT NULL AFTER `date`;
		");
		
		$db->query( "
			ALTER TABLE `dark_taigachat`
				ADD INDEX `last_update` (`last_update`);
		");

		$db->query( "
			update dark_taigachat set last_update = date
		");
		

	}

	protected function _installVersion36()
	{
		/** @var Dark_TaigaChat_Model_TaigaChat */
		$taigamodel = XenForo_Model::create('Dark_TaigaChat_Model_TaigaChat');
		$taigamodel->regeneratePublicHtml();		
	}
	
}
