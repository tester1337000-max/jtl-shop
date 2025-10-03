<?php

declare(strict_types=1);

namespace JTL\VerificationVAT;

/**
 * Class VATCheckNonEU
 * @package JTL\VerificationVAT
 */
class VATCheckNonEU extends AbstractVATCheck
{
    /**
     * @inheritdoc
     */
    public function doCheckID(string $ustID): array
    {
        $VatParser = new VATCheckVatParserNonEU($this->condenseSpaces($ustID));
        if ($VatParser->parseVatId() === true) {
            return [
                'success'   => true,
                'errortype' => 'parse',
                'errorcode' => 0,
                'errorinfo' => ''
            ];
        }

        return [
            'success'   => false,
            'errortype' => 'parse',
            'errorcode' => VATCheckInterface::ERR_PATTERN_MISMATCH,
            'errorinfo' => ($szErrorInfo = $VatParser->getErrorInfo()) !== '' ? (string)$szErrorInfo : ''
        ];
    }
}
