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
class sonnb_XenGallery_Listener
{
	protected static $_addedUsernameChange = false;
	protected static $_addedImporter = false;

	protected static $_hasPerm = null;
	protected static $_hasImportOwnThreadPerm = null;
	protected static $_hasImportAnyThreadPerm = null;

	const SONNB_XENGALLERY_IMPORTING = 'SONNB_XENGALLERY_IMPORTING';
	
	protected static $extendClasses = array(			
		'XenForo_DataWriter_User',
		'XenForo_DataWriter_Discussion_Thread',

		'XenForo_Model_Moderator',
		'XenForo_Model_User',
		'XenForo_Model_Deferred',

		'XenForo_ControllerPublic_Account',
		'XenForo_ControllerPublic_Thread',

		'XenForo_ControllerAdmin_Import',

		'XenForo_BbCode_Formatter_Base',
		'XenForo_BbCode_Formatter_BbCode_Filter',
	);

	/**
	 * @param $class
	 * @param array $extend
	 */
	public static function load_class($class, array &$extend)
	{
		if (in_array($class, self::$extendClasses))
		{
			$extend[] = 'sonnb_XenGallery_'.$class;
		}
		
		if (!self::$_addedImporter && $class === 'XenForo_Model_Import')
		{
			self::$_addedImporter = true;

			XenForo_Model_Import::$extraImporters[] = 'sonnb_XenGallery_Importer_DBTechGalleryPro';
			XenForo_Model_Import::$extraImporters[] = 'sonnb_XenGallery_Importer_ipb';
			XenForo_Model_Import::$extraImporters[] = 'sonnb_XenGallery_Importer_PhotoPlog';
			XenForo_Model_Import::$extraImporters[] = 'sonnb_XenGallery_Importer_PhotopostVbGallery2';
			XenForo_Model_Import::$extraImporters[] = 'sonnb_XenGallery_Importer_PhotopostVbGallery3';
			XenForo_Model_Import::$extraImporters[] = 'sonnb_XenGallery_Importer_PhotopostProXf';
			XenForo_Model_Import::$extraImporters[] = 'sonnb_XenGallery_Importer_PhotopostProVb';
			XenForo_Model_Import::$extraImporters[] = 'sonnb_XenGallery_Importer_Thread';
			XenForo_Model_Import::$extraImporters[] = 'sonnb_XenGallery_Importer_UserAlbum';
			XenForo_Model_Import::$extraImporters[] = 'sonnb_XenGallery_Importer_vbulletin4';
			XenForo_Model_Import::$extraImporters[] = 'sonnb_XenGallery_Importer_vbulletin38';
			XenForo_Model_Import::$extraImporters[] = 'sonnb_XenGallery_Importer_XenGallery';
			XenForo_Model_Import::$extraImporters[] = 'sonnb_XenGallery_Importer_XenMedioFree';
			XenForo_Model_Import::$extraImporters[] = 'sonnb_XenGallery_Importer_XenMedioPro';
			XenForo_Model_Import::$extraImporters[] = 'sonnb_XenGallery_Importer_VideoDirectory38';
			XenForo_Model_Import::$extraImporters[] = 'sonnb_XenGallery_Importer_XenMediaGallery';
		}

		if (!self::$_addedUsernameChange && $class === 'XenForo_DataWriter_User')
		{
			self::$_addedUsernameChange = true;

			XenForo_DataWriter_User::$usernameChangeUpdates['permanent']['xengallery_album'] = array('sonnb_xengallery_album', 'username', 'user_id');
			XenForo_DataWriter_User::$usernameChangeUpdates['permanent']['xengallery_content'] = array('sonnb_xengallery_content', 'username', 'user_id');
			XenForo_DataWriter_User::$usernameChangeUpdates['permanent']['xengallery_comment'] = array('sonnb_xengallery_comment', 'username', 'user_id');
			XenForo_DataWriter_User::$usernameChangeUpdates['permanent']['xengallery_myplaylist'] = array('sonnb_xengallery_myplaylist', 'username', 'user_id');
			XenForo_DataWriter_User::$usernameChangeUpdates['permanent']['xengallery_stream'] = array('sonnb_xengallery_stream', 'username', 'user_id');
			XenForo_DataWriter_User::$usernameChangeUpdates['permanent']['xengallery_watch'] = array('sonnb_xengallery_watch', 'username', 'user_id');
			XenForo_DataWriter_User::$usernameChangeUpdates['permanent']['xengallery_tag_tagged'] = array('sonnb_xengallery_tag', 'username', 'user_id');
			XenForo_DataWriter_User::$usernameChangeUpdates['permanent']['xengallery_tag_tagger'] = array('sonnb_xengallery_tag', 'tagger_username', 'tagger_user_id');
		}
	}

