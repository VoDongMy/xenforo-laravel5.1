<?php

class sonnb_XenGallery_XenForo_Model_Deferred extends XFCP_sonnb_XenGallery_XenForo_Model_Deferred
{
	public function defer($class, array $data, $uniqueKey = null, $manual = false, $triggerDate = null)
	{
		if ($uniqueKey === 'importRebuild'
				&& !empty($GLOBALS[sonnb_XenGallery_Listener::SONNB_XENGALLERY_IMPORTING]))
		{
			$data = array(
				'simple' => array('sonnb_XenGallery_Deferred_Album', 'sonnb_XenGallery_Deferred_Content')
			);

			unset($GLOBALS[sonnb_XenGallery_Listener::SONNB_XENGALLERY_IMPORTING]);
		}

		return parent::defer($class, $data, $uniqueKey, $manual, $triggerDate);
	}
}