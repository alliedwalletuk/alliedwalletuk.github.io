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

class AW_AlliedWallet_ProcessingController extends Mage_Core_Controller_Front_Action
{
    protected $_redirectBlockType = 'alliedwallet/processing';
    protected $_successBlockType = 'alliedwallet/success';
    protected $_failureBlockType = 'alliedwallet/failure';
    
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
	
	public function SubscriptionAction(){
		try {
    		$request = $this->_checkReturnedSubscriptionPost();
    		// save transaction ID 
			$this->_paymentInst->setTransactionId($request['id']);
    		if($request['status']=='Declined'){
				Mage::register('isSecureArea', 1);		 
				 $orderid = explode('-',$request['trackingId']);
				 $id = $orderid[0];
				  try{
					$order = Mage::getModel('sales/order')->load($id);
						Mage::getModel('sales/order')->loadByIncrementId($id)->delete();
				}catch(Exception $e){
					echo "order #".$id." could not be remvoved: ".$e->getMessage().PHP_EOL;
				}
				 
				$order->delete();
				//$this->_redirect('checkout/onepage/failure');
			}
			elseif($request['status']=='Successful' || $request['status']=='Approved'){
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
				$this->_order->addStatusToHistory($this->_paymentInst->getConfigData('order_status'), Mage::helper('alliedwallet')->__('Operation Successfully Processed'));
				$order->setState(Mage_Sales_Model_Order::STATE_COMPLETE, true)->save();
				$this->_order->save();
				$this->_redirect('checkout/onepage/success');
			}
			else{
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
				$this->_order->addStatusToHistory($this->_paymentInst->getConfigData('order_status'), Mage::helper('alliedwallet')->__('Operation Subscription Successfully Processed'));
				$this->_order->save();
				$order->setState(Mage_Sales_Model_Order::STATE_COMPLETE, true)->save();
				//$this->_redirect('checkout/onepage/success');
			}
    	} catch (Exception $e) {
    		
    		$this->getResponse()->setBody(
	            $this->getLayout()
	                ->createBlock($this->_failureBlockType)
	                ->setOrder($this->_order)
	                ->toHtml()
	        );
    	}
	}
	
