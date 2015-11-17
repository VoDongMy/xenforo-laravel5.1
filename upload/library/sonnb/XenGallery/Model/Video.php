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
class sonnb_XenGallery_Model_Video extends sonnb_XenGallery_Model_Content
{

	public static $contentType = 'video';
	public static $xfContentType = 'sonnb_xengallery_video';

	public function countVideosByUserId($userId, array $conditions = array())
	{
		if (!$userId)
		{
			return 0;
		}

		$conditions['user_id'] = $userId;
		$conditions['content_type'] = self::$contentType;

		return $this->countContents($conditions);
	}

	public function getVideoDataConstraints()
	{
		$visitor = XenForo_Visitor::getInstance();
		$xenOptions = XenForo_Application::getOptions();

		$videoSize = $visitor->hasPermission('sonnb_xengallery', 'limitVideoSize');
		$maxFiles = $this->getVideoCountLimit();

		$globalMaximumSize = $xenOptions->sonnbXG_globalMaxVideoSize;
		if ($globalMaximumSize < 1024)
		{
			$globalMaximumSize = 150000*1024; //150mb
		}

		$constraints = array(
			'extensions' => sonnb_XenGallery_Model_ContentData::$videoExtension,
			'size' => ($videoSize > 0 ? $videoSize: $globalMaximumSize),
			'width' => 4096,
			'height' => 3072,
			'count' => ($maxFiles > 0 ? $maxFiles: 0),
		);

		return $constraints;
	}

	public function getVideoCountLimit(array $viewingUser = null)
	{
		$this->standardizeViewingUserReference($viewingUser);

		$maxFiles = XenForo_Permission::hasPermission($viewingUser['permissions'], 'sonnb_xengallery', 'maximumVideo');

		return ($maxFiles <= 0 ? 0 : $maxFiles);
	}
}
