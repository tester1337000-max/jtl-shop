<?php

declare(strict_types=1);

namespace JTL\Smarty;

/**
 * Class JTLSmartyTemplateHints
 * @package JTL\Smarty
 */
class JTLSmartyTemplateHints extends JTLSmartyTemplateClass
{
    /**
     * @inheritdoc
     */
    public function fetch($template = null, $cache_id = null, $compile_id = null, $parent = null): string
    {
        $prefix  = '';
        $postfix = '';
        if (\SHOW_TEMPLATE_HINTS === 1 && $template !== null) {
            $prefix  = '<!-- start ' . $template . '-->';
            $postfix = '<!-- end ' . $template . '-->';
        } elseif (\SHOW_TEMPLATE_HINTS === 2) {
            $prefix = '<section class="tpl-debug">';
            $prefix .= '<span class="badge tpl-name">' . $template . '</span></section>';
        } elseif (\SHOW_TEMPLATE_HINTS === 3) {
            $tplID   = \uniqid('tpl', true);
            $prefix  = '<span class="tpl-debug-start" data-uid="'
                . $tplID . '" style="display:none;" data-tpl="' . $template . '">';
            $prefix  .= '<span class="tpl-name">' . $template . '</span>';
            $prefix  .= '</span>';
            $postfix = '<span class="tpl-debug-end" data-uid="' . $tplID . '" style="display:none"></span>';
        }

        return $prefix . parent::fetch($template, $cache_id, $compile_id, $parent) . $postfix;
    }

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
        $tplID   = null;
        $tplName = \str_contains($template, ':')
            ? \mb_substr($template, \mb_strpos($template, ':') + 1)
            : $template;
        if (\SHOW_TEMPLATE_HINTS === 1) {
            echo '<!-- start ' . $tplName . '-->';
        } elseif (\SHOW_TEMPLATE_HINTS === 2) {
            if ($tplName !== 'layout/header.tpl') {
                echo '<section class="tpl-debug">';
                echo '<span class="badge tpl-name">' . $tplName . '</span></section>';
            }
        } elseif (\SHOW_TEMPLATE_HINTS === 3) {
            $tplID = \uniqid('tpl', true);
            if ($tplName !== 'layout/header.tpl' && $tplName !== 'layout/header_custom.tpl') {
                echo '<span class="tpl-debug-start" data-uid="' .
                    $tplID . '" style="display:none;" data-tpl="' . $tplName . '">';
                echo '<span class="tpl-name">' . $tplName . '</span>';
                echo '</span>';
            }
        }
        parent::_subTemplateRender(
            $template,
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
        if (\SHOW_TEMPLATE_HINTS === 1) {
            echo '<!-- end ' . $tplName . '-->';
        } elseif (
            \SHOW_TEMPLATE_HINTS === 2
            && ($tplName === 'layout/header.tpl' || $tplName === 'layout/header_custom.tpl')
        ) {
            echo '<style>
                    .tpl-debug{border:1px dashed black;position:relative;min-height:25px;opacity:.75;z-index:9;}
                    .tpl-name{position:absolute;left:0;}
                </style>';
        } elseif (\SHOW_TEMPLATE_HINTS === 3) {
            if ($tplName !== 'layout/header.tpl' && $tplName !== 'layout/header_custom.tpl') {
                echo '<span class="tpl-debug-end" data-uid="' . $tplID . '" style="display:none"></span>';
            } else {
                echo
                '<style>
                        .tpl-debug-start{position:absolute;z-index:9;}
                        .tpl-name{position:relative;left:0;min-height:25px;opacity:.75;}
                        .bounding-box{border:1px dashed black;pointer-events:none;}
                    </style>';
                echo '<script type="text/javascript">
                function getBoundingBoxes() {
                    $(\'.bounding-box\').remove();
                    $(\'.tpl-debug-start\').each(function(){
                        var elem = $(this),
                            boxElem,
                            uid  = elem.attr(\'data-uid\'),
                            tpl  = elem.attr(\'data-tpl\'),
                            next = elem.nextUntil(\'.tpl-debug-end[data-uid=" + uid + "]\'),
                            box  = {
                                left: 999999,
                                right: 0,
                                top: 999999,
                                bottom: 0
                            };
                                
                        next.each(function(i, c) {
                            var bb, 
                                elem = $(c);
                            if (elem.css(\'display\') === \'block\' && elem.css(\'visibility\') === \'visible\') {
                                bb = c.getBoundingClientRect();
                                box = {
                                    left: Math.min(box.left, bb.left),
                                    right: Math.max(box.right, bb.right),
                                    top: Math.min(box.top, bb.top),
                                    bottom: Math.max(box.bottom, bb.bottom)
                                };
                            }
                        });
                        var bb = document.createElement(\'div\');
                        bb.className = \'bounding-box\';
                        boxElem = $(bb);
                        boxElem.html(\'<span class="tpl-name badge">\' + tpl + \'</span>\')
                            .css(\'position\', \'fixed\')
                            .css(\'top\', box.top + \'px\')
                            .css(\'left\', box.left + \'px\')
                            .css(\'width\', (box.right-box.left)  + \'px\')
                            .css(\'height\', (box.bottom-box.top) + \'px\');
                        $(\'body\').append(boxElem);
                    });
                }
                $(document).ready(function () {
                    getBoundingBoxes();                
                    $(window).scroll(getBoundingBoxes).resize(getBoundingBoxes);
                });
                </script>';
            }
        }
    }
}
