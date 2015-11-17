<?php

class sonnb_XenGallery_XenForo_ControllerAdmin_Tools extends XFCP_sonnb_XenGallery_XenForo_ControllerAdmin_Abstract
{
	public function actionCacheRebuild()
	{
		if (XenForo_Application::$versionId < 1020000 && isset($GLOBALS[sonnb_XenGallery_Listener::SONNB_XENGALLERY_IMPORTING]))
		{
			$this->getRequest()->setParam('caches', array('sonnbXenGalleryAlbum', 'sonnbXenGalleryContent', 'User'));

			unset($GLOBALS[sonnb_XenGallery_Listener::SONNB_XENGALLERY_IMPORTING]);
		}

		return parent::actionCacheRebuild();
	}
}