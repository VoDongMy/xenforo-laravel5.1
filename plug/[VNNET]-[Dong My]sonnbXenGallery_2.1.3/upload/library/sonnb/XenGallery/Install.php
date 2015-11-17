<?php

/**
 * @category    XenForo
 * @package     sonnb - XenGallery
 * @version     2.1.3
 * @copyright:  sonnb
 * @link        www.sonnb.com
 * @version     One license is valid for only one nominated domain.
 * @license     You might not copy or redistribute this addon. Any action to public or redistribute must be authorized from author
 */
class sonnb_XenGallery_Install
{
	protected static $_checkAlters = array(
		array(
			'showTablesQuery' => 'SHOW TABLES LIKE \'xf_user\'',
			'showColumnsQuery' => 'SHOW COLUMNS FROM `xf_user` LIKE \'sonnb_xengallery_album_count\'',
			'alterQuery' => 'ALTER TABLE `xf_user` ADD COLUMN `sonnb_xengallery_album_count` INT(10) UNSIGNED NULL DEFAULT 0'
		),
		array(
			'showTablesQuery' => 'SHOW TABLES LIKE \'xf_user\'',
			'showColumnsQuery' => 'SHOW COLUMNS FROM `xf_user` LIKE \'sonnb_xengallery_photo_count\'',
			'alterQuery' => 'ALTER TABLE `xf_user` ADD COLUMN `sonnb_xengallery_photo_count` INT(10) UNSIGNED NULL DEFAULT 0'
		),
		array(
			'showTablesQuery' => 'SHOW TABLES LIKE \'xf_user\'',
			'showColumnsQuery' => 'SHOW COLUMNS FROM `xf_user` LIKE \'sonnb_xengallery_video_count\'',
			'alterQuery' => 'ALTER TABLE `xf_user` ADD COLUMN `sonnb_xengallery_video_count` INT(10) UNSIGNED NULL DEFAULT 0'
		),
		array(
			'showTablesQuery' => 'SHOW TABLES LIKE \'xf_user\'',
			'showColumnsQuery' => 'SHOW COLUMNS FROM `xf_user` LIKE \'sonnb_xengallery_cover\'',
			'alterQuery' => 'ALTER TABLE `xf_user` ADD COLUMN `sonnb_xengallery_cover` blob NULL'
		),
		array(
			'showTablesQuery' => 'SHOW TABLES LIKE \'xf_user_option\'',
			'showColumnsQuery' => 'SHOW COLUMNS FROM `xf_user_option` LIKE \'xengallery\'',
			'alterQuery' => 'ALTER TABLE `xf_user_option` ADD COLUMN `xengallery` blob NULL'
		),
		array(
			'showTablesQuery' => 'SHOW TABLES LIKE \'xf_user_option\'',
			'showColumnsQuery' => 'SHOW COLUMNS FROM `xf_user_option` LIKE \'sonnb_xengallery_watermark\'',
			'alterQuery' => 'ALTER TABLE `xf_user_option` ADD COLUMN `sonnb_xengallery_watermark` blob NULL'
		),
		array(
			'showTablesQuery' => 'SHOW TABLES LIKE \'xf_thread\'',
			'showColumnsQuery' => 'SHOW COLUMNS FROM `xf_thread` LIKE \'sonnb_xengallery_import\'',
			'alterQuery' => 'ALTER TABLE `xf_thread` ADD COLUMN `sonnb_xengallery_import` TINYINT(3) NULL DEFAULT 0'
		),
		array(
			'showTablesQuery' => 'SHOW TABLES LIKE \'sonnb_xengallery_album\'',
			'showColumnsQuery' => 'SHOW COLUMNS FROM `sonnb_xengallery_album` LIKE \'video_count\'',
			'alterQuery' => 'ALTER TABLE `sonnb_xengallery_album` ADD COLUMN `video_count` int(10) UNSIGNED NOT NULL DEFAULT 0'
		),
		array(
			'showTablesQuery' => 'SHOW TABLES LIKE \'sonnb_xengallery_album\'',
			'showColumnsQuery' => 'SHOW COLUMNS FROM `sonnb_xengallery_album` LIKE \'audio_count\'',
			'alterQuery' => 'ALTER TABLE `sonnb_xengallery_album` ADD COLUMN `audio_count` int(10) UNSIGNED NOT NULL DEFAULT 0'
		),
		array(
			'showTablesQuery' => 'SHOW TABLES LIKE \'sonnb_xengallery_album\'',
			'showColumnsQuery' => 'SHOW COLUMNS FROM `sonnb_xengallery_album` LIKE \'cover_content_type\'',
			'alterQuery' => 'ALTER TABLE `sonnb_xengallery_album` ADD COLUMN `cover_content_type` enum(\'audio\',\'video\',\'photo\') COLLATE utf8_bin NOT NULL DEFAULT \'photo\''
		),
		array(
			'showTablesQuery' => 'SHOW TABLES LIKE \'sonnb_xengallery_album\'',
			'showColumnsQuery' => 'SHOW COLUMNS FROM `sonnb_xengallery_album` LIKE \'cover_content_id\'',
			'alterQuery' => 'ALTER TABLE `sonnb_xengallery_album` ADD COLUMN `cover_content_id` int(10) UNSIGNED NOT NULL'
		),
		array(
			'showTablesQuery' => 'SHOW TABLES LIKE \'sonnb_xengallery_album\'',
			'showColumnsQuery' => 'SHOW COLUMNS FROM `sonnb_xengallery_album` LIKE \'latest_video_ids\'',
			'alterQuery' => 'ALTER TABLE `sonnb_xengallery_album` ADD COLUMN `latest_video_ids` varbinary(100) NOT NULL DEFAULT \'\''
		),
		array(
			'showTablesQuery' => 'SHOW TABLES LIKE \'sonnb_xengallery_album\'',
			'showColumnsQuery' => 'SHOW COLUMNS FROM `sonnb_xengallery_album` LIKE \'latest_audio_ids\'',
			'alterQuery' => 'ALTER TABLE `sonnb_xengallery_album` ADD COLUMN `latest_audio_ids` varbinary(100) NOT NULL DEFAULT \'\''
		),
		array(
			'showTablesQuery' => 'SHOW TABLES LIKE \'sonnb_xengallery_album\'',
			'showColumnsQuery' => 'SHOW COLUMNS FROM `sonnb_xengallery_album` LIKE \'latest_content_ids\'',
			'alterQuery' => 'ALTER TABLE `sonnb_xengallery_album` ADD COLUMN `latest_content_ids` varbinary(100) NOT NULL DEFAULT \'\''
		),
		array(
			'showTablesQuery' => 'SHOW TABLES LIKE \'sonnb_xengallery_collection\'',
			'showColumnsQuery' => 'SHOW COLUMNS FROM `sonnb_xengallery_collection` LIKE \'last_content_date\'',
			'alterQuery' => 'ALTER TABLE `sonnb_xengallery_collection` ADD COLUMN `last_content_date` INT(10) UNSIGNED NOT NULL DEFAULT 0'
		)
	);

