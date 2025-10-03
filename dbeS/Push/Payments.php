<?php

declare(strict_types=1);

namespace JTL\dbeS\Push;

/**
 * Class Payments
 * @package JTL\dbeS\Push
 */
final class Payments extends AbstractPush
{
    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        $xml      = [];
        $payments = $this->db->getArrays(
            "SELECT *, date_format(dZeit, '%d.%m.%Y') AS dZeit_formatted
                FROM tzahlungseingang
                WHERE cAbgeholt = 'N'
                ORDER BY kZahlungseingang"
        );
        $count    = \count($payments);
        if ($count === 0) {
            return $xml;
        }
        foreach ($payments as $i => $payment) {
            $payments[$i . ' attr'] = $this->buildAttributes($payment);
            $payments[$i]           = $payment;
        }
        $xml['zahlungseingaenge']['tzahlungseingang'] = $payments;
        $xml['zahlungseingaenge attr']['anzahl']      = $count;

        return $xml;
    }
}
