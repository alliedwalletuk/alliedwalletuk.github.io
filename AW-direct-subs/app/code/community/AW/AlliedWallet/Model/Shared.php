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

class AW_AlliedWallet_Model_Shared extends Mage_Payment_Model_Method_Cc

{  

	/**   
	* unique internal payment method identifier   
	*    
	* @var string [a-z0-9_]   
	**/
	protected $_code = 'alliedwallet_shared';

    protected $_isGateway               = false;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = false;
    protected $_canRefund               = false;
    protected $_canVoid                 = false;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = true;

    protected $_paymentMethod			= 'shared';
    protected $_defaultLocale			= 'en';
    
    protected $_testUrl					= 'https://quickpay.alliedwallet.com/';
    protected $_liveUrl					= 'https://quickpay.alliedwallet.com/';

    protected $_order;

    /**
     * Get order model
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if (!$this->_order) {
            $paymentInfo = $this->getInfoInstance();
            $this->_order = Mage::getModel('sales/order')
                            ->loadByIncrementId($paymentInfo->getOrder()->getRealOrderId());
        }
        return $this->_order;
    }
	
	public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) 
        {
            $data = new Varien_Object($data);
        }
        $info = $this->getInfoInstance();
        $info->setCcType($data->getCcType())
       ->setCcExpMonth($data->getCcExpMonth())
       ->setCcExpYear($data->getCcExpYear())
       ->setCcNumber($data->getCcNumber())
	   ->setCcCid($data->getCcCid())
       ->setCcLast4($data->getCcNumber())
       ->setCcOwner($data->getCcOwner());
       return $this;
    }
	
	public function prepareSave ()
	{
		$info = $this->getInfoInstance();
		$info->setCcNumberEnc($info->getCcCid());
		return $this;
	}
	

    public function getOrderPlaceRedirectUrl()
    {
          return Mage::getUrl('alliedwallet/processing/redirect');
    }

    public function capture(Varien_Object $payment, $amount)
    {
        $payment->setStatus(self::STATUS_APPROVED)
            ->setLastTransId($this->getTransactionId());

        return $this;
    }

    public function cancel(Varien_Object $payment)
    {
        $payment->setStatus(self::STATUS_DECLINED)
            ->setLastTransId($this->getTransactionId());

        return $this;
    }

    /**
     * Return redirect block type
     *
     * @return string
     */
    public function getRedirectBlockType()
    {
        return $this->_redirectBlockType;
    }

    /**
     * Return payment method type string
     *
     * @return string
     */
    public function getPaymentMethodType()
    {
        return $this->_paymentMethod;
    }
    
    public function getUrl()
    {
    	if ($this->getConfigData('transaction_mode') == 'live')
    		return $this->_liveUrl;
    	return $this->_testUrl;
    }
	
	
    
    /**
     * prepare params array to send it to gateway page via POST
     *
     * @return array
     */
    public function getFormFields()
    {

    	$amount		= number_format($this->getOrder()->getBaseGrandTotal(),2,'.','');
		$taxamount		= number_format($this->getOrder()->getBaseTaxAmount(),2,'.','');
		$orderItems = $this->getOrder()->getAllItems();
		$billing	= $this->getOrder()->getBillingAddress();
		$currency	= $this->getOrder()->getBaseCurrencyCode();
		$street		= $billing->getStreet();
		$hashStr	= '';
		$i=0;
		$name = explode(' ',$billing->getName());
 		$locale = explode('_', Mage::app()->getLocale()->getLocaleCode());
		if (is_array($locale) && !empty($locale))
			$locale = $locale[0];
		else
			$locale = $this->getDefaultLocale();
    	$params = 	array(
	    				'QuickPayUserId'	=>	$this->getConfigData('QPUsername'),
						'QuickPayPassword'	=>	$this->getConfigData('QPPassword'),
	    				'SiteID'			=>	$this->getConfigData('SiteID'),
	    				
    					'MerchantReference'	=>	$this->getOrder()->getRealOrderId() . '-' . $this->getOrder()->getQuoteId(),
	    				'AmountTotal'		=>	$amount,
						'AmountShipping'	=>  $this->getOrder()->getShippingAmount(),
						'ShippingRequired' 	=>	'true',
    					'CurrencyID'		=>	$currency,
						'NoMembership'		=>	'1',
						'FirstName'			=>	$name[0],
						'LastName'			=>	$name[1],
						'Email'				=>	$billing->getEmail(),
						'PostalCode'		=>	$billing->getPostcode(),
						'State'				=>	$billing->getState(),
						'Country'			=>	$billing->getCountry(),
						'City'				=>	$billing->getCity(),
						'Phone'				=>	$billing->getTelephone(),
						'Address'			=>	$billing->getStreet(1),
						'Address1'			=>	$billing->getStreet(2),
						'ApprovedURL'			=>	Mage::getUrl('alliedwallet/processing/success'),
						'DeclinedURL'			=>	Mage::getUrl('alliedwallet/processing/failure')
						
    				);
		foreach($orderItems as $item) {
			$product_name = $item->getName();
			$product_price = $item->getPrice();
			$product_quantity = $item->getQtyOrdered();
			$product_discount = $this->getOrder()->getDiscountAmount();
			$params['ItemName['.$i.']'] = $product_name;
			$params['ItemQuantity['.$i.']'] = (int)$product_quantity;
			$params['ItemAmount['.$i.']'] = number_format(($product_price*$product_quantity)+$product_discount,2,'.','');;
			$params['ItemDesc['.$i.']'] = $product_name;
			$params['test['.$i.']'] = $product_discount;
			++$i;
			if ($taxamount > '0.00'){
			$params['ItemName['.$i.']'] = 'Tax';
			$params['ItemQuantity['.$i.']'] = '1';
			$params['ItemAmount['.$i.']'] = number_format($taxamount,2,'.','');;
			$params['ItemDesc['.$i.']'] = 'Tax';
			}
			}
			
			
			
    		// set additional flags
    	if ($this->getConfigData('fix_contact') == 1)
    		$params['fixContact'] = 1;
    	if ($this->getConfigData('hide_contact') == 1)
    		$params['hideContact'] = 1;
    		
			// add md5 hash
		if ($this->getConfigData('security_key') != '') {
			$params['signatureFields'] = 'amount:currency:cartId:email';
			$params['signature'] = md5(
										$this->getConfigData('security_key') . ':' .
										$params['amount'] . ':' .
										$params['currency'] . ':' .
										$params['cartId'] . ':' .
										$params['email']
									);
		}

    	return $params;
	
		
    }
	