	protected function _checkReturnedSubscriptionPost()
    {
        // get request variables
        //$request = $this->getRequest()->getParams();
        $request = $_POST();
		//print_r($request);
		//die('sss');
		$trackingId = $request['trackingId'];
		$merchantReferenceArr = explode("-", $trackingId);
        // load order for further validation
        $this->_order = Mage::getModel('sales/order')->loadByIncrementId($merchantReferenceArr[0]);
        $this->_paymentInst = $this->_order->getPayment()->getMethodInstance();
        
        return $request;
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
		$order->addStatusToHistory(Mage_Sales_Model_Order::STATE_HOLDED, Mage::helper('alliedwallet')->__('Processing Order.'));
        $order->save();
		//die('sss');

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
    		$this->_paymentInst->setTransactionId($request['id']);
    		if($request['status']=='Declined'){
				Mage::register('isSecureArea', 1);		 
				 $orderid = explode('-',$request['trackingId']);
				 $id = $orderid[0];
				 $order = Mage::getModel('sales/order')->load($id); 
				  //try{
					

			/*$invoices = $order->getInvoiceCollection();
			foreach ($invoices as $invoice){
				//delete all invoice items
				$items = $invoice->getAllItems(); 
				foreach ($items as $item) {
					$item->delete();
				}
				//delete invoice
				$invoice->delete();
			}
			$creditnotes = $order->getCreditmemosCollection();
			foreach ($creditnotes as $creditnote){
				//delete all creditnote items
				$items = $creditnote->getAllItems(); 
				foreach ($items as $item) {
					$item->delete();
				}
				//delete credit note
				$creditnote->delete();
			}
			$shipments = $order->getShipmentsCollection();
			foreach ($shipments as $shipment){
				//delete all shipment items
				$items = $shipment->getAllItems(); 
				foreach ($items as $item) {
					$item->delete();
				}
				//delete shipment
				$shipment->delete();
			}
			//delete all order items
			$items = $order->getAllItems(); 
			foreach ($items as $item) {
				$item->delete();
			}*/
				//		Mage::getModel('sales/order')->loadByIncrementId($id)->delete();
			//	}catch(Exception $e){
				//	echo "order #".$id." could not be remvoved: ".$e->getMessage().PHP_EOL;
				//}
				 
				//$order->delete();
//				print_r($order);
	//			die('d');		
				//print_r($request);
				//die('sss');
				/*$session = Mage::getSingleton('checkout/session');
				$session->setQuoteId($session->getPaypalStandardQuoteId(true));
				if ($session->getLastRealOrderId()) {
					$order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
					if ($order->getId()) {
						$order->addStatusToHistory(Mage_Sales_Model_Order::STATE_HOLDED, Mage::helper('alliedwallet')->__('Payment Declined.'));

						$order->cancel()->save();
					}*/
				
				//$this->_order->addStatusToHistory($this->_paymentInst->getConfigData('order_status'), Mage::helper('alliedwallet')->__($this->_paymentInst->getConfigData('request_type').':Customer returned successfully'));
				$this->_redirect('checkout/onepage/failure');
			}
			elseif($request['status']=='Successful' || $request['status']=='Approved'){
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
				$this->_order->addStatusToHistory($this->_paymentInst->getConfigData('order_status'), Mage::helper('alliedwallet')->__('Operation Successfully Processed'));
				$order->setState(Mage_Sales_Model_Order::STATE_COMPLETE, true)->save();
				$this->_order->save();
				$this->_redirect('checkout/onepage/success');
			}
			else{
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
				$this->_order->addStatusToHistory($this->_paymentInst->getConfigData('order_status'), Mage::helper('alliedwallet')->__('Operation Successfully Processed'));
				//$order->setState(Mage_Sales_Model_Order::STATE_COMPLETE, true)->save();
				$this->_order->save();
				$this->_redirect('checkout/onepage/success');
			}
	        /*$this->getResponse()->setBody(
	            $this->getLayout()
	                ->createBlock($this->_successBlockType)
	                ->setOrder($this->_order)
	                ->toHtml()
	        );*/
            
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
		
   		$this->_paymentInst->setTransactionId($request['id']);
		$this->_order->addStatusToHistory($this->_paymentInst->getConfigData('order_status'), Mage::helper('alliedwallet')->__('Customer Transaction Id:'.$request['TransactionID']));
        $this->_order->save();
		
        $session->unsAlliedWalletRealOrderId();
        $session->setQuoteId($session->getAlliedWalletQuoteId(true));
        $session->getQuote()->setIsActive(false)->save();

        $order = Mage::getModel('sales/order');
        $order->load($this->getCheckout()->getLastOrderId());
        if($order->getId() && $this->_sendNewOrderEmail)
            $order->sendNewOrderEmail();

		$this->_redirect('checkout/onepage/success');
    }

    /**
     * Checking POST variables.
     * Creating invoice if payment was successfull or cancel order if payment was declined
     */
    protected function _checkReturnedPost()
    {
        // get request variables
        $request = $this->getRequest()->getParams();
	//	$post = $this->getRequest()->getPost();
		//$post = $_POST();
		//file_put_contents('filename.txt', print_r($request, true));
		//file_put_contents('filename1.txt', print_r($post, true));
		//print_r($request);
		//die('sss');
		$trackingId = $request['trackingId'];
		$merchantReferenceArr = explode("-", $trackingId);
        // load order for further validation
        $this->_order = Mage::getModel('sales/order')->loadByIncrementId($merchantReferenceArr[0]);
        $this->_paymentInst = $this->_order->getPayment()->getMethodInstance();
        
        return $request;
    }
}