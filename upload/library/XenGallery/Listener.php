<?php

class XenGallery_Listener
{
	protected static $_addedUsernameChange = false;

	protected static $_bbCodeCache = null;

	protected static $_permissionCache = array(
		'view' => array(
			'id' => 'canViewMedia', 'value' => null
		),
		'viewAlbums' => array(
			'id' => 'canViewAlbums', 'value' => null
		),
		'viewCategories' => array(
			'id' => 'canViewCategories', 'value' => null
		),
		'add' => array(
			'id' => 'canAddMedia', 'value' => null
		)
	);

	protected static $_templateParamCache = array();

	protected static $_updatedMediaCounts = false;

	public static function templateCreate(&$templateName, array &$params, XenForo_Template_Abstract $template)
	{
		if (empty(self::$_templateParamCache))
		{
			$visitor = XenForo_Visitor::getInstance();

			$preparedPermissions = array();
			foreach (self::$_permissionCache AS $permissionId => $permission)
			{
				if ($permission['value'] === null)
				{
					if ($permissionId == 'add')
					{
						$preparedPermissions[$permissionId] = array(
							'id' => $permission['id'],
							'value' => $visitor->getUserId() && $visitor->hasPermission('xengallery', $permissionId)
						);
					}
					else
					{
						$preparedPermissions[$permissionId] = array(
							'id' => $permission['id'],
							'value' => $visitor->hasPermission('xengallery', $permissionId)
						);
					}
				}
			}

			if ($visitor->hasPermission('xengallery', 'viewOverride'))
			{
				$preparedPermissions['viewAlbums'] = array(
					'id' => self::$_permissionCache['viewAlbums']['id'],
					'value' => true
				);

				$preparedPermissions['viewCategories'] = array(
					'id' => self::$_permissionCache['viewCategories']['id'],
					'value' => true
				);
			}

			$preparedPermissions['watchMedia'] = array(
				'id' => 'canWatchMedia',
				'value' => $visitor['user_id'] ? true : false
			);

			$preparedPermissions['watchAlbums'] = array(
				'id' => 'canWatchAlbums',
				'value' => $visitor['user_id'] ? true : false
			);

			$preparedPermissions['watchCategories'] = array(
				'id' => 'canWatchCategories',
				'value' => $visitor['user_id'] ? true : false
			);

			foreach ($preparedPermissions AS $permission)
			{
				self::$_templateParamCache[$permission['id']] = $permission['value'];
			}
		}

		$params += self::$_templateParamCache;

		if (self::$_bbCodeCache === null)
		{
			if (XenForo_Application::isRegistered('bbCode'))
			{
				self::$_bbCodeCache = XenForo_Application::get('bbCode');
			}
		}

		if (!isset($params['xmgBbCodeCache']))
		{
			$params['xmgBbCodeCache'] = self::$_bbCodeCache;
		}

		$template->preloadTemplate('xengallery_bb_code_tag_gallery');
	}

	public static function navigationTabs(array &$extraTabs, $selectedTabId)
	{
		$visitor = XenForo_Visitor::getInstance();

		if ($visitor->hasPermission('xengallery', 'view'))
		{
			$options = XenForo_Application::getOptions();
			$tabAction = $options->xengalleryTabAction;

			if ($tabAction == 'media_home' || $tabAction == 'find_new_media')
			{
				$tabActionLink = XenForo_Link::buildPublicLink('full:xengallery');
			}
			elseif ($tabAction == 'album_home' || $tabAction == 'find_new_album')
			{
				$tabActionLink = XenForo_Link::buildPublicLink('full:xengallery/albums');
			}
			else
			{
				$tabActionLink = XenForo_Link::buildPublicLink('full:xengallery');
			}

			$extraTabs['xengallery'] = array(
				'title' => new XenForo_Phrase($options->xengalleryTabPhrase),
				'href' => $tabActionLink,
				'position' => $options->xengalleryTabPosition,
				'linksTemplate' => 'xengallery_tab_links'
			);

			if ($options->xengalleryUnviewedCounter['enabled']
				&& $visitor->xengallery_unviewed_media_count
				&& XenForo_Application::isRegistered('session')
			)
			{
				$session = XenForo_Application::get('session');
				$mediaUnviewed = $session->get('mediaUnviewed');

				$extraTabs['xengallery']['counter'] = is_array($mediaUnviewed) ? count($mediaUnviewed['unviewed']) : 0;
				if ($extraTabs['xengallery']['counter'] && strstr($tabAction, 'find_new'))
				{
					$extraTabs['xengallery']['href'] = XenForo_Link::buildPublicLink('full:find-new/media');
				}
			}
		}
	}

