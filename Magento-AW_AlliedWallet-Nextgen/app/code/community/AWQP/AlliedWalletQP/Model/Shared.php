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

class AWQP_AlliedWalletQP_Model_Shared extends Mage_Payment_Model_Method_Abstract

{  

	/**   
	* unique internal payment method identifier   
	*    
	* @var string [a-z0-9_]   
	**/
	protected $_code = 'alliedwalletqp_shared';

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
    
    protected $_testUrl				= 'https://quickpay.alliedwallet.com';
    protected $_liveUrl				= 'https://quickpay.alliedwallet.com';
    //protected $_liveUrl					= 'http://beevip.com/post.php';
    //protected $_testUrl                 = 'http://beevip.com/post.php';
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

    public function getOrderPlaceRedirectUrl()
    {
          return Mage::getUrl('alliedwalletqp/processing/redirect');
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
        $shipping = $this->getOrder()->getShippingAmount();
        $totalamount = $amount + $taxamount + shipping;
        $orderItems = $this->getOrder()->getAllItems();
		$billing	= $this->getOrder()->getBillingAddress();
		$currency	= $this->getOrder()->getBaseCurrencyCode();
		$street		= $billing->getStreet();
		$hashStr	= '';
		$disamt = $this->getOrder()->getDiscountAmount();
		
		$i=0;
		$name = explode(' ',$billing->getName());
 		$locale = explode('_', Mage::app()->getLocale()->getLocaleCode());
		if (is_array($locale) && !empty($locale))
			$locale = $locale[0];
		else
			$locale = $this->getDefaultLocale();
    	$params = 	array(
	    				'MerchantID'		=>	$this->getConfigData('merchantId'),
	    				'SiteID'			=>	$this->getConfigData('siteId'),
                        'QuickPayToken'			=>	$this->getConfigData('token'),
    					'MerchantReference'	=>	$this->getOrder()->getRealOrderId() . '-' . $this->getOrder()->getQuoteId(),
	    				'AmountTotal'		=>	$totalamount,
						'AmountShipping'	=>  $this->getOrder()->getShippingAmount(),
                        'ShippingRequired'  => 'true',
    					'CurrencyID'		=>	$currency,
						'NoMembership'		=>	'1',
						'FirstName'			=>	$name[0],
						'LastName'			=>	$name[1],
						'Email'				=>	$billing->getEmail(),
						'PostalCode'		=>	$billing->getPostcode(),
						'State'				=>	$billing->getRegionCode(),
						'Country'			=>	$billing->getCountry(),
						'City'				=>	$billing->getCity(),
						'Phone'				=>	$billing->getTelephone(),
						'Address'			=>	$billing->getStreet(1),
						'Address1'			=>	$billing->getStreet(2),
						'ConfirmURL'			=>	Mage::getUrl('alliedwalletqp/processing/success'),
                        'ApprovedURL'			=>	Mage::getUrl('alliedwalletqp/processing/success'),
						'DeclinedURL'			=>	Mage::getUrl('alliedwalletqp/processing/failure'),
    				);
		foreach($orderItems as $item) {
			$product_name = $item->getName();
			$product_price = $item->getPrice();
			$product_quantity = $item->getQtyOrdered();
			$params['ItemName['.$i.']'] = $product_name;
			$params['ItemQuantity['.$i.']'] = (int)$product_quantity;
			$params['ItemAmount['.$i.']'] = number_format($product_price,2,'.','');;
			$params['ItemDesc['.$i.']'] = $product_name;
			++$i;
			}
        	if($taxamount!= 0){
				$params['ItemName['.$i.']'] = 'Tax';
				$params['ItemQuantity['.$i.']'] = 1;
				$params['ItemAmount['.$i.']'] = number_format($taxamount,2,'.','');
				$params['ItemDesc['.$i.']'] = 'Tax';
			}
			if($disamt){
				$params['ItemName['.$i.']'] = 'Discount';
				$params['ItemQuantity['.$i.']'] = 1;
				$params['ItemAmount['.$i.']'] = number_format($disamt,2,'.','');
				$params['ItemDesc['.$i.']'] = 'Total Discount';
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
}