	/**
	 * @param $templateName
	 * @param array $params
	 * @param XenForo_Template_Abstract $template
	 */
	public static function template_create($templateName, array &$params, XenForo_Template_Abstract $template)
	{
		if (!self::canViewGallery())
		{
			return;
		}

		if (self::$_hasImportOwnThreadPerm === null)
		{
			self::$_hasImportOwnThreadPerm = XenForo_Visitor::getInstance()->hasPermission('sonnb_xengallery', 'importOwnThread');
		}

		if (self::$_hasImportAnyThreadPerm === null)
		{
			self::$_hasImportAnyThreadPerm = XenForo_Visitor::getInstance()->hasPermission('sonnb_xengallery', 'importAnyThread');
		}

		if ($template instanceof XenForo_Template_Admin)
		{
			return;
		}
		
		switch ($templateName)
		{
			case 'PAGE_CONTAINER':
				$template->preloadTemplate('sonnb_xengallery_nav_visitor');
				break;
			case 'account_wrapper':
				$template->preloadTemplate('sonnb_xengallery_account_nav');
				break;
			case 'member_card':
				$template->preloadTemplate('sonnb_xengallery_member_card');
				$template->preloadTemplate('sonnb_xengallery_member_card_links');
				break;
			case 'member_view':
				$template->preloadTemplate('sonnb_xengallery_member_view_info');
				$template->preloadTemplate('sonnb_xengallery_member_view_tabs_heading');
				$template->preloadTemplate('sonnb_xengallery_member_view_tabs_content');
			case 'thread_view':
				if (self::$_hasImportAnyThreadPerm || self::$_hasImportOwnThreadPerm)
				{
					$template->preloadTemplate('sonnb_xengallery_thread_import');
				}
				break;
		}

		if (strpos($templateName, 'sonnb_xengallery') !== false)
		{
			$template->preloadTemplate('sonnb_xengallery_option_copyright');

			if (XenForo_Application::getConfig()->get('sonnbXengalleryCopyrightRemoved') !== true)
			{
				$template->preloadTemplate('sonnbXG_copyright');
			}
		}
	}

	/**
	 * @param $templateName
	 * @param $content
	 * @param array $containerData
	 * @param XenForo_Template_Abstract $template
	 */
	public static function template_post_render($templateName, &$content, array &$containerData, XenForo_Template_Abstract $template)
	{
		if ($template instanceof XenForo_Template_Admin)
		{
			$params = $template->getParams();

			switch ($templateName)
			{
				case 'tools_rebuild':
					$content .= $template->create('sonnb_xengallery_tools_rebuild_gallery', $params)->render();
					break;
				case 'option_list':
					if (isset($params['group']['group_id']) &&
						$params['group']['group_id'] === 'sonnb_xengallery')
					{
						$content = $template->create('sonnb_xengallery_option_tab', $params);
					}
					break;
			}
		}

		if ($template instanceof XenForo_Template_Public)
		{
			if (!self::canViewGallery())
			{
				return;
			}

			$xenOptions = XenForo_Application::getOptions();
			$params = $template->getParams();

			switch ($templateName)
			{
				case 'thread_view':
					$applicableNodes = $xenOptions->sonnb_XG_importNodes;
					if (!empty($applicableNodes) &&
						!empty($params['thread']) &&
						(in_array($params['thread']['node_id'], $applicableNodes)) &&
							(empty($params['thread']['sonnb_xengallery_import']) || $xenOptions->sonnbXG_allowMultipleImport) &&
							(self::$_hasImportAnyThreadPerm ||
								($params['thread']['user_id'] == XenForo_Visitor::getUserId() && self::$_hasImportOwnThreadPerm)))
					{
						if (preg_match('#<div[^>]*?\bclass="linkGroup\s*SelectionCountContainer"[^>]*+>(.*)<\/div>#Usi', $content, $matchCtrl))
						{
							$btnHtml = $template->create('sonnb_xengallery_thread_import', $template->getParams());
							$matchCtrlNew = str_replace($matchCtrl[1], $btnHtml.$matchCtrl[1], $matchCtrl[0]);
							$content = str_replace($matchCtrl[0], $matchCtrlNew, $content);
						}
					}
					break;
			}
		}
	}

