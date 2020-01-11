<?php
namespace App\Verification\PaymentModules;

use App\Verification\Abstracts\PaymentModule;
use App\Verification\Abstracts\SupportSms;
use App\Verification\DataField;
use App\Verification\Exceptions\BadCodeException;
use App\Verification\Exceptions\ExternalErrorException;
use App\Verification\Exceptions\NoConnectionException;
use App\Verification\Exceptions\UnknownErrorException;
use App\Verification\Exceptions\WrongCredentialsException;
use App\Verification\Results\SmsSuccessResult;

class Simpay extends PaymentModule implements SupportSms
{
    const MODULE_ID = "simpay";

    public function verifySms($returnCode, $number)
    {
        $response = $this->requester->post('https://simpay.pl/api/1/status', [
            'params' => [
                'auth' => [
                    'key' => $this->getKey(),
                    'secret' => $this->getSecret(),
                ],
                'service_id' => $this->getServiceId(),
                'number' => $number,
                'code' => $returnCode,
            ],
        ]);

        if (!$response) {
            throw new NoConnectionException();
        }

        $content = $response->json();

        if (isset($content['respond']['status']) && $content['respond']['status'] == 'OK') {
            return new SmsSuccessResult(!!$content['respond']['test']);
        }

        if (isset($content['error'][0]) && is_array($content['error'][0])) {
            switch ((int) $content['error'][0]['error_code']) {
                case 103:
                case 104:
                    throw new WrongCredentialsException();

                case 404:
                case 405:
                    throw new BadCodeException();
            }

            throw new ExternalErrorException($content['error'][0]['error_name']);
        }

        throw new UnknownErrorException();
    }

    public function getSmsCode()
    {
        return $this->getData('sms_text');
    }

    public static function getDataFields()
    {
        return [
            new DataField("key"),
            new DataField("secret"),
            new DataField("service_id"),
            new DataField("sms_text"),
        ];
    }

    private function getKey()
    {
        return $this->getData('key');
    }

    private function getSecret()
    {
        return $this->getData('secret');
    }

    private function getServiceId()
    {
        return $this->getData('service_id');
    }
}