	/**
	 * @param $existingAddOn
	 * @param $addOnData
	 */
	public static function install($existingAddOn, $addOnData)
	{
		$db = XenForo_Application::get('db');

		if (!$existingAddOn)
		{
			foreach (self::installTables() AS $tableName => $tableSql)
			{
				try
				{
					$db->query($tableSql);
				}
				catch (Zend_Db_Exception $e)
				{
					XenForo_Error::logException($e);
				}
			}

			foreach (self::installAlters() AS $tableName => $alterSql)
			{
				try
				{
					$db->query($alterSql);
				}
				catch (Zend_Db_Exception $e)
				{
					XenForo_Error::logException($e);
				}
			}

			foreach (self::getData() AS $tableName => $dataSql)
			{
				try
				{
					if (is_array($dataSql))
					{
						foreach ($dataSql as $subSql)
						{
							$db->query($subSql);
						}
					}
					else
					{
						$db->query($dataSql);
					}
				}
				catch (Zend_Db_Exception $e)
				{
					XenForo_Error::logException($e);
				}
			}
		}
		else
		{
			if ($existingAddOn['version_id'] === $addOnData['version_id'])
			{
				foreach (self::installTables() AS $tableName => $tableSql)
				{
					try
					{
						if (!$db->fetchOne("SHOW TABLES LIKE '$tableName'"))
						{
							$db->query($tableSql);
						}
					}
					catch (Zend_Db_Exception $e)
					{
						XenForo_Error::logException($e);
					}
				}

				foreach (self::$_checkAlters as $alter)
				{
					try
					{
						$tableExisted = $db->fetchOne($alter['showTablesQuery']);
						if (empty($tableExisted))
						{
							continue;
						}

						$existed = $db->fetchOne($alter['showColumnsQuery']);
						if (empty($existed))
						{
							$db->query($alter['alterQuery']);
						}
					}
					catch (Zend_Db_Exception $e)
					{
						XenForo_Error::logException($e);
					}
				}
			}

			$versionId = $existingAddOn['version_id'];
			switch (true)
			{
				case ($versionId < 107):
					self::_upgrade(107);
				case ($versionId < 109):
					self::_upgrade(109);
				case ($versionId < 10012):
					self::_upgrade(10012);
				case ($versionId < 10100):
					self::_upgrade(10100);
				case ($versionId < 10101):
					self::_upgrade(10101);
				case ($versionId < 10102):
					self::_upgrade(10102);
				case ($versionId < 20000):
					self::_upgrade(20000);
				case ($versionId < 20004):
					self::_upgrade(20004);
				case ($versionId < 20100):
					self::_upgrade(20100);
					break;
			}
		}
	}

	/**
	 * @param $existingAddOn
	 */
	public static function uninstall($existingAddOn)
	{
		if (!$existingAddOn)
		{
			return;
		}

		$db = XenForo_Application::get('db');

		foreach (self::installTables() AS $tableName => $tableSql)
		{
			try
			{
				$db->query("DROP TABLE IF EXISTS `$tableName`");
			}
			catch (Zend_Db_Exception $e) {}
		}

		$contentTypes = array(
			'sonnb_xengallery_album',
			'sonnb_xengallery_photo',
			'sonnb_xengallery_video',
			'sonnb_xengallery_comment'
		);

		$contentTypesQuoted = $db->quote($contentTypes);

		XenForo_Db::beginTransaction($db);

		$contentTypeTables = array(
			'xf_content_type',
			'xf_content_type_field',
			'xf_deletion_log',
			'xf_liked_content',
			'xf_moderation_queue',
			'xf_moderator_log',
			'xf_news_feed',
			'xf_report',
			'xf_user_alert',
			'xf_search_index'
		);

		try
		{
			foreach ($contentTypeTables AS $table)
			{
				$db->delete($table, 'content_type IN (' . $contentTypesQuoted . ')');
			}
		}
		catch (Zend_Db_Exception $e) {}

		try
		{
			$db->delete('xf_stats_daily', 'stats_type IN (' . $contentTypesQuoted . ')');
		}
		catch (Zend_Db_Exception $e) {}

		foreach (self::uninstallAlters() AS $tableName => $fieldName)
		{
			try
			{
				if (is_array($fieldName))
				{
					foreach ($fieldName as $_fieldName)
					{
						$db->query("ALTER TABLE `$tableName` DROP `$_fieldName`");
					}
				}
				else
				{
					$db->query("ALTER TABLE `$tableName` DROP `$fieldName`");
				}
			}
			catch (Zend_Db_Exception $e) {}
		}

		XenForo_Db::commit($db);
	}

	/**
	 * @param $version
	 */
	protected static function _upgrade($version)
	{
		$db = XenForo_Application::get('db');

		$methodTables = '_upgradeTables'.$version;
		$methodAlters = '_upgradeAlters'.$version;
		$methodData = '_upgradeData'.$version;

		if (method_exists(__CLASS__, $methodTables))
		{
			foreach (self::$methodTables() AS $tableName => $tableSql)
			{
				try
				{
					$db->query($tableSql);
				}
				catch (Zend_Db_Exception $e)
				{
					XenForo_Error::logException($e);
				}
			}
		}

		if (method_exists(__CLASS__, $methodAlters))
		{
			foreach (self::$methodAlters() AS $tableName => $alterSql)
			{
				try
				{
					$db->query($alterSql);
				}
				catch (Zend_Db_Exception $e)
				{
					XenForo_Error::logException($e);
				}
			}
		}

		if (method_exists(__CLASS__, $methodData))
		{
			foreach (self::$methodData() AS $tableName => $data)
			{
				try
				{
					$db->query($data);
				}
				catch (Zend_Db_Exception $e)
				{
					XenForo_Error::logException($e);
				}
			}
		}
	}