	/**
	 * @param $hookName
	 * @param $contents
	 * @param array $hookParams
	 * @param XenForo_Template_Abstract $template
	 */
	public static function template_hook($hookName, &$contents, array $hookParams, XenForo_Template_Abstract $template)
	{
		if (!self::canViewGallery())
		{
			return;
		}

		if ($template instanceof XenForo_Template_Admin)
		{
			return;
		}

		$params = array_merge($template->getParams(), $hookParams);

		switch ($hookName)
		{
			case 'account_wrapper_sidebar':
				$contents .= $template->create('sonnb_xengallery_account_nav', $params);
				break;
			case 'navigation_visitor_tab_links2':
				$contents .= $template->create('sonnb_xengallery_nav_visitor', $params);
				break;
			case 'member_card_stats':
				$contents .= $template->create('sonnb_xengallery_member_card', $params);
				break;
			case 'member_view_info_block':
				$contents .= $template->create('sonnb_xengallery_member_view_info', $params);
				break;
			case 'member_card_links':
				$contents .= $template->create('sonnb_xengallery_member_card_links', $params);
				break;
			case 'member_view_tabs_heading':
				$contents .= $template->create('sonnb_xengallery_member_view_tabs_heading', $params);
				break;
			case 'member_view_tabs_content':
				$contents .= $template->create('sonnb_xengallery_member_view_tabs_content', $params);
				break;
			case 'search_form_tabs':
				$contents .= $template->create('sonnb_xengallery_search_form_tabs_album', $params);
				$contents .= $template->create('sonnb_xengallery_search_form_tabs_photo', $params);
				$contents .= $template->create('sonnb_xengallery_search_form_tabs_video', $params);
				break;
			case 'sonnb_cr_information':
				if ($tos = XenForo_Application::getOptions()->sonnbXG_copyright)
				{
					$contents .= $template->create('sonnb_xengallery_option_copyright', array('tos' => $tos));
				}
				if (XenForo_Application::getConfig()->get('sonnbXengalleryCopyrightRemoved') !== true)
				{
					$contents .= $template->create('sonnb_xengallery_copyright');
				}
				break;
			case 'editor':
				if (XenForo_Application::$versionId > 1020000 && XenForo_Application::getOptions()->sonnbXG_editorButtons)
				{
					$template->addRequiredExternal('js', 'js/sonnb/XenGallery/gallery.editor.js');
				}
				break;
			case 'xengallery_quick_navigation_menu':
				$contents .= $template->create('sonnb_xengallery_quick_navigation_menu_gallery');
				break;
		}
	}

	/**
	 * @param array $extraTabs
	 * @param $selectedTabId
	 */
	public static function navigation_tabs(array &$extraTabs, $selectedTabId)
	{
		if (!self::canViewGallery())
		{
			return;
		}

		if (self::$_hasPerm)
		{
			$extraTabs['sonnb_xengallery'] = array(
				'title' => new XenForo_Phrase('sonnb_xengallery_gallery'),
				'href' => XenForo_Link::buildPublicLink('full:gallery'),
				'selectedTabId' => XenForo_Application::getOptions()->sonnbXG_disableNavPopup ? '' : $selectedTabId,
				'position' => 'middle',
				'linksTemplate' => 'sonnb_xengallery_navbar_template',
			);
		}
	}

	/**
	 * @param array $renderers
	 */
	public static function widget_framework_ready(array &$renderers)
	{
		$renderers[] = 'sonnb_XenGallery_WidgetRenderer_Album';
		$renderers[] = 'sonnb_XenGallery_WidgetRenderer_Photo';
		$renderers[] = 'sonnb_XenGallery_WidgetRenderer_Comment';
		$renderers[] = 'sonnb_XenGallery_WidgetRenderer_Video';
		$renderers[] = 'sonnb_XenGallery_WidgetRenderer_Content';
	}

	/**
	 * @param XenForo_Dependencies_Abstract $dependencies
	 * @param array $data
	 */
	public static function init_dependencies(XenForo_Dependencies_Abstract $dependencies, array $data) 
	{
		if ($dependencies instanceof XenForo_Dependencies_Public)
		{
	        XenForo_Template_Helper_Core::$helperCallbacks += array(
	            'sonnb_xengallery_tag' => array('sonnb_XenGallery_Template_Helper_Tag', 'helperTags'),
		        'sonnb_xengallery_cover' => array('sonnb_XenGallery_Template_Helper_Cover', 'helperCover')
	        );
		}

		if ($dependencies instanceof XenForo_Dependencies_Admin)
		{
			XenForo_CacheRebuilder_Abstract::$builders['sonnbXenGalleryAlbum'] = 'sonnb_XenGallery_CacheRebuilder_Album';
			XenForo_CacheRebuilder_Abstract::$builders['sonnbXenGalleryContent'] = 'sonnb_XenGallery_CacheRebuilder_Content';
			XenForo_CacheRebuilder_Abstract::$builders['sonnbXenGalleryLocation'] = 'sonnb_XenGallery_CacheRebuilder_Location';
		}
	}

