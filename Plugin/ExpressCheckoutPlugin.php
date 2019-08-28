<?php

namespace Onatera\Payment\BuyboxBundle\Plugin;

use JMS\Payment\CoreBundle\Model\ExtendedDataInterface;
use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Plugin\AbstractPlugin;
use JMS\Payment\CoreBundle\Plugin\Exception\Action\VisitUrl;
use JMS\Payment\CoreBundle\Plugin\Exception\ActionRequiredException;
use JMS\Payment\CoreBundle\Plugin\Exception\FinancialException;
use JMS\Payment\CoreBundle\Plugin\Exception\PaymentPendingException;
use JMS\Payment\CoreBundle\Plugin\PluginInterface;
use JMS\Payment\CoreBundle\Util\Number;
use Onatera\Payment\BuyboxBundle\Client\Client;
use Onatera\Payment\BuyboxBundle\Client\Response;

class ExpressCheckoutPlugin extends AbstractPlugin
{
    private $returnUrl;
    private $cancelUrl;
    private $notifyUrl;
    private $userAction;
    private $client;

    public function __construct(
        ?string $returnUrl,
        ?string $cancelUrl,
        ?Client $client,
        ?string $notifyUrl = null,
        ?string $userAction = null
    ) {
        $this->client = $client;
        $this->returnUrl = $returnUrl;
        $this->cancelUrl = $cancelUrl;
        $this->notifyUrl = $notifyUrl;
        $this->userAction = $userAction;
    }

    /**
     * @param FinancialTransactionInterface $transaction
     * @param $retry
     * @throws ActionRequiredException
     * @throws FinancialException
     * @throws PaymentPendingException
     * @throws \JMS\Payment\CoreBundle\Plugin\Exception\CommunicationException
     */
    public function approve(FinancialTransactionInterface $transaction, $retry): void
    {
        $this->createCheckoutBillingAgreement($transaction, 'Authorization');
    }

    /**
     * @param FinancialTransactionInterface $transaction
     * @param $retry
     * @throws ActionRequiredException
     * @throws FinancialException
     * @throws PaymentPendingException
     * @throws \JMS\Payment\CoreBundle\Plugin\Exception\CommunicationException
     */
    public function approveAndDeposit(FinancialTransactionInterface $transaction, $retry)
    {
        $this->createCheckoutBillingAgreement($transaction, 'Sale');
    }

    /**
     * @param FinancialTransactionInterface $transaction
     * @param $retry
     * @throws FinancialException
     * @throws \JMS\Payment\CoreBundle\Plugin\Exception\CommunicationException
     */
    public function credit(FinancialTransactionInterface $transaction, $retry)
    {
        $data = $transaction->getExtendedData();
        $approveTransaction = $transaction->getCredit()->getPayment()->getApproveTransaction();

        $parameters = [];
        if (Number::compare($transaction->getRequestedAmount(), $approveTransaction->getProcessedAmount()) !== 0) {
            $parameters['REFUNDTYPE'] = 'Partial';
            $parameters['AMT'] = $this->client->convertAmountToBuyboxFormat($transaction->getRequestedAmount());
            $parameters['CURRENCYCODE'] = $transaction->getCredit()->getPaymentInstruction()->getCurrency();
        }

        $response = $this->client->requestRefundTransaction($data->get('authorization_id'), $parameters);

        $this->throwUnlessSuccessResponse($response, $transaction);

        $transaction->setReferenceNumber($response->body->get('REFUNDTRANSACTIONID'));
        $transaction->setProcessedAmount($response->body->get('NETREFUNDAMT'));
        $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
    }

