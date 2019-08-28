<?php

namespace Onatera\Payment\BuyboxBundle\Client;

use Symfony\Component\HttpFoundation\ParameterBag;

class Response
{
    public $body;

    public function __construct(array $parameters)
    {
        $this->body = new ParameterBag($parameters);
    }

    public function isSuccess(): bool
    {
        $ack = $this->body->get('ACK');

        return 'Success' === $ack || 'SuccessWithWarning' === $ack;
    }

    public function isPartialSuccess(): bool
    {
        return 'PartialSuccess' === $this->body->get('ACK');
    }

    public function isError(): bool
    {
        $ack = $this->body->get('ACK');

        return 'Failure' === $ack || 'FailureWithWarning' === $ack || 'Warning' === $ack;
    }

    public function getErrors(): array
    {
        $errors = [];
        $i = 0;
        while ($this->body->has('L_ERRORCODE'.$i)) {
            $errors[] = array(
                'code' => $this->body->get('L_ERRORCODE'.$i),
                'short_message' => $this->body->get('L_SHORTMESSAGE'.$i),
                'long_message' => $this->body->get('L_LONGMESSAGE'.$i),
            );

            ++$i;
        }

        return $errors;
    }

    public function __toString(): string
    {
        if ($this->isError()) {
            $str = 'Debug-Token: '.$this->body->get('CORRELATIONID')."\n";

            foreach ($this->getErrors() as $error) {
                $str .= "{$error['code']}: {$error['short_message']} ({$error['long_message']})\n";
            }
        } else {
            $str = var_export($this->body->all(), true);
        }

        return $str;
    }
}
