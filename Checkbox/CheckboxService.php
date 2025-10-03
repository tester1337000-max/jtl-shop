<?php

declare(strict_types=1);

namespace JTL\Checkbox;

use JTL\Abstracts\AbstractService;
use JTL\CheckBox;
use JTL\Checkbox\CheckboxFunction\CheckboxFunctionDomainObject;
use JTL\Checkbox\CheckboxFunction\CheckboxFunctionService;
use JTL\Checkbox\CheckboxLanguage\CheckboxLanguageDomainObject;
use JTL\Checkbox\CheckboxLanguage\CheckboxLanguageService;
use JTL\Helpers\Typifier;
use JTL\Shop;

/**
 * Class CheckboxService
 * @package JTL\Checkbox
 */
class CheckboxService extends AbstractService
{
    public function __construct(
        protected CheckboxRepository $repository = new CheckboxRepository(),
        protected CheckboxLanguageService $languageService = new CheckboxLanguageService(),
        protected CheckboxFunctionService $functionService = new CheckboxFunctionService()
    ) {
    }

    protected function getRepository(): CheckboxRepository
    {
        return $this->repository;
    }

    /**
     * @param array<mixed>                   $data
     * @param CheckboxLanguageDomainObject[] $languages
     * @return CheckboxDomainObject
     */
    public function getCheckBoxDomainObject(array $data, array $languages): CheckboxDomainObject
    {
        $data = $this->getPreparedLanguages($languages, $data);
        if (!isset($data['nLink']) || $data['nLink'] === false || (int)$data['nLink'] === -1) {
            $data['kLink'] = 0;
        }
        $customerGroups = $this->getCustomerGroups($data);

        return new CheckboxDomainObject(
            Typifier::intify($data['kCheckBox'] ?? 0),
            Typifier::intify($data['kLink'] ?? 0),
            Typifier::intify($data['kCheckBoxFunktion'] ?? 0),
            Typifier::stringify($data['cName'] ?? ''),
            $customerGroups,
            $this->getDisplayAt($data['cAnzeigeOrt'] ?? ''),
            Typifier::boolify($data['nAktiv'] ?? false),
            Typifier::boolify($data['nPflicht'] ?? false),
            Typifier::boolify($data['nLogging'] ?? false),
            Typifier::intify($data['nSort'] ?? 0),
            Typifier::stringify($data['dErstellt'] ?? 'now()'),
            Typifier::boolify($data['nInternal'] ?? false),
            Typifier::stringify(($data['dErstellt_DE'] ?? '')),
            Typifier::arrify($data['languages']),
            Typifier::boolify($data['cLink'] ?? false),
            Typifier::arrify($data['oCheckBoxSprache_arr'] ?? []),
            Typifier::arrify($data['kKundengruppe_arr'] ?? []),
            Typifier::arrify($data['kAnzeigeOrt_arr'] ?? []),
            $data['function'] ?? null
        );
    }

    /**
     * @param CheckboxLanguageDomainObject[] $languages
     * @param array<mixed>                   $post
     * @return array<string, mixed>
     */
    private function prepareTranslationsForDO(array $languages, array $post): array
    {
        $collected = [];
        foreach ($languages as $language) {
            $code             = $language->getIso();
            $textCode         = 'cText_' . $code;
            $descrCode        = 'cBeschreibung_' . $code;
            $texts[$code]     = isset($post[$textCode])
                ? \str_replace('"', '&quot;', $post[$textCode])
                : '';
            $descr[$code]     = isset($post[$descrCode])
                ? \str_replace('"', '&quot;', $post[$descrCode])
                : '';
            $collected[$code] = [
                'text'  => $texts[$code],
                'descr' => $descr[$code]
            ];
        }

        return $collected;
    }

    public function get(int $id): ?CheckboxDomainObject
    {
        $data = $this->getRepository()->get($id);
        if ($data === null) {
            return null;
        }
        if ((int)$data->kLink > 0) {
            $data->nLink = true;
        }
        $function = $this->validateFunction((int)$data->kCheckBoxFunktion);

        return $this->getCheckBoxDomainObject(
            [...\json_decode(\json_encode($data), true), 'function' => $function],
            []
        );
    }

    /**
     * @param int[] $checkboxIDs
     * @return bool
     */
    public function activate(array $checkboxIDs): bool
    {
        return $this->getRepository()->activate($checkboxIDs);
    }

    /**
     * @param int[] $checkboxIDs
     * @return bool
     */
    public function deactivate(array $checkboxIDs): bool
    {
        return $this->getRepository()->deactivate($checkboxIDs);
    }

