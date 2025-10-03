<?php

declare(strict_types=1);

namespace JTL\Newsletter;

use JTL\Shop;

/**
 * Class Helper
 * @package JTL\Newsletter
 */
class Helper
{
    public static function customerIsSubscriber(int $customerID): bool
    {
        $recipient = Shop::Container()->getDB()->select('tnewsletterempfaenger', 'kKunde', $customerID);

        return ($recipient->kKunde ?? 0) > 0;
    }
}
