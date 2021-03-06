<?php //Nulled by VxF.cc


class Dark_TaigaChat_Uninstall
{
	/**
	 * Instance manager.
	 *
	 * @var Dark_TaigaChat_Uninstall
	 */
	private static $_instance;

	/**
	 * Database object
	 *
	 * @var Zend_Db_Adapter_Abstract
	 */
	protected $_db;

	/**
	 * Gets the uninstaller instance.
	 *
	 * @return Dark_TaigaChat_Uninstall
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
	 * Begins the uninstallation process and runs uninstall routines.
	 *
	 * @param array Information about the (now uninstalled) add-on
	 *
	 * @return void
	 */
	public static function uninstall($addOnData)
	{
		// opposite of install!
		$startVersionId = $addOnData['version_id'];
		$endVersionId = 1;

		// create our uninstall object
		$uninstall = self::getInstance();

		for ($i = $startVersionId; $i >= $endVersionId; $i--)
		{
			$method = '_uninstallVersion' . $i;
			if (method_exists($uninstall, $method) === false)
			{
				continue;
			}

			$uninstall->$method();
		}

	}

	/**
	 * Uninstall routine for version ID 1.
	 *
	 * @return void
	 */
	protected function _uninstallVersion1()
	{
		$db = $this->_getDb();

		$db->query("DROP TABLE dark_taigachat");
	}
	
	protected function _uninstallVersion20()
	{
		$db = $this->_getDb();

		$db->query("
			ALTER TABLE `xf_user`
				DROP COLUMN `taigachat_color`");
				
		$db->query("DROP TABLE dark_taigachat_activity");
	}
}
