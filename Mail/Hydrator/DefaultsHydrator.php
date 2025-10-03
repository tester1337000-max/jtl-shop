<?php

declare(strict_types=1);

namespace JTL\Mail\Hydrator;

use JTL\Customer\Customer;
use JTL\Customer\CustomerGroup;
use JTL\DB\DbInterface;
use JTL\Firma;
use JTL\Helpers\GeneralObject;
use JTL\Helpers\Request;
use JTL\Helpers\Text;
use JTL\Language\LanguageModel;
use JTL\Shop;
use JTL\Shopsetting;
use JTL\Smarty\JTLSmarty;
use stdClass;

/**
 * Class DefaultsHydrator
 * @package JTL\Mail\Hydrator
 */
class DefaultsHydrator implements HydratorInterface
{
    public function __construct(protected JTLSmarty $smarty, protected DbInterface $db, protected Shopsetting $settings)
    {
    }

    /**
     * @inheritdoc
     */
    public function add(string $variable, mixed $content): void
    {
        $this->smarty->assign($variable, $content);
    }

    /**
     * @inheritdoc
     */
    public function hydrate(?object $data, LanguageModel $language): void
    {
        /** @var stdClass $data */
        $data         = $data ?? new stdClass();
        $data->tkunde = $data->tkunde ?? new Customer();

        if (!isset($data->tkunde->kKundengruppe) || !$data->tkunde->kKundengruppe) {
            $data->tkunde->kKundengruppe = CustomerGroup::getDefaultGroupID();
        }
        $data->tfirma        = new Firma(true, $this->db);
        $data->tkundengruppe = new CustomerGroup($data->tkunde->kKundengruppe, $this->db);
        $customer            = $data->tkunde instanceof Customer
            ? $data->tkunde->localize($language)
            : $this->localizeCustomer($language, $data->tkunde);

        $this->smarty->assign('int_lang', $language)
            ->assign('Firma', $data->tfirma)
            ->assign('Kunde', $customer)
            ->assign('Kundengruppe', $data->tkundengruppe)
            ->assign('NettoPreise', $data->tkundengruppe->isMerchant())
            ->assign('ShopLogoURL', Shop::getLogo(true))
            ->assign('ShopURL', Shop::getURL())
            ->assign('Einstellungen', $this->settings)
            ->assign('IP', Text::htmlentities(Text::filterXSS(Request::getRealIP())));
    }

    /**
     * @inheritdoc
     */
    public function getSmarty(): JTLSmarty
    {
        return $this->smarty;
    }

    /**
     * @inheritdoc
     */
    public function setSmarty(JTLSmarty $smarty): void
    {
        $this->smarty = $smarty;
    }

    /**
     * @inheritdoc
     */
    public function getDB(): DbInterface
    {
        return $this->db;
    }

    /**
     * @inheritdoc
     */
    public function setDB(DbInterface $db): void
    {
        $this->db = $db;
    }

    /**
     * @inheritdoc
     */
    public function getSettings(): Shopsetting
    {
        return $this->settings;
    }

    /**
     * @inheritdoc
     */
    public function setSettings(Shopsetting $settings): void
    {
        $this->settings = $settings;
    }

    /**
     * @param LanguageModel     $lang
     * @param stdClass|Customer $customer
     * @return stdClass|Customer
     */
    private function localizeCustomer(LanguageModel $lang, stdClass|Customer $customer): stdClass|Customer
    {
        $oldLangCode = Shop::Lang()->gibISO();
        if ($oldLangCode !== $lang->getCode()) {
            Shop::Lang()->setzeSprache($lang->getCode());
        }
        if (isset($customer->cAnrede)) {
            if ($customer->cAnrede === 'w') {
                $customer->cAnredeLocalized = Shop::Lang()->get('salutationW');
            } elseif ($customer->cAnrede === 'm') {
                $customer->cAnredeLocalized = Shop::Lang()->get('salutationM');
            } else {
                $customer->cAnredeLocalized = Shop::Lang()->get('salutationGeneral');
            }
        }
        /** @var stdClass|Customer $customer */
        $customer = GeneralObject::deepCopy($customer);
        if (isset($customer->cLand)) {
            if (isset($_SESSION['Kunde'])) {
                $_SESSION['Kunde']->cLand = $customer->cLand;
            }
            if (($country = Shop::Container()->getCountryService()->getCountry($customer->cLand)) !== null) {
                $customer->angezeigtesLand = $country->getName($lang->getId());
            }
        }
        Shop::Lang()->setzeSprache($oldLangCode);

        return $customer;
    }
}
