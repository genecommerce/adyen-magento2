<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2020 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Block\Buttons;

use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Model\MethodInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

/***/
abstract class AbstractButton extends Template
{
    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var MethodInterface
     */
    protected $payment;

    /**
     * @var UrlInterface $url
     */
    private $url;

    /**
     * @var CustomerSession $customerSession
     */
    private $customerSession;

    /**
     * @var StoreManagerInterface $storeManager
     */
    private $storeManager;

    /**
     * @var ScopeConfigInterface $scopeConfig
     */
    private $scopeConfig;

    /**
     * @var \Adyen\Payment\Helper\Data
     */
    protected $adyenHelper;

    const COUNTRY_CODE_PATH = 'general/country/default';
    const PRODUCTION = 'production';

    /**
     * Button constructor.
     * @param Context $context
     * @param Session $checkoutSession
     * @param MethodInterface $payment
     * @param UrlInterface $url
     * @param CustomerSession $customerSession
     * @param StoreManagerInterface $storeManagerInterface
     * @param ScopeConfigInterface $scopeConfig
     * @param \Adyen\Payment\Helper\Data $adyenHelper
     * @param array $data
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        MethodInterface $payment,
        UrlInterface $url,
        CustomerSession $customerSession,
        StoreManagerInterface $storeManagerInterface,
        ScopeConfigInterface $scopeConfig,
        \Adyen\Payment\Helper\Data $adyenHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->checkoutSession = $checkoutSession;
        $this->payment = $payment;
        $this->url = $url;
        $this->customerSession = $customerSession;
        $this->storeManager = $storeManagerInterface;
        $this->scopeConfig = $scopeConfig;
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml(): string // @codingStandardsIgnoreLine
    {
        if ($this->isActive()) {
            return parent::_toHtml();
        }

        return '';
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->payment->isAvailable($this->checkoutSession->getQuote());
    }

    /**
     * Cart grand total
     *
     * @return float|null
     */
    public function getAmount()
    {
        return $this->checkoutSession->getQuote()->getBaseGrandTotal();
    }

    /**
     * URL To success page
     *
     * @return string
     */
    public function getActionSuccess(): string
    {
        return $this->url->getUrl('checkout/onepage/success', ['_secure' => true]);
    }

    /**
     * Is customer logged in flag
     *
     * @return bool
     */
    public function isCustomerLoggedIn(): bool
    {
        return (bool) $this->customerSession->isLoggedIn();
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getStorecode(): string
    {
        return $this->storeManager->getStore()->getCode();
    }

    /**
     * @return string
     */
    public function getDefaultCountryCode(): string
    {
        return $this->scopeConfig->getValue(
            self::COUNTRY_CODE_PATH,
            \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITES
        );
    }

    /**
     * @return string|null
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getCurrency()
    {
        return $this->checkoutSession->getQuote()->getCurrency()->getBaseCurrencyCode();
    }

    /**
     * @return string
     */
    public function getMerchantAccount(): string
    {
        return $this->adyenHelper->getAdyenMerchantAccount(
            "adyen_google_pay",
            $this->storeManager->getStore()->getId()
        );
    }

    /**
     * @return string
     */
    public function getLocale(): string
    {
        return $this->adyenHelper->getStoreLocale(
            $this->storeManager->getStore()->getId()
        );
    }

    /**
     * @return string
     */
    public function getFormat(): string
    {
        return $this->adyenHelper->decimalNumbers($this->getCurrency());
    }

    /**
     * @return string
     */
    public function getMerchantIdentifier(): string
    {
        return $this->adyenHelper->getAdyenGooglePayMerchantIdentifier($this->storeManager->getStore()->getId());
    }

    /**
     * @return string
     */
    public function getOriginKey(): string
    {
        return $this->adyenHelper->getOriginKeyForBaseUrl();
    }

    /**
     * @return string
     */
    public function getCheckoutEnvironment(): string
    {
        if ($this->adyenHelper->isDemoMode($this->storeManager->getStore()->getId())) {
            return \Adyen\Payment\Helper\Data::TEST;
        }

        return self::PRODUCTION;
    }
}
