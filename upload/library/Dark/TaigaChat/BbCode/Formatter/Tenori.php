<?php
  
//                                      :3
class Dark_TaigaChat_BbCode_Formatter_Tenori extends XenForo_BbCode_Formatter_Base
{
	
	/**
	 * List of tags which should be displayed
	 *
	 * @var boolean|array True=all, false=none, array=display these
	 */
	public $displayableTags = true;
	
		
	public function getTagsAgain(){		
		$this->_tags = null;
		$this->_tags = $this->getTags();
		$this->preLoadData();
	}
	
	public function getTags()
	{
		if ($this->_tags !== null)
		{
			return $this->_tags;
		}

		$tags = parent::getTags();

		foreach ($tags AS $tagName => &$tag)
		{
			if ($this->displayableTags === false || (is_array($this->displayableTags) && !in_array($tagName, $this->displayableTags)))
			{
				unset($tags[$tagName]);
			}
		}
		
		return $tags;
	}
		
}