	/**
	 * @return array
	 */
	public static function installTables()
	{
		$tables = array();

		$tables['sonnb_xengallery_album'] = "
			CREATE TABLE `sonnb_xengallery_album` (
			  `album_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			  `title` varchar(255) CHARACTER SET utf8 NOT NULL,
			  `description` mediumtext CHARACTER SET utf8 NOT NULL,
			  `user_id` int(10) unsigned NOT NULL,
			  `username` varchar(50) COLLATE utf8_bin NOT NULL,
			  `category_id` int(10) unsigned NOT NULL DEFAULT '0',
			  `album_state` enum('visible','moderated','deleted') CHARACTER SET utf8 NOT NULL DEFAULT 'visible',
			  `album_type` tinyint(3) unsigned DEFAULT '0',
			  `comment_count` int(10) unsigned NOT NULL DEFAULT '0',
			  `view_count` int(10) unsigned NOT NULL DEFAULT '0',
			  `photo_count` int(10) unsigned NOT NULL DEFAULT '0',
			  `video_count` int(10) unsigned NOT NULL DEFAULT '0',
			  `audio_count` int(10) unsigned NOT NULL DEFAULT '0',
			  `content_count` int(10) unsigned NOT NULL DEFAULT '0',
			  `likes` int(10) unsigned NOT NULL DEFAULT '0',
			  `like_users` blob NOT NULL,
			  `album_location` varchar(255) COLLATE utf8_bin NOT NULL,
			  `album_hover` blob NOT NULL,
			  `cover_content_type` enum('audio','video','photo') COLLATE utf8_bin NOT NULL DEFAULT 'photo',
			  `cover_content_id` int(10) unsigned NOT NULL,
			  `latest_photo_ids` varbinary(100) NOT NULL DEFAULT '',
			  `latest_video_ids` varbinary(100) NOT NULL DEFAULT '',
			  `latest_audio_ids` varbinary(100) NOT NULL DEFAULT '',
			  `latest_content_ids` varbinary(100) NOT NULL DEFAULT '',
			  `latest_comment_ids` varbinary(100) NOT NULL DEFAULT '',
			  `album_date` int(10) unsigned NOT NULL,
			  `album_updated_date` int(10) unsigned NOT NULL,
			  `album_privacy` blob NOT NULL,
			  `tags` int(10) unsigned NOT NULL DEFAULT '0',
			  `tag_users` blob NOT NULL,
			  `collection_id` int(10) unsigned NOT NULL DEFAULT '0',
			  `album_streams` mediumblob NOT NULL,
			  PRIMARY KEY (`album_id`),
			  KEY `update_date` (`album_updated_date`) USING BTREE,
			  KEY `category_update_date` (`category_id`,`album_updated_date`),
			  KEY `collection_update_date` (`collection_id`,`album_updated_date`) USING BTREE,
			  KEY `user_update_date` (`user_id`,`album_updated_date`) USING BTREE,
			  KEY `date_update` (`album_date`,`album_updated_date`)
			) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
		";

		$tables['sonnb_xengallery_album_view'] = "
			CREATE TABLE `sonnb_xengallery_album_view` (
			  `album_id` INT(10) UNSIGNED NOT NULL
			) ENGINE=MEMORY DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
		";

		$tables['sonnb_xengallery_category'] = "
			CREATE TABLE `sonnb_xengallery_category` (
			  `category_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
			  `title` varchar(255) COLLATE utf8_bin NOT NULL,
			  `description` mediumtext COLLATE utf8_bin NOT NULL,
			  `parent_category_id` INT(10) NOT NULL,
			  `lft` INT(10) UNSIGNED NOT NULL default 0,
			  `rgt` INT(10) UNSIGNED NOT NULL default 0,
			  `album_count` INT(10) UNSIGNED NOT NULL DEFAULT 0,
			  `display_order` INT(10) UNSIGNED NOT NULL DEFAULT 0,
			  `category_breadcrumb` blob NOT NULL,
			  `depth` INT(10) UNSIGNED NOT NULL DEFAULT 0,
			  `category_privacy` blob NOT NULL,
			  PRIMARY KEY (`category_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
		";

		$tables['sonnb_xengallery_comment'] = "
			CREATE TABLE `sonnb_xengallery_comment` (
			  `comment_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
			  `content_type` enum('album','photo','audio','video') COLLATE utf8_bin NOT NULL DEFAULT 'album',
			  `content_id` INT(10) NOT NULL DEFAULT 0,
			  `user_id` INT(10) UNSIGNED NOT NULL,
			  `username` varchar(50) CHARACTER SET utf8 NOT NULL,
			  `message` mediumtext CHARACTER SET utf8 NOT NULL,
			  `likes` INT(10) UNSIGNED NOT NULL DEFAULT 0,
			  `like_users` blob NOT NULL,
			  `comment_state` enum('visible','moderated','deleted') CHARACTER SET utf8 NOT NULL DEFAULT 'visible',
			  `comment_date` INT(10) UNSIGNED NOT NULL,
			  PRIMARY KEY (`comment_id`),
			  KEY `content_type_id_date` (`content_type`,`content_id`,`comment_date`),
			  KEY `content_type_id` (`content_type`, `content_id`) ,
			  KEY `comment_date` (`comment_state`)
			) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
		";

		$tables['sonnb_xengallery_history'] = "
			CREATE TABLE `sonnb_xengallery_history` (
			  `history_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
			  `content_type` enum('album','photo','comment','audio','video') COLLATE utf8_bin NOT NULL DEFAULT 'album',
			  `content_id` INT(10) NOT NULL,
			  `history_type` enum('insert','update','delete') COLLATE utf8_bin NOT NULL,
			  `history_sub_type` enum('') COLLATE utf8_bin NOT NULL,
			  `history_old_data` blob NOT NULL,
			  `history_new_data` blob NOT NULL,
			  `history_date` INT(10) NOT NULL,
			  PRIMARY KEY (`history_id`),
			  KEY `history_id_date` (`history_id`,`history_date`),
			  KEY `content_id_type_date` (`content_type`,`content_id`,`history_date`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
		";

		$tables['sonnb_xengallery_location'] = "
			CREATE TABLE `sonnb_xengallery_location` (
			  `location_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
			  `content_type` enum('album','photo','audio','video') NOT NULL DEFAULT 'album',
			  `content_id` INT(10) UNSIGNED NOT NULL,
			  `location_name` varchar(255) NOT NULL,
			  `location_lat` varchar(25) DEFAULT NULL,
			  `location_lng` varchar(25) DEFAULT NULL,
			  `location_url` varchar(255) NOT NULL,
			  PRIMARY KEY (`location_id`),
			  KEY `content_type_id` (`content_type`,`content_id`),
			  KEY `content_type_id_location` (`content_type`,`content_id`,`location_url`),
			  KEY `location_url` (`location_url`)
			) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
		";

		$tables['sonnb_xengallery_video'] = "
			CREATE TABLE `sonnb_xengallery_video` (
			  `content_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			  `video_type` varchar(20) COLLATE utf8_bin NOT NULL,
			  `video_key` varchar(100) COLLATE utf8_bin NOT NULL,
			  PRIMARY KEY (`content_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
		";

		$tables['sonnb_xengallery_photo'] = "
			CREATE TABLE `sonnb_xengallery_photo` (
			  `content_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
			  `photo_exif` blob NOT NULL,
			  PRIMARY KEY (`content_id`)
			) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
		";

		$tables['sonnb_xengallery_photo_camera'] = "
			CREATE TABLE `sonnb_xengallery_photo_camera` (
			  `camera_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
			  `photo_id` INT(10) UNSIGNED NOT NULL DEFAULT 0,
			  `camera_name` varchar(255) NOT NULL,
			  `camera_url` varchar(255) NOT NULL,
			  PRIMARY KEY (`camera_id`),
			  KEY `camera_name` (`camera_name`),
			  KEY `photo_id` (`photo_id`),
			  KEY `photo_id_camera` (`photo_id`,`camera_url`),
			  KEY `camera_url` (`camera_url`)
			) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
		";

		$tables['sonnb_xengallery_tag'] = "
			CREATE TABLE `sonnb_xengallery_tag` (
			  `tag_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
			  `content_type` enum('album','photo','audio','video') COLLATE utf8_bin NOT NULL DEFAULT 'album',
			  `content_id` INT(10) NOT NULL DEFAULT 0,
			  `user_id` INT(10) UNSIGNED NOT NULL,
			  `username` varchar(50) COLLATE utf8_bin NOT NULL,
			  `tag_x` INT(10) NOT NULL,
			  `tag_y` INT(10) NOT NULL,
			  `tag_state` enum('awaiting','accepted','rejected') COLLATE utf8_bin DEFAULT 'awaiting',
			  `tagger_user_id` INT(10) UNSIGNED NOT NULL,
			  `tagger_username` varchar(50) COLLATE utf8_bin NOT NULL,
			  `tag_date` INT(10) NOT NULL DEFAULT 0,
			  PRIMARY KEY (`tag_id`),
			  KEY `content_user` (`content_type`,`content_id`, `user_id`),
			  KEY `content_type_id` (`content_type`, `content_id`) ,
			  KEY `content_type_state` (`content_type`, `content_id`, `tag_state`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
		";

		$tables['sonnb_xengallery_watch'] = "
			CREATE TABLE `sonnb_xengallery_watch` (
			  `watch_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
			  `content_type` enum('album','photo','audio','video') COLLATE utf8_bin NOT NULL DEFAULT 'album',
			  `content_id` INT(10) NOT NULL DEFAULT 0,
			  `user_id` INT(10) UNSIGNED NOT NULL,
			  `username` varchar(50) CHARACTER SET utf8 NOT NULL,
			  `watch_date` INT(10) UNSIGNED NOT NULL,
			  PRIMARY KEY (`watch_id`),
			  KEY `content_type_id_date` (`content_type`,`content_id`,`watch_date`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
		";

		$tables['sonnb_xengallery_stream'] = "
			CREATE TABLE `sonnb_xengallery_stream` (
			  `stream_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
			  `stream_name` varchar(255) CHARACTER SET utf8 NOT NULL,
			  `content_type` enum('album','photo','audio','video') COLLATE utf8_bin NOT NULL DEFAULT 'photo',
			  `content_id` INT(10) NOT NULL DEFAULT 0,
			  `user_id` INT(10) UNSIGNED NOT NULL,
			  `username` varchar(50) CHARACTER SET utf8 NOT NULL,
			  `stream_date` INT(10) UNSIGNED NOT NULL,
			  PRIMARY KEY (`stream_id`),
			  KEY `name_content_type_id` (`content_type`,`content_id`,`stream_name`),
			  KEY `stream_name` (`stream_name`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
		";

        //TODO: active/de-active ???
		$tables['sonnb_xengallery_collection'] = "
			CREATE TABLE `sonnb_xengallery_collection` (
			  `collection_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
			  `title` varchar(255) COLLATE utf8_bin NOT NULL,
			  `description` mediumtext COLLATE utf8_bin NOT NULL,
			  `thumbnail` varchar(255) COLLATE utf8_bin NOT NULL,
			  `item_count` INT(10) UNSIGNED NOT NULL DEFAULT 0,
			  `last_content_date` INT(10) UNSIGNED NOT NULL DEFAULT 0,
			  PRIMARY KEY (`collection_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
		";

		$tables['sonnb_xengallery_collection_item'] = "
			CREATE TABLE `sonnb_xengallery_collection_item` (
			  `collection_item_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
			  `collection_id` INT(10) UNSIGNED NOT NULL,
			  `content_type` enum('album','photo','audio','video') COLLATE utf8_bin DEFAULT NULL,
			  `content_id` INT(10) UNSIGNED NOT NULL,
			  `collection_date` INT(10) UNSIGNED NOT NULL,
			  `user_id` INT(10) UNSIGNED NOT NULL,
			  `username` varchar(50) COLLATE utf8_bin NOT NULL,
			  PRIMARY KEY (`collection_item_id`),
			  KEY `collection_id` (`collection_id`),
			  KEY `collection_content_type` (`collection_id`,`content_type`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
		";

		$tables['sonnb_xengallery_camera'] = "
			CREATE TABLE `sonnb_xengallery_camera` (
			  `unique_id` INT(11) NOT NULL AUTO_INCREMENT,
			  `camera_id` varchar(50) NOT NULL,
			  `camera_vendor` varchar(100) NOT NULL,
			  `camera_name` varchar(100) NOT NULL,
			  `camera_thumbnail` varchar(255) NOT NULL,
			  `camera_data` blob NOT NULL,
			  `updated_date` INT(10) UNSIGNED NOT NULL,
			  PRIMARY KEY (`unique_id`,`camera_id`),
			  UNIQUE KEY `camera_id` (`camera_id`),
			  KEY `camera_name` (`camera_name`),
			  KEY `name_vendor` (`camera_vendor`,`camera_name`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";

		$tables['sonnb_xengallery_field'] = "
			CREATE TABLE `sonnb_xengallery_field` (
			  `field_id` varchar(25) COLLATE utf8_bin NOT NULL,
			  `title` varchar(255) COLLATE utf8_bin NOT NULL,
			  `description` varchar(500) COLLATE utf8_bin NOT NULL,
			  `category` blob NOT NULL,
			  `content` blob NOT NULL,
			  `field_type` enum('textbox','textarea','select','radio','checkbox','multiselect') COLLATE utf8_bin NOT NULL DEFAULT 'textbox',
			  `field_choices` mediumblob NOT NULL,
			  `required` tinyint(3) unsigned NOT NULL,
			  `display_order` int(10) unsigned NOT NULL,
			  `match_type` enum('regex','url','email','alphanumeric','number','none') COLLATE utf8_bin NOT NULL DEFAULT 'none',
			  `match_regex` varchar(250) COLLATE utf8_bin NOT NULL,
			  `max_length`  int(10) UNSIGNED NOT NULL,
			  `active` tinyint(3) unsigned NOT NULL DEFAULT '0',
			  PRIMARY KEY (`field_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
		";

		$tables['sonnb_xengallery_field_value'] = "
			CREATE TABLE `sonnb_xengallery_field_value` (
			  `field_id` varchar(25) COLLATE utf8_bin NOT NULL,
			  `content_type` enum('audio','video','photo','album') COLLATE utf8_bin NOT NULL,
			  `content_id` int(10) unsigned NOT NULL,
			  `field_value` mediumtext COLLATE utf8_bin NOT NULL,
			  `user_id` int(10) unsigned NOT NULL,
			  `username` varchar(50) CHARACTER SET utf8 NOT NULL,
			  PRIMARY KEY (`field_id`, `content_type`, `content_id`),
			  KEY `content_type_id` (`content_type`,`content_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
		";

		$tables['sonnb_xengallery_content'] = "
			CREATE TABLE `sonnb_xengallery_content` (
			  `content_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			  `content_type` enum('photo','audio','video') COLLATE utf8_bin NOT NULL DEFAULT 'photo',
			  `content_data_id` int(10) unsigned NOT NULL,
			  `album_id` int(10) unsigned NOT NULL,
			  `user_id` int(10) unsigned NOT NULL,
			  `username` varchar(50) CHARACTER SET utf8 NOT NULL,
			  `title` varchar(255) COLLATE utf8_bin NOT NULL DEFAULT '',
			  `description` mediumtext CHARACTER SET utf8 NOT NULL,
			  `position` int(10) unsigned NOT NULL,
			  `comment_count` int(10) unsigned NOT NULL DEFAULT '0',
			  `view_count` int(10) unsigned NOT NULL DEFAULT '0',
			  `tags` int(10) unsigned NOT NULL DEFAULT '0',
			  `tag_users` blob NOT NULL,
			  `likes` int(10) unsigned NOT NULL DEFAULT '0',
			  `like_users` blob NOT NULL,
			  `latest_comment_ids` varbinary(100) NOT NULL DEFAULT '',
			  `content_state` enum('visible','moderated','deleted') CHARACTER SET utf8 NOT NULL DEFAULT 'visible',
			  `collection_id` int(10) unsigned NOT NULL DEFAULT '0',
			  `content_location` varchar(255) COLLATE utf8_bin NOT NULL,
			  `content_date` int(10) unsigned NOT NULL,
			  `content_updated_date` int(10) unsigned NOT NULL,
			  `content_privacy` blob NOT NULL,
			  `content_streams` mediumblob NOT NULL,
			  PRIMARY KEY (`content_id`),
			  KEY `content_date` (`content_date`) USING BTREE,
			  KEY `position` (`position`) USING BTREE,
			  KEY `album_id_position` (`album_id`,`position`) USING BTREE,
			  KEY `collection_content_update` (`collection_id`,`content_updated_date`) USING BTREE,
			  KEY `user_content_date` (`user_id`,`content_updated_date`) USING BTREE,
			  KEY `content_updated_date` (`content_updated_date`) USING BTREE,
			  KEY `content_type_id` (`content_id`,`content_type`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
		";

		$tables['sonnb_xengallery_content_data'] = "
			CREATE TABLE `sonnb_xengallery_content_data` (
			  `content_data_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			  `content_type` enum('photo','video','audio') DEFAULT 'photo',
			  `temp_hash` varchar(32) NOT NULL DEFAULT '',
			  `unassociated` tinyint(3) unsigned NOT NULL,
			  `file_size` int(10) unsigned NOT NULL,
			  `file_hash` varchar(32) NOT NULL,
			  `extension` varchar(5) NOT NULL,
			  `duration` int(10) unsigned NOT NULL,
			  `is_animated` tinyint(3) unsigned NOT NULL DEFAULT '0',
			  `width` int(10) unsigned NOT NULL DEFAULT '0',
			  `height` int(10) unsigned NOT NULL DEFAULT '0',
			  `upload_date` int(10) unsigned NOT NULL,
			  `large_width` int(10) unsigned NOT NULL DEFAULT '0',
			  `large_height` int(10) unsigned NOT NULL DEFAULT '0',
			  `medium_width` int(10) unsigned NOT NULL DEFAULT '0',
			  `medium_height` int(10) unsigned NOT NULL DEFAULT '0',
			  `small_width` int(10) unsigned NOT NULL DEFAULT '0',
			  `small_height` int(10) unsigned NOT NULL DEFAULT '0',
			  `bdattachmentstore_engine` varchar(100) DEFAULT NULL,
			  `bdattachmentstore_options` mediumblob,
			  PRIMARY KEY (`content_data_id`),
			  KEY `temp_has_upload_date` (`temp_hash`,`upload_date`),
			  KEY `unassociate_upload_date` (`unassociated`,`upload_date`),
			  KEY `content_type_id` (`content_data_id`,`content_type`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";

		$tables['sonnb_xengallery_content_view'] = "
			CREATE TABLE `sonnb_xengallery_content_view` (
			  `content_id` int(10) unsigned NOT NULL
			) ENGINE=MEMORY DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
		";

		$tables['sonnb_xengallery_myplaylist'] = "
			CREATE TABLE `sonnb_xengallery_myplaylist` (
			  `playlist_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			  `title` varchar(255) NOT NULL,
			  `description` text NOT NULL,
			  `user_id` int(10) unsigned NOT NULL,
			  `username` varchar(50) NOT NULL,
			  `content_count` int(10) unsigned NOT NULL,
			  `icon_date` int(10) unsigned NOT NULL,
			  `added_date` int(10) unsigned NOT NULL,
			  `updated_date` int(10) unsigned NOT NULL,
			  PRIMARY KEY (`playlist_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";

		$tables['sonnb_xengallery_myplaylist_item'] = "
			CREATE TABLE `sonnb_xengallery_myplaylist_item` (
			  `playlist_item_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
			  `content_id` int(10) unsigned NOT NULL,
			  `content_type` enum('video','audio') NOT NULL,
			  `playlist_id` int(10) unsigned NOT NULL,
			  `added_date` int(10) unsigned NOT NULL,
			  PRIMARY KEY (`playlist_item_id`)
			) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8;
		";

		return $tables;
	}

	/**
	 * @return array
	 */
	public static function installAlters()
	{
		$alters = array();

		$alters['xf_user'] = "ALTER TABLE  xf_user
    							ADD COLUMN sonnb_xengallery_album_count INT(10) UNSIGNED NULL DEFAULT 0,
    						  	ADD COLUMN sonnb_xengallery_photo_count INT(10) UNSIGNED NULL DEFAULT 0,
    						  	ADD COLUMN sonnb_xengallery_video_count INT(10) UNSIGNED NULL DEFAULT 0,
    						  	ADD COLUMN sonnb_xengallery_cover blob NULL;";

		$alters['xf_user_option'] = "ALTER TABLE  xf_user_option
										ADD COLUMN xengallery blob NULL,
										ADD COLUMN sonnb_xengallery_watermark blob NULL;";

		$alters['xf_thread'] = "ALTER TABLE  xf_thread ADD COLUMN sonnb_xengallery_import TINYINT(3) NULL DEFAULT 0;";

		return $alters;
	}

	/**
	 * @return array
	 */
	public static function uninstallAlters()
	{
		$alters = array();

		$alters['xf_user'] = array(
			'sonnb_xengallery_album_count',
			'sonnb_xengallery_photo_count',
			'sonnb_xengallery_video_count',
			'sonnb_xengallery_cover'
		);

		$alters['xf_user_option'] = array('xengallery');
		$alters['xf_thread'] = array('sonnb_xengallery_import');

		return $alters;
	}

	/**
	 * @return array
	 */
	public static function getData()
	{
		$data = array();

		$data['xf_content_type'] = "
			INSERT IGNORE INTO xf_content_type
				(content_type, addon_id, fields)
			VALUES
				('sonnb_xengallery_album', 'sonnb_xengallery', ''),
				('sonnb_xengallery_photo', 'sonnb_xengallery', ''),
				('sonnb_xengallery_video', 'sonnb_xengallery', ''),
				('sonnb_xengallery_comment', 'sonnb_xengallery', '')
		";

		$data['xf_content_type_field'] = "
			INSERT IGNORE INTO xf_content_type_field
				(content_type, field_name, field_value)
			VALUES
				('sonnb_xengallery_album', 'like_handler_class', 'sonnb_XenGallery_LikeHandler_Album'),
				('sonnb_xengallery_album', 'alert_handler_class', 'sonnb_XenGallery_AlertHandler_Album'),
				('sonnb_xengallery_album', 'news_feed_handler_class', 'sonnb_XenGallery_NewsFeedHandler_Album'),
				('sonnb_xengallery_album', 'report_handler_class', 'sonnb_XenGallery_ReportHandler_Album'),
				('sonnb_xengallery_album',  'moderation_queue_handler_class', 'sonnb_XenGallery_ModerationQueueHandler_Album'),
				('sonnb_xengallery_album',  'moderator_log_handler_class',    'sonnb_XenGallery_ModeratorLogHandler_Album'),
				('sonnb_xengallery_album',  'stats_handler_class',    'sonnb_XenGallery_StatsHandler_Album'),
				('sonnb_xengallery_album',  'search_handler_class',   'sonnb_XenGallery_Search_DataHandler_Album'),
    			
				('sonnb_xengallery_photo', 'like_handler_class', 'sonnb_XenGallery_LikeHandler_Photo'),
				('sonnb_xengallery_photo', 'alert_handler_class', 'sonnb_XenGallery_AlertHandler_Photo'),
				('sonnb_xengallery_photo', 'news_feed_handler_class', 'sonnb_XenGallery_NewsFeedHandler_Photo'),
				('sonnb_xengallery_photo', 'report_handler_class', 'sonnb_XenGallery_ReportHandler_Photo'),
				('sonnb_xengallery_photo', 'moderation_queue_handler_class', 'sonnb_XenGallery_ModerationQueueHandler_Photo'),
				('sonnb_xengallery_photo', 'moderator_log_handler_class',    'sonnb_XenGallery_ModeratorLogHandler_Photo'),
				('sonnb_xengallery_photo', 'stats_handler_class',    'sonnb_XenGallery_StatsHandler_Photo'),
				('sonnb_xengallery_photo',  'search_handler_class',   'sonnb_XenGallery_Search_DataHandler_Photo'),

				('sonnb_xengallery_video', 'like_handler_class', 'sonnb_XenGallery_LikeHandler_Video'),
				('sonnb_xengallery_video', 'alert_handler_class', 'sonnb_XenGallery_AlertHandler_Video'),
				('sonnb_xengallery_video', 'news_feed_handler_class', 'sonnb_XenGallery_NewsFeedHandler_Video'),
				('sonnb_xengallery_video', 'report_handler_class', 'sonnb_XenGallery_ReportHandler_Video'),
				('sonnb_xengallery_video', 'moderation_queue_handler_class', 'sonnb_XenGallery_ModerationQueueHandler_Video'),
				('sonnb_xengallery_video', 'moderator_log_handler_class',    'sonnb_XenGallery_ModeratorLogHandler_Video'),
				('sonnb_xengallery_video', 'stats_handler_class',    'sonnb_XenGallery_StatsHandler_Video'),
				('sonnb_xengallery_video',  'search_handler_class',   'sonnb_XenGallery_Search_DataHandler_Video'),
    			
				('sonnb_xengallery_comment', 'like_handler_class', 'sonnb_XenGallery_LikeHandler_Comment'),
				('sonnb_xengallery_comment', 'alert_handler_class', 'sonnb_XenGallery_AlertHandler_Comment'),
				('sonnb_xengallery_comment', 'news_feed_handler_class', 'sonnb_XenGallery_NewsFeedHandler_Comment'),
				('sonnb_xengallery_comment', 'report_handler_class', 'sonnb_XenGallery_ReportHandler_Comment'),
				('sonnb_xengallery_comment', 'moderation_queue_handler_class', 'sonnb_XenGallery_ModerationQueueHandler_Comment'),
				('sonnb_xengallery_comment', 'moderator_log_handler_class',    'sonnb_XenGallery_ModeratorLogHandler_Comment'),
				('sonnb_xengallery_comment', 'stats_handler_class',    'sonnb_XenGallery_StatsHandler_Comment')
		";

		$data['sonnb_xengallery_camera'] = sonnb_XenGallery_InstallCamera::getData();

		return $data;
	}

	/**
	 * @return array
	 */
	protected static function _upgradeData107()
	{
		$data = array();

		$data['xf_content_type_field'] = "
			INSERT IGNORE INTO xf_content_type_field
				(content_type, field_name, field_value)
			VALUES
				('sonnb_xengallery_album',  'search_handler_class',   'sonnb_XenGallery_Search_DataHandler_Album'),
				('sonnb_xengallery_photo',  'search_handler_class',   'sonnb_XenGallery_Search_DataHandler_Photo')
		";

		return $data;
	}

	/**
	 * @return array
	 */
	protected static function _upgradeAlters109()
	{
		$alters = array();

		$alters['xf_thread'] = "ALTER TABLE  xf_thread ADD COLUMN sonnb_xengallery_import TINYINT(3) NULL DEFAULT 0;";

		return $alters;
	}

	/**
	 * @return array
	 */
	protected static function _upgradeAlters10012()
	{
		$alters = array();

		$alters['sonnb_xengallery_category'] = "ALTER TABLE sonnb_xengallery_category ADD COLUMN category_privacy blob NOT NULL;";

		return $alters;
	}

	/**
	 * @return array
	 */
	protected static function _upgradeAlters10100()
	{
		$alters = array();

		$alters['sonnb_xengallery_photo_data'] = "ALTER TABLE sonnb_xengallery_photo_data
													ADD COLUMN bdattachmentstore_engine VARCHAR(100),
													ADD COLUMN bdattachmentstore_options MEDIUMBLOB,

													ADD COLUMN large_width INT(10) UNSIGNED NOT NULL DEFAULT 0,
                                                    ADD COLUMN large_height INT(10) UNSIGNED NOT NULL DEFAULT 0,
													ADD COLUMN medium_width INT(10) UNSIGNED NOT NULL DEFAULT 0,
                                                    ADD COLUMN medium_height INT(10) UNSIGNED NOT NULL DEFAULT 0,
													ADD COLUMN small_width INT(10) UNSIGNED NOT NULL DEFAULT 0,
                                                    ADD COLUMN small_height INT(10) UNSIGNED NOT NULL DEFAULT 0,

                                                    ADD COLUMN is_animated TINYINT UNSIGNED NOT NULL DEFAULT 0;";

		return $alters;
	}

	/**
	 * @return array
	 */
	protected static function _upgradeAlters10101()
	{
		$alters = array();

		$alters['sonnb_xengallery_photo'] = "ALTER TABLE sonnb_xengallery_photo
												ADD COLUMN title varchar(255) NOT NULL DEFAULT '' AFTER `photo_id`;";

		return $alters;
	}

	/**
	 * @return array
	 */
	protected static function _upgradeAlters10102()
	{
		$alters = array();

		$alters['sonnb_xengallery_album1'] = "ALTER TABLE sonnb_xengallery_album
												ADD COLUMN video_count  int(10) UNSIGNED NOT NULL DEFAULT 0 AFTER photo_count;";
		$alters['sonnb_xengallery_album2'] = "ALTER TABLE sonnb_xengallery_album
												ADD COLUMN audio_count  int(10) UNSIGNED NOT NULL DEFAULT 0 AFTER photo_count;";

		$alters['sonnb_xengallery_collection_item'] = "ALTER TABLE sonnb_xengallery_collection_item
												MODIFY COLUMN `content_type`  enum('album','photo','audio','video') CHARACTER SET utf8 COLLATE utf8_bin NULL DEFAULT NULL;";
		$alters['sonnb_xengallery_comment'] = "ALTER TABLE sonnb_xengallery_comment
												MODIFY COLUMN `content_type`  enum('album','photo','audio','video') CHARACTER SET utf8 COLLATE utf8_bin NULL DEFAULT NULL;";
		$alters['sonnb_xengallery_location'] = "ALTER TABLE sonnb_xengallery_location
												MODIFY COLUMN `content_type`  enum('album','photo','audio','video') CHARACTER SET utf8 COLLATE utf8_bin NULL DEFAULT NULL;";
		$alters['sonnb_xengallery_stream'] = "ALTER TABLE sonnb_xengallery_stream
												MODIFY COLUMN `content_type`  enum('album','photo','audio','video') CHARACTER SET utf8 COLLATE utf8_bin NULL DEFAULT NULL;";
		$alters['sonnb_xengallery_tag'] = "ALTER TABLE sonnb_xengallery_tag
												MODIFY COLUMN `content_type`  enum('album','photo','audio','video') CHARACTER SET utf8 COLLATE utf8_bin NULL DEFAULT NULL;";
		$alters['sonnb_xengallery_watch'] = "ALTER TABLE sonnb_xengallery_watch
												MODIFY COLUMN `content_type`  enum('album','photo','audio','video') CHARACTER SET utf8 COLLATE utf8_bin NULL DEFAULT NULL;";
		$alters['sonnb_xengallery_import'] = "ALTER TABLE sonnb_xengallery_import
												MODIFY COLUMN `content_type`  enum('album','photo','comment','audio','video') CHARACTER SET utf8 COLLATE utf8_bin NULL DEFAULT NULL;";
		$alters['sonnb_xengallery_history'] = "ALTER TABLE sonnb_xengallery_history
												MODIFY COLUMN `content_type`  enum('album','photo','comment','audio','video') CHARACTER SET utf8 COLLATE utf8_bin NULL DEFAULT NULL;";

		return $alters;
	}

	/**
	 * @return array
	 */
	protected static function _upgradeTables20000()
	{
		$tables = array();

		$tables['sonnb_xengallery_photo_bk'] = "RENAME TABLE sonnb_xengallery_photo TO sonnb_xengallery_photo_bk;";
		$tables['sonnb_xengallery_photo_data_bk'] = "RENAME TABLE sonnb_xengallery_photo_data TO sonnb_xengallery_photo_data_bk;";
		$tables['sonnb_xengallery_photo_view'] = "RENAME TABLE sonnb_xengallery_photo_view TO sonnb_xengallery_photo_view_bk;";

		$needed = array(
			'sonnb_xengallery_video',
			'sonnb_xengallery_photo',

			'sonnb_xengallery_content',
			'sonnb_xengallery_content_data',
			'sonnb_xengallery_content_view',

			'sonnb_xengallery_field',
			'sonnb_xengallery_field_value',

			'sonnb_xengallery_myplaylist',
			'sonnb_xengallery_myplaylist_item'
		);

		$tables = array_merge($tables, array_intersect_key(self::installTables(), array_flip($needed)));

		return $tables;
	}

	/**
	 * @return array
	 */
	protected static function _upgradeAlters20000()
	{
		$alters = array();

		$alters['xf_user_alter'] = "ALTER TABLE xf_user MODIFY COLUMN sonnb_xengallery_cover blob NULL;";
		$alters['xf_user_reset'] = "UPDATE xf_user SET sonnb_xengallery_cover = 'a:0:{}';";

		$alters['xf_user'] = "ALTER TABLE  xf_user ADD COLUMN sonnb_xengallery_video_count INT(10) UNSIGNED NULL DEFAULT 0;";

		$alters['sonnb_xengallery_album1'] = "ALTER TABLE  sonnb_xengallery_album ADD COLUMN content_count int(10) unsigned NOT NULL DEFAULT '0';";
		$alters['sonnb_xengallery_album2'] = "ALTER TABLE  sonnb_xengallery_album ADD COLUMN video_count int(10) unsigned NOT NULL DEFAULT '0';";
		$alters['sonnb_xengallery_album3'] = "ALTER TABLE  sonnb_xengallery_album ADD COLUMN audio_count int(10) unsigned NOT NULL DEFAULT '0';";
		$alters['sonnb_xengallery_album4'] = "ALTER TABLE  sonnb_xengallery_album
												ADD COLUMN cover_content_type enum('audio','video','photo') COLLATE utf8_bin NOT NULL DEFAULT 'photo';";
		$alters['sonnb_xengallery_album5'] = "ALTER TABLE  sonnb_xengallery_album ADD COLUMN cover_content_id int(10) unsigned NOT NULL;";
		$alters['sonnb_xengallery_album6'] = "ALTER TABLE  sonnb_xengallery_album ADD COLUMN latest_video_ids varbinary(100) NOT NULL DEFAULT '';";
		$alters['sonnb_xengallery_album7'] = "ALTER TABLE  sonnb_xengallery_album ADD COLUMN latest_audio_ids varbinary(100) NOT NULL DEFAULT '';";
		$alters['sonnb_xengallery_album8'] = "ALTER TABLE  sonnb_xengallery_album ADD COLUMN latest_content_ids varbinary(100) NOT NULL DEFAULT '';";

		$alters['sonnb_xengallery_album9'] = "UPDATE sonnb_xengallery_album
												SET content_count = photo_count, cover_content_id = cover_photo_id, cover_content_type = 'photo';";

		$alters['sonnb_xengallery_content_data'] = "
			INSERT INTO sonnb_xengallery_content_data
				(content_data_id, content_type, temp_hash, unassociated, file_size, file_hash, extension, duration, is_animated, width, height, upload_date, large_width, large_height, medium_width, medium_height, small_width, small_height, bdattachmentstore_engine, bdattachmentstore_options)
			SELECT photo_data_id, 'photo', temp_hash, unassociated, file_size, file_hash, extension, 0, is_animated, width, height, upload_date, large_width, large_height, medium_width, medium_height, small_width, small_height, bdattachmentstore_engine, bdattachmentstore_options
			FROM sonnb_xengallery_photo_data_bk
		";

		$alters['sonnb_xengallery_content'] = "
			INSERT INTO sonnb_xengallery_content
				(content_id, content_type, content_data_id, album_id, user_id, username, title, description, position, content_state, comment_count, view_count, tags, tag_users, likes, like_users, latest_comment_ids, collection_id, content_location, content_date, content_updated_date, content_privacy, content_streams)
			SELECT photo_id, 'photo', photo_data_id, album_id, user_id, username, title, description, position, photo_state, comment_count, view_count, tags, tag_users, likes, like_users, latest_comment_ids, collection_id, photo_location, photo_date, photo_updated_date, photo_privacy, photo_streams
			FROM sonnb_xengallery_photo_bk
		";

		$alters['sonnb_xengallery_photo'] = "
			INSERT INTO sonnb_xengallery_photo
				(content_id, photo_exif)
			SELECT photo_id, photo_exif
			FROM sonnb_xengallery_photo_bk
		";

		return $alters;
	}

	/**
	 * @return array
	 */
	protected static function _upgradeData20000()
	{
		$data = array();

		$data['xf_content_type'] = "
			INSERT IGNORE INTO xf_content_type
				(content_type, addon_id, fields)
			VALUES
				('sonnb_xengallery_video', 'sonnb_xengallery', '')
		";

		$data['xf_content_type_field'] = "
			INSERT IGNORE INTO xf_content_type_field
				(content_type, field_name, field_value)
			VALUES
				('sonnb_xengallery_video', 'like_handler_class', 'sonnb_XenGallery_LikeHandler_Video'),
				('sonnb_xengallery_video', 'alert_handler_class', 'sonnb_XenGallery_AlertHandler_Video'),
				('sonnb_xengallery_video', 'news_feed_handler_class', 'sonnb_XenGallery_NewsFeedHandler_Video'),
				('sonnb_xengallery_video', 'report_handler_class', 'sonnb_XenGallery_ReportHandler_Video'),
				('sonnb_xengallery_video', 'moderation_queue_handler_class', 'sonnb_XenGallery_ModerationQueueHandler_Video'),
				('sonnb_xengallery_video', 'moderator_log_handler_class',    'sonnb_XenGallery_ModeratorLogHandler_Video'),
				('sonnb_xengallery_video', 'stats_handler_class',    'sonnb_XenGallery_StatsHandler_Video'),
				('sonnb_xengallery_video', 'search_handler_class',   'sonnb_XenGallery_Search_DataHandler_Video')
		";

		return $data;
	}

	protected static function _upgradeAlters20004()
	{
		$alters = array();

		$alters['sonnb_xengallery_import'] = "DROP TABLE sonnb_xengallery_import";

		return $alters;
	}

	protected static function _upgradeAlters20100()
	{
		$alters = array();

		$alters['xf_user_option'] = "ALTER TABLE `xf_user_option` ADD COLUMN `sonnb_xengallery_watermark` blob NULL";
		$alters['sonnb_xengallery_collection'] = "ALTER TABLE `sonnb_xengallery_collection` ADD COLUMN `last_content_date` INT(10) UNSIGNED NOT NULL DEFAULT 0";

		return $alters;
	}
}