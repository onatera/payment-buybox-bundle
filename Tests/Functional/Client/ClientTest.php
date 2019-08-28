<?php

namespace Onatera\Payment\BuyboxBundle\Tests\Functional\Client;

use Onatera\Payment\BuyboxBundle\Client\Response;
use Onatera\Payment\BuyboxBundle\Tests\Functional\FunctionalTest;

class ClientTest extends FunctionalTest
{
    private $client;

    protected function setUp()
    {
        $this->client = $this->getClient();
    }

    public function testRequestSetExpressCheckout()
    {
        $response = $this->client
            ->requestSetExpressCheckout(123.43, 'http://www.foo.com/returnUrl', 'http://www.foo.com/cancelUrl', [
                'CURRENCYCODE'  => 'EUR',
            ]);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->body->has('TOKEN'));
        $this->assertTrue($response->isSuccess());
        $this->assertEquals('Success', $response->body->get('ACK'));
    }

    public function testRequestGetExpressCheckoutDetails()
    {
        $response = $this->client->requestSetExpressCheckout('123', 'http://www.foo.com/', 'http://www.foo.com/', [
            'CURRENCYCODE'  => 'EUR',
        ]);

        //guard
        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->body->has('TOKEN'));

        $response = $this->client->requestGetExpressCheckoutDetails($response->body->get('TOKEN'));

        $this->assertTrue($response->body->has('TOKEN'));
        $this->assertTrue($response->body->has('CHECKOUTSTATUS'));
        $this->assertEquals('PaymentActionNotInitiated', $response->body->get('CHECKOUTSTATUS'));
        $this->assertEquals('Success', $response->body->get('ACK'));
    }
}
