<?php

declare(strict_types=1);

namespace JTL\Smarty;

use JTL\Shop;

/**
 * Class JTLSmartyTemplateClass
 * @package JTL\Smarty
 */
class JTLSmartyTemplateClass extends \Smarty_Internal_Template
{
    public bool $noOutputFilter = true;

    /**
     * @inheritdoc
     * @param array<mixed> $data
     */
    public function _subTemplateRender(
        $template,
        $cache_id,
        $compile_id,
        $caching,
        $cache_lifetime,
        $data,
        $scope,
        $forceTplCache,
        $uid = null,
        $content_func = null
    ): void {
        if ($template === null) {
            return;
        }
        parent::_subTemplateRender(
            Shop::Smarty()->getResourceName($template) ?? $template,
            $cache_id,
            $compile_id,
            $caching,
            $cache_lifetime,
            $data,
            $scope,
            $forceTplCache,
            $uid,
            $content_func
        );
    }

    /**
     * @inheritdoc
     */
    public function render($no_output_filter = true, $display = null): ?string
    {
        if ($no_output_filter === false && $display !== 1) {
            $no_output_filter = $this->noOutputFilter;
        }

        return parent::render($no_output_filter, $display);
    }
}
