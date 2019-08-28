<?php

namespace Onatera\Payment\BuyboxBundle\Client\Authentication;

use JMS\Payment\CoreBundle\BrowserKit\Request;

class TokenAuthenticationStrategy implements AuthenticationStrategyInterface
{
    private const API_CHECKOUT = 'https://www2.buybox.net/secure/express-checkout/nvp.php';
    private const API_CHECKOUT_SANDBOX = 'https://sandbox.buybox.net/secure/express-checkout/nvp.php';

    private $username;
    private $password;
    private $signature;

    public function __construct(string $username, string $password, string $signature)
    {
        $this->username = $username;
        $this->password = $password;
        $this->signature = $signature;
    }

    public function authenticate(Request $request): void
    {
        $request->request->set('PWD', $this->password);
        $request->request->set('USER', $this->username);
        $request->request->set('SIGNATURE', $this->signature);
    }

    public function getApiEndpoint(bool $isDebug): string
    {
        if ($isDebug) {
            return self::API_CHECKOUT_SANDBOX;
        } else {
            return self::API_CHECKOUT;
        }
    }
}
