<?php

declare(strict_types=1);

namespace JTL\Country;

use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\Helpers\Form;
use JTL\Helpers\Request;
use JTL\Helpers\Text;
use JTL\L10n\GetText;
use JTL\Services\JTL\AlertServiceInterface;
use JTL\Services\JTL\CountryService;
use JTL\Services\JTL\CountryServiceInterface;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;

/**
 * Class Manager
 * @package JTL\Country
 */
class Manager
{
    public function __construct(
        protected DbInterface $db,
        protected JTLSmarty $smarty,
        protected CountryServiceInterface $countryService,
        protected JTLCacheInterface $cache,
        protected AlertServiceInterface $alertService,
        protected GetText $getText
    ) {
        $this->getText->loadAdminLocale('pages/countrymanager');
    }

    public function finalize(string $step): void
    {
        switch ($step) {
            case 'add':
                $this->smarty->assign('countryPost', Text::filterXSS($_POST));
                break;
            case 'update':
                /** @var string $iso */
                $iso     = Request::verifyGPDataString('cISO');
                $country = $this->countryService->getCountry($iso);
                if ($country?->isShippingAvailable() === true) {
                    $this->alertService->addWarning(\__('warningShippingAvailable'), 'warningShippingAvailable');
                }
                $this->smarty->assign('countryPost', Text::filterXSS($_POST))
                    ->assign('country', $country);
                break;
            default:
                break;
        }

        $this->smarty->assign('step', $step)
            ->assign('countries', $this->countryService->getCountrylist())
            ->assign('continents', $this->countryService->getContinents())
            ->assign(
                $step === 'overview' ? 'scrollPosition' : 'scrollPos',
                Request::pInt('scrollPosition') ?: Request::gInt('scrollPosition')
            )
            ->assign(
                'activeSort',
                Request::pInt('activeSort') ?: Request::gInt('activeSort')
            )
            ->assign(
                'activeSortOrder',
                Request::pString('activeSortOrder') ?: Request::gString('activeSortOrder')
            );
    }

    public function getAction(): string
    {
        $action = 'overview';
        if (Request::verifyGPDataString('action') !== '' && Form::validateToken()) {
            $action = Request::verifyGPDataString('action');
        }
        switch ($action) {
            case 'add':
                $action = $this->addCountry(Text::filterXSS($_POST));
                $this->alertService->addWarning(\__('warningCreateCountryInWawi'), 'warningCreateCountryInWawi');
                break;
            case 'delete':
                $action = $this->deleteCountry();
                break;
            case 'update':
                $action = $this->updateCountry(Text::filterXSS($_POST));
                break;
            default:
                break;
        }

        return $action;
    }

    /**
     * @param array<string, string> $postData
     */
    private function addCountry(array $postData): string
    {
        $iso = \mb_strtoupper($postData['cISO'] ?? '');
        if ($this->countryService->getCountry($iso) !== null) {
            $this->alertService->addDanger(\sprintf(\__('errorCountryIsoExists'), $iso), 'errorCountryIsoExists');
            return 'add';
        }
        if ($iso === '' || Request::pInt('save') !== 1 || !$this->checkIso($iso)) {
            return 'add';
        }
        $country                          = new \stdClass();
        $country->cISO                    = $iso;
        $country->cDeutsch                = $postData['cDeutsch'];
        $country->cEnglisch               = $postData['cEnglisch'];
        $country->nEU                     = $postData['nEU'];
        $country->cKontinent              = $postData['cKontinent'];
        $country->bPermitRegistration     = $postData['bPermitRegistration'];
        $country->bRequireStateDefinition = $postData['bRequireStateDefinition'];

        $this->db->insert('tland', $country);
        $this->cache->flush(CountryService::CACHE_ID);
        $this->alertService->addSuccess(
            \sprintf(\__('successCountryAdd'), $iso),
            'successCountryAdd',
            ['saveInSession' => true]
        );
        $this->refreshPage();
    }

    private function deleteCountry(): string
    {
        $val = Request::verifyGPDataString('cISO');
        $iso = Text::filterXSS($val);
        if ($this->db->delete('tland', 'cISO', $iso) > 0) {
            $this->cache->flush(CountryService::CACHE_ID);
            $this->alertService->addSuccess(
                \sprintf(\__('successCountryDelete'), $iso),
                'successCountryDelete',
                ['saveInSession' => true]
            );

            $this->refreshPage();
        }

        return 'delete';
    }

