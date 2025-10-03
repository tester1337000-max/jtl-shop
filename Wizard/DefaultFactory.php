<?php

declare(strict_types=1);

namespace JTL\Backend\Wizard;

use Illuminate\Support\Collection;
use JTL\Backend\AdminAccount;
use JTL\Backend\Wizard\Steps\EmailSettings;
use JTL\Backend\Wizard\Steps\GeneralSettings;
use JTL\Backend\Wizard\Steps\LegalPlugins;
use JTL\Backend\Wizard\Steps\PaymentPlugins;
use JTL\Backend\Wizard\Steps\StepInterface;
use JTL\DB\DbInterface;
use JTL\L10n\GetText;
use JTL\Services\JTL\AlertServiceInterface;

/**
 * Class DefaultFactory
 * @package JTL\Backend\Wizard
 */
final class DefaultFactory
{
    /**
     * @var Collection<int, StepInterface>
     */
    private Collection $steps;

    public function __construct(
        DbInterface $db,
        GetText $getText,
        AlertServiceInterface $alertService,
        AdminAccount $adminAccount
    ) {
        $getText->loadConfigLocales();
        $getText->loadAdminLocale('pages/wizard');

        $this->steps = new Collection();
        $this->steps->push(new GeneralSettings($db, $alertService));
        $this->steps->push(new EmailSettings($db, $alertService, $adminAccount));
        $this->steps->push(new LegalPlugins($db, $alertService));
        $this->steps->push(new PaymentPlugins($db, $alertService));
    }

    /**
     * @return Collection<int, StepInterface>
     */
    public function getSteps(): Collection
    {
        return $this->steps;
    }

    /**
     * @param Collection<int, StepInterface> $steps
     */
    public function setSteps(Collection $steps): void
    {
        $this->steps = $steps;
    }
}
