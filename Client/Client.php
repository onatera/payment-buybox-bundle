<?php

namespace Onatera\Payment\BuyboxBundle\Client;

use JMS\Payment\CoreBundle\BrowserKit\Request;
use JMS\Payment\CoreBundle\Plugin\Exception\CommunicationException;
use Onatera\Payment\BuyboxBundle\Client\Authentication\AuthenticationStrategyInterface;
use Symfony\Component\BrowserKit\Response as RawResponse;

class Client
{
    private const API_LOGIN = 'https://www2.buybox.net/secure/payment_login.php';
    private const API_LOGIN_SANDBOX = 'https://sandbox.buybox.net/secure/payment_login.php';

    private $authenticationStrategy;
    private $isDebug;
    private $curlOptions;

    public function __construct(AuthenticationStrategyInterface $authenticationStrategy, bool $isDebug)
    {
        $this->authenticationStrategy = $authenticationStrategy;
        $this->isDebug = $isDebug;
        $this->curlOptions = [];
    }

    /**
     * @param string $email
     * @param string $street
     * @param string $postalCode
     * @return Response
     * @throws CommunicationException
     */
    public function requestAddressVerify(string $email, string $street, string $postalCode): Response
    {
        return $this->sendApiRequest([
            'METHOD' => 'AddressVerify',
            'EMAIL'  => $email,
            'STREET' => $street,
            'ZIP'    => $postalCode,
        ]);
    }

    /**
     * @param string $profileId
     * @param array $optionalParameters
     * @return Response
     * @throws CommunicationException
     */
    public function requestBillOutstandingAmount(string $profileId, array $optionalParameters = []): Response
    {
        return $this->sendApiRequest(array_merge($optionalParameters, [
            'METHOD' => 'BillOutstandingAmount',
            'PROFILEID' => $profileId,
        ]));
    }

    /**
     * @param string $token
     * @return Response
     * @throws CommunicationException
     */
    public function requestCreateRecurringPaymentsProfile(string $token): Response
    {
        return $this->sendApiRequest([
            'METHOD' => 'CreateRecurringPaymentsProfile',
            'TOKEN' => $token,
        ]);
    }

    /**
     * @param string $transactionId
     * @param string $amount
     * @param array $optionalParameters
     * @return Response
     * @throws CommunicationException
     */
    public function requestDoAuthorization(
        string $transactionId,
        string $amount,
        array $optionalParameters = []
    ): Response {
        return $this->sendApiRequest(array_merge($optionalParameters, [
            'METHOD' => 'DoAuthorization',
            'TRANSACTIONID' => $transactionId,
            'AMT' => $this->convertAmountToBuyboxFormat($amount),
        ]));
    }

    /**
     * @param string $authorizationId
     * @param string $amount
     * @param string $completeType
     * @param array $optionalParameters
     * @return Response
     * @throws CommunicationException
     */
    public function requestDoCapture(
        string $authorizationId,
        string $amount,
        string $completeType,
        array $optionalParameters = []
    ): Response {
        return $this->sendApiRequest(array_merge($optionalParameters, [
            'METHOD' => 'DoCapture',
            'AUTHORIZATIONID' => $authorizationId,
            'AMT' => $this->convertAmountToBuyboxFormat($amount),
            'COMPLETETYPE' => $completeType,
        ]));
    }

    /**
     * @param string $ipAddress
     * @param array $optionalParameters
     * @return Response
     * @throws CommunicationException
     */
    public function requestDoDirectPayment(string $ipAddress, array $optionalParameters = []): Response
    {
        return $this->sendApiRequest(array_merge($optionalParameters, [
            'METHOD' => 'DoDirectPayment',
            'IPADDRESS' => $ipAddress,
        ]));
    }

    /**
     * @param string $token
     * @param string $amount
     * @param string $paymentAction
     * @param string $payerId
     * @param array $optionalParameters
     * @return Response
     * @throws CommunicationException
     */
    public function requestDoExpressCheckoutPayment(
        string $token,
        string $amount,
        string $paymentAction,
        ?string $payerId,
        array $optionalParameters = []
    ): Response {
        return $this->sendApiRequest(array_merge($optionalParameters, [
            'METHOD' => 'DoExpressCheckoutPayment',
            'TOKEN'  => $token,
            'AMT' => $this->convertAmountToBuyboxFormat($amount),
            'PAYMENTACTION' => $paymentAction,
            'PAYERID' => $payerId,
        ]));
    }

    /**
     * @param string $authorizationId
     * @param array $optionalParameters
     * @return Response
     * @throws CommunicationException
     */
    public function requestDoVoid(string $authorizationId, array $optionalParameters = []): Response
    {
        return $this->sendApiRequest(array_merge($optionalParameters, [
            'METHOD' => 'DoVoid',
            'AUTHORIZATIONID' => $authorizationId,
        ]));
    }

