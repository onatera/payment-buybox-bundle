<?php

namespace Onatera\Payment\BuyboxBundle\Tests\Functional\Plugin;

use JMS\Payment\CoreBundle\Entity\ExtendedData;
use JMS\Payment\CoreBundle\Entity\FinancialTransaction;
use JMS\Payment\CoreBundle\Entity\Payment;
use JMS\Payment\CoreBundle\Entity\PaymentInstruction;
use JMS\Payment\CoreBundle\Plugin\Exception\Action\VisitUrl;
use JMS\Payment\CoreBundle\Plugin\Exception\ActionRequiredException;
use Onatera\Payment\BuyboxBundle\Plugin\ExpressCheckoutPlugin;
use Onatera\Payment\BuyboxBundle\Tests\Functional\FunctionalTest;

class ExpressCheckoutPluginTest extends FunctionalTest
{
    public function testApproveNoUserActionByDefault()
    {
        $plugin = new ExpressCheckoutPlugin(
            'http://example.com',
            'http://example.com',
            $this->getClient()
        );

        $transaction = $this->getTransaction();

        try {
            $plugin->approve($transaction, $retry = false);
        } catch (ActionRequiredException $ex) {
            $action = $ex->getAction();
            $this->assertInstanceOf(VisitUrl::class, $action);
            $this->assertNotContains('useraction', $action->getUrl());
        }
    }

//    public function testApproveWithUserAction()
//    {
//        $plugin = new ExpressCheckoutPlugin(
//            'http://example.com',
//            'http://example.com',
//            $this->getClient(),
//            null,
//            'commit'
//        );
//
//        $transaction = $this->getTransaction();
//
//        try {
//            $plugin->approve($transaction, $retry = false);
//        } catch (ActionRequiredException $ex) {
//            $action = $ex->getAction();
//            $this->assertInstanceOf(VisitUrl::class, $action);
//            $this->assertContains('useraction=commit', $action->getUrl());
//        }
//    }
//
//    public function testApproveWithUserActionInExtendedData()
//    {
//        $plugin = new ExpressCheckoutPlugin(
//            'http://example.com',
//            'http://example.com',
//            $this->getClient()
//        );
//
//        $transaction = $this->getTransaction();
//        $transaction->getExtendedData()->set('useraction', 'commit');
//
//        try {
//            $plugin->approve($transaction, $retry = false);
//        } catch (ActionRequiredException $ex) {
//            $action = $ex->getAction();
//            $this->assertInstanceOf(VisitUrl::class, $action);
//            $this->assertContains('useraction=commit', $action->getUrl());
//        }
//    }

    private function getTransaction()
    {
        $amount = 123.45;
        $instruction = new PaymentInstruction($amount, 'EUR', 'foo', new ExtendedData());
        $payment = new Payment($instruction, $amount);

        $transaction = new FinancialTransaction();
        $transaction->setPayment($payment);
        $transaction->setRequestedAmount($amount);

        return $transaction;
    }
}
