<?php

class Ecomatic_Collectorbank_Block_Form extends Mage_Payment_Block_Form
{
    const AGREEMENTS_URL = '//www.collector.se/upload/Partners/Agreements/';
    const AGREEMENT_CREDIT = 'Credit_terms_All_';
    const AGREEMENT_CREDIT_COMPANY = 'Credit_terms_Comp_';
    const AGREEMENT_SECCI = 'SECCI_';
    const AGREEMENT_TYPE_ACCOUNT = 'InvAccount';

    protected function _construct() {
        $this->setTemplate('collectorbank/form.phtml');
        parent::_construct();
    }

    /**
     * Attempts to check if current checkout session has a company name
     * specified (for multi-step checkouts like Magento's default "one-step"
     * checkout where we can't rely on JS that monitors input fields)
     * @return bool
     */
    public function getHasCompanyName() {
        $company = Mage::getSingleton('checkout/session')
            ->getQuote()
            ->getBillingAddress()
            ->getData('company');
        if($company) { return true; }
        return false;
    }

    /**
     * Returns the URL to to the relevant Collector logo.
     * @return string Logo URL
     */
    protected function getLogoUrl() {
        return $this->getSkinUrl('images/ecomatic/collectorbank/logos/'.$this->getMethod()->getLogo());
    }

    /**
     * Constructs the agreement link.
     * @param string $type Type of link
     * @return string Link
     */
    public function getAgreementLink($type, $company = false) {
        $agreementUrl = self::AGREEMENTS_URL;
        $agreementCode = Mage::helper('collectorbank')->getModuleConfig('settings/agreement_code');
        if ($type == 'credit') {
            if($company) {
                $agreementType = self::AGREEMENT_CREDIT_COMPANY;
            }
            else {
                $agreementType = self::AGREEMENT_CREDIT;
            }
            
            if ($this->getMethodCode() == 'collectorbank_invoice') {
                $linkText = $this->__('General terms invoice and account credit');
            } else {
                $linkText = $this->__('General terms partpayment credit');
            }
        }
        elseif ($type == 'secci') {
            $agreementType = self::AGREEMENT_SECCI;
            $linkText = $this->__('SECCI');
        }
        $agreementInvoiceType = '';
        $agreementCountry = Mage::helper('collectorbank/invoiceservice')->getCountryCode();

        $href = $agreementUrl . $agreementCode . '/' . $agreementType . $agreementInvoiceType . $agreementCountry . '.pdf';

        return '<a target="_blank" href="'.$href.'">'.$linkText.'</a>';
    }
    
    /**
     * Fetches the info text for the current payment method
     * @return string Info text
     */
    public function getInfoText() {
        return $this->getMethod()->getInfoText();
    }
    
    /**
     * Checks if campaigns are active for the current payment method
     * @return bool True if campaigns are active
     */
    protected function useCampaign() {
        return $this->getMethod()->useCampaign();
    }
    
    /**
     * Fetches the list of campaigns for the current payment method
     * @return array Campaigns as array sorted by position property.
     */
    public function getCampaignList($company = false) {
        $method = $this->getMethod();

        $campaignList = array();
       if($company) {
            $campaignList = unserialize($method->getConfigData('campaign_list_company'));
        }
        else {
            $campaignList = unserialize($method->getConfigData('campaign_list'));
        }

        $_showNoCampaign = $method->getConfigData('show_no_campaign');

        // If "Show 'No Campaign' option" is enabled we add it at the top of the list
        if($_showNoCampaign == 1 && is_array($campaignList) && !empty($campaignList)) {
            $_campaignListCopy = array_slice($campaignList, 0);
            usort($_campaignListCopy, function($a, $b) { return $a['position'] - $b['position']; });
            $_lowestPosition = $_campaignListCopy[0]['position'];

            array_push($campaignList, array(
                "label"	=> $this->getMethod()->getConfigData('no_campaign_label'),
                "value" => "",
                "position" => $_lowestPosition-1));
        }

        if (is_array($campaignList) && is_array($campaignList) && !empty($campaignList)) {
            foreach ($campaignList as $campaign) {
                $options[$campaign['position']] = $campaign;
            }
            ksort($options);

            // Sort occasionally loses campaigns so only rely on the sorted
            // data if it's the same size as the unsorted data.
            if(sizeof($options) == sizeof($campaignList)) {
                return $options;
            }
            return $campaignList;
        }
        return array();
    }
    
    public function getCampaignHtmlSelect() {
        $html = $this->getLayout()->createBlock('core/html_select')
            ->setName('payment['.$this->getMethod()->getCode().'_campaign_id]')
            ->setTitle(Mage::helper('collectorbank')->__('Choose Campaign'))
            ->setId($this->getMethod()->getCode().'_campaign_id')
            ->setClass('collector-campaign')
            ->setOptions($this->getCampaignList())
            ->getHtml();
        return $html;
    }

    /**
     * Radio button replacement for getCampaignHtmlSelect()
     * Takes a slightly more manual approach to generating its
     * HTML as there is no corresponding core/html_radio block
     */
    public function getCampaignHtmlRadio($company = false)
    {
        $_name = $this->getMethod()->getCode();
        $_campaignList = $this->getCampaignList($company);

        $_html = '<ul>';
        $_firstIteration = true;
        foreach ($_campaignList as $_campaign) {
            $_html .= '<li><input type="radio" name="payment['.$_name.'_campaign_id]" '.($_firstIteration ? 'checked="checked" ' : '').'id="'.$_name.'_campaign_id" class="" value="'.$_campaign['value'].'">'.$_campaign['label'].'</input></li>';
            if ($_firstIteration == true) {
                $_firstIteration = false;
            }
        }
        $_html .= '</ul>';

        return $_html;
    }
}
