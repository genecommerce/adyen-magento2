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
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Model\MethodInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class Button extends AbstractButton implements ShortcutInterface
{
    const ALIAS_ELEMENT_INDEX = 'alias';

    const BUTTON_ELEMENT_INDEX = 'button_id';

    /**
     * @var DefaultConfigProvider $defaultConfigProvider
     */
    private $defaultConfigProvider;

    /**
     * Button constructor
     *
     * @param Context $context
     * @param Session $checkoutSession
     * @param MethodInterface $payment
     * @param DefaultConfigProvider $defaultConfigProvider
     * @param array $data
     * @throws InputException
     * @throws NoSuchEntityException
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        MethodInterface $payment,
        DefaultConfigProvider $defaultConfigProvider,
        array $data = []
    ) {
        parent::__construct($context, $checkoutSession, $payment, $data);
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
