<?php

class XenGallery_Deferred_XenMedioBbConversion extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'position' => 0,
			'importLog' => '',
			'batch' => 0
		), $data);

		if (!$data['importLog'])
		{
			$data['importLog'] = 'xf_import_log';
		}

		/** @var $importModel XenGallery_Model_Importers */
		$importModel = XenForo_Model::create('XenGallery_Model_Importers');

		/** @var $mediaModel XenGallery_Model_Media */
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');

		/** @var $postModel XenForo_Model_Post */
		$postModel = XenForo_Model::create('XenForo_Model_Post');

		$postIds = $mediaModel->getPostIdsInRangeContaining($data['position'], $data['batch'], '[medio=full]');
		if (sizeof($postIds) == 0)
		{
			return true;
		}

		foreach ($postIds AS $postId)
		{
			$post = $postModel->getPostById($postId);

			$contentId = preg_match('!\d+!', $post['message'], $matches) ? (int)$matches[0] : NULL;
			$newContentId = $importModel->getImportContentMap('xengallery_media', $contentId, $data['importLog']);

			if ($newContentId)
			{
				$newContentId = reset($newContentId);
			}
			else
			{
				$newContentId = $contentId;
			}

			$post['message'] = preg_replace('/\[medio=full\](.*?)\[\/medio\]/is', '[GALLERY=media, ' . $newContentId . '][/GALLERY]', $post['message']);

			$dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post', XenForo_DataWriter::ERROR_SILENT);
			$dw->setExistingData($postId);
			$dw->set('message', $post['message']);

			$dw->save();

			$data['position'] = $postId;
		}

		$actionPhrase = new XenForo_Phrase('xengallery_converting');
		$typePhrase = new XenForo_Phrase('xengallery_xenmedio_bb_codes');

		$status = sprintf('%s... %s', $actionPhrase, $typePhrase);

		return $data;
	}
}