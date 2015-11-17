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
class sonnb_XenGallery_XenForo_BbCode_Formatter_BbCode_Filter extends XFCP_sonnb_XenGallery_XenForo_BbCode_Formatter_BbCode_Filter
{
	public function getTags()
	{
		$tags = parent::getTags();

        $tags['photo'] = array(
            //'hasOption' => true,
            //'optionRegex' => '/^[a-z]+$/',
            'callback' => array($this, 'filterTag'),
            'plainChildren' => true,
        );

        $tags['video'] = array(
            //'hasOption' => true,
            //'optionRegex' => '/^[a-z]+$/',
            'callback' => array($this, 'filterTag'),
            'plainChildren' => true,
        );

        $tags['album'] = array(
            //'hasOption' => true,
            //'optionRegex' => '/^[a-z]+$/',
            'callback' => array($this, 'filterTag'),
            'plainChildren' => true,
        );

		return $tags;
	}

	public function configureFromSignaturePermissions(array $perms)
	{
		parent::configureFromSignaturePermissions($perms);

		if (!XenForo_Permission::hasPermission($perms, 'signature', 'sonnbXG_album'))
		{
			$this->disableTags('album');
		}
		if (!XenForo_Permission::hasPermission($perms, 'signature', 'sonnbXG_video'))
		{
			$this->disableTags('video');
		}
		if (!XenForo_Permission::hasPermission($perms, 'signature', 'sonnbXG_photo'))
		{
			$this->disableTags('photo');
		}
	}

	public function filterTag(array $tag, array $rendererStates)
	{
		if (!in_array('album', $this->_nonPrintableTags))
		{
			$this->_nonPrintableTags += array('album', 'photo', 'video');
		}

		return parent::filterTag($tag, $rendererStates);
	}

	protected function _handleDisabledTag(array $tag, $text, array $rendererStates)
	{
		if (!in_array('album', $this->_nonPrintableTags))
		{
			$this->_nonPrintableTags += array('album', 'photo', 'video');
		}

		return parent::_handleDisabledTag($tag, $text, $rendererStates);
	}
}