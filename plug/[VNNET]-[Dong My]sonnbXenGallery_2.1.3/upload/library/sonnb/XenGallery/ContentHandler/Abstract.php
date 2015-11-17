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
abstract class sonnb_XenGallery_ContentHandler_Abstract
{
	/**
	 * @var array
	 */
	public static $handlers = array(
		'content' => 'sonnb_XenGallery_ContentHandler_Content',
		'photo' => 'sonnb_XenGallery_ContentHandler_Content',
		'video' => 'sonnb_XenGallery_ContentHandler_Content',
		'album' => 'sonnb_XenGallery_ContentHandler_Album'
	);

	/**
	 * @var array
	 */
	protected static $_handlerClasses = array();

	/**
	 * @param $class
	 * @return sonnb_XenGallery_ContentHandler_Abstract
	 * @throws XenForo_Exception
	 */
	public static function create($class)
	{
		if (isset(self::$_handlerClasses[$class]))
		{
			return self::$_handlerClasses[$class];
		}

		$objClass = self::$handlers[$class];

		self::$_handlerClasses[$class] = new $objClass();
		if (self::$_handlerClasses[$class] instanceof sonnb_XenGallery_ContentHandler_Abstract)
		{
			return self::$_handlerClasses[$class];
		}

		unset(self::$_handlerClasses[$class]);
		throw new XenForo_Exception("Invalid collection handler '$class' specified");
	}

	abstract public function getContentById($contentId, array $viewingUser = null);
	abstract public function getContentsByIds(array $contentIds, array $viewingUser = null);
	abstract public function canViewContent(array $item, array $viewingUser = null);
	abstract public function getContentLink(array $item);

	abstract public function renderHtml(array $item, XenForo_View $view);
}