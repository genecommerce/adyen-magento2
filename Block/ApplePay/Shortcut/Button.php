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

namespace Adyen\Payment\Block\ApplePay\Shortcut;

use Adyen\Payment\Block\ApplePay\AbstractButton;
use Magento\Checkout\Model\Session;
use Magento\Catalog\Block\ShortcutInterface;
use Magento\Checkout\Model\DefaultConfigProvider;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Model\MethodInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class Button extends AbstractButton implements ShortcutInterface
{
    const ALIAS_ELEMENT_INDEX = 'alias';

    const BUTTON_ELEMENT_INDEX = 'button_id';

    /**
     * @var DefaultConfigProvider $defaultConfigProvider
     */
    private $defaultConfigProvider;

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
     * Button constructor
     *
     * @param Context $context
     * @param Session $checkoutSession
     * @param MethodInterface $payment
     * @param UrlInterface $url
     * @param CustomerSession $customerSession
     * @param StoreManagerInterface $storeManagerInterface
     * @param DefaultConfigProvider $defaultConfigProvider
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
        DefaultConfigProvider $defaultConfigProvider,
        array $data = []
    ) {
        parent::__construct($context, $checkoutSession, $payment, $url, $customerSession, $storeManagerInterface, $data);
        $this->defaultConfigProvider = $defaultConfigProvider;
    }

    /**
     * @inheritdoc
     */
    public function getAlias(): string
    {
        return $this->getData(self::ALIAS_ELEMENT_INDEX);
    }

    /**
     * @return string
     */
    public function getContainerId(): string
    {
        return $this->getData(self::BUTTON_ELEMENT_INDEX);
    }

    /**
     * Current Quote ID for guests
     *
     * @return string
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getQuoteId(): string
    {
        try {
            $config = $this->defaultConfigProvider->getConfig();
            if (!empty($config['quoteData']['entity_id'])) {
                return $config['quoteData']['entity_id'];
            }
        } catch (NoSuchEntityException $e) {
            if ($e->getMessage() !== 'No such entity with cartId = ') {
                throw $e;
            }
        }

        return '';
    }

    /**
     * @return string
     */
    public function getExtraClassname(): string
    {
        return $this->getIsCart() ? 'cart' : 'minicart';
    }
}