    /**
     * @param array<string, string> $postData
     */
    private function updateCountry(array $postData): string
    {
        if (Request::pInt('save') !== 1 || !$this->checkIso($postData['cISO'])) {
            return 'update';
        }
        $country                          = new \stdClass();
        $country->cDeutsch                = $postData['cDeutsch'];
        $country->cEnglisch               = $postData['cEnglisch'];
        $country->nEU                     = $postData['nEU'];
        $country->cKontinent              = $postData['cKontinent'];
        $country->bPermitRegistration     = $postData['bPermitRegistration'];
        $country->bRequireStateDefinition = $postData['bRequireStateDefinition'];

        $this->db->update(
            'tland',
            'cISO',
            $postData['cISO'],
            $country
        );
        $this->cache->flush(CountryService::CACHE_ID);
        $this->alertService->addSuccess(
            \sprintf(\__('successCountryUpdate'), $postData['cISO']),
            'successCountryUpdate',
            ['saveInSession' => true]
        );
        $this->refreshPage();
    }

    private function checkIso(string $iso): bool
    {
        $countryName = \locale_get_display_region('sl-Latn-' . $iso . '-nedis', 'en');
        if ($countryName === false || $countryName === $iso) {
            $this->alertService->addError(\sprintf(\__('errorIsoDoesNotExist'), $iso), 'errorIsoDoesNotExist');

            return false;
        }

        return true;
    }

    private function refreshPage(): never
    {
        \header('Refresh:0');
        exit;
    }

    /**
     * @param string[] $inactiveCountries
     * @param bool     $showAlerts
     */
    public function updateRegistrationCountries(array $inactiveCountries = [], bool $showAlerts = true): void
    {
        $deactivated      = [];
        $currentCountries = $this->db->getCollection('SELECT cISO FROM tland WHERE bPermitRegistration=1')
            ->pluck('cISO')->toArray();
        $this->db->query(
            "UPDATE tland
                INNER JOIN tversandart
                  ON tversandart.cLaender RLIKE CONCAT(tland.cISO, ' ')
                SET tland.bPermitRegistration = 1
                WHERE tland.bPermitRegistration = 0"
        );
        /** @var string[] $newCountries */
        $newCountries = $this->db->getCollection('SELECT cISO FROM tland WHERE bPermitRegistration=1')
            ->pluck('cISO')->toArray();
        $activated    = \array_diff($newCountries, $currentCountries);
        if (\count($inactiveCountries) > 0) {
            $possibleShippingCountries = $this->db->getCollection(
                "SELECT DISTINCT(tland.cISO)
                  FROM tland
                  INNER JOIN tversandart
                    ON tversandart.cLaender RLIKE CONCAT(tland.cISO, ' ')"
            )->pluck('cISO')->toArray();
            $deactivated               = \array_diff($inactiveCountries, $possibleShippingCountries);
            $this->db->query(
                "UPDATE tland
                    SET bPermitRegistration = 0
                    WHERE cISO IN ('" . \implode("', '", Text::filterXSS($deactivated)) . "')"
            );
        }
        if ($showAlerts === false) {
            return;
        }
        if (\count($activated) > 0) {
            $activatedCountries = $this->countryService->getFilteredCountryList($activated)->map(
                static fn(Country $country): string => $country->getName()
            )->toArray();
            $this->alertService->addInfo(
                \sprintf(
                    \__('infoRegistrationCountriesActivated'),
                    \implode(', ', $activatedCountries),
                    Shop::getAdminURL()
                ),
                'infoRegistrationCountriesActivated'
            );
        }
        if (\count($deactivated) > 0) {
            $deactivatedCountries = $this->countryService->getFilteredCountryList($deactivated)->map(
                static fn(Country $country): string => $country->getName()
            )->toArray();
            $this->alertService->addWarning(
                \sprintf(
                    \__('warningRegistrationCountriesDeactivated'),
                    \implode(', ', $deactivatedCountries),
                    Shop::getAdminURL()
                ),
                'warningRegistrationCountriesDeactivated'
            );
        }
    }
}
