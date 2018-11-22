<?php
require_once 'Mage/Checkout/controllers/OnepageController.php';
class Ecomatic_Ajax_OnepageController extends Mage_Checkout_OnepageController
{
    
    /**
     * Save payment ajax action
     *
     * Sets either redirect or a JSON response
     */
    public function savePaymentAction()
    {		
        if ($this->_expireAjax()) {
            return;
        }

        if ($this->isFormkeyValidationOnCheckoutEnabled() && !$this->_validateFormKey()) {
            return;
        }

        try {
            if (!$this->getRequest()->isPost()) {
                $this->_ajaxRedirectResponse();
                return;
            }

            $data = $this->getRequest()->getPost('payment', array());
            $result = $this->getOnepage()->savePayment($data);
			
			// redirect to cart if collector payment is selected
			if($data['method'] == 'collectorbank_invoice'){
				$result['redirect'] = Mage::getUrl('checkout/cart');
			} else {
				$redirectUrl = $this->getOnepage()->getQuote()->getPayment()->getCheckoutRedirectUrl();
				if (empty($result['error']) && !$redirectUrl) {
					$this->loadLayout('checkout_onepage_review');
					$result['goto_section'] = 'review';
					$result['update_section'] = array(
						'name' => 'review',
						'html' => $this->_getReviewHtml()
					);
				}
				if ($redirectUrl) {
					$result['redirect'] = $redirectUrl;
				}
			}
           
        } catch (Mage_Payment_Exception $e) {
            if ($e->getFields()) {
                $result['fields'] = $e->getFields();
            }
            $result['error'] = $e->getMessage();
        } catch (Mage_Core_Exception $e) {
            $result['error'] = $e->getMessage();
        } catch (Exception $e) {
            Mage::logException($e);
            $result['error'] = $this->__('Unable to set Payment Method.');
        }
        $this->_prepareDataJSON($result);
    }
    
}