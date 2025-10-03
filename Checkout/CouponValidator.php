<?php

declare(strict_types=1);

namespace JTL\Checkout;

use JTL\Customer\Customer;
use JTL\Helpers\Form;
use JTL\Settings\Option\Checkout;
use JTL\Settings\Settings;
use JTL\Shop;

/**
 * Class CouponValidator
 * @package JTL\Checkout
 */
class CouponValidator
{
    /**
     * @param array<mixed> $post
     * @return array<string, int>|int
     * @former plausiKupon()
     * @since 5.2.0
     */
    public static function validateCoupon(array $post, Customer $customer): array|int
    {
        $errors = [];
        if (
            isset($post['Kuponcode'])
            && (isset($_SESSION['Bestellung']->lieferadresseGleich) || $_SESSION['Lieferadresse'])
        ) {
            $coupon = new Kupon();
            $coupon = $coupon->getByCode($_POST['Kuponcode']);
            if ($coupon !== false && $coupon->kKupon > 0) {
                $errors = $coupon->check();
                if (Form::hasNoMissingData($errors)) {
                    $coupon->accept();
                    if ($coupon->cKuponTyp === Kupon::TYPE_SHIPPING) { // Versandfrei Kupon
                        $_SESSION['oVersandfreiKupon'] = $coupon;
                    }
                }
            } else {
                $errors['ungueltig'] = 11;
            }
            Kupon::mapCouponErrorMessage($errors['ungueltig'] ?? 0);
        }
        self::validateNewCustomerCoupon($customer);

        return (\count($errors) > 0)
            ? $errors
            : 0;
    }

    /**
     * @former plausiNeukundenKupon()
     * @since 5.2.0
     */
    public static function validateNewCustomerCoupon(Customer $customer): void
    {
        if (isset($_SESSION['NeukundenKuponAngenommen']) && $_SESSION['NeukundenKuponAngenommen'] === true) {
            return;
        }

        if (
            $customer->getID() <= 0
            && Settings::boolValue(Checkout::ALLOW_NEW_CUSTOMER_COUPONS_FOR_GUESTS) === false
        ) {
            // unregistrierte Neukunden, keine Kupons für Gastbestellungen zugelassen
            return;
        }
        if (($_SESSION['Kupon']->cKuponTyp ?? '') === 'standard' || empty($customer->cMail)) {
            return;
        }
        // not for already registered customers with order(s)
        $order = Shop::Container()->getDB()->getSingleObject(
            'SELECT kBestellung
                FROM tbestellung
                WHERE kKunde = :customerID
                LIMIT 1',
            ['customerID' => $customer->getID()]
        );
        if ($order !== null) {
            return;
        }

        $coupons = (new Kupon())->getNewCustomerCoupon();
        if (!empty($coupons) && !Kupon::newCustomerCouponUsed($customer->cMail)) {
            foreach ($coupons as $coupon) {
                if (Form::hasNoMissingData($coupon->check())) {
                    $coupon->accept();
                    break;
                }
            }
        }
    }
}
