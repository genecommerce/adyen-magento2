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
use Magento\Framework\View\Result\Page;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

class SaveShippingMethod extends Action
{
    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * Constructor
     *
     * @param Context $context
     * @param Session $checkoutSession
     * @param  CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        Context $context,
        Session $checkoutSession,
        CartRepositoryInterface $quoteRepository
    ) {
        parent::__construct($context);
        $this->quoteRepository = $quoteRepository;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        $isAjax = $this->getRequest()->getParam('isAjax');
        $quote = $this->checkoutSession->getQuote();

        try {
            $this->validateQuote($quote);
            $shippingMethod = $this->getRequest()->getParam('shipping_method');

            if (!$quote->getIsVirtual()) {
                $shippingAddress = $quote->getShippingAddress();
                if ($shippingMethod !== $shippingAddress->getShippingMethod()) {
                    $this->disabledQuoteAddressValidation($quote);

                    $shippingAddress->setShippingMethod($shippingMethod);
                    $shippingAddress->setCollectShippingRates(true);

                    $cartExtension = $quote->getExtensionAttributes();
                    if ($cartExtension && $cartExtension->getShippingAssignments()) {
                        $cartExtension->getShippingAssignments()[0]
                            ->getShipping()
                            ->setMethod($shippingMethod);
                    }

                    $quote->collectTotals();
                    $this->quoteRepository->save($quote);
                }
            }


            if ($isAjax) {
                /** @var Page $response */
                $response = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
                $layout = $response->addHandle('paypal_express_review_details')->getLayout();
                $response = $layout->getBlock('page.block')->toHtml();
                $this->getResponse()->setBody($response);

                return;
            }
        } catch (Exception $e) {
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
        }

        $path = $this->_url->getUrl('*/*/review', ['_secure' => true]);

        if ($isAjax) {
            $this->getResponse()->setBody(sprintf('<script>window.location.href = "%s";</script>', $path));

            return;
        }

        $this->_redirect($path);
    }

    /**
     * Make sure addresses will be saved without validation errors
     *
     * @param Quote $quote
     * @return void
     */
    protected function disabledQuoteAddressValidation(Quote $quote)
    {
        $billingAddress = $quote->getBillingAddress();
        $billingAddress->setShouldIgnoreValidation(true);

        if (!$quote->getIsVirtual()) {
            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->setShouldIgnoreValidation(true);
            if (!$billingAddress->getEmail()) {
                $billingAddress->setSameAsBilling(1);
            }
        }
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
