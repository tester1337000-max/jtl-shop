<?php

declare(strict_types=1);

namespace JTL\RMA\Services;

use Exception;
use JTL\Abstracts\AbstractService;
use JTL\Helpers\Typifier;
use JTL\RMA\DomainObjects\RMAReasonDomainObject;
use JTL\RMA\DomainObjects\RMAReasonLangDomainObject;
use JTL\RMA\Repositories\RMAReasonLangRepository;
use JTL\RMA\Repositories\RMAReasonRepository;

/**
 * Class RMAReasonService
 * @package JTL\RMA
 */
class RMAReasonService extends AbstractService
{
    /**
     * @var RMAReasonLangDomainObject[]|null
     */
    public ?array $reasons = null;

    /**
     * @param RMAReasonRepository     $RMAReasonRepository
     * @param RMAReasonLangRepository $RMAReasonLangRepository
     */
    public function __construct(
        public RMAReasonRepository $RMAReasonRepository = new RMAReasonRepository(),
        public RMAReasonLangRepository $RMAReasonLangRepository = new RMAReasonLangRepository(),
    ) {
    }

    /**
     * @return RMAReasonRepository
     */
    protected function getRepository(): RMAReasonRepository
    {
        return $this->RMAReasonRepository;
    }

    /**
     * @throws Exception
     * @since 5.3.0
     */
    public function loadReasons(int $langID): self
    {
        foreach ($this->RMAReasonLangRepository->getList(['langID' => $langID]) as $reason) {
            $this->reasons[$reason->reasonID] = new RMAReasonLangDomainObject(
                id: Typifier::intify($reason->id ?? 0),
                reasonID: Typifier::intify($reason->reasonID ?? 0),
                langID: Typifier::intify($reason->langID ?? 0),
                title: Typifier::stringify($reason->title ?? '')
            );
        }

        return $this;
    }

    /**
     * @throws Exception
     * @since 5.3.0
     */
    public function getReason(int $id, int $languageID): RMAReasonLangDomainObject
    {
        if (!isset($this->reasons)) {
            $this->loadReasons($languageID);
        }

        return $this->reasons[$id] ?? new RMAReasonLangDomainObject();
    }

    /**
     * @param RMAReasonDomainObject       $reason
     * @param RMAReasonLangDomainObject[] $reasonsLocalized
     */
    public function saveReason(
        RMAReasonDomainObject $reason,
        array $reasonsLocalized
    ): void {
        if ($reason->id > 0) {
            $this->RMAReasonRepository->update($reason);
            foreach ($reasonsLocalized as $reasonLocalized) {
                if ($reasonLocalized->id > 0) {
                    $this->RMAReasonLangRepository->update($reasonLocalized);
                }
            }
        } else {
            $id = $this->RMAReasonRepository->insert($reason);
            if ($id > 0) {
                foreach ($reasonsLocalized as $reasonLocalized) {
                    $this->RMAReasonLangRepository->insert(
                        new RMAReasonLangDomainObject(...$reasonLocalized->copyWith(['reasonID' => $id])->toArray())
                    );
                }
            }
        }
    }
}
