<?php

declare(strict_types=1);

namespace JTL\TwoFA;

use InvalidArgumentException;

/**
 * PHP Class for handling Google Authenticator 2-factor authentication.
 *
 * @author Michael Kliewe
 * @copyright 2012 Michael Kliewe
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 * @link http://www.phpgangsta.de/
 */
class GoogleAuthenticator
{
    protected int $codeLength = 6;

    /**
     * Create new secret.
     * 16 characters, randomly chosen from the allowed base32 characters.
     *
     * @throws InvalidArgumentException
     */
    public function createSecret(int $secretLength = 16): string
    {
        $validChars = $this->getBase32LookupTable();
        // Valid secret lengths are 80 to 640 bits
        if ($secretLength < 16 || $secretLength > 128) {
            throw new InvalidArgumentException('Bad secret length');
        }
        $secret = '';
        $rnd    = \random_bytes($secretLength);
        for ($i = 0; $i < $secretLength; ++$i) {
            $secret .= $validChars[\ord($rnd[$i]) & 31];
        }

        return $secret;
    }

    /**
     * Calculate the code, with given secret and point in time.
     *
     * @throws InvalidArgumentException
     */
    public function getCode(string $secret, ?float $timeSlice = null): string
    {
        if ($timeSlice === null) {
            $timeSlice = \floor(\time() / 30);
        }
        $secretkey = $this->base32Decode($secret);
        if ($secretkey === false) {
            throw new InvalidArgumentException('Invalid secret');
        }
        // Pack time into binary string
        $time = \chr(0) . \chr(0) . \chr(0) . \chr(0) . \pack('N*', $timeSlice);
        // Hash it with users secret key
        $hm = \hash_hmac('SHA1', $time, $secretkey, true);
        // Use last nipple of result as index/offset
        $offset = \ord(\substr($hm, -1)) & 0x0F;
        // grab 4 bytes of the result
        $hashpart = \substr($hm, $offset, 4);
        // Unpak binary value
        $value = \unpack('N', $hashpart) ?: throw new InvalidArgumentException('Unpacking failed');
        $value = $value[1];
        // Only 32 bits
        $value &= 0x7FFFFFFF;

        $modulo = 10 ** $this->codeLength;

        return \str_pad((string)($value % $modulo), $this->codeLength, '0', \STR_PAD_LEFT);
    }

    /**
     * Get QR-Code URL for image, from google charts.
     *
     * @param array{width?: int, height?: int, level?: string} $params
     */
    public function getQRCodeGoogleUrl(string $name, string $secret, ?string $title = null, array $params = []): string
    {
        $width      = (int)($params['width'] ?? 200);
        $height     = (int)($params['height'] ?? 200);
        $level      = \in_array($params['level'] ?? '_', ['L', 'M', 'Q', 'H'], true)
            ? $params['level']
            : 'M';
        $urlencoded = \urlencode('otpauth://totp/' . $name . '?secret=' . $secret);
        if (isset($title)) {
            $urlencoded .= \urlencode('&issuer=' . \urlencode($title));
        }

        return 'https://api.qrserver.com/v1/create-qr-code/?data='
            . $urlencoded
            . '&size=' . $width . 'x' . $height
            . '&ecc=' . $level;
    }

    /**
     * Check if the code is correct.
     * This will accept codes starting from $discrepancy*30sec ago to $discrepancy*30sec from now.
     */
    public function verifyCode(
        string $secret,
        string $code,
        int $discrepancy = 1,
        ?float $currentTimeSlice = null
    ): bool {
        if (\strlen($code) !== 6) {
            return false;
        }
        $currentTimeSlice = $currentTimeSlice ?? (\floor(\time() / 30));
        for ($i = -$discrepancy; $i <= $discrepancy; ++$i) {
            $calculatedCode = $this->getCode($secret, $currentTimeSlice + $i);
            if (\hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set the code length, should be >=6.
     */
    public function setCodeLength(int $length): self
    {
        $this->codeLength = $length;

        return $this;
    }

    protected function base32Decode(string $secret): false|string
    {
        if (empty($secret)) {
            return '';
        }
        $base32chars        = $this->getBase32LookupTable();
        $base32charsFlipped = \array_flip($base32chars);
        $paddingCharCount   = \substr_count($secret, $base32chars[32]);
        $allowedValues      = [6, 4, 3, 1, 0];
        if (!\in_array($paddingCharCount, $allowedValues, true)) {
            return false;
        }
        for ($i = 0; $i < 4; ++$i) {
            if (
                $paddingCharCount === $allowedValues[$i]
                && \substr($secret, -($allowedValues[$i])) !== \str_repeat($base32chars[32], $allowedValues[$i])
            ) {
                return false;
            }
        }
        $secret       = \str_replace('=', '', $secret);
        $split        = \str_split($secret);
        $len          = \count($split);
        $binaryString = '';
        for ($i = 0; $i < $len; $i += 8) {
            $x = '';
            if (!\in_array($split[$i], $base32chars, true)) {
                return false;
            }
            for ($j = 0; $j < 8; ++$j) {
                $x .= \str_pad(
                    \base_convert((string)$base32charsFlipped[$split[$i + $j]], 10, 2),
                    5,
                    '0',
                    \STR_PAD_LEFT
                );
            }
            foreach (\str_split($x, 8) as $zValue) {
                $binaryString .= (($y = \chr((int)\base_convert($zValue, 2, 10))) || \ord($y) === 48) ? $y : '';
            }
        }

        return $binaryString;
    }

    /**
     * Get array with all 32 characters for decoding from/encoding to base32.
     *
     * @return string[]
     */
    protected function getBase32LookupTable(): array
    {
        return [
            'A',
            'B',
            'C',
            'D',
            'E',
            'F',
            'G',
            'H', //  7
            'I',
            'J',
            'K',
            'L',
            'M',
            'N',
            'O',
            'P', // 15
            'Q',
            'R',
            'S',
            'T',
            'U',
            'V',
            'W',
            'X', // 23
            'Y',
            'Z',
            '2',
            '3',
            '4',
            '5',
            '6',
            '7', // 31
            '=',  // padding char
        ];
    }
}