	public static function editorSetup(XenForo_View $view, $formCtrlName, &$message, array &$editorOptions, &$showWysiwyg)
	{
		$forceFalse = false;
		if (isset($editorOptions['json']['enableXmgButton']))
		{
			$forceFalse = $editorOptions['json']['enableXmgButton'] === false;
		}
		$editorOptions['json']['enableXmgButton'] = $forceFalse ? false : XenForo_Visitor::getInstance()->hasPermission('xengallery', 'view');
	}

	public static function controllerPreDispatch(XenForo_Controller $controller, $action)
	{
		if ($controller instanceof XenForo_ControllerPublic_Abstract)
		{
			if (self::$_updatedMediaCounts === false)
			{
				self::$_updatedMediaCounts = true;

				$options = XenForo_Application::getOptions();
				$visitor = XenForo_Visitor::getInstance();

				if ($options->xengalleryUnviewedCounter['enabled']
					&& $visitor->xengallery_unviewed_media_count
					&& XenForo_Application::isRegistered('session')
				)
				{
					$time = XenForo_Application::$time;

					$session = XenForo_Application::get('session');
					$mediaUnviewed = $session->get('mediaUnviewed');

					if ($mediaUnviewed === false
						|| $mediaUnviewed['lastUpdateDate'] < ($time - ($options->xengalleryUnviewedCounter['length'] * 60))
					)
					{
						/** @var $mediaModel XenGallery_Model_Media */
						$mediaModel = XenForo_Model::create('XenGallery_Model_Media');

						$unviewedIds = $mediaModel->getUnviewedMediaIds($visitor->user_id, array('viewCategoryIds' => $mediaModel->getViewableCategoriesForVisitor($visitor->toArray()), 'viewAlbums' => XenForo_Permission::hasPermission($visitor->permissions, 'xengallery', 'viewAlbums')));

						if ($unviewedIds !== false)
						{
							if (sizeof($unviewedIds))
							{
								$mediaUnviewed = array(
									'unviewed' => array_combine($unviewedIds, $unviewedIds),
									'lastUpdateDate' => $time
								);
							}
							else
							{
								$mediaUnviewed = self::_getDefaultUnviewedArray();
							}
						}
					}
					elseif (!$visitor->user_id)
					{
						$mediaUnviewed = self::_getDefaultUnviewedArray();
					}

					$session->set('mediaUnviewed', $mediaUnviewed);
				}
			}
		}
	}

	protected static function _getDefaultUnviewedArray()
	{
		return array(
			'unviewed' => array(),
			'lastUpdateDate' => XenForo_Application::$time
		);
	}

	public static function extendImportModel($class, array &$extend)
	{
		XenForo_Model_Import::$extraImporters[] = 'XenGallery_Importer_IPGallery50x';
		XenForo_Model_Import::$extraImporters[] = 'XenGallery_Importer_PhotopostVb';
		XenForo_Model_Import::$extraImporters[] = 'XenGallery_Importer_PhotopostVbGallery';
		XenForo_Model_Import::$extraImporters[] = 'XenGallery_Importer_PhotopostXf';
		XenForo_Model_Import::$extraImporters[] = 'XenGallery_Importer_SonnbXenGallery';
		XenForo_Model_Import::$extraImporters[] = 'XenGallery_Importer_vBulletin38x';
		XenForo_Model_Import::$extraImporters[] = 'XenGallery_Importer_vBulletin42x';
		XenForo_Model_Import::$extraImporters[] = 'XenGallery_Importer_XenMedio';
		XenForo_Model_Import::$extraImporters[] = 'XenGallery_Importer_XFRUserAlbums';
	}

