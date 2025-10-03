<?php

declare(strict_types=1);

namespace JTL\Checkbox\CheckboxLanguage;

use JTL\Abstracts\AbstractService;
use JTL\DataObjects\AbstractDomainObject;
use JTL\Helpers\Typifier;

/**
 * Class CheckboxLanguageService
 * @package JTL\Checkbox\CheckboxLanguage
 */
class CheckboxLanguageService extends AbstractService
{
    public function __construct(protected CheckboxLanguageRepository $repository = new CheckboxLanguageRepository())
    {
    }

    protected function getRepository(): CheckboxLanguageRepository
    {
        return $this->repository;
    }

    private function getCheckboxLanguageDomainObject(array $language): CheckboxLanguageDomainObject
    {
        $language['ISO'] = $language['ISO'] ?? '';

        return new CheckboxLanguageDomainObject(
            Typifier::intify($language['kCheckBox'] ?? null),
            Typifier::intify($language['kCheckBoxSprache'] ?? null),
            Typifier::intify($language['kSprache'] ?? null),
            Typifier::stringify($language['ISO']),
            Typifier::stringify($language['cText'] ?? null),
            Typifier::stringify($language['cBeschreibung'] ?? null)
        );
    }

    /**
     * @param array<mixed> $filters
     * @return array<mixed>
     */
    public function getList(array $filters): array
    {
        $languageList = [];
        foreach ($this->getRepository()->getList($filters) as $checkboxLanguage) {
            $languageList[] = $this->getCheckboxLanguageDomainObject((array)$checkboxLanguage);
        }

        return $languageList;
    }

    /**
     * @return CheckboxLanguageDomainObject[]
     */
    public function getLanguagesByCheckboxID(int $checkBoxID): array
    {
        $objects = [];
        foreach ($this->getRepository()->getLanguagesByCheckboxID($checkBoxID) as $language) {
            $objects[$language['kSprache']] = $this->getCheckboxLanguageDomainObject($language);
        }

        return $objects;
    }

    public function update(AbstractDomainObject $updateDO): bool
    {
        if (!$updateDO instanceof CheckboxLanguageDomainObject) {
            return false;
        }
        // need checkboxLanguageId, not provided by post
        $languageList = $this->getList([
            'kCheckBox' => $updateDO->getCheckboxID(),
            'kSprache'  => $updateDO->getLanguageID()
        ]);
        $language     = $languageList[0] ?? null;
        if ($language === null) {
            return $this->insert($updateDO) > 0;
        }

        return $this->getRepository()->update($updateDO, $language->getCheckboxLanguageID());
    }
}
