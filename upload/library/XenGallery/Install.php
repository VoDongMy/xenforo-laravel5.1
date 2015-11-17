<?php

class XenGallery_Install
{
	protected static $_db = null;

	protected static $_rebuildCategories = false;

	protected static $_version = 0;

	protected static $_versions = array(
		110, 112, 120,
		2000070, 2010070, 2010670,
		901000170, 901010070, 901010170
	);

	protected static $_contentTypes = array(
		'xengallery', 'xengallery_media', 'xengallery_album',
		'xengallery_category', 'xengallery_comment', 'xengallery_rating',
		'xengallery_user_album', 'xengallery_user_media'
	);

	protected static $_contentTypeTables = array(
		'xf_content_type', 'xf_content_type_field', 'xf_deletion_log',
		'xf_deletion_log', 'xf_liked_content',
		'xf_moderation_queue', 'xf_moderator_log',
		'xf_news_feed', 'xf_report',
		'xf_search_index', 'xf_user_alert'
	);

	protected static function _canBeInstalled(&$error)
	{
		if (XenForo_Application::$versionId < 1050070)
		{
			$error = 'This add-on requires XenForo 1.5.0 or higher.';
			return false;
		}

		return true;
	}

	public static function installer($previous)
	{
		if (!self::_canBeInstalled($error))
		{
			throw new XenForo_Exception($error, true);
		}

		self::$_version = is_array($previous) ? $previous['version_id'] : 0;

		self::stepApplyPermissionDefaults();
		self::stepRouteFilters();
		self::stepTables();
		self::stepUserAlters();
		self::stepVersionAlters();
		self::stepData();
		self::stepVersions();

		// Rebuilds the field cache after the default Example Category has been inserted.
		if (!self::$_version)
		{
			XenForo_Model::create('XenGallery_Model_Field')->rebuildFieldCategoryAssociationCache(1);
		}
	}

	public static function stepApplyPermissionDefaults()
	{
		$version = self::$_version;

		if (!$version)
		{
			// XenForo Media Gallery Media Permissions
			self::applyGlobalPermission('xengallery', 'view', 'general', 'viewNode', false);
			self::applyGlobalPermission('xengallery', 'add', 'forum', 'postThread', false);
			self::applyGlobalPermission('xengallery', 'bypassModQueueMedia', 'general', 'followModerationRules', false);
			self::applyGlobalPermission('xengallery', 'delete', 'forum', 'deleteOwnPost', false);
			self::applyGlobalPermission('xengallery', 'edit', 'forum', 'editOwnPost', false);
			self::applyGlobalPermission('xengallery', 'like', 'forum', 'like', false);
			self::applyGlobalPermission('xengallery', 'move', 'forum', 'editOwnPost', false);
			self::applyGlobalPermission('xengallery', 'editUrl', 'forum', 'editOwnPost', false);
			self::applyGlobalPermission('xengallery', 'viewRatings', 'general', 'viewNode', false);
			self::applyGlobalPermission('xengallery', 'rate', 'forum', 'like', false);
			self::applyGlobalPermission('xengallery', 'download', 'general', 'viewNode', false);
			self::applyGlobalPermission('xengallery', 'rotate', 'forum', 'editOwnPost', false);
			self::applyGlobalPermission('xengallery', 'crop', 'forum', 'editOwnPost', false);
			self::applyGlobalPermission('xengallery', 'flip', 'forum', 'editOwnPost', false);
			self::applyGlobalPermission('xengallery', 'avatar', 'avatar', 'allowed', true);
			self::applyGlobalPermission('xengallery', 'thumbnail', 'forum', 'editOwnPost', false);

			// XenForo Media Gallery Media Moderator Permissions
			self::applyGlobalPermission('xengallery', 'viewDeleted', 'forum', 'viewDeleted', true);
			self::applyGlobalPermission('xengallery', 'deleteAny', 'forum', 'deleteAnyPost', true);
			self::applyGlobalPermission('xengallery', 'editAny', 'forum', 'editAnyPost', true);
			self::applyGlobalPermission('xengallery', 'moveAny', 'forum', 'editAnyPost', true);
			self::applyGlobalPermission('xengallery', 'moveToAnyAlbum', 'forum', 'editAnyPost', true);
			self::applyGlobalPermission('xengallery', 'editUrlAny', 'forum', 'editAnyPost', true);
			self::applyGlobalPermission('xengallery', 'viewOverride', 'general', 'bypassUserPrivacy', false);
			self::applyGlobalPermission('xengallery', 'hardDeleteAny', 'forum', 'hardDeleteAnyPost', true);
			self::applyGlobalPermission('xengallery', 'approveUnapproveMedia', 'forum', 'approveUnapprove', true);
			self::applyGlobalPermission('xengallery', 'flipAny', 'forum', 'editAnyPost', true);
			self::applyGlobalPermission('xengallery', 'rotateAny', 'forum', 'editAnyPost', true);
			self::applyGlobalPermission('xengallery', 'cropAny', 'forum', 'hardDeleteAnyPost', true);
			self::applyGlobalPermission('xengallery', 'avatarAny', 'avatar', 'allowed', true);
			self::applyGlobalPermission('xengallery', 'warn', 'forum', 'warn', true);
			self::applyGlobalPermission('xengallery', 'thumbnailAny', 'forum', 'editAnyPost', true);

			// XenForo Media Gallery Album Permissions
			self::applyGlobalPermission('xengallery', 'viewAlbums', 'general', 'viewNode', false);
			self::applyGlobalPermission('xengallery', 'createAlbum', 'forum', 'postThread', false);
			self::applyGlobalPermission('xengallery', 'uploadImage', 'forum', 'postThread', false);
			self::applyGlobalPermission('xengallery', 'embedVideo', 'forum', 'postThread', false);
			self::applyGlobalPermission('xengallery', 'deleteAlbum', 'forum', 'deleteOwnPost', false);
			self::applyGlobalPermission('xengallery', 'editAlbum', 'forum', 'editOwnPost', false);
			self::applyGlobalPermission('xengallery', 'likeAlbum', 'forum', 'like', false);
			self::applyGlobalPermission('xengallery', 'rateAlbum', 'forum', 'like', false);
			self::applyGlobalPermission('xengallery', 'changeViewPermission', 'forum', 'editOwnPost', false);
			self::applyGlobalPermission('xengallery', 'changeAddPermission', 'forum', 'editOwnPost', false);
			self::applyGlobalPermission('xengallery', 'customOrder', 'forum', 'editOwnPost', false);
			self::applyGlobalPermission('xengallery', 'albumThumbnail', 'forum', 'editOwnPost', false);

			// XenForo Media Gallery Album Moderator Permissions
			self::applyGlobalPermission('xengallery', 'viewDeletedAlbums', 'forum', 'viewDeleted', true);
			self::applyGlobalPermission('xengallery', 'deleteAlbumAny', 'forum', 'deleteAnyPost', true);
			self::applyGlobalPermission('xengallery', 'editAlbumAny', 'forum', 'editAnyPost', true);
			self::applyGlobalPermission('xengallery', 'hardDeleteAlbumAny', 'forum', 'hardDeleteAnyPost', true);
			self::applyGlobalPermission('xengallery', 'changeViewPermissionAny', 'forum', 'editAnyPost', true);
			self::applyGlobalPermission('xengallery', 'changeAddPermissionAny', 'forum', 'editAnyPost', true);
			self::applyGlobalPermission('xengallery', 'customOrderAny', 'forum', 'editAnyPost', true);
			self::applyGlobalPermission('xengallery', 'warnAlbum', 'forum', 'warn', true);
			self::applyGlobalPermission('xengallery', 'albumThumbnailAny', 'forum', 'editAnyPost', true);

			// XenForo Media Gallery Category Permissions
			self::applyGlobalPermission('xengallery', 'viewCategories', 'general', 'viewNode', false);

			// XenForo Media Gallery Watermark Permissions
			self::applyGlobalPermission('xengallery', 'bypassWatermark', 'forum', 'approveUnapprove', true);
			self::applyGlobalPermission('xengallery', 'addWatermark', 'forum', 'editOwnPost', false);
			self::applyGlobalPermission('xengallery', 'removeWatermark', 'forum', 'deleteOwnPost', false);

			// XenForo Media Gallery Watermark Moderator Permissions
			self::applyGlobalPermission('xengallery', 'addWatermarkAny', 'forum', 'editAnyPost', true);
			self::applyGlobalPermission('xengallery', 'removeWatermarkAny', 'forum', 'deleteAnyPost', true);

			// XenForo Media Gallery Comment Permissions
			self::applyGlobalPermission('xengallery', 'viewComments', 'general', 'viewNode', false);
			self::applyGlobalPermission('xengallery', 'addComment', 'forum', 'postThread', false);
			self::applyGlobalPermission('xengallery', 'bypassModQueueComment', 'general', 'followModerationRules', false);
			self::applyGlobalPermission('xengallery', 'deleteComment', 'forum', 'deleteOwnPost', false);
			self::applyGlobalPermission('xengallery', 'editComment', 'forum', 'editOwnPost', false);
			self::applyGlobalPermission('xengallery', 'likeComment', 'forum', 'like', false);

			// XenForo Media Gallery Comment Moderator Permissions
			self::applyGlobalPermission('xengallery', 'viewDeletedComments', 'forum', 'viewDeleted', true);
			self::applyGlobalPermission('xengallery', 'deleteCommentAny', 'forum', 'deleteAnyPost', true);
			self::applyGlobalPermission('xengallery', 'editCommentAny', 'forum', 'editAnyPost', true);
			self::applyGlobalPermission('xengallery', 'warnComment', 'forum', 'warn', true);
			self::applyGlobalPermission('xengallery', 'approveUnapproveComment', 'forum', 'approveUnapprove', true);

			// XenForo Media Gallery User Tagging Permissions
			self::applyGlobalPermission('xengallery', 'viewTag', 'general', 'viewNode', false);
			self::applyGlobalPermission('xengallery', 'tagSelf', 'forum', 'postThread', false);
			self::applyGlobalPermission('xengallery', 'tag', 'forum', 'postThread', false);
			self::applyGlobalPermission('xengallery', 'tagAny', 'forum', 'postThread', false);
			self::applyGlobalPermission('xengallery', 'deleteTagSelf', 'forum', 'deleteOwnPost', false);
			self::applyGlobalPermission('xengallery', 'deleteTag', 'forum', 'deleteOwnPost', false);
			self::applyGlobalPermission('xengallery', 'deleteTagAny', 'forum', 'deleteAnyPost', true);
			self::applyGlobalPermission('xengallery', 'bypassApproval', 'general', 'bypassUserPrivacy', false);

			// XenForo Media Gallery General Media Quotas
			self::applyGlobalPermission('xengallery', 'generalStorageQuota', 'use_int', 50, false);

			// XenForo Media Gallery Image Media Quotas
			self::applyGlobalPermission('xengallery', 'imageWidth', 'use_int', -1, false);
			self::applyGlobalPermission('xengallery', 'imageHeight', 'use_int', -1, false);
			self::applyGlobalPermission('xengallery', 'imageFileSize', 'use_int', 10, false);
			self::applyGlobalPermission('xengallery', 'imageMaxItems', 'use_int', 10, false);
		}

		if (!$version || $version < 901000170)
		{
			self::applyGlobalPermission('xengallery', 'hardDeleteCommentAny', 'forum', 'hardDeleteAnyPost', true);
		}

		if (!$version || $version < 901010070)
		{
			self::applyGlobalPermission('xengallery', 'tagOwnMedia', 'xengallery', 'add', false);
			self::applyGlobalPermission('xengallery', 'manageOthersTagsOwnMedia', 'xengallery', 'edit', false);
			self::applyGlobalPermission('xengallery', 'manageAnyTag', 'xengallery', 'editAny', true);

			// XenForo Media Gallery Video Media Quotas
			self::applyGlobalPermission('xengallery', 'videoFileSize', 'use_int', 20, false);
			self::applyGlobalPermission('xengallery', 'videoMaxItems', 'use_int', 5, false);

			// Note: Absence of default value for "uploadVideo" permission is intentional. Off by default.
		}
	}