    /**
     * @param string $amount
     * @param string $returnUrl
     * @param string $cancelUrl
     * @param array $optionalParameters
     * @return Response
     * @throws CommunicationException
     */
    public function requestSetExpressCheckout(
        string $amount,
        string $returnUrl,
        string $cancelUrl,
        array $optionalParameters = []
    ): Response {
        return $this->sendApiRequest(array_merge($optionalParameters, [
            'METHOD' => 'SetExpressCheckout',
            'AMT' => $this->convertAmountToBuyboxFormat($amount),
            'RETURNURL' => $returnUrl,
            'CANCELURL' => $cancelUrl,
        ]));
    }

    /**
     * @param string $token
     * @return Response
     * @throws CommunicationException
     */
    public function requestGetExpressCheckoutDetails(string $token): Response
    {
        return $this->sendApiRequest([
            'METHOD' => 'GetExpressCheckoutDetails',
            'TOKEN'  => $token,
        ]);
    }

    /**
     * @param string $transactionId
     * @return Response
     * @throws CommunicationException
     */
    public function requestGetTransactionDetails(string $transactionId)
    {
        return $this->sendApiRequest([
            'METHOD' => 'GetTransactionDetails',
            'TRANSACTIONID' => $transactionId,
        ]);
    }

    /**
     * @param string $transactionId
     * @param array $optionalParameters
     * @return Response
     * @throws CommunicationException
     */
    public function requestRefundTransaction(string $transactionId, array $optionalParameters = []): Response
    {
        return $this->sendApiRequest(array_merge($optionalParameters, array(
            'METHOD' => 'RefundTransaction',
            'TRANSACTIONID' => $transactionId,
        )));
    }

    /**
     * @param array $parameters
     * @return Response
     * @throws CommunicationException
     */
    public function sendApiRequest(array $parameters): Response
    {
        // setup request, and authenticate it
        $request = new Request(
            $this->authenticationStrategy->getApiEndpoint($this->isDebug),
            'POST',
            $parameters
        );
        $this->authenticationStrategy->authenticate($request);

        $response = $this->request($request);
        if (200 !== $response->getStatus()) {
            throw new CommunicationException(sprintf(
                'The API request was not successful (Status: %s): %s',
                $response->getStatus(),
                $response->getContent()
            ));
        }

        $parameters = [];
        parse_str($response->getContent(), $parameters);

        return new Response($parameters);
    }

    /**
     * @param string $token
     * @param array $params
     * @return string
     */
    public function getAuthenticateExpressCheckoutTokenUrl(?string $token, array $params = []): string
    {
        $url = $this->isDebug ? self::API_LOGIN_SANDBOX : self::API_LOGIN;

        $url .= sprintf('?%s=%s', 'token', $token);

        foreach ($params as $key => $value) {
            $url .= sprintf('&%s=%s', $key, $value);
        }

        return $url;
    }

    public function convertAmountToBuyboxFormat(string $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    public function setCurlOption(string $name, string $value)
    {
        $this->curlOptions[$name] = $value;
    }

    protected function urlEncodeArray(array $encode): string
    {
        $encoded = '';
        foreach ($encode as $name => $value) {
            $encoded .= '&'.urlencode($name).'='.urlencode($value);
        }

        return substr($encoded, 1);
    }

    /**
     * Performs a request to an external payment service.
     *
     * @throws CommunicationException when an curl error occurs
     * @throws \RuntimeException
     *
     * @param Request $request
     *
     * @return RawResponse
     */
    public function request(Request $request): RawResponse
    {
        if (!extension_loaded('curl')) {
            throw new \RuntimeException('The cURL extension must be loaded.');
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt_array($curl, $this->curlOptions);
        curl_setopt($curl, CURLOPT_URL, $request->getUri());
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);

        $headers = [];
        foreach ($request->headers->all() as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $subValue) {
                    $headers[] = sprintf('%s: %s', $name, $subValue);
                }
            } else {
                $headers[] = sprintf('%s: %s', $name, $value);
            }
        }
        if (count($headers) > 0) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        // set method
        $method = strtoupper($request->getMethod());
        if ('POST' === $method) {
            curl_setopt($curl, CURLOPT_POST, true);

            if (!$request->headers->has('Content-Type')
                || 'multipart/form-data' !== $request->headers->get('Content-Type')
            ) {
                $postFields = $this->urlEncodeArray($request->request->all());
            } else {
                $postFields = $request->request->all();
            }

            curl_setopt($curl, CURLOPT_POSTFIELDS, $postFields);
        } elseif ('PUT' === $method) {
            curl_setopt($curl, CURLOPT_PUT, true);
        } elseif ('HEAD' === $method) {
            curl_setopt($curl, CURLOPT_NOBODY, true);
        }

        // perform the request
        if (false === $returnTransfer = curl_exec($curl)) {
            throw new CommunicationException(
                'cURL Error: '.curl_error($curl),
                curl_errno($curl)
            );
        }

        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $headers = [];
        if (preg_match_all(
            '#^([^:\r\n]+):\s+([^\n\r]+)#m',
            substr($returnTransfer, 0, $headerSize),
            $matches
        )) {
            foreach ($matches[1] as $key => $name) {
                $headers[$name] = $matches[2][$key];
            }
        }

        $response = new RawResponse(
            substr($returnTransfer, $headerSize),
            curl_getinfo($curl, CURLINFO_HTTP_CODE),
            $headers
        );
        curl_close($curl);

        return $response;
    }
}
