<?php

class XenGallery_Deferred_CategoryMediaCount extends XenForo_Deferred_Abstract
{
	public function execute(array $deferred, array $data, $targetRunTime, &$status)
	{
		$data = array_merge(array(
			'position' => 0,
			'batch' => 5
		), $data);
		$data['batch'] = max(1, $data['batch']);

		/* @var $categoryModel XenGallery_Model_Category */
		$categoryModel = XenForo_Model::create('XenGallery_Model_Category');

		/* @var $mediaModel XenGallery_Model_Media */
		$mediaModel = XenForo_Model::create('XenGallery_Model_Media');

		$categoryIds = $categoryModel->getCategoryIdsInRange($data['position'], $data['batch']);
		if (sizeof($categoryIds) == 0)
		{
			return true;
		}

		foreach ($categoryIds AS $categoryId)
		{
			$count = $mediaModel->countMedia(array('category_id' => $categoryId));

			$categoryWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Category');
			$categoryWriter->setExistingData($categoryId);

			$categoryWriter->set('category_media_count', $count);

			$categoryWriter->save();

			$data['position'] = $categoryId;
		}

		$actionPhrase = new XenForo_Phrase('rebuilding');
		$typePhrase = new XenForo_Phrase('xengallery_rebuilding_category_media_counts');
		$status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

		return $data;
	}

	public function canCancel()
	{
		return true;
	}
}