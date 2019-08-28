<?php

namespace Onatera\Payment\BuyboxBundle\Client\Authentication;

use JMS\Payment\CoreBundle\BrowserKit\Request;

interface AuthenticationStrategyInterface
{
    public function getApiEndpoint(bool $isDebug): string;
    public function authenticate(Request $request): void;
}