	public static function extendAvatarModel($class, array &$extend)
	{
		$extend[] = 'XenGallery_Model_Avatar';
	}

	public static function extendModeratorModel($class, array &$extend)
	{
		$extend[] = 'XenGallery_Model_Moderator';
	}

	public static function extendOptionModel($class, array &$extend)
	{
		$extend[] = 'XenGallery_Model_Option';
	}

	public static function extendUserModel($class, array &$extend)
	{
		if (!self::$_addedUsernameChange)
		{
			self::$_addedUsernameChange = true;
			XenForo_Model_User::$userContentChanges['xengallery_album'] = array(array('album_user_id', 'album_username'));
			XenForo_Model_User::$userContentChanges['xengallery_album_watch'] = array(array('user_id'));
			XenForo_Model_User::$userContentChanges['xengallery_category_watch'] = array(array('user_id'));
			XenForo_Model_User::$userContentChanges['xengallery_comment'] = array(array('user_id', 'username'));
			XenForo_Model_User::$userContentChanges['xengallery_media'] = array(array('user_id', 'username'));
			XenForo_Model_User::$userContentChanges['xengallery_media_user_view'] = array(array('user_id'));
			XenForo_Model_User::$userContentChanges['xengallery_media_watch'] = array(array('user_id'));
			XenForo_Model_User::$userContentChanges['xengallery_private_map'] = array(array('private_user_id'));
			XenForo_Model_User::$userContentChanges['xengallery_rating'] = array(array('user_id', 'username'));
			XenForo_Model_User::$userContentChanges['xengallery_shared_map'] = array(array('shared_user_id'));
			XenForo_Model_User::$userContentChanges['xengallery_user_tag'] = array(array('user_id', 'username'));
			XenForo_Model_User::$userContentChanges['xengallery_watermark'] = array(array('watermark_user_id'));
		}

		$extend[] = 'XenGallery_Model_User';
	}

	public static function extendAttachmentController($class, array &$extend)
	{
		$extend[] = 'XenGallery_ControllerAdmin_Attachment';
	}

	public static function extendOptionController($class, array &$extend)
	{
		$extend[] = 'XenGallery_ControllerAdmin_Option';
	}

	public static function extendWatchedController($class, array &$extend)
	{
		$extend[] = 'XenGallery_ControllerPublic_Watched';
	}

	public static function extendAccountController($class, array &$extend)
	{
		$extend[] = 'XenGallery_ControllerPublic_Account';
	}

	public static function extendMemberController($class, array &$extend)
	{
		$extend[] = 'XenGallery_ControllerPublic_Member';
	}

	public static function extendFindNewController($class, array &$extend)
	{
		$extend[] = 'XenGallery_ControllerPublic_FindNew';
	}

	public static function extendPostDataWriter($class, array &$extend)
	{
		$extend[] = 'XenGallery_DataWriter_DiscussionMessage_Post';
	}

	public static function extendUserDataWriter($class, array &$extend)
	{
		$extend[] = 'XenGallery_DataWriter_User';
	}

	public static function extendAttachmentDataDataWriter($class, array &$extend)
	{
		$extend[] = 'XenGallery_DataWriter_AttachmentData';
	}

	public static function criteriaUser($rule, array $data, array $user, &$returnValue)
	{
		switch ($rule)
		{
			case 'xengallery_media_count':
				if (!isset($user['xengallery_media_count']))
				{
					$returnValue = false;
				}
				if (isset($user['xengallery_media_count']) && $user['xengallery_media_count'] >= $data['items'])
				{
					$returnValue = true;
				}
				break;

			case 'xengallery_album_count':
				if (!isset($user['xengallery_album_count']))
				{
					$returnValue = false;
				}
				if (isset($user['xengallery_album_count']) && $user['xengallery_album_count'] >= $data['items'])
				{
					$returnValue = true;
				}
				break;
		}
	}

