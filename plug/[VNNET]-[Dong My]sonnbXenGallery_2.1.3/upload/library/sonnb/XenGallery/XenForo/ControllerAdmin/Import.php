<?php

class sonnb_XenGallery_XenForo_ControllerAdmin_Import extends XFCP_sonnb_XenGallery_XenForo_ControllerAdmin_Import
{
	public function actionComplete()
	{
		if ($this->_request->isPost())
		{
			$session = new XenForo_ImportSession();
			$key = $session->getImporterKey();

			if (strpos($key, 'sonnb_XenGallery') !== false)
			{
				$GLOBALS[sonnb_XenGallery_Listener::SONNB_XENGALLERY_IMPORTING] = true;
			}
		}

		return parent::actionComplete();
	}
}