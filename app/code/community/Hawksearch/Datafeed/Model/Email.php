<?php
/**
 * Copyright (c) 2013 Hawksearch (www.hawksearch.com) - All Rights Reserved
 *
 * THIS CODE AND INFORMATION ARE PROVIDED "AS IS" WITHOUT WARRANTY OF ANY
 * KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND/OR FITNESS FOR A
 * PARTICULAR PURPOSE.
 */
 
class Hawksearch_Datafeed_Model_Email extends Mage_Core_Model_Abstract {

    /**
     * Set up some default variables that can be set from sys config
     */
    public function __construct() {
        $this->setFromName(Mage::getStoreConfig('trans_email/ident_general/name'));
        $this->setFromEmail(Mage::getStoreConfig('trans_email/ident_general/email'));
        $this->setType('text');
    }

    /**
     * Hawksearch feed generation email subject
     *
     * @return string
     */
    public function getSubject() {
        return "HawkSearch Scheduled Feed Generation";
    }

    /**
     * Hawksearch feed generation email body
     *
     * @return string
     */
    public function getBody() {
        return <<<BODY
{$this->getData('msg')}

Please check HawkSearch log files for more information (if an error is reported above)

Sincerely,
HawkSearch Administrator

BODY;
    }

    public function send() {
        $email = Mage::helper('hawksearch_datafeed')->getCronEmail();
        if ($email) {
            mail($email, $this->getSubject(), $this->getBody(), "From: {$this->getFromName()} <{$this->getFromEmail()}>\r\nReply-To: {$this->getFromEmail()}");
        }
    }

}