    /**
     * @param FinancialTransactionInterface $transaction
     * @param $retry
     * @throws FinancialException
     * @throws PaymentPendingException
     * @throws \JMS\Payment\CoreBundle\Plugin\Exception\CommunicationException
     */
    public function deposit(FinancialTransactionInterface $transaction, $retry)
    {
        $authorizationId = $transaction->getPayment()->getApproveTransaction()->getReferenceNumber();

        if (Number::compare(
            $transaction->getPayment()->getApprovedAmount(),
            $transaction->getRequestedAmount()
        ) === 0) {
            $completeType = 'Complete';
        } else {
            $completeType = 'NotComplete';
        }

        $response = $this->client
            ->requestDoCapture($authorizationId, $transaction->getRequestedAmount(), $completeType, [
                'CURRENCYCODE' => $transaction->getPayment()->getPaymentInstruction()->getCurrency(),
            ]);
        $this->throwUnlessSuccessResponse($response, $transaction);

        $details = $this->client->requestGetTransactionDetails($authorizationId);
        $this->throwUnlessSuccessResponse($details, $transaction);

        switch ($details->body->get('PAYMENTSTATUS')) {
            case 'Completed':
                break;

            case 'Pending':
                throw new PaymentPendingException('Payment is still pending: '.$response->body->get('PENDINGREASON'));

            default:
                $ex = new FinancialException('PaymentStatus is not completed: '.$response->body->get('PAYMENTSTATUS'));
                $ex->setFinancialTransaction($transaction);
                $transaction->setResponseCode('Failed');
                $transaction->setReasonCode($response->body->get('PAYMENTSTATUS'));

                throw $ex;
        }

        $transaction->setReferenceNumber($authorizationId);
        $transaction->setProcessedAmount($details->body->get('AMT'));
        $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
        $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);
    }

    /**
     * @param FinancialTransactionInterface $transaction
     * @param $retry
     * @throws FinancialException
     * @throws \JMS\Payment\CoreBundle\Plugin\Exception\CommunicationException
     */
    public function reverseApproval(FinancialTransactionInterface $transaction, $retry)
    {
        $data = $transaction->getExtendedData();

        $response = $this->client->requestDoVoid($data->get('authorization_id'));
        $this->throwUnlessSuccessResponse($response, $transaction);

        $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
    }

    public function processes($paymentSystemName): bool
    {
        return 'buybox_express_checkout' === $paymentSystemName;
    }

    public function isIndependentCreditSupported(): bool
    {
        return false;
    }

    /**
     * @param FinancialTransactionInterface $transaction
     * @param $paymentAction
     * @throws ActionRequiredException
     * @throws FinancialException
     * @throws PaymentPendingException
     * @throws \JMS\Payment\CoreBundle\Plugin\Exception\CommunicationException
     */
    protected function createCheckoutBillingAgreement(FinancialTransactionInterface $transaction, $paymentAction)
    {
        $data = $transaction->getExtendedData();

        $token = $this->obtainExpressCheckoutToken($transaction, $paymentAction);

        $details = $this->client->requestGetExpressCheckoutDetails($token);
        $this->throwUnlessSuccessResponse($details, $transaction);

        // verify checkout status
        switch ($details->body->get('CHECKOUTSTATUS')) {
            case 'PaymentActionFailed':
                $ex = new FinancialException('PaymentAction failed.');
                $transaction->setResponseCode('Failed');
                $transaction->setReasonCode('PaymentActionFailed');
                $ex->setFinancialTransaction($transaction);

                throw $ex;

            case 'PaymentCompleted':
                break;

            case 'PaymentActionNotInitiated':
                break;

            case 'PaymentActionInProgress':
                break;

            default:
                $this->throwActionRequired($token, $data, $transaction);
        }

        // complete the transaction
        $data->set('buybox_payer_id', $details->body->get('PAYERID'));

        $optionalParameters = [
            'CURRENCYCODE' => $transaction->getPayment()->getPaymentInstruction()->getCurrency(),
        ];

        if (null !== $notifyUrl = $this->getNotifyUrl($data)) {
            $optionalParameters['NOTIFYURL'] = $notifyUrl;
        }

        $optionalParameters['DESC'] = 'test';

        $response = $this->client->requestDoExpressCheckoutPayment(
            $data->get('express_checkout_token'),
            $transaction->getRequestedAmount(),
            $paymentAction,
            $details->body->get('PAYERID'),
            $optionalParameters
        );
        $this->throwUnlessSuccessResponse($response, $transaction);

        switch ($response->body->get('PAYMENTSTATUS')) {
            case 'Completed':
                break;

            case 'Pending':
                $transaction->setReferenceNumber($response->body->get('TRANSACTIONID'));

                throw new PaymentPendingException('Payment is still pending: '.$response->body->get('PENDINGREASON'));

            default:
                $ex = new FinancialException('PaymentStatus is not completed: '.$response->body->get('PAYMENTSTATUS'));
                $ex->setFinancialTransaction($transaction);
                $transaction->setResponseCode('Failed');
                $transaction->setReasonCode($response->body->get('PAYMENTSTATUS'));

                throw $ex;
        }

        $transaction->setReferenceNumber($response->body->get('TRANSACTIONID'));
        $transaction->setProcessedAmount($response->body->get('AMT'));
        $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
        $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);
    }

    /**
     * @param FinancialTransactionInterface $transaction
     * @param $paymentAction
     * @return mixed
     * @throws ActionRequiredException
     * @throws FinancialException
     * @throws \JMS\Payment\CoreBundle\Plugin\Exception\CommunicationException
     */
    protected function obtainExpressCheckoutToken(FinancialTransactionInterface $transaction, $paymentAction)
    {
        $data = $transaction->getExtendedData();
        if ($data->has('express_checkout_token')) {
            return $data->get('express_checkout_token');
        }

        $opts = $data->has('checkout_params') ? $data->get('checkout_params') : [];
        $opts['PAYMENTACTION'] = $paymentAction;
        $opts['CURRENCYCODE'] = $transaction->getPayment()->getPaymentInstruction()->getCurrency();

        $response = $this->client->requestSetExpressCheckout(
            $transaction->getRequestedAmount(),
            $this->getReturnUrl($data),
            $this->getCancelUrl($data),
            $opts
        );

        $this->throwUnlessSuccessResponse($response, $transaction);

        $data->set('express_checkout_token', $response->body->get('TOKEN'));

        $this->throwActionRequired($response->body->get('TOKEN'), $data, $transaction);

        return null;
    }

    /**
     * @param Response $response
     * @param FinancialTransactionInterface $transaction
     * @throws FinancialException
     */
    protected function throwUnlessSuccessResponse(Response $response, FinancialTransactionInterface $transaction)
    {
        if ($response->isSuccess()) {
            return;
        }

        $transaction->setResponseCode($response->body->get('ACK'));
        $transaction->setReasonCode($response->body->get('L_ERRORCODE0'));

        $ex = new FinancialException('Buybox-Response was not successful: '.$response);
        $ex->setFinancialTransaction($transaction);

        throw $ex;
    }

    /**
     * @param $token
     * @param $data
     * @param $transaction
     * @throws ActionRequiredException
     */
    protected function throwActionRequired(?string $token, $data, $transaction): void
    {
        $ex = new ActionRequiredException('User must authorize the transaction.');
        $ex->setFinancialTransaction($transaction);

        $params = [];

        if ($useraction = $this->getUserAction($data)) {
            $params['useraction'] = $this->getUserAction($data);
        }

        $ex->setAction(new VisitUrl(
            $this->client->getAuthenticateExpressCheckoutTokenUrl($token, $params)
        ));

        throw $ex;
    }

    protected function getReturnUrl(ExtendedDataInterface $data): string
    {
        if ($data->has('return_url')) {
            return $data->get('return_url');
        } elseif (!empty($this->returnUrl)) {
            return $this->returnUrl;
        }

        throw new \RuntimeException('You must configure a return url.');
    }

    protected function getCancelUrl(ExtendedDataInterface $data): string
    {
        if ($data->has('cancel_url')) {
            return $data->get('cancel_url');
        } elseif (!empty($this->cancelUrl)) {
            return $this->cancelUrl;
        }

        throw new \RuntimeException('You must configure a cancel url.');
    }

    protected function getNotifyUrl(ExtendedDataInterface $data): ?string
    {
        if ($data->has('notify_url')) {
            return $data->get('notify_url');
        } elseif (!empty($this->notifyUrl)) {
            return $this->notifyUrl;
        }
        return null;
    }

    protected function getUserAction(ExtendedDataInterface $data): ?string
    {
        if ($data->has('useraction')) {
            return $data->get('useraction');
        } elseif (!empty($this->userAction)) {
            return $this->userAction;
        }
        return null;
    }
}
