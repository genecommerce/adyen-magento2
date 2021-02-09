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

namespace Adyen\Payment\Controller\GooglePay;

use Exception;
use InvalidArgumentException;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Adyen\Payment\Model\GooglePay\Helper\OrderPlace;
use Magento\Quote\Api\Data\CartInterface;

class PlaceOrder extends Action
{
    /**
     * @var OrderPlace
     */
    private $orderPlace;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * Constructor
     *
     * @param Context $context
     * @param OrderPlace $orderPlace
     * @param Session $checkoutSession
     */
    public function __construct(
        Context $context,
        OrderPlace $orderPlace,
        Session $checkoutSession
    ) {
        parent::__construct($context);

        $this->orderPlace = $orderPlace;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $agreement = array_keys($this->getRequest()->getPostValue('agreement', []));
        $quote = $this->checkoutSession->getQuote();

        try {
            $this->validateQuote($quote);
            $this->orderPlace->execute($quote, $agreement);

            /** @var Redirect $resultRedirect */
            return $resultRedirect->setPath('checkout/onepage/success', ['_secure' => true]);
        } catch (Exception $e) {
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
        }

        return $resultRedirect->setPath('checkout/cart', ['_secure' => true]);
    }

    /**
     * @param CartInterface $quote
     * @return void
     * @throws InvalidArgumentException
     */
    protected function validateQuote($quote)
    {
        if (!$quote || !$quote->getItemsCount()) {
            throw new InvalidArgumentException(__("We can't initialize checkout."));
        }
    }
}
