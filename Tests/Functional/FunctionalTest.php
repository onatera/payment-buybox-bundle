<?php

namespace Onatera\Payment\BuyboxBundle\Tests\Functional;

use Onatera\Payment\BuyboxBundle\Client\Authentication\TokenAuthenticationStrategy;
use Onatera\Payment\BuyboxBundle\Client\Client;

abstract class FunctionalTest extends \PHPUnit_Framework_TestCase
{
    protected function getClient(): Client
    {
        if (empty($_SERVER['API_USERNAME']) || empty($_SERVER['API_PASSWORD']) || empty($_SERVER['API_SIGNATURE'])) {
            $this->markTestSkipped('In order to run these tests you have to configure: API_USERNAME, API_PASSWORD, API_SIGNATURE parameters in phpunit.xml file');
        }

        $authenticationStrategy = new TokenAuthenticationStrategy(
            $_SERVER['API_USERNAME'],
            $_SERVER['API_PASSWORD'],
            $_SERVER['API_SIGNATURE']
        );

        return new Client($authenticationStrategy, $debug = true);
    }
}