	public function getJsonField()
    {	
    	$amount		= number_format($this->getOrder()->getBaseGrandTotal(),2,'.','');
		//print_r($this->getOrder());
		$lastOrderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
		$order=Mage::getModel('sales/order')->loadByIncrementID($lastOrderId);
		$orderData = $order->getData();
		$paymentdata=$order->getPayment()->debug();
		$ccnumber = $paymentdata['cc_last4'];
		$cccvvnumber = $paymentdata['cc_number_enc'];
		$cc_exp_year = $paymentdata['cc_exp_year'];
		$cc_exp_month = $paymentdata['cc_exp_month'];
		$mid = $this->getConfigData('MerchantID');
		//$mid = '1';
		$taxamount		= number_format($this->getOrder()->getBaseTaxAmount(),2,'.','');
		$orderItems = $this->getOrder()->getAllItems();
		$billing	= $this->getOrder()->getBillingAddress();
		$currency	= $this->getOrder()->getBaseCurrencyCode();
		$street		= $billing->getStreet();
		$hashStr	= '';
		$i=0;
		$name = explode(' ',$billing->getName());
 		$locale = explode('_', Mage::app()->getLocale()->getLocaleCode());
		if (is_array($locale) && !empty($locale))
			$locale = $locale[0];
		else
		$locale = $this->getDefaultLocale();
    	$name = explode(' ',$billing->getName());
		$jsonData = array(
		   'amount' => $amount,
		   'isInitialForRecurring' => false,
		   'siteId' => $this->getConfigData('SiteID'),
		   'currency' => $currency,
		   'firstName' => $name[0],
		   'lastName' => $name[1],
		   'phone' => '1234567890',
		   'SubscriptionPlanId'			=>	$this->getConfigData('SubscriptionPlanId'),
		   'addressLine1' => $billing->getStreet(1),
		   'addressLine2' => 'Hlloywood',
		   'shippingAddressLine1' => $billing->getStreet(1),
		   'shippingAddressLine2' => 'Hlloywood',
		   'shippingCity' => $billing->getStreet(1),
		   'shippingState' => 'Hlloywood',
		   'city' => $billing->getCity(),
		   'state' => $billing->getState(),
		   //'state' => 'California',
		   'countryId' => $billing->getCountry(),
		   'shippingCountryId' => $billing->getCountry(),
		   'postalCode' => $billing->getPostcode(),
		   'shippingPostalCode' => $billing->getPostcode(),
		   'email' => $billing->getEmail(),
		   'cardNumber' => $ccnumber,
		   //'cardNumber' => '4242424242424242',
		   'nameOnCard' => $order->getPayment()->getCcOwner(),
		   'expirationMonth' => $cc_exp_month,
		   'expirationYear' => $cc_exp_year,
		   'iPAddress' => $_SERVER["REMOTE_ADDR"],
		   'cVVCode' => $cccvvnumber,
		   'trackingId' => $this->getOrder()->getRealOrderId() . '-' . $this->getOrder()->getQuoteId(),
			//'TokenId' => $this->getConfigData('Token'),			
		);
		//print_r($jsonData);
		//die('kk');
		$token = $this->getConfigData('Token');
		$action = 'verifytransactions';
		//$action = 'saletransactions';
			
 		$url = 'https://api.alliedwallet.com/merchants/'.$mid.'/'.$action;
		//echo $url."<br><br>";
		//Initiate cURL.
		$ch = curl_init($url);
		 //Encode the array into JSON.
		$jsonDataEncoded = json_encode($jsonData);
		 
		//Tell cURL that we want to send a POST request.
		curl_setopt($ch, CURLOPT_POST, 1);
		 
		//Attach our encoded JSON string to the POST fields.
		curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		 
		//Set the content type to application/json
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Authorization: Bearer '.$token,'Accept-Encoding: gzip,deflate',
				'Host: api.alliedwallet.com','Content-Length:'.strlen($jsonDataEncoded))); 
		//echo $token.'====';
		//print_r($jsonDataEncoded);
		//Execute the request
		$result = curl_exec($ch);
		
//die('dd');
		///Deocde Json
		//$data = json_decode($result, true);
		//echo "<pre>"; var_dump($data); echo "</pre>";
		//$acs = ($data['result'][1]['responseValue']);
		//$pareq = ($data['result'][0]['responseValue']);
		//$md = ($data['id']);
		///Count
//             $total=count($data);
	//		   foreach ($data as $key => $value)
      //        {
		//	echo '<p>'.$key.'  :  '.$value.'</p>';
			//  }			
		return $result;		
    }
}