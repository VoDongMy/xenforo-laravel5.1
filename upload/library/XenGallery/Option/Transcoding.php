<?php

class XenGallery_Option_Transcoding
{
	public static function verifyOption(array &$values, XenForo_DataWriter $dw, $fieldName)
	{
		if ($dw->isInsert())
		{
			return true;
		}

		if (empty($values['enabled']))
		{
			return true;
		}

		try
		{
			$helper = new XenGallery_Helper_Video(null, $values['ffmpegPath'], !empty($values['transcode']));
			$errors = $helper->getFfmpegErrors();
			if ($errors)
			{
				$dw->error(reset($errors), $fieldName);
				return false;
			}
		}
		catch (Exception $e)
		{
			$dw->error($e->getMessage(), $fieldName);
			return false;
		}

		if (!empty($values['transcode']))
		{
			try
			{
				if (!is_file($values['phpPath']) && !is_executable($values['phpPath']))
				{
					$dw->error(new XenForo_Phrase('xengallery_php_binary_path_could_not_be_verified'));
					return false;
				}
			}
			catch (Exception $e)
			{
				$dw->error($e->getMessage(), $fieldName);
				return false;
			}
		}

		return true;
	}
}