	/**
	 * @param XenForo_View $view
	 * @param $formCtrlName
	 * @param $message
	 * @param array $editorOptions
	 * @param $showWysiwyg
	 */
	public static function editor_setup(XenForo_View $view, $formCtrlName, &$message, array &$editorOptions, &$showWysiwyg)
	{
		if ($showWysiwyg && XenForo_Application::getOptions()->sonnbXG_editorButtons)
		{
			$view->createOwnTemplateObject()->addRequiredExternal('css', 'sonnb_xengallery_editor');

			$time = XenForo_Application::$time;
			$visitor = XenForo_Visitor::getInstance();

			if ($formCtrlName !== 'signature' || ($formCtrlName === 'signature' && $visitor->hasPermission('signature', 'sonnbXG_album')))
			{
				$editorOptions['json']['buttons']['insertAlbum'] = array(
					'title' => new XenForo_Phrase('sonnb_xengallery_insert_album'),
					'dialogUrl' => XenForo_Link::buildPublicLink('gallery/editor', null, array('type' => 'album', 'time' => $time))
				);
			}

			if ($formCtrlName !== 'signature' || ($formCtrlName === 'signature' && ($visitor->hasPermission('signature', 'sonnbXG_photo') || $visitor->hasPermission('signature', 'sonnbXG_video'))))
			{
				$editorOptions['json']['buttons']['insertContent'] = array(
					'title' => new XenForo_Phrase('sonnb_xengallery_insert_content'),
					'dialogUrl' => XenForo_Link::buildPublicLink('gallery/editor', null, array('type' => 'content', 'time' => $time))
				);
			}
		}
	}

	/**
	 * @param XenForo_View $view
	 * @param $fieldPrefix
	 * @param array $preparedOption
	 * @param $canEdit
	 * @return XenForo_Template_Abstract
	 */
	public static function renderStyleList(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$preparedOption['formatParams'] = self::getStyleOptions(
			$preparedOption['option_value']
		);

		return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
			'sonnb_xengallery_styles', $view, $fieldPrefix, $preparedOption, $canEdit
		);
	}

	/**
	 * @param $optionValue
	 * @param XenForo_DataWriter $dw
	 * @param $optionId
	 * @return bool
	 */
	public static function renderStyleListVerify(&$optionValue, XenForo_DataWriter $dw, $optionId)
	{
		if ($optionId === 'sonnbXG_forceStyle')
		{
			$styleModel = XenForo_Model::create('XenForo_Model_Style');
			$styles = $styleModel->getAllStylesAsFlattenedTree();

			if (!in_array($optionValue, array_keys($styles)))
			{
				$optionValue = 0;
			}
		}

		return true;
	}

	/**
	 * @param $selectedStyle
	 * @return array
	 */
	public static function getStyleOptions($selectedStyle)
	{
		$styleModel = XenForo_Model::create('XenForo_Model_Style');
		$styles = $styleModel->getAllStylesAsFlattenedTree();

		$data = array();
		foreach ($styles AS $styleId => $style)
		{
			$data[$styleId] = array(
				'value' => $styleId,
				'label' => $style['title'],
				'selected' => $styleId === $selectedStyle
			);
		}

		return $data;
	}

	/**
	 * @param XenForo_View $view
	 * @param $fieldPrefix
	 * @param array $preparedOption
	 * @param $canEdit
	 * @return XenForo_Template_Abstract
	 */
	public static function renderNodes(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
    {
        $preparedOption['formatParams'] = self::getNodeOptions(
        	$preparedOption['option_value']
        );

        return XenForo_ViewAdmin_Helper_Option::renderOptionTemplateInternal(
        	'sonnb_xengallery_nodes', $view, $fieldPrefix, $preparedOption, $canEdit
        );
    }

	/**
	 * @param $selectedForum
	 * @param bool $includeRoot
	 * @return array
	 */
	public static function getNodeOptions($selectedForum, $includeRoot = false)
    {
        $nodeModel = XenForo_Model::create('XenForo_Model_Node');

        $options = array();

        foreach ($nodeModel->getAllNodes() AS $nodeId => $node)
        {
            $node['depth'] += (($includeRoot && $nodeId) ? 1 : 0);

            $options[$nodeId] = array(
                'value' => $nodeId,
                'label' => $node['title'],
                'selected' => in_array($nodeId, $selectedForum),
                'depth' => $node['depth'],
                'node_type_id' => $node['node_type_id']
            );
        }

        return $options;
    }

	protected static function canViewGallery()
	{
		if (self::$_hasPerm === null)
		{
			self::$_hasPerm = XenForo_Visitor::getInstance()->hasPermission('sonnb_xengallery', 'viewGallery');
		}

		return self::$_hasPerm;
	}
}