	public static function applyGlobalPermission($applyGroupId, $applyPermissionId, $dependGroupId = null, $dependPermissionId = null, $checkModerator = true)
	{
		$db = self::_getDb();

		XenForo_Db::beginTransaction($db);

		if ($dependGroupId == 'use_int')
		{
			self::_executeQuery("
				INSERT IGNORE INTO xf_permission_entry
					(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT user_group_id, user_id, ?, ?, ?, ?
				FROM xf_permission_entry
				WHERE user_group_id = ?
			", array(
					$applyGroupId,
					$applyPermissionId,
					$dependGroupId,
					$dependPermissionId,
					XenForo_Model_User::$defaultRegisteredGroupId)
			);
		}
		else if ($dependGroupId && $dependPermissionId)
		{
			self::_executeQuery("
				INSERT IGNORE INTO xf_permission_entry
					(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT user_group_id, user_id, ?, ?, 'allow', 0
				FROM xf_permission_entry
				WHERE permission_group_id = ?
					AND permission_id = ?
					AND permission_value = 'allow'
			", array($applyGroupId, $applyPermissionId, $dependGroupId, $dependPermissionId));
		}
		else
		{
			self::_executeQuery("
				INSERT IGNORE INTO xf_permission_entry
					(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				SELECT DISTINCT user_group_id, user_id, ?, ?, 'allow', 0
				FROM xf_permission_entry
			", array($applyGroupId, $applyPermissionId));
		}

		if ($checkModerator)
		{
			$moderators = self::_getGlobalModPermissions();
			foreach ($moderators AS $userId => $permissions)
			{
				if (!$dependGroupId || !$dependPermissionId || !empty($permissions[$dependGroupId][$dependPermissionId]))
				{
					$permissions[$applyGroupId][$applyPermissionId] = '1'; // string 1 is stored by the code
					self::_updateGlobalModPermissions($userId, $permissions);
				}
			}
		}

		XenForo_Db::commit($db);
	}

	protected static $_globalModPermCache = null;

	protected static function _getGlobalModPermissions()
	{
		if (self::$_globalModPermCache === null)
		{
			$moderators = self::_getDb()->fetchPairs('
				SELECT user_id, moderator_permissions
				FROM xf_moderator
			');
			foreach ($moderators AS &$permissions)
			{
				$permissions = unserialize($permissions);
			}

			self::$_globalModPermCache = $moderators;
		}

		return self::$_globalModPermCache;
	}

	protected static function _updateGlobalModPermissions($userId, array $permissions)
	{
		self::$_globalModPermCache[$userId] = $permissions;

		self::_executeQuery('
			UPDATE xf_moderator
			SET moderator_permissions = ?
			WHERE user_id = ?
		', array(serialize($permissions), $userId));
	}

	public static function stepRouteFilters()
	{
		try
		{
			self::_insertRouteFilters();
		}
		catch (Zend_Db_Exception $e) {}
	}

	public static function stepTables()
	{
		foreach (self::_getTables() AS $tableSql)
		{
			self::_executeQuery($tableSql);
		}
	}

	public static function stepUserAlters()
	{
		$db = self::_getDb();

		$oldExists = $db->fetchOne("SHOW COLUMNS FROM xf_user LIKE 'media_count'");
		$newExists = $db->fetchOne("SHOW COLUMNS FROM xf_user LIKE 'xengallery_media_count'");

		if ($oldExists && !$newExists)
		{
			self::_executeQuery("ALTER TABLE xf_user CHANGE COLUMN media_count xengallery_media_count INT(10) UNSIGNED NOT NULL DEFAULT '0'");
		}

		if (!$oldExists && !$newExists)
		{
			self::_executeQuery("
				ALTER TABLE xf_user
				ADD xengallery_media_count INT(10) unsigned NOT NULL DEFAULT '0',
				ADD INDEX xengallery_media_count (xengallery_media_count)
			");
		}

		if ($oldExists && $newExists)
		{
			self::_executeQuery("ALTER TABLE xf_user DROP COLUMN media_count");
		}

		$oldExists = $db->fetchOne("SHOW COLUMNS FROM xf_user LIKE 'album_count'");
		$newExists = $db->fetchOne("SHOW COLUMNS FROM xf_user LIKE 'xengallery_album_count'");

		if ($oldExists && !$newExists)
		{
			self::_executeQuery("ALTER TABLE xf_user CHANGE COLUMN album_count xengallery_album_count INT(10) UNSIGNED NOT NULL DEFAULT '0'");
		}

		if (!$oldExists && !$newExists)
		{
			self::_executeQuery("
				ALTER TABLE xf_user
				ADD xengallery_album_count INT(10) unsigned NOT NULL DEFAULT '0' AFTER xengallery_media_count,
				ADD INDEX xengallery_album_count (xengallery_album_count)
			");
		}

		if ($oldExists && $newExists)
		{
			self::_executeQuery("ALTER TABLE xf_user DROP COLUMN album_count");
		}

		$oldExists = $db->fetchOne("SHOW COLUMNS FROM xf_user LIKE 'media_quota'");
		$newExists = $db->fetchOne("SHOW COLUMNS FROM xf_user LIKE 'xengallery_media_quota'");

		if ($oldExists && !$newExists)
		{
			self::_executeQuery("ALTER TABLE xf_user CHANGE COLUMN media_quota xengallery_media_quota INT(10) UNSIGNED NOT NULL DEFAULT '0'");
		}

		if (!$oldExists && !$newExists)
		{
			self::_executeQuery("ALTER TABLE xf_user ADD xengallery_media_quota INT(10) unsigned NOT NULL DEFAULT '0' AFTER xengallery_album_count");
		}

		if ($oldExists && $newExists)
		{
			self::_executeQuery("ALTER TABLE xf_user DROP COLUMN media_quota");
		}

		self::_executeQuery("ALTER TABLE xf_user_option ADD COLUMN xengallery_default_media_watch_state enum('','watch_no_email','watch_email') NOT NULL DEFAULT 'watch_no_email'");

		self::_executeQuery("ALTER TABLE xf_user_option ADD COLUMN xengallery_default_album_watch_state enum('','watch_no_email','watch_email') NOT NULL DEFAULT 'watch_no_email'");

		self::_executeQuery("ALTER TABLE xf_user_option ADD COLUMN xengallery_default_category_watch_state enum('','watch_no_email','watch_email') NOT NULL DEFAULT 'watch_no_email'");

		self::_executeQuery("ALTER TABLE xf_user_option ADD COLUMN xengallery_unviewed_media_count TINYINT(3) NOT NULL DEFAULT 1");
	}

	public static function stepVersionAlters()
	{
		$version = self::$_version;

		if (!$version)
		{
			return;
		}

		foreach (self::$_versions AS $key)
		{
			if ($version < $key)
			{
				foreach (self::_getAlters($key) AS $alterSql)
				{
					self::_executeQuery($alterSql);
				}
			}
		}
	}

	public static function stepData()
	{
		foreach (self::_getData() AS $dataSql)
		{
			self::_executeQuery($dataSql);
		}

		if (!self::$_version)
		{
			try
			{
				self::setCategoryViewPermissions(array(1));
			}
			catch(Zend_Db_Exception $e) {}
		}
	}

	public static function stepVersions()
	{
		$version = self::$_version;

		if (!$version)
		{
			return;
		}

		$db = self::_getDb();

		if ($version < 2000070)
		{
			$categoryIds = $db->fetchCol('
				SELECT category_id
				FROM xengallery_category
			');

			if (is_array($categoryIds))
			{
				self::$_rebuildCategories = true;
				foreach ($categoryIds AS $categoryId)
				{
					try
					{
						$db->insert('xengallery_field_category', array(
							'field_id' => 'caption',
							'category_id' => $categoryId)
						);
					}
					catch (Zend_Db_Exception $e) {}
				}
			}
		}

		$key = 'cat_view';
		foreach (self::_getAlters($key) AS $alterSql)
		{
			self::_executeQuery($alterSql);
		}

		if (self::$_rebuildCategories)
		{
			try
			{
				self::setCategoryViewPermissions($categoryIds);
			}
			catch(Zend_Db_Exception $e) {}
		}

		self::mediaCaptionUpgrade();

		if ($version < 2010170)
		{
			XenForo_Application::defer('XenGallery_Deferred_AlbumPermissions', array(), 'XFMGAlbumPermissions', true, XenForo_Application::$time + 5);
		}

		if ($version < 901000070)
		{
			$fontAwesomeOption = XenForo_Application::getOptions()->xengalleryFontAwesome;

			if ($fontAwesomeOption == 'gallery' || $fontAwesomeOption == 'all')
			{
				$db->update('xf_option', array(
					'option_value' => 1
				), "option_id = 'xengalleryFontAwesome'");
			}

			self::_deleteRouteFilters();
		}

		if ($version < 901000170)
		{
			XenForo_Application::defer('XenGallery_Deferred_MediaRating', array(), 'XFMGMediaRatingRebuild', true, XenForo_Application::$time + 5);
			XenForo_Application::defer('XenGallery_Deferred_AlbumRating', array(), 'XFMGAlbumRatingRebuild', true, XenForo_Application::$time + 6);

			$blockPositionOption = XenForo_Application::getOptions()->xengalleryForumListPosition;
			if (!is_array($blockPositionOption))
			{
				$db->update('xf_option', array(
					'option_value' => serialize(array($blockPositionOption => 1))
				), "option_id = 'xengalleryForumListPosition'");
			}
		}

		if ($version < 901000470)
		{
			XenForo_Application::defer('XenGallery_Deferred_Upgrade_901000470', array(), 'XFMGAddIndexes', true, time() + 1);
		}

		if ($version < 901000570)
		{
			$db->query("UPDATE xengallery_media SET media_privacy = 'category' WHERE category_id > 0 AND media_privacy <> 'category'");

			XenForo_Application::defer('XenGallery_Deferred_Upgrade_901000570', array(), 'XFMGAlbumPermFix', true, time() + 1);

			$mediaModel = XenForo_Model::create('XenGallery_Model_Media');
			XenForo_Application::setSimpleCacheData('xengalleryRandomMediaCache', $mediaModel->generateRandomMediaCache());
		}

		if ($version < 901010070)
		{
			if ($version < 901010000) // Do not re-run if already running a 1.1 build.
			{
				XenForo_Application::defer('XenGallery_Deferred_Upgrade_901010070', array(), 'XFMGTagMigrate', true, time() + 1);
			}

			$db->delete('xf_content_type', "content_type = 'xengallery_content_tag'");
			$db->delete('xf_content_type_field', "content_type = 'xengallery_content_tag'");

			// Categories which are "all" may not necessarily want video upload enabling - should be opt in.
			foreach ($db->fetchAll('SELECT * FROM xengallery_category') AS $category)
			{
				$allowedTypes = @unserialize($category['allowed_types']);
				if (!$allowedTypes || !in_array('all', $allowedTypes))
				{
					continue;
				}

				$allowedTypes = array(
					'image_upload', 'video_embed'
				);
				$db->update('xengallery_category', array('allowed_types' => serialize($allowedTypes)), 'category_id = ' . $db->quote($category['category_id']));
			}

			if ($db->fetchRow('SELECT * FROM xengallery_field WHERE field_id = \'caption\''))
			{
				$titleExists = $db->fetchRow('SELECT * FROM xf_phrase WHERE title = \'xengallery_field_caption\'');
				if ($titleExists)
				{
					$db->update('xf_phrase', array('addon_id' => ''), 'title = \'xengallery_field_caption\'');
				}

				$descExists = $db->fetchRow('SELECT * FROM xf_phrase WHERE title = \'xengallery_field_caption_desc\'');
				if ($descExists)
				{
					$db->update('xf_phrase', array('addon_id' => ''), 'title = \'xengallery_field_caption_desc\'');
				}

				if (!$titleExists && !$descExists)
				{
					self::_executeQuery(self::_getFieldPhraseSql(true));
				}
			}
		}
	}

	public static function setCategoryViewPermissions(array $categoryIds)
	{
		$db = self::_getDb();

		$userGroupIds = $db->fetchCol('
			SELECT user_group_id
			FROM xf_user_group
		');

		foreach ($categoryIds AS $categoryId)
		{
			foreach ($userGroupIds AS $userGroupId)
			{
				self::_executeQuery("
					INSERT IGNORE INTO xengallery_category_map
						(category_id, view_user_group_id)
					VALUES
						($categoryId, $userGroupId)
				");
			}
			$db->update('xengallery_category', array('view_user_groups' => 'a:1:{i:0;i:-1;}'), 'category_id = ' . $db->quote($categoryId));
		}

		$db->update('xengallery_media', array('media_privacy' => 'category'), 'category_id > 0');
	}

	public static function uninstaller()
	{
		$db = self::_getDb();

		self::_executeQuery("ALTER TABLE xf_user DROP COLUMN xengallery_album_count");

		self::_executeQuery("ALTER TABLE xf_user DROP COLUMN xengallery_media_count");

		self::_executeQuery("ALTER TABLE xf_user DROP COLUMN xengallery_media_quota");

		self::_executeQuery("ALTER TABLE xf_user_option DROP COLUMN xengallery_default_media_watch_state");

		self::_executeQuery("ALTER TABLE xf_user_option DROP COLUMN xengallery_default_album_watch_state");

		self::_executeQuery("ALTER TABLE xf_user_option DROP COLUMN xengallery_default_category_watch_state");

		self::_executeQuery("ALTER TABLE xf_user_option DROP COLUMN xengallery_unviewed_media_count");

		$tables = self::_getTables();

		// Clean up legacy tables too.
		$tables['xengallery_content_tag'] = '';
		$tables['xengallery_content_tag_map'] = '';

		foreach ($tables AS $tableName => $tableSql)
		{
			self::_executeQuery("DROP TABLE IF EXISTS $tableName");
		}

		XenForo_Db::beginTransaction($db);

		$contentTypes = $db->quote(self::$_contentTypes);
		foreach (self::$_contentTypeTables AS $table)
		{
			$db->delete($table, 'content_type IN (' . $contentTypes . ')');
		}

		$db->update('xf_attachment', array('unassociated' => 1), 'content_type IN (' . $contentTypes . ')');

		$db->delete('xf_admin_permission_entry', "admin_permission_id = 'manageXenGallery'");
		$db->delete('xf_permission_entry', "permission_group_id = 'xengallery'");
		$db->delete('xf_data_registry', "data_key = 'xengalleryFieldsInfo'");

		$moderators = self::_getGlobalModPermissions();
		foreach ($moderators AS $userId => $permissions)
		{
			unset($permissions['xengallery']);
			self::_updateGlobalModPermissions($userId, $permissions);
		}

		self::_deleteRouteFilters(true);

		XenForo_Db::commit($db);

		XenForo_Application::setSimpleCacheData('xengalleryStatisticsCache', false);
		XenForo_Application::setSimpleCacheData('xengalleryRandomMediaCache', false);
	}

	public static function rebuildAttachmentContentIds()
	{
		$db = self::_getDb();

		$media = $db->fetchPairs('
			SELECT media_id, attachment_id
			FROM xengallery_media
			WHERE media_type = \'image_upload\'
		');

		foreach ($media AS $contentId => $attachmentId)
		{
			$db->update('xf_attachment', array('content_id' => $contentId), 'attachment_id = ' . $db->quote($attachmentId));
		}
	}

	protected static function _getTables()
	{
		$tables = array();

		$tables['xengallery_add_map'] = "
			CREATE TABLE IF NOT EXISTS xengallery_add_map (
				album_id INT(10) UNSIGNED NOT NULL,
				add_user_id INT(10) UNSIGNED NOT NULL,
				PRIMARY KEY (album_id,add_user_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";

		$tables['xengallery_album'] = "
			CREATE TABLE IF NOT EXISTS xengallery_album (
				album_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
				album_title TEXT NOT NULL,
				album_description TEXT NOT NULL,
				album_create_date INT(10) UNSIGNED NOT NULL DEFAULT '0',
				last_update_date INT(10) UNSIGNED NOT NULL DEFAULT '0',
				media_cache BLOB,
				manual_media_cache TINYINT(3) NOT NULL DEFAULT '0',
				album_state ENUM('visible','moderated','deleted') NOT NULL DEFAULT 'visible',
				album_user_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				album_username VARCHAR(50) NOT NULL DEFAULT '',
				ip_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				album_likes INT(10) UNSIGNED NOT NULL DEFAULT '0',
				album_like_users BLOB,
				album_media_count INT(10) UNSIGNED NOT NULL DEFAULT '0',
				album_view_count INT(10) UNSIGNED NOT NULL DEFAULT '0',
				album_rating_count INT(10) UNSIGNED NOT NULL DEFAULT '0',
				album_rating_sum INT(10) UNSIGNED NOT NULL DEFAULT '0',
				album_rating_avg float UNSIGNED NOT NULL DEFAULT '0',
				album_rating_weighted float UNSIGNED NOT NULL DEFAULT '0',
				album_comment_count INT(10) UNSIGNED NOT NULL DEFAULT '0',
				album_last_comment_date INT(10) UNSIGNED NOT NULL DEFAULT '0',
				album_warning_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				album_warning_message VARCHAR(255) NOT NULL DEFAULT '',
				album_default_order ENUM('','custom') NOT NULL DEFAULT '',
				album_thumbnail_date INT(10) NOT NULL DEFAULT '0',
				PRIMARY KEY (album_id),
				KEY album_create_date (album_create_date),
				KEY album_user_id_album_create_date (album_user_id, album_create_date)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";
		
		$tables['xengallery_album_permission'] = "
			CREATE TABLE IF NOT EXISTS xengallery_album_permission (
				album_id INT(10) UNSIGNED NOT NULL,
				permission ENUM('view','add') NOT NULL,
				access_type ENUM('public','followed','members','private','shared') DEFAULT 'public',
				share_users MEDIUMBLOB NOT NULL,
				PRIMARY KEY (album_id,permission)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";

		$tables['xengallery_album_view'] = "
			CREATE TABLE IF NOT EXISTS xengallery_album_view (
				album_id INT(10) UNSIGNED NOT NULL,
				KEY album_id (album_id)
			) ENGINE=MEMORY DEFAULT CHARSET=utf8;
		";

		$tables['xengallery_album_watch'] = "
			CREATE TABLE IF NOT EXISTS xengallery_album_watch (
				user_id INT(10) UNSIGNED NOT NULL,
				album_id INT(10) UNSIGNED NOT NULL,
				notify_on ENUM('','media','comment','media_comment') NOT NULL,
				send_alert TINYINT(3) UNSIGNED NOT NULL,
				send_email TINYINT(3) UNSIGNED NOT NULL,
				PRIMARY KEY (user_id,album_id),
				KEY album_id_notify_on (album_id,notify_on)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";

		$tables['xengallery_category'] = "
			CREATE TABLE IF NOT EXISTS xengallery_category (
				category_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
				category_title VARCHAR(100) NOT NULL,
				category_description TEXT NOT NULL,
				upload_user_groups BLOB NOT NULL,
				view_user_groups BLOB NOT NULL,
				allowed_types BLOB NOT NULL,
				parent_category_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				display_order INT(10) UNSIGNED NOT NULL DEFAULT '0',
				category_breadcrumb BLOB NOT NULL,
				depth smallINT(5) UNSIGNED NOT NULL DEFAULT '0',
				category_media_count INT(10) UNSIGNED NOT NULL DEFAULT '0',
				field_cache MEDIUMBLOB NOT NULL,
				min_tags SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				PRIMARY KEY (category_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";

		$tables['xengallery_category_map'] = "
			CREATE TABLE IF NOT EXISTS xengallery_category_map (
				category_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				view_user_group_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (category_id,view_user_group_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";

		$tables['xengallery_category_watch'] = "
			CREATE TABLE IF NOT EXISTS xengallery_category_watch (
				user_id INT(10) UNSIGNED NOT NULL,
				category_id INT(10) UNSIGNED NOT NULL,
				notify_on ENUM('','media') NOT NULL,
				send_alert TINYINT(3) UNSIGNED NOT NULL,
				send_email TINYINT(3) UNSIGNED NOT NULL,
				include_children TINYINT(3) UNSIGNED NOT NULL,
				PRIMARY KEY (user_id,category_id),
				KEY category_id_notify_on (category_id,notify_on)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";

		$tables['xengallery_comment'] = "
			CREATE TABLE IF NOT EXISTS xengallery_comment (
				comment_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
				content_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				content_type ENUM('media','album') NOT NULL DEFAULT 'media',
				message MEDIUMTEXT NOT NULL,
				user_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				username VARCHAR(50) NOT NULL DEFAULT '',
				ip_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				comment_date INT(10) UNSIGNED NOT NULL DEFAULT '0',
				comment_state ENUM('visible','moderated','deleted') NOT NULL DEFAULT 'visible',
				rating_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				likes INT(10) UNSIGNED NOT NULL DEFAULT '0',
				like_users BLOB,
				warning_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				warning_message VARCHAR(255) NOT NULL DEFAULT '',
				PRIMARY KEY (comment_id),
				KEY comment_date (comment_date),
				KEY content_type_content_id_comment_date (content_type, content_id, comment_date)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";

		$tables['xengallery_exif'] = "
			CREATE TABLE IF NOT EXISTS xengallery_exif (
				media_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				exif_name VARCHAR(200) NOT NULL DEFAULT '',
				exif_value VARCHAR(200) NOT NULL DEFAULT '',
				exif_format VARCHAR(50) NOT NULL DEFAULT '{value}',
				PRIMARY KEY (media_id,exif_name)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";

		$tables['xengallery_exif_cache'] = "
			CREATE TABLE IF NOT EXISTS xengallery_exif_cache (
				data_id INT(10) NOT NULL DEFAULT '0',
				media_exif_data_cache_full MEDIUMBLOB NOT NULL,
				cache_date INT(10) UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (data_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";

		$tables['xengallery_field'] = "
			CREATE TABLE IF NOT EXISTS xengallery_field (
				field_id varbinary(25) NOT NULL,
				display_group VARCHAR(25) NOT NULL DEFAULT 'below_info_tab',
				display_order INT(10) UNSIGNED NOT NULL DEFAULT '1',
				field_type VARCHAR(25) NOT NULL DEFAULT 'textbox',
				field_choices BLOB NOT NULL,
				match_type VARCHAR(25) NOT NULL DEFAULT 'none',
				match_regex VARCHAR(250) NOT NULL DEFAULT '',
				match_callback_class VARCHAR(75) NOT NULL DEFAULT '',
				match_callback_method VARCHAR(75) NOT NULL DEFAULT '',
				max_length INT(10) UNSIGNED NOT NULL DEFAULT '0',
				album_use TINYINT(3) UNSIGNED NOT NULL DEFAULT '1',
				display_template TEXT NOT NULL,
				display_add_media TINYINT(3) UNSIGNED NOT NULL DEFAULT  '0',
				required TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (field_id),
				KEY display_group_order (display_group,display_order)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";

		$tables['xengallery_field_category'] = "
			CREATE TABLE IF NOT EXISTS xengallery_field_category (
				field_id varbinary(25) NOT NULL,
				category_id INT(11) NOT NULL,
				PRIMARY KEY (field_id,category_id),
				KEY category_id (category_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";

		$tables['xengallery_field_value'] = "
			CREATE TABLE IF NOT EXISTS xengallery_field_value (
				media_id INT(10) UNSIGNED NOT NULL,
				field_id varbinary(25) NOT NULL,
				field_value MEDIUMTEXT NOT NULL,
				PRIMARY KEY (media_id,field_id),
				KEY field_id (field_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";

		$tables['xengallery_media'] = "
			CREATE TABLE IF NOT EXISTS xengallery_media (
				media_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
				media_title TEXT NOT NULL,
				media_description TEXT NOT NULL,
				media_date INT(10) UNSIGNED NOT NULL DEFAULT '0',
				last_edit_date INT(10) UNSIGNED NOT NULL DEFAULT '0',
				last_comment_date INT(10) UNSIGNED NOT NULL DEFAULT '0',
				media_type ENUM('image_upload','video_upload','video_embed') NOT NULL DEFAULT 'image_upload',
				media_tag TEXT,
				media_embed_url TEXT,
				media_embed_cache BLOB,
				media_state ENUM('visible','moderated','deleted') NOT NULL DEFAULT 'visible',
				album_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				media_privacy ENUM('private','public','shared','members','followed','category') NOT NULL DEFAULT 'public',
				category_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				attachment_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				user_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				username VARCHAR(50) NOT NULL,
				ip_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				likes INT(10) UNSIGNED NOT NULL DEFAULT '0',
				like_users BLOB,
				comment_count INT(10) UNSIGNED NOT NULL DEFAULT '0',
				media_view_count INT(10) UNSIGNED NOT NULL DEFAULT '0',
				rating_count INT(10) UNSIGNED NOT NULL DEFAULT '0',
				rating_sum INT(10) UNSIGNED NOT NULL DEFAULT '0',
				rating_avg float UNSIGNED NOT NULL DEFAULT '0',
				rating_weighted float UNSIGNED NOT NULL DEFAULT '0',
				watermark_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				custom_media_fields MEDIUMBLOB NOT NULL,
				media_exif_data_cache MEDIUMBLOB NOT NULL,
				media_exif_data_cache_full MEDIUMBLOB NOT NULL,
				warning_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				warning_message VARCHAR(255) NOT NULL DEFAULT '',
				position INT(10) UNSIGNED NOT NULL DEFAULT '0',
				imported INT(10) UNSIGNED NOT NULL DEFAULT '0',
				thumbnail_date INT(10) NOT NULL DEFAULT '0',
				tags MEDIUMBLOB NOT NULL,
				PRIMARY KEY (media_id),
				KEY position (position),
				KEY media_date (media_date),
				KEY user_id_media_date (user_id, media_date),
				KEY album_id_media_date (album_id, media_date),
				KEY category_id_media_date (category_id, media_date)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";

		$tables['xengallery_media_user_view'] = "
			CREATE TABLE IF NOT EXISTS xengallery_media_user_view (
				media_view_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id INT(10) UNSIGNED NOT NULL,
				media_id INT(10) UNSIGNED NOT NULL,
				media_view_date INT(10) UNSIGNED NOT NULL,
				PRIMARY KEY (media_view_id),
				UNIQUE KEY user_id_media_id (user_id,media_id),
				KEY media_id (media_id),
				KEY media_view_date (media_view_date)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";

		$tables['xengallery_media_view'] = "
			CREATE TABLE IF NOT EXISTS xengallery_media_view (
				media_id INT(10) UNSIGNED NOT NULL,
				KEY media_id (media_id)
			) ENGINE=MEMORY DEFAULT CHARSET=utf8;
		";

		$tables['xengallery_media_watch'] = "
			CREATE TABLE IF NOT EXISTS xengallery_media_watch (
				user_id INT(10) UNSIGNED NOT NULL,
				media_id INT(10) UNSIGNED NOT NULL,
				notify_on ENUM('','comment') NOT NULL DEFAULT '',
				send_alert TINYINT(3) UNSIGNED NOT NULL,
				send_email TINYINT(3) UNSIGNED NOT NULL,
				PRIMARY KEY (user_id,media_id),
				KEY media_id_notify_on (media_id,notify_on)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";

		$tables['xengallery_private_map'] = "
			CREATE TABLE IF NOT EXISTS xengallery_private_map (
				album_id INT(10) UNSIGNED NOT NULL,
				private_user_id INT(10) UNSIGNED NOT NULL,
				PRIMARY KEY (album_id,private_user_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";

		$tables['xengallery_rating'] = "
			CREATE TABLE IF NOT EXISTS xengallery_rating (
				rating_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
				content_id INT(10) UNSIGNED NOT NULL,
				content_type ENUM('media','album') NOT NULL DEFAULT 'media',
				user_id INT(10) UNSIGNED NOT NULL,
				username VARCHAR(50) NOT NULL DEFAULT '',
				rating TINYINT(3) UNSIGNED NOT NULL,
				rating_date INT(10) UNSIGNED NOT NULL,
				PRIMARY KEY (rating_id),
				UNIQUE KEY content_type_id_user_id (content_type,content_id,user_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";

		$tables['xengallery_shared_map'] = "
			CREATE TABLE IF NOT EXISTS xengallery_shared_map (
				album_id INT(10) UNSIGNED NOT NULL,
				shared_user_id INT(10) UNSIGNED NOT NULL,
				PRIMARY KEY (album_id,shared_user_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";
		
		$tables['xengallery_transcode_queue'] = "
			CREATE TABLE IF NOT EXISTS xengallery_transcode_queue (
				transcode_queue_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
				queue_data MEDIUMBLOB NOT NULL,
				queue_state ENUM('pending', 'processing') DEFAULT 'pending',
				queue_date INT(10) UNSIGNED NOT NULL,
				PRIMARY KEY (transcode_queue_id),
				KEY queue_date (queue_date)
			) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci
		";

		$tables['xengallery_user_tag'] = "
			CREATE TABLE IF NOT EXISTS xengallery_user_tag (
				tag_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
				media_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				user_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				username VARCHAR(50) NOT NULL DEFAULT '',
				tag_data BLOB,
				tag_date INT(10) UNSIGNED NOT NULL DEFAULT '0',
				tag_by_user_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				tag_by_username VARCHAR(50) NOT NULL DEFAULT '',
				tag_state ENUM('approved','pending','rejected') NOT NULL DEFAULT 'approved',
				tag_state_date INT(10) UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (tag_id),
				KEY media_id (media_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";

		$tables['xengallery_watermark'] = "
			CREATE TABLE IF NOT EXISTS xengallery_watermark (
				watermark_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
				watermark_user_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				watermark_date INT(10) UNSIGNED NOT NULL DEFAULT '0',
				is_site TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
				PRIMARY KEY (watermark_id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
		";

		return $tables;
	}

	protected static function _getAlters($key = null)
	{
		$alters = array();
		if (!$key)
		{
			return $alters;
		}

		$alters[110]['xengallery_media_caption'] = "
			ALTER TABLE xengallery_media ADD media_caption TEXT AFTER media_description
		";

		$alters[110]['xengallery_media_type'] = "
			ALTER TABLE xengallery_media ADD media_type ENUM('image','video') NOT NULL DEFAULT 'image' AFTER media_date
		";

		$alters[110]['xengallery_media_view_count'] = "
			ALTER TABLE xengallery_media ADD view_count int(10) unsigned NOT NULL DEFAULT '0' AFTER rating_count
		";

		$alters[110]['xengallery_media_embed_url'] = "
			ALTER TABLE xengallery_media ADD media_embed_url TEXT AFTER media_tag
		";

		$alters[110]['xengallery_category_allowed_types'] = "
			ALTER TABLE xengallery_category ADD allowed_types BLOB NOT NULL AFTER upload_user_groups
		";

		$alters[110]['xengallery_category_allowed_types_data'] = '
			UPDATE xengallery_category SET allowed_types = \'a:1:{i:0;s:3:"all";}\'
		';

		$alters[112]['xengallery_media_type_1'] = "
			ALTER TABLE xengallery_media CHANGE COLUMN media_type media_type ENUM('image','video','image_upload','video_embed') NOT NULL DEFAULT 'image'
		";

		$alters[112]['xengallery_media_type_2'] = "
			UPDATE xengallery_media SET media_type = 'image_upload' WHERE media_type = 'image'
		";

		$alters[112]['xengallery_media_type_3'] = "
			UPDATE xengallery_media SET media_type = 'video_embed' WHERE media_type = 'video_embed'
		";

		$alters[112]['xemgallery_media_type_4'] = "
			ALTER TABLE xengallery_media CHANGE COLUMN media_type media_type ENUM('image_upload','video_embed') NOT NULL DEFAULT 'image_upload'
		";

		$alters[120]['xengallery_media_last_comment_date'] = "
			ALTER TABLE xengallery_media ADD last_comment_date INT(10) NOT NULL DEFAULT '0' AFTER media_date
		";

		$alters[2000070]['xengallery_category_id'] = "
			ALTER TABLE xengallery_category CHANGE COLUMN xengallery_category_id category_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT
		";

		$alters[2000070]['xengallery_media_category_id'] = "
			ALTER TABLE xengallery_media CHANGE COLUMN xengallery_category_id category_id INT(10) UNSIGNED NOT NULL
		";

		$alters[2000070]['xengallery_media_album_id'] = "
			ALTER TABLE xengallery_media ADD album_id INT(10) NOT NULL DEFAULT '0' AFTER media_state
		";

		$alters[2000070]['xengallery_media_privacy'] = "
			ALTER TABLE xengallery_media ADD media_privacy enum('private','public','shared','members','followers') NOT NULL DEFAULT 'public' AFTER album_id
		";

		$alters[2000070]['xengallery_media_last_edit_date'] = "
			ALTER TABLE xengallery_media ADD last_edit_date INT(10) NOT NULL DEFAULT '0' AFTER media_date
		";

		$alters[2000070]['xengallery_media_custom_media_fields'] = "
			ALTER TABLE xengallery_media ADD custom_media_fields MEDIUMBLOB NOT NULL
		";

		$alters[2000070]['xengallery_rating_id'] = "
			ALTER TABLE xengallery_rating CHANGE COLUMN media_rating_id rating_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT
		";

		$alters[2000070]['xengallery_content_id'] = "
			ALTER TABLE xengallery_rating CHANGE COLUMN media_id content_id INT(10) UNSIGNED NOT NULL	AFTER rating_id
		";

		$alters[2000070]['xengallery_content_type'] = "
			ALTER TABLE xengallery_rating ADD content_type ENUM('album', 'media') NOT NULL DEFAULT 'media' AFTER content_id
		";

		$alters[2000070]['xengallery_media_media_view_count'] = "
			ALTER TABLE xengallery_media CHANGE COLUMN view_count media_view_count INT(10) UNSIGNED NOT NULL DEFAULT '0'
		";

		$alters[2000070]['xengallery_media_media_title'] = "
			ALTER TABLE xengallery_media CHANGE COLUMN media_title media_title TEXT NOT NULL
		";

		$alters[2000070]['xengallery_media_media_description'] = "
			ALTER TABLE xengallery_media CHANGE COLUMN media_description media_description TEXT NOT NULL
		";

		$alters[2000070]['xengallery_media_media_type'] = "
			ALTER TABLE xengallery_media CHANGE COLUMN media_type media_type ENUM('image_upload','video_upload','video_embed') NOT NULL DEFAULT 'image_upload'
		";

		$alters[2000070]['xengallery_media_watermark_id'] = "
			ALTER TABLE xengallery_media ADD COLUMN watermark_id INT(10) UNSIGNED NOT NULL DEFAULT '0';
		";

		$alters[2000070]['xengallery_media_media_exif_data_cache'] = "
			ALTER TABLE xengallery_media ADD COLUMN media_exif_data_cache MEDIUMBLOB NOT NULL
		";

		$alters[2000070]['xengallery_media_media_exif_data_cache_full'] = "
			ALTER TABLE xengallery_media ADD COLUMN media_exif_data_cache_full MEDIUMBLOB NOT NULL AFTER media_exif_data_cache
		";

		$alters[2000070]['xengallery_category_media_count'] = "
			ALTER TABLE xengallery_category ADD COLUMN category_media_count INT(10) UNSIGNED NOT NULL DEFAULT '0';
		";

		$alters[2000070]['xengallery_category_field_cache'] = "
			ALTER TABLE xengallery_category	ADD field_cache MEDIUMBLOB NOT NULL
		";

		$alters[2000070]['xengallery_option_thumbnail_dimensions'] = "
			UPDATE xf_option SET option_value = 'a:2:{s:5:\"width\";s:3:\"300\";s:6:\"height\";s:3:\"300\";}' WHERE option_id = 'xengalleryThumbnailDimension'
		";

		$alters[2000070]['xengallery_media_media_privacy'] = "
			ALTER TABLE xengallery_media CHANGE COLUMN media_privacy media_privacy ENUM('private','public','shared','members','followed') NOT NULL DEFAULT 'public'
		";

		$alters[2000070]['xengallery_comments'] = "
			ALTER TABLE xengallery_comment
				CHANGE COLUMN media_id content_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
				ADD COLUMN content_type ENUM('media','album') NOT NULL DEFAULT 'media' AFTER content_id,
				CHANGE COLUMN media_comment message MEDIUMTEXT NOT NULL AFTER content_type;
		";

		$alters[2000070]['xengallery_album_comments'] = "
			ALTER TABLE xengallery_album
				ADD COLUMN album_comment_count INT(10) UNSIGNED NOT NULL DEFAULT 0 AFTER album_rating_avg,
				ADD COLUMN album_last_comment_date INT(10) UNSIGNED NOT NULL DEFAULT 0 AFTER album_comment_count;
		";

		$alters['cat_view']['xengallery_category_view_user_groups'] = "
			ALTER TABLE xengallery_category
				ADD COLUMN view_user_groups BLOB NOT NULL AFTER upload_user_groups;
		";

		$alters['cat_view']['xengallery_media_privacy_category'] = "
			ALTER TABLE xengallery_media
				CHANGE COLUMN media_privacy media_privacy ENUM('private','public','shared','members','followed','category') NOT NULL DEFAULT 'public'
		";

		$alters[2010070]['xengallery_media_warning_id'] = "
			ALTER TABLE xengallery_media
				ADD COLUMN warning_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
				ADD COLUMN warning_message VARCHAR(255) NOT NULL DEFAULT '';
		";

		$alters[2010070]['xengallery_album_warning_id'] = "
			ALTER TABLE xengallery_album
				ADD COLUMN warning_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
				ADD COLUMN warning_message VARCHAR(255) NOT NULL DEFAULT '';
		";

		$alters[2010070]['xengallery_comment_warning_id'] = "
			ALTER TABLE xengallery_comment
				ADD COLUMN warning_id INT(10) UNSIGNED NOT NULL DEFAULT 0,
				ADD COLUMN warning_message VARCHAR(255) NOT NULL DEFAULT '';
		";

		$alters[2010070]['xengallery_user_tag_by_user_id'] = "
			ALTER TABLE xengallery_user_tag ADD tag_by_user_id INT(10)	UNSIGNED	NOT NULL	DEFAULT '0'
		";

		$alters[2010070]['xengallery_user_tag_by_username'] = "
			ALTER TABLE xengallery_user_tag ADD tag_by_username VARCHAR(50)	NOT NULL	DEFAULT ''
		";

		$alters[2010070]['xengallery_user_tag_state'] = "
			ALTER TABLE xengallery_user_tag ADD tag_state ENUM('approved', 'pending', 'rejected')	NOT NULL	DEFAULT 'approved'
		";

		$alters[2010070]['xengallery_user_tag_state_date'] = "
			ALTER TABLE xengallery_user_tag ADD tag_state_date INT(10)	UNSIGNED	NOT NULL	DEFAULT '0'
		";

		$alters[2010070]['xengallery_media_media_tag'] = "
			ALTER TABLE xengallery_media CHANGE media_tag media_tag TEXT DEFAULT NULL
		";

		$alters[2010070]['xengallery_album_user_id_username'] = "
			ALTER TABLE xengallery_album
				CHANGE user_id album_user_id INT(10) UNSIGNED NOT NULL DEFAULT '0',
				CHANGE username album_username VARCHAR(50) NOT NULL DEFAULT '';
		";

		$alters[2010070]['xengallery_album_album_default_order'] = "
			ALTER TABLE xengallery_album
				ADD COLUMN album_default_order ENUM('','custom') DEFAULT ''	NOT NULL AFTER warning_message;
		";

		$alters[2010070]['xengallery_media_position'] = "
			ALTER TABLE xengallery_media
				ADD COLUMN position INT(10) UNSIGNED DEFAULT 0	NOT NULL AFTER warning_message;
		";

		$alters[2010070]['xengallery_media_position_index'] = "
			ALTER TABLE xengallery_media
				ADD INDEX position (position);
		";

		$alters[2010070]['xengallery_media_imported_flag'] = "
			ALTER TABLE xengallery_media ADD COLUMN imported INT(10) UNSIGNED DEFAULT 0 NOT NULL;
		";

		$alters[2010070]['xengallery_album_media_cache'] = "
			ALTER TABLE xengallery_album
				CHANGE COLUMN random_media_cache media_cache BLOB NULL DEFAULT NULL
		";

		$alters[2010070]['xengallery_album_manual_media_cache'] = "
			ALTER TABLE xengallery_album
				ADD COLUMN manual_media_cache TINYINT(3) DEFAULT '0'	NOT NULL AFTER media_cache
		";

		$alters[2010070]['xengallery_album_thumbnail_date'] = "
			ALTER TABLE xengallery_album
				ADD COLUMN album_thumbnail_date INT(10) DEFAULT '0'	NOT NULL AFTER album_default_order
		";

		$alters[2010070]['xengallery_media_thumbnail_date'] = "
			ALTER TABLE xengallery_media
				ADD COLUMN thumbnail_date INT(10) DEFAULT '0'	NOT NULL AFTER imported
		";

		$alters[2010670]['xengallery_rating_remove_index'] = "
			ALTER TABLE xengallery_rating DROP INDEX media_user_id
		";

		$alters[2010670]['xengallery_rating_new_index'] = "
			ALTER TABLE xengallery_rating ADD INDEX content_type_id_user_id (content_type,content_id,user_id)
		";

		$alters[2010670]['xengallery_album_album_title'] = "
			ALTER TABLE xengallery_album CHANGE COLUMN album_title album_title TEXT NOT NULL
		";

		$alters[2010670]['xengallery_album_album_description'] = "
			ALTER TABLE xengallery_album CHANGE COLUMN album_description album_description TEXT NOT NULL
		";

		$alters[2010870]['xengallery_album_media_cache_medium_blob'] = "
			ALTER TABLE xengallery_album CHANGE COLUMN media_cache media_cache MEDIUMBLOB NULL DEFAULT NULL
		";

		$alters[2010870]['xengallery_rating_new_unique_index0'] = "
			ALTER TABLE xengallery_rating DROP INDEX content_type_id_user_id
		";

		$alters[2010870]['xengallery_rating_new_unique_index1'] = "
			ALTER TABLE xengallery_rating ENGINE MyISAM
		";

		$alters[2010870]['xengallery_rating_new_unique_index2'] = "
			ALTER IGNORE TABLE xengallery_rating ADD UNIQUE INDEX content_type_id_user_id (content_type, content_id, user_id)
		";

		$alters[2010870]['xengallery_rating_new_unique_index3'] = "
			ALTER TABLE xengallery_rating ENGINE InnoDB
		";

		$alters[901000170]['xengallery_album_weighted_rating'] = "
			ALTER TABLE xengallery_album
				ADD COLUMN album_rating_weighted float DEFAULT '0' NOT NULL AFTER album_rating_avg
		";

		$alters[901000170]['xengallery_media_weighted_rating'] = "
			ALTER TABLE xengallery_media
				ADD COLUMN rating_weighted float DEFAULT '0' NOT NULL AFTER rating_avg
		";

		$alters[901010070]['xengallery_media_tags'] = "
			ALTER TABLE xengallery_media
			ADD tags MEDIUMBLOB NOT NULL
		";

		$alters[901010070]['xengallery_media_drop_content_tag_cache'] = "
			ALTER TABLE xengallery_media
			DROP COLUMN media_content_tag_cache
		";

		$alters[901010070]['xengallery_category_min_tags'] = "
			ALTER TABLE xengallery_category
			ADD min_tags SMALLINT UNSIGNED NOT NULL DEFAULT '0'
		";

		$alters[901010070]['xengallery_field'] = "
			ALTER TABLE xengallery_field
			ADD display_add_media TINYINT(3) UNSIGNED NOT NULL DEFAULT '0',
			ADD required TINYINT(3) UNSIGNED NOT NULL DEFAULT '0'
		";

		$alters[901010170]['xengallery_album_album_warning_id'] = "
			ALTER TABLE xengallery_album
			CHANGE COLUMN warning_id album_warning_id INT(10) NOT NULL DEFAULT 0,
			CHANGE COLUMN warning_message album_warning_message VARCHAR(255) NOT NULL DEFAULT ''
		";

		return isset($alters[$key]) ? $alters[$key] : array();
	}

	protected static function _getData()
	{
		$data = array();

		$data['xf_content_type'] = "
			INSERT IGNORE INTO xf_content_type
				(content_type, addon_id, fields)
			VALUES
				('xengallery', 				'XenGallery', 	''),
				('xengallery_album', 		'XenGallery', 	''),
				('xengallery_category', 	'XenGallery', 	''),
				('xengallery_comment', 		'XenGallery', 	''),
				('xengallery_media', 		'XenGallery',	''),
				('xengallery_rating', 		'XenGallery', 	''),
				('xengallery_user_album',	'XenGallery',	''),
				('xengallery_user_media',	'XenGallery',	'')
		";

		$data['xf_content_type_field'] = "
			INSERT IGNORE INTO xf_content_type_field
				(content_type, field_name, field_value)
			VALUES
				('xengallery',				'stats_handler_class',				'XenGallery_StatsHandler_Gallery'),
			
				('xengallery_album',		'alert_handler_class',				'XenGallery_AlertHandler_Album'),
				('xengallery_album',		'like_handler_class',				'XenGallery_LikeHandler_Album'),
				('xengallery_album',        'moderator_log_handler_class',		'XenGallery_ModeratorLogHandler_Album'),
				('xengallery_album',		'report_handler_class',				'XenGallery_ReportHandler_Album'),
				('xengallery_album',		'sitemap_handler_class', 			'XenGallery_SitemapHandler_Album'),
				('xengallery_album', 		'spam_handler_class', 				'XenGallery_SpamHandler_Album'),
				('xengallery_album', 		'warning_handler_class', 			'XenGallery_WarningHandler_Album'),

				('xengallery_category',		'sitemap_handler_class', 			'XenGallery_SitemapHandler_Category'),
	
				('xengallery_comment',		'alert_handler_class',				'XenGallery_AlertHandler_Comment'),
				('xengallery_comment',		'like_handler_class',				'XenGallery_LikeHandler_Comment'),
				('xengallery_comment',		'moderator_log_handler_class',		'XenGallery_ModeratorLogHandler_Comment'),
				('xengallery_comment', 		'moderation_queue_handler_class', 	'XenGallery_ModerationQueueHandler_Comment'),
				('xengallery_comment',		'news_feed_handler_class',			'XenGallery_NewsFeedHandler_Comment'),
				('xengallery_comment',		'report_handler_class',				'XenGallery_ReportHandler_Comment'),
				('xengallery_comment', 		'warning_handler_class', 			'XenGallery_WarningHandler_Comment'),
				('xengallery_comment', 		'spam_handler_class', 				'XenGallery_SpamHandler_Comment'),

				('xengallery_media', 		'alert_handler_class', 				'XenGallery_AlertHandler_Media'),
				('xengallery_media', 		'attachment_handler_class', 			'XenGallery_AttachmentHandler_Media'),
				('xengallery_media', 		'like_handler_class', 				'XenGallery_LikeHandler_Media'),
				('xengallery_media',        'moderator_log_handler_class',		'XenGallery_ModeratorLogHandler_Media'),
				('xengallery_media', 		'moderation_queue_handler_class', 	'XenGallery_ModerationQueueHandler_Media'),
				('xengallery_media', 		'news_feed_handler_class', 			'XenGallery_NewsFeedHandler_Media'),
				('xengallery_media', 		'report_handler_class', 				'XenGallery_ReportHandler_Media'),
				('xengallery_media', 		'search_handler_class', 			'XenGallery_Search_DataHandler_Media'),
				('xengallery_media',			'sitemap_handler_class', 			'XenGallery_SitemapHandler_Media'),
				('xengallery_media', 		'spam_handler_class', 				'XenGallery_SpamHandler_Media'),
				('xengallery_media',			'tag_handler_class',					'XenGallery_TagHandler_Media'),
				('xengallery_media', 		'warning_handler_class', 			'XenGallery_WarningHandler_Media'),

				('xengallery_rating',		'alert_handler_class',				'XenGallery_AlertHandler_Rating'),
				('xengallery_rating',		'news_feed_handler_class',			'XenGallery_NewsFeedHandler_Rating'),
				('xengallery_rating', 		'spam_handler_class', 				'XenGallery_SpamHandler_Rating'),

				('xengallery_user_album',	'sitemap_handler_class', 			'XenGallery_SitemapHandler_UserAlbum'),

				('xengallery_user_media',	'sitemap_handler_class', 			'XenGallery_SitemapHandler_UserMedia')
		";

		if (!self::$_version)
		{
			$data['xengallery_category'] = "
				INSERT IGNORE INTO xengallery_category
					(category_id, category_title, category_description, upload_user_groups, allowed_types, parent_category_id, display_order, category_breadcrumb, depth, category_media_count, field_cache)
				VALUES
					('1', 'Example Category', 'An example category', 'a:1:{i:0;i:-1;}', 'a:2:{i:0;s:12:\"image_upload\";i:1;s:11:\"video_embed\";}', '0', '100', 'a:0:{}', '0', '0', 'a:1:{s:7:\"new_tab\";a:1:{s:7:\"caption\";s:7:\"caption\";}}')
			";

			$data['xengallery_field'] = "
				INSERT IGNORE INTO xengallery_field
					(field_id, display_group, display_order, field_type, field_choices, match_type, match_regex, match_callback_class, match_callback_method, max_length, album_use, display_template)
				VALUES
					('caption', 'new_tab', '10', 'bbcode', 'a:0:{}', 'none', '', '', '', '0', '1', '')
			";

			$data['xengallery_field_category'] = "
				INSERT IGNORE INTO xengallery_field_category
					(field_id, category_id)
				VALUES
					('caption', '1')
			";

			$data['xf_phrase'] = self::_getFieldPhraseSql();
		}

		return $data;
	}

	protected static function _getFieldPhraseSql($ignore = false)
	{
		$sql = 'INSERT ';
		if ($ignore)
		{
			$sql .= 'IGNORE ';
		}
		$sql .= "INTO xf_phrase
					(language_id, title, phrase_text, global_cache, addon_id, version_id, version_string)
				VALUES
					(0, 'xengallery_field_caption', 'Caption', 0, '', 0, ''),
					(0, 'xengallery_field_caption_desc', 'Enter a caption for this media. You may use BB Code.', 0, '', 0, '')";
		if (!$ignore)
		{
			$sql .= "
				ON DUPLICATE KEY UPDATE
					title = VALUES(title),
					phrase_text = VALUES(phrase_text),
					global_cache = VALUES(global_cache),
					addon_id = VALUES(addon_id)
			";
		}

		return $sql;
	}

	protected static function _deleteRouteFilters($uninstall = false)
	{
		$db = self::_getDb();

		$existingFilters = array();

		if ($uninstall)
		{
			$existingFilters[] = $db->fetchRow('
				SELECT *
				FROM xf_route_filter
				WHERE find_route = ?
			', 'xengallery/');
		}

		$options = XenForo_Application::getOptions();

		if ($uninstall || (!$uninstall && empty($options->xengalleryRedirectXFRUA['enabled'])))
		{
			$existingFilters[] = $db->fetchRow('
				SELECT *
				FROM xf_route_filter
				WHERE find_route = ?
			', 'xengallery-xfruseralbums/');
		}

		if ($uninstall || (!$uninstall && empty($options->xengalleryRedirectXenMedio['enabled'])))
		{
			$existingFilters[] = $db->fetchRow('
				SELECT *
				FROM xf_route_filter
				WHERE find_route = ?
			', 'xengallery-ewrmedio/');
		}

		foreach ($existingFilters AS $filter)
		{
			$routeFilterWriter = XenForo_DataWriter::create('XenForo_DataWriter_RouteFilter', XenForo_DataWriter::ERROR_SILENT);
			$routeFilterWriter->setExistingData($filter);
			$routeFilterWriter->delete();
		}
	}

	protected static function _insertRouteFilters()
	{
		$filterExists = self::_getDb()->fetchRow('
			SELECT *
			FROM xf_route_filter
			WHERE find_route = ?
		', 'xengallery/');

		if (!$filterExists)
		{
			$routeFilterWriter = XenForo_DataWriter::create('XenForo_DataWriter_RouteFilter');

			$routeFilterWriter->bulkSet(array(
				'route_type' => 'public',
				'prefix' => 'xengallery',
				'find_route' => 'xengallery',
				'replace_route' => 'media',
				'enabled' => 1,
				'url_to_route_only' => 0
			));

			$routeFilterWriter->save();
		}

		return true;
	}

	public static function mediaCaptionUpgrade()
	{
		$db = self::_getDb();

		try
		{
			$mediaCaptions = $db->fetchPairs('
				SELECT media_id, media_caption
				FROM xengallery_media
				WHERE media_caption <> \'\'
			');
		}
		catch (Zend_Db_Exception $e)
		{
			$mediaCaptions = array();
		}

		foreach ($mediaCaptions AS $mediaId => $caption)
		{
			$exists = $db->fetchRow('
				SELECT media_id
				FROM xengallery_field_value
				WHERE media_id = ? AND field_id = ?
			', array($mediaId, 'caption'));

			if ($exists)
			{
				continue;
			}
			
			/* @var $mediaWriter XenGallery_DataWriter_Media */
			$mediaWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Media', XenForo_DataWriter::ERROR_SILENT);
			
			$mediaWriter->setExistingData($mediaId);
			$mediaWriter->setCustomFields(array('caption' => $caption), array('caption'));

			$mediaWriter->save();
		}
	}

	protected static function _executeQuery($sql, array $bind = array())
	{
		try
		{
			return self::_getDb()->query($sql, $bind);
		}
		catch (Zend_Db_Exception $e)
		{
			return false;
		}
	}

	/**
	 * @return Zend_Db_Adapter_Abstract
	 */
	protected static function _getDb()
	{
		if (!self::$_db)
		{
			self::$_db = XenForo_Application::getDb();
		}

		return self::$_db;
	}
}