	public static function criteriaPage($rule, array $data, array $params, array $containerData, &$returnValue)
	{
		switch ($rule)
		{
			case 'xengallery_categories':

				// The following is only true on XenForo Media Gallery pages.
				if (isset($params['xengalleryCategory']['category_id'])
					&& !empty($data['category_ids'])
				)
				{
					if (in_array($params['xengalleryCategory']['category_id'], $data['category_ids']))
					{
						$returnValue = true;
					}
				}
				break;
		}
	}

	public static function templateCreateMediaBlock(&$templateName, array &$params, XenForo_Template_Abstract $template)
	{
		if ($template instanceof XenForo_Template_Public)
		{
			$template->preloadTemplate('xengallery_media_block_items');
		}
	}

	public static function templateCreateCommentBlock(&$templateName, array &$params, XenForo_Template_Abstract $template)
	{
		if ($template instanceof XenForo_Template_Public)
		{
			$template->preloadTemplate('xengallery_comments_block');
		}
	}

	public static function templateCreateBbCodeTagGallery(&$templateName, array &$params, XenForo_Template_Abstract $template)
	{
		if ($template instanceof XenForo_Template_Public)
		{
			$template->preloadTemplate('xengallery_bb_code_tag_gallery');
		}
	}

	public static function templateCreateHelperCriteriaPage(&$templateName, array &$params, XenForo_Template_Abstract $template)
	{
		if ($template instanceof XenForo_Template_Admin)
		{
			$template->preloadTemplate('xengallery_helper_criteria_page');
		}
	}

	public static function initDependencies(XenForo_Dependencies_Abstract $dependencies, array $data)
	{
		XenForo_Template_Helper_Core::$helperCallbacks['dummy'] = array(
			'XenGallery_Template_Helper_Core', 'helperDummyImage'
		);

		XenForo_Template_Helper_Core::$helperCallbacks['galleryuniqueid'] = array(
			'XenGallery_Template_Helper_Core', 'helperGalleryUniqueId'
		);

		XenForo_Template_Helper_Core::$helperCallbacks['mediafieldtitle'] = array(
			'XenGallery_Template_Helper_Core', 'getMediaFieldTitle'
		);

		XenForo_Template_Helper_Core::$helperCallbacks['mediafieldvalue'] = array(
			'XenGallery_Template_Helper_Core', 'getMediaFieldValueHtml'
		);

		XenForo_Template_Helper_Core::$helperCallbacks['shortnumber'] = array(
			'XenGallery_Template_Helper_Core', 'helperShortNumber'
		);

		XenForo_Template_Helper_Core::$helperCallbacks['watermark'] = array(
			'XenGallery_Template_Helper_Core', 'helperWatermarkUrl'
		);

		XenForo_Template_Helper_Core::$helperCallbacks['xmgjs'] = array(
			'XenGallery_Template_Helper_Core', 'helperXmgJs'
		);
	}

	public static function prepareUserChangeLogField(XenForo_Model_UserChangeLog $logModel, array &$field)
	{
		if (strstr($field['field'], 'xengallery') && !isset($field['name']))
		{
			$field['name'] = new XenForo_Phrase($field['field']);

			$values = array(
				'old_value' => $field['old_value'],
				'new_value' => $field['new_value']
			);
			foreach ($values AS &$value)
			{
				switch ($value)
				{
					case 'watch_no_email': case 1: $value = new XenForo_Phrase('yes'); break;
					case 'watch_email': $value = new XenForo_Phrase('yes_with_email'); break;
					case '': case 0: $value = new XenForo_Phrase('no'); break;
				}
			}
			$field['old_value'] = $values['old_value'];
			$field['new_value'] = $values['new_value'];
		}
	}
}