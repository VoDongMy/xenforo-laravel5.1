<?php

class XenGallery_Option_Redirects
{
	public $addOnId = '';
	public $route = '';
	public $replaceRoute = '';

	public function verifyOptionForAddOn(&$optionValue, XenForo_DataWriter $dw, $fieldName)
	{
		if ($optionValue && !$optionValue['log'])
		{
			$dw->error(new XenForo_Phrase('xengallery_no_import_log_table_specified'), $fieldName);
		}

		$routeFilterWriter = XenForo_DataWriter::create('XenForo_DataWriter_RouteFilter');

		$filterExists = XenForo_Application::getDb()->fetchRow('
			SELECT *
			FROM xf_route_filter
			WHERE find_route = ?
		', 'xengallery-' . $this->route . '/');

		if ($optionValue)
		{
			if (!$filterExists)
			{
				$routeFilterWriter->bulkSet(array(
					'route_type' => 'public',
					'prefix' => 'xengallery-' . $this->route,
					'find_route' => 'xengallery-' . $this->route,
					'replace_route' => $this->replaceRoute,
					'enabled' => 1,
					'url_to_route_only' => 0
				));

				$routeFilterWriter->save();
			}
		}
		else
		{
			if ($filterExists)
			{
				$routeFilterWriter->setExistingData($filterExists);
				$routeFilterWriter->delete();
			}
		}

		return true;
	}
}