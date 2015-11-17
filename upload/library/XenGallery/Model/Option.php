<?php

class XenGallery_Model_Option extends XFCP_XenGallery_Model_Option
{
	protected $_optionDisplayOrderMap = array(
		1 => '#mediaOptions',
		2 => '#imageOptions',
		3 => '',
		4 => '#watermarkOptions',
		5 => '#pageLayoutOptions',
		6 => '#blocksOptions',
		7 => '#mediaSiteOptions',
		8 => '#redirectOptions'
	);

	public function prepareOptions(array $options, $includeDisabledAddOns = true)
	{
		$options = parent::prepareOptions($options, $includeDisabledAddOns);

		$optionIds = array();

		foreach ($options AS $optionId => $option)
		{
			if (empty($option['addon_id']) || $option['addon_id'] != 'XenGallery')
			{
				continue;
			}


			$optionIds[] = $optionId;
		}

		$optionRelations = $this->getOptionRelationsGroupedByOption($optionIds);

		foreach ($optionRelations AS $optionId => $group)
		{
			foreach ($group AS $groupId => $relation)
			{
				if ($groupId == 'XenGallery')
				{
					$linkSuffixId = floor($relation['display_order'] / 100);
					if (isset($this->_optionDisplayOrderMap[$linkSuffixId]))
					{
						$options[$relation['option_id']]['linkSuffix'] = $this->_optionDisplayOrderMap[$linkSuffixId];
					}
				}
			}
		}

		return $options;
	}
}