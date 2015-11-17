<?php

class vtPhong_DataWriter_SlideShow extends XenForo_DataWriter
{

    /**
     * Title of the phrase that will be created when a call to set the
     * existing data fails (when the data doesn't exist).
     *
     * @var string
     */
    protected $_existingDataErrorPhrase = 'requested_product_not_found';

    /**
     * Gets the fields that are defined for the table. See parent for explanation.
     *
     * @return array
     */
    protected function _getFields()
    {
        return array(
            'xf_thread_slide' => array(
                'id' => array('type' => self::TYPE_UINT, 'autoIncrement' => TRUE),
                'thread_id' => array('type' => self::TYPE_UINT, 'required' => TRUE),
                'url_slide' => array('type' => self::TYPE_STRING, 'default' => ''),
                'title_slide' => array('type' => self::TYPE_STRING, 'default' => ''),
                'des_slide' => array('type' => self::TYPE_STRING, 'default' => '')
            )
        );
    }

    /**
     * Gets the actual existing data out of data that was passed in. See parent for explanation.
     *
     * @param mixed
     *
     * @return array|false
     */
    protected function _getExistingData($data)
    {}

    /**
     * Gets SQL condition to update the existing record.
     *
     * @return string
     */
    protected function _getUpdateCondition($tableName)
    {}

}