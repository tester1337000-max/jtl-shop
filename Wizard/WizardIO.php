<?php

declare(strict_types=1);

namespace JTL\Backend\Wizard;

use JTL\Backend\Wizard\Steps\Error;
use JTL\Cache\JTLCacheInterface;
use JTL\DB\DbInterface;
use JTL\L10n\GetText;
use JTL\Services\JTL\AlertServiceInterface;
use JTL\Shop;

/**
 * Class WizardIO
 * @package JTL\Backend\Wizard
 */
class WizardIO
{
    private Controller $wizardController;

    public function __construct(
        protected DbInterface $db,
        protected JTLCacheInterface $cache,
        protected AlertServiceInterface $alertService,
        protected GetText $getText
    ) {
        $this->init();
    }

    /**
     * @param array<mixed> $post
     * @return Error[]
     */
    public function validateStep(array $post): array
    {
        return $this->wizardController->validateStep($post);
    }

    /**
     * @param array<mixed> $post
     * @return Error[]
     */
    public function answerQuestions(array $post): array
    {
        return $this->wizardController->answerQuestions($post);
    }

    private function init(): void
    {
        $wizardFactory          = new DefaultFactory(
            $this->db,
            $this->getText,
            $this->alertService,
            Shop::Container()->getAdminAccount()
        );
        $this->wizardController = new Controller($wizardFactory, $this->db, $this->cache, $this->getText);
    }
}
