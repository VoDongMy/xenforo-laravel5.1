<?php

class XenGallery_ControllerAdmin_Option extends XFCP_XenGallery_ControllerAdmin_Option
{
	/**
	 * Lists all the options that belong to a particular group.
	 *
	 * @return XenForo_ControllerResponse_Abstract
	 */
	public function actionList()
	{
		$input = $this->_input->filter(array(
			'group_id' => XenForo_Input::STRING
		));

		if ($input['group_id'] == 'XenGallery')
		{
			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildAdminLink('xengallery/options')
			);
		}

		return parent::actionList();
	}

	public function actionXenGallerySave()
	{
		$this->_assertPostOnly();

		$input = $this->_input->filter(array(
			'group_id' => XenForo_Input::STRING,
			'options' => XenForo_Input::ARRAY_SIMPLE,
			'options_listed' => array(XenForo_Input::STRING, array('array' => true))
		));

		$options = XenForo_Application::getOptions();

		$optionModel = $this->_getOptionModel();
		$group = $optionModel->getOptionGroupById($input['group_id']);

		foreach ($input['options_listed'] AS $optionName)
		{
			if ($optionName == 'xengalleryUploadWatermark')
			{
				continue;
			}

			if (!isset($input['options'][$optionName]))
			{
				$input['options'][$optionName] = '';
			}
		}

		$delete = $this->_input->filterSingle('delete_watermark', XenForo_Input::BOOLEAN);
		if ($delete)
		{
			$existingWatermark = $options->get('xengalleryUploadWatermark');
			if ($existingWatermark)
			{
				$watermarkWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Watermark', XenForo_DataWriter::ERROR_SILENT);
				$watermarkWriter->setExistingData($existingWatermark);
				$watermarkWriter->delete();

				$input['options']['xengalleryUploadWatermark'] = 0;
				$optionModel->updateOptions($input['options']);

				return $this->responseRedirect(
					XenForo_ControllerResponse_Redirect::SUCCESS,
					$this->getDynamicRedirect(XenForo_Link::buildAdminLink('options/list', $group))
				);
			}
		}

		$fileTransfer = new Zend_File_Transfer_Adapter_Http();
		if ($fileTransfer->isUploaded('watermark'))
		{
			$fileInfo = $fileTransfer->getFileInfo('watermark');
			$fileName = $fileInfo['watermark']['tmp_name'];

			$watermarkWriter = XenForo_DataWriter::create('XenGallery_DataWriter_Watermark', XenForo_DataWriter::ERROR_SILENT);

			$existingWatermark = $options->get('xengalleryUploadWatermark');
			if ($existingWatermark)
			{
				$watermarkWriter->setExistingData($existingWatermark);
			}

			$watermarkData = array(
				'watermark_user_id' => XenForo_Visitor::getUserId(),
				'is_site' => 1
			);

			$watermarkWriter->bulkSet($watermarkData);
			$watermarkWriter->save();

			$image = new XenGallery_Helper_Image($fileName);
			$image->resize($options->xengalleryWatermarkDimensions['width'],
				$options->xengalleryWatermarkDimensions['height'], 'fit'
			);

			$watermarkModel = $this->_getWatermarkModel();
			$watermarkPath = $watermarkModel->getWatermarkFilePath($watermarkWriter->get('watermark_id'));

			if (XenForo_Helper_File::createDirectory(dirname($watermarkPath), true))
			{
				XenForo_Helper_File::safeRename($fileName, $watermarkPath);

				$input['options']['xengalleryUploadWatermark'] = $watermarkWriter->get('watermark_id');
			}
		}

		$optionModel->updateOptions($input['options']);

		return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$this->getDynamicRedirect(XenForo_Link::buildAdminLink('options/list', $group))
		);
	}

	/**
	 * @return XenGallery_Model_Watermark
	 */
	protected function _getWatermarkModel()
	{
		return $this->getModelFromCache('XenGallery_Model_Watermark');
	}
}