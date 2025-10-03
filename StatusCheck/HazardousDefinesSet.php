<?php

declare(strict_types=1);

namespace JTL\Backend\StatusCheck;

use JTL\Router\Route;
use JTL\Shop;

class HazardousDefinesSet extends AbstractStatusCheck
{
    protected const BOOL_ASSERTIONS = [
        'PROFILE_SHOP'               => false,
        'PLUGIN_DEV_MODE'            => false,
        'IO_LOG_CONSOLE'             => false,
        'PROFILE_QUERIES'            => false,
        'PROFILE_QUERIES_ECHO'       => false,
        'KEEP_SYNC_FILES'            => false,
        'EXS_LIVE'                   => true,
        'NICEDB_EXCEPTION_BACKTRACE' => false,
        'NICEDB_EXCEPTION_ECHO'      => false,
        'SMARTY_DEBUG_CONSOLE'       => false,
        'SMARTY_SHOW_LANGKEY'        => false,
        'SMARTY_FORCE_COMPILE'       => false,
        'SHOW_DEBUG_BAR'             => false,
        'SHOW_REST_API'              => false,
    ];

    /**
     * @var array{string, string, string}[]
     */
    protected array $problems = [];

    /**
     * @return array{string, string, string}[] list of problems with each having 3 entries: constant name,
     * default value and current value
     */
    protected function getBoolAssertionProblems(): array
    {
        $problems = [];

        foreach (self::BOOL_ASSERTIONS as $constant => $defValue) {
            $curValue = \constant($constant);
            if ($curValue !== $defValue) {
                $problems[] = [$constant, $defValue ? 'true' : 'false', $curValue ? 'true' : 'false'];
            }
        }

        return $problems;
    }

    protected function getHumanReadableErrorReporting(int $value): string
    {
        if ($value === \E_ALL) {
            return 'E_ALL';
        }

        return \implode(
            ' | ',
            \array_filter([
                $value & E_ERROR ? 'E_ERROR' : null,
                $value & E_WARNING ? 'E_WARNING' : null,
                $value & E_PARSE ? 'E_PARSE' : null,
                $value & E_NOTICE ? 'E_NOTICE' : null,
                $value & E_DEPRECATED ? 'E_DEPRECATED' : null,
            ])
        );
    }

    public function isOK(): bool
    {
        $this->problems = [];
        $config         = Shop::getSettingSection(\CONF_GLOBAL);

        if ($config['wartungsmodus_aktiviert'] === 'Y') {
            return true;
        }

        if (\ini_get('display_errors') !== '0') {
            if ((\SHOP_LOG_LEVEL ^ E_ERROR ^ E_PARSE) !== 0) {
                $this->problems[] = [
                    'SHOP_LOG_LEVEL',
                    'E_ERROR | E_PARSE',
                    $this->getHumanReadableErrorReporting(\SHOP_LOG_LEVEL)
                ];
            }
            if ((\SMARTY_LOG_LEVEL ^ E_ERROR ^ E_PARSE) !== 0) {
                $this->problems[] = [
                    'SMARTY_LOG_LEVEL',
                    'E_ERROR | E_PARSE',
                    $this->getHumanReadableErrorReporting(\SHOP_LOG_LEVEL)
                ];
            }
        }
        if (\SHOW_TEMPLATE_HINTS !== 0) {
            $this->problems[] = ['SHOW_TEMPLATE_HINTS', '0', (string)\SHOW_TEMPLATE_HINTS];
        }
        \array_push($this->problems, ...$this->getBoolAssertionProblems());

        return empty($this->problems);
    }

    public function getURL(): ?string
    {
        return $this->adminURL . Route::STATUS . '#hazardousDefines';
    }

    public function generateMessage(): void
    {
        $this->addNotification(\__('hazardousDefinesSetDesc'), 'hazardousDefinesSet', \__('hazardousDefinesSetTitle'));
    }

    /**
     * @return array{string, string, string}[]
     */
    public function getProblems(): array
    {
        return $this->problems;
    }
}
