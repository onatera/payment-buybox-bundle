<?php

namespace Onatera\Payment\BuyboxBundle\Tests\Client;

use Onatera\Payment\BuyboxBundle\Client\Authentication\AuthenticationStrategyInterface;
use Onatera\Payment\BuyboxBundle\Client\Client;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    public function testShouldAllowGetAuthenticateExpressCheckoutTokenUrlInProdMode()
    {
        $expectedUrl = 'https://www2.buybox.net/secure/payment_login.php?token=foobar';
        $token = 'foobar';

        $client = $this->getClient($debug = false);

        $this->assertEquals($expectedUrl, $client->getAuthenticateExpressCheckoutTokenUrl($token));
    }

    public function testShouldAllowGetAuthenticateExpressCheckoutTokenUrlInDebugMode()
    {
        $expectedUrl = 'https://sandbox.buybox.net/secure/payment_login.php?token=foobar';
        $token = 'foobar';

        $client = $this->getClient($debug = true);

        $this->assertEquals($expectedUrl, $client->getAuthenticateExpressCheckoutTokenUrl($token));
    }

    public function testGetAuthenticateExpressCheckoutTokenUrlParams()
    {
        $expectedUrl = 'https://sandbox.buybox.net/secure/payment_login.php?token=foobar&param1=foo&param2=bar';
        $token = 'foobar';
        $params = ['param1' => 'foo', 'param2' => 'bar'];

        $client = $this->getClient($debug = true);

        $this->assertEquals($expectedUrl, $client->getAuthenticateExpressCheckoutTokenUrl($token, $params));
    }

    private function getClient($debug)
    {
        return new Client($this->createAuthenticationStrategyMock(), $debug);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|\Onatera\Payment\BuyboxBundle\Client\Authentication\AuthenticationStrategyInterface
     */
    private function createAuthenticationStrategyMock()
    {
        return $this->getMockBuilder(AuthenticationStrategyInterface::class)->getMock();
    }
}
