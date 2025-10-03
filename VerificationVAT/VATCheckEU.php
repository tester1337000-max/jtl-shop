<?php

declare(strict_types=1);

namespace JTL\VerificationVAT;

use JTL\Shop;
use SoapClient;

/**
 * Class VATCheckEU
 * @package JTL\VerificationVAT
 * External documentation
 * @link http://ec.europa.eu/taxation_customs/vies/faq.html
 */
class VATCheckEU extends AbstractVATCheck
{
    private string $viesWSDL = 'https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl';

    private const ERROR_MAX_REQUESTS = 'MS_MAX_CONCURRENT_REQ';

    /**
     * ask the remote APIs of the VIES-online-system
     *
     * return a array of check-results
     * [
     *        success   : boolean, "true" = all checks were fine, "false" somthing went wrong
     *      , errortype : string, which type of error was occure, time- or parse-error
     *      , errorcode : string, numerical code to identify the error
     *      , errorinfo : additional information to show it the user in the frontend
     * ]
     *
     * @return array{success: bool, errortype: string, errorcode: int, errorinfo: string}
     */
    public function doCheckID(string $ustID): array
    {
        if (!\extension_loaded('soap')) {
            return [
                'success'   => false,
                'errortype' => 'core',
                'errorcode' => -1,
                'errorinfo' => 'VAT check not possible! Module "php_soap" was disabled.'
            ];
        }

        $vatParser = new VATCheckVatParser($this->condenseSpaces($ustID));
        if ($vatParser->parseVatId() === true) {
            [$countryCode, $vatNumber] = $vatParser->getIdAsParams();
        } else {
            return [
                'success'   => false,
                'errortype' => 'parse',
                'errorcode' => $vatParser->getErrorCode(),
                'errorinfo' => ($errorInfo = $vatParser->getErrorInfo()) !== '' ? (string)$errorInfo : ''
            ];
        }
        // asking the remote service if the VAT-office is reachable
        if ($this->downTimes->isDown($countryCode) === false) {
            $soap   = new SoapClient($this->viesWSDL);
            $result = null;
            $msg    = null;
            try {
                /** @var \stdClass $result */
                $result = $soap->checkVat(['countryCode' => $countryCode, 'vatNumber' => $vatNumber]);
            } catch (\Exception $e) {
                $msg = $e->getMessage();
                Shop::Container()->getLogService()->warning('VAT ID problem: {msg}', ['msg' => $msg]);
            }
            if ($msg === self::ERROR_MAX_REQUESTS) {
                return [
                    'success'   => false,
                    'errortype' => 'maxrequests',
                    'errorcode' => 6,
                    'errorinfo' => ''
                ];
            }
            if ($result !== null && $result->valid === true) {
                $this->logger->notice('VAT ID valid. ({msg})', ['msg' => \print_r($result, true)]);

                return [
                    'success'   => true,
                    'errortype' => 'vies',
                    'errorcode' => 0,
                    'errorinfo' => ''
                ];
            }
            $this->logger->notice('VAT ID invalid! ({msg})', ['msg' => \print_r($result, true)]);

            return [
                'success'   => false,
                'errortype' => 'vies',
                'errorcode' => 5, // error: ID is invalid according to the VIES-system
                'errorinfo' => ''
            ];
        }
        // inform the user: "The VAT-office in this country has closed this time."
        $this->logger->notice(
            'TAX authority of this country currently not available. (ID: {id})',
            ['id' => $ustID]
        );

        return [
            'success'   => false,
            'errortype' => 'time',
            'errorcode' => 200,
            'errorinfo' => $this->downTimes->getDownInfo() // the time, till which the office has closed
        ];
    }
}
