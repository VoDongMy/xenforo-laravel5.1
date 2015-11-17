<?php

class XenGallery_CronEntry_CleanUps
{
	protected static $_db = null;

	public static function runCleanUps()
	{
		$db = self::_getDb();
		$options = XenForo_Application::getOptions();

		$db->delete('xengallery_exif_cache', 'cache_date > ' . XenForo_Application::$time - 86400);

		if ($options->xengalleryTagExpiry['enabled'])
		{
			$db->delete('xengallery_user_tag',
				'tag_state_date > ' . XenForo_Application::$time - (86400 * $options->xengalleryTagExpiry['days']) .
				' AND tag_state IN (' . $db->quote(array('rejected', 'pending')) . ')'
			);
		}
	}

	protected static function _getDb()
	{
		if (self::$_db === null)
		{
			self::$_db = XenForo_Application::getDb();
		}

		return self::$_db;
	}
}