<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category   design_default
 * @package    AW_AlliedWallet
 * @copyright  Copyright (c) 2008 Allied Wallet (http://www.alliedwallet.com)
 */

class AWQP_AlliedWalletQP_ProcessingController extends Mage_Core_Controller_Front_Action
{
    protected $_redirectBlockType = 'alliedwalletqp/processing';
    protected $_successBlockType = 'alliedwalletqp/success';
    protected $_failureBlockType = 'alliedwalletqp/failure';
    
    protected $_sendNewOrderEmail = true;
    
    protected $_order = NULL;
    protected $_paymentInst = NULL;
	
    protected function _expireAjax()
    {
        if (!$this->getCheckout()->getQuote()->hasItems()) {
            $this->getResponse()->setHeader('HTTP/1.1','403 Session Expired');
            exit;
        }
    }

    /**
     * Get singleton of Checkout Session Model
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * when customer select AlliedWallet payment method
     */
    public function redirectAction()
    {
        $session = $this->getCheckout();
        $session->setAlliedWalletQuoteId($session->getQuoteId());
        $session->setAlliedWalletRealOrderId($session->getLastRealOrderId());

        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($session->getLastRealOrderId());
		$order->addStatusToHistory(Mage_Sales_Model_Order::STATE_HOLDED, Mage::helper('alliedwalletqp')->__('Customer was redirected to AlliedWallet.'));
        $order->save();

        $this->getResponse()->setBody(
            $this->getLayout()
                ->createBlock($this->_redirectBlockType)
                ->setOrder($order)
                ->toHtml()
        );

        $session->unsQuoteId();
    }
    
    /**
     * AlliedWallet returns POST variables to this action
     */
    public function responseAction()
    {
    	try {
    		$request = $this->_checkReturnedPost();
            
            
         
    		
    		// save transaction ID
    		$this->_paymentInst->setTransactionId($request['TransactionID']);
            if ($this->_order->canInvoice()) {
           		//  CHANGE FOR MAGENTO 1.1
            	$invoice = $this->_order->prepareInvoice();
            	
                $invoice->register()->capture(); 
                Mage::getModel('core/resource_transaction')
                    ->addObject($invoice)
                    ->addObject($invoice->getOrder())
                    ->save();
            }
            $this->_order->addStatusToHistory($this->_paymentInst->getConfigData('order_status'), Mage::helper('alliedwalletqp')->__($this->_paymentInst->getConfigData('request_type').':Customer returned successfully!!'));
            $this->_order->save();

	        $this->getResponse()->setBody(
	            $this->getLayout()
	                ->createBlock($this->_successBlockType)
	                ->setOrder($this->_order)
	                ->toHtml()
	        );
                        
            
    	} catch (Exception $e) {
    		
    		$this->getResponse()->setBody(
	            $this->getLayout()
	                ->createBlock($this->_failureBlockType)
	                ->setOrder($this->_order)
	                ->toHtml()
	        );
    	}
    }

    /**
     * AlliedWallet return action
     */
    protected function successAction()
    {
        $session = $this->getCheckout();
		
		$request = $this->_checkReturnedPost();
        
        
                
                
                
                
                
		if($request['TransactionStatus']=='Declined'){
            $this->_order->addStatusToHistory(Mage_Sales_Model_Order::STATE_CANCELED, Mage::helper('alliedwalletqp')->__('Payment Declined.'));
            $this->_order->cancel()->save();
            $this->_redirect('checkout/onepage/failure');
        }
                else{
   		$this->_paymentInst->setTransactionId($request['TransactionID']);
		$this->_order->addStatusToHistory($this->_paymentInst->getConfigData('order_status'), Mage::helper('alliedwalletqp')->__('Customer Transaction Id:'.$request['TransactionID']));
        
        $this->_order->save();
		
        $session->unsAlliedWalletRealOrderId();
        $session->setQuoteId($session->getAlliedWalletQuoteId(true));
        $session->getQuote()->setIsActive(false)->save();

        $order = Mage::getModel('sales/order');
        $order->load($this->getCheckout()->getLastOrderId());
        
        if ($this->_order->canInvoice()) {
					//  CHANGE FOR MAGENTO 1.1
					$invoice = $this->_order->prepareInvoice();
					
					$invoice->register()->capture(); 
					Mage::getModel('core/resource_transaction')
						->addObject($invoice)
						->addObject($invoice->getOrder());
						//->addStatusToHistory(Mage_Sales_Model_Order::STATE_HOLDED, Mage::helper('alliedwallet')->__('Operation Successfully Processed.'));
						//->save();
				}
				$this->_order->addStatusToHistory($this->_paymentInst->getConfigData('order_status'), Mage::helper('alliedwalletqp')->__('Operation Successfully Processed'));
                
				$this->_order->save();
				$this->_redirect('checkout/onepage/success');

        
		$this->_redirect('checkout/onepage/success');
                }
                    }
    

    

    /**
     * Checking POST variables.
     * Creating invoice if payment was successfull or cancel order if payment was declined
     */
    protected function _checkReturnedPost()
    {
        // get request variables
        $request = $this->getRequest()->getParams();
		
		$merchantReference = $request['MerchantReference'];
        $transactionStatus = $request['TransactionStatus'];
		$merchantReferenceArr = explode("-", $merchantReference);
        // load order for further validation
        $this->_order = Mage::getModel('sales/order')->loadByIncrementId($merchantReferenceArr[0]);
        $this->_paymentInst = $this->_order->getPayment()->getMethodInstance();
        
        return $request;
    }
}