<?php

declare(strict_types=1);

namespace JTL\Settings\Option;

use JTL\Settings\Section;

enum Payment: string implements OptionInterface
{
    case DIRECT_DEBIT_MAX_ORDER_VALUE             = 'zahlungsart_lastschrift_max';
    case DIRECT_DEBIT_MIN_ORDER_VALUE             = 'zahlungsart_lastschrift_min';
    case DIRECT_DEBIT_MIN_ORDERS                  = 'zahlungsart_lastschrift_min_bestellungen';
    case BANK_TRANSFER_MIN_ORDERS                 = 'zahlungsart_ueberweisung_min_bestellungen';
    case BANK_TRANSFER_MIN_ORDER_VALUE            = 'zahlungsart_ueberweisung_min';
    case BANK_TRANSFER_MAX_ORDER_VALUE            = 'zahlungsart_ueberweisung_max';
    case BANK_TRANSFER_BIC_PROMPT                 = 'zahlungsart_lastschrift_bic_abfrage';
    case BANK_TRANSFER_ACCOUNT_HOLDER_PROMPT      = 'zahlungsart_lastschrift_kontoinhaber_abfrage';
    case BANK_TRANSFER_ACCOUNT_INSTITUTION_PROMPT = 'zahlungsart_lastschrift_kreditinstitut_abfrage';
    case INVOICE_MIN_ORDERS                       = 'zahlungsart_rechnung_min_bestellungen';
    case INVOICE_MIN_ORDER_VALUE                  = 'zahlungsart_rechnung_min';
    case INVOICE_MAX_ORDER_VALUE                  = 'zahlungsart_rechnung_max';
    case COP_MIN_ORDERS                           = 'zahlungsart_nachnahme_min_bestellungen';
    case COP_MIN_ORDER_VALUE                      = 'zahlungsart_nachnahme_min';
    case COP_MAX_ORDER_VALUE                      = 'zahlungsart_nachnahme_max';
    case CASH_MIN_ORDERS                          = 'zahlungsart_barzahlung_min_bestellungen';
    case CASH_MIN_ORDER_VALUE                     = 'zahlungsart_barzahlung_min';
    case CASH_MAX_ORDER_VALUE                     = 'zahlungsart_barzahlung_max';

    public function getValue(): string
    {
        return $this->value;
    }

    public function getSection(): Section
    {
        return Section::PAYMENT;
    }
}
