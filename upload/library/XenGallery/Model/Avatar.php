<?php

class XenGallery_Model_Avatar extends XFCP_XenGallery_Model_Avatar
{
	public function insertAvatar($filePath, $userId, $permissions)
	{
		if (!$userId)
		{
			throw new XenForo_Exception('Missing user ID.');
		}

		if ($permissions !== false && !is_array($permissions))
		{
			throw new XenForo_Exception('Invalid permission set.');
		}

		$data = getimagesize($filePath);
		$imageType = $data[2];

		$image = XenForo_Image_Abstract::createFromFile($filePath, $imageType);

		if (!$image)
		{
			throw new XenForo_Exception(new XenForo_Phrase('uploaded_file_is_not_valid_image'), true);
		};

		if (!in_array($imageType, array(IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG)))
		{
			throw new XenForo_Exception(new XenForo_Phrase('uploaded_file_is_not_valid_image'), true);
		}

		$width = $image->getWidth();
		$height = $image->getHeight();

		return $this->applyAvatar($userId, $filePath, $imageType, $width, $height, $permissions);
	}
}