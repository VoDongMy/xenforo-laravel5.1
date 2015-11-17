<?php

class XenGallery_ControllerAdmin_Attachment extends XFCP_XenGallery_ControllerAdmin_Attachment
{
	public function actionIndex()
	{
		$parent = parent::actionIndex();

		if ($parent instanceof XenForo_ControllerResponse_View && !empty($parent->params['attachments']))
		{
			/** @var $mediaModel XenGallery_Model_Media */
			$mediaModel = $this->getModelFromCache('XenGallery_Model_Media');

			foreach ($parent->params['attachments'] AS &$attachment)
			{
				if ($attachment['content_type'] == 'xengallery_media')
				{
					$attachment = $mediaModel->prepareMedia($attachment);
				}
			}
		}

		return $parent;
	}
}