    /**
     * @param int[] $checkboxIDs
     * @return bool
     */
    public function deleteByIDs(array $checkboxIDs): bool
    {
        return $this->repository->deleteByIDs($checkboxIDs);
    }

    /**
     * @param CheckboxValidationDomainObject $data
     * @param array<mixed>                   $post
     * @return array<mixed>
     */
    public function validateCheckBox(CheckboxValidationDomainObject $data, array $post): array
    {
        $checks = [];
        foreach ($this->getCheckBoxValidationData($data) as $checkBox) {
            if (
                $checkBox->nPflicht === 1
                && !isset($post[$checkBox->cID])
            ) {
                if (
                    $checkBox->cName === CheckBox::CHECKBOX_DOWNLOAD_ORDER_COMPLETE
                    && $data->getHasDownloads() === false
                ) {
                    continue;
                }
                $checks[$checkBox->cID] = 1;
            }
        }

        return $checks;
    }

    /**
     * @param CheckboxValidationDomainObject $data
     * @return CheckBox[]
     */
    public function getCheckBoxValidationData(CheckboxValidationDomainObject $data): array
    {
        $checkboxes = $this->getRepository()->getCheckBoxValidationData($data);
        \executeHook(\HOOK_CHECKBOX_CLASS_GETCHECKBOXFRONTEND, [
            'oCheckBox_arr' => &$checkboxes,
            'nAnzeigeOrt'   => $data->getLocation(),
            'kKundengruppe' => $data->getCustomerGroupId(),
            'bAktiv'        => $data->getActive(),
            'bSprache'      => $data->getLanguage(),
            'bSpecial'      => $data->getSpecial(),
            'bLogging'      => $data->getLogging(),
        ]);

        return $checkboxes;
    }

    /**
     * @param string[] $value
     */
    private function implodeWithAdditionalSeparators(
        array $value,
        string $separator = ';',
        bool $addLeadingAndTrailingSeparator = false
    ): string {
        if ($addLeadingAndTrailingSeparator === true) {
            $result = $separator . \implode($separator, $value) . $separator;
        } else {
            $result = \implode($separator, $value);
        }

        return $result;
    }

    protected function validateFunction(int $kCheckBoxFunktionID): ?CheckboxFunctionDomainObject
    {
        $functionData = $this->functionService->get($kCheckBoxFunktionID);

        return $functionData !== null ? $this->prepareCheckboxFunctionDomainObject($functionData) : null;
    }

    public function prepareCheckboxFunctionDomainObject(object $functionData): CheckboxFunctionDomainObject
    {
        if (Shop::isAdmin()) {
            Shop::Container()->getGetText()->loadAdminLocale('pages/checkbox');
            $name = \__($functionData->cName);
        } else {
            $name = $functionData->cName;
        }
        // Falls kCheckBoxFunktion gesetzt war aber diese Funktion nicht mehr existiert (deinstallation vom Plugin)
        // wird kCheckBoxFunktion auf 0 gesetzt
        return new CheckboxFunctionDomainObject(
            Typifier::intifyOrNull($functionData->kPlugin),
            Typifier::intifyOrNull($functionData->kCheckBoxFunktion),
            Typifier::stringify($name),
            Typifier::stringify($functionData->cID)
        );
    }

    /**
     * @param array<string, string[]> $data
     */
    private function getCustomerGroups(array $data): string
    {
        $customerGroupsTmp = $data['cKundengruppe'] ?? ($data['kKundengruppe'] ?? '');
        if (\is_array($customerGroupsTmp) === true) {
            $customerGroups = $this->implodeWithAdditionalSeparators($customerGroupsTmp, ';', true);
        } else {
            $customerGroups = $customerGroupsTmp;
        }

        return $customerGroups;
    }

    /**
     * @param string|string[] $cAnzeigeOrt
     */
    public function getDisplayAt(string|array $cAnzeigeOrt): string
    {
        return \is_array($cAnzeigeOrt)
            ? $this->implodeWithAdditionalSeparators($cAnzeigeOrt, ';', true)
            : $cAnzeigeOrt;
    }

    /**
     * @param array<mixed> $languages
     * @param array<mixed> $data
     * @return array<mixed>
     */
    public function getPreparedLanguages(array $languages, array $data): array
    {
        if ($languages === [] && isset($data['kCheckBox'])) {
            $languages = $this->languageService->getLanguagesByCheckboxID((int)$data['kCheckBox']);
        }
        $data['languages'] = $this->prepareTranslationsForDO($languages, $data);

        return $data;
    }

    public function updateFunctionID(int $checkboxID, int $functionId): void
    {
        $this->repository->updateFunctionID($checkboxID, $functionId);
    }
}
