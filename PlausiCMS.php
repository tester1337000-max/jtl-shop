<?php

declare(strict_types=1);

namespace JTL;

use JTL\Language\LanguageHelper;
use JTL\Link\Link;

/**
 * Class PlausiCMS
 * @package JTL
 */
class PlausiCMS extends Plausi
{
    /**
     * @inheritdoc
     */
    public function doPlausi(?string $type = null, bool $update = false): bool
    {
        if ($type === null || \count($this->xPostVar_arr) === 0 || \mb_strlen($type) === 0) {
            return false;
        }
        switch ($type) {
            case 'lnk':
                // unique special page
                if (
                    isset($this->xPostVar_arr['nSpezialseite'], $this->xPostVar_arr['nLinkart'])
                    && (int)$this->xPostVar_arr['nLinkart'] === 3
                ) {
                    $link = new Link(Shop::Container()->getDB());
                    $link->setCustomerGroups($this->xPostVar_arr['cKundengruppen']);
                    $link->setLinkType((int)$this->xPostVar_arr['nSpezialseite']);
                    $link->setID((int)$this->xPostVar_arr['kLink']);

                    if ($isDuplicateSepcialLink = $link->hasDuplicateSpecialLink()) {
                        $this->xPlausiVar_arr['nSpezialseite'] = $isDuplicateSepcialLink;
                    }
                }
                // cName
                if (!isset($this->xPostVar_arr['cName']) || \mb_strlen($this->xPostVar_arr['cName']) === 0) {
                    $this->xPlausiVar_arr['cName'] = 1;
                }
                // cKundengruppen
                if (
                    !\is_array($this->xPostVar_arr['cKundengruppen'])
                    || \count($this->xPostVar_arr['cKundengruppen']) === 0
                ) {
                    $this->xPlausiVar_arr['cKundengruppen'] = 1;
                }
                // nLinkart
                if (!isset($this->xPostVar_arr['nLinkart']) || (int)$this->xPostVar_arr['nLinkart'] === 0) {
                    $this->xPlausiVar_arr['nLinkart'] = 1;
                } elseif (
                    (int)$this->xPostVar_arr['nLinkart'] === 3
                    && (!isset($this->xPostVar_arr['nSpezialseite']) || (int)$this->xPostVar_arr['nSpezialseite'] <= 0)
                ) {
                    $this->xPlausiVar_arr['nLinkart'] = 3;
                }
                if (isset($this->xPostVar_arr['nLinkart']) && (int)$this->xPostVar_arr['nLinkart'] === 2) {
                    // external link
                    foreach (LanguageHelper::getAllLanguages(0, true, true) as $language) {
                        $code = $language->getIso();
                        if (!empty($this->xPostVar_arr['cSeo_' . $code])) {
                            $url = $this->xPostVar_arr['cSeo_' . $code];
                            if (\parse_url($url, \PHP_URL_SCHEME) === null) {
                                $this->xPlausiVar_arr['scheme'] = 1;
                                $this->xPlausiVar_arr['lang']   = $code;
                            }
                        } else {
                            $this->xPlausiVar_arr['cSeo'] = 1;
                            $this->xPlausiVar_arr['lang'] = $code;
                        }
                    }
                }

                return true;

            case 'grp':
                // cName
                if (!isset($this->xPostVar_arr['cName']) || \mb_strlen($this->xPostVar_arr['cName']) === 0) {
                    $this->xPlausiVar_arr['cName'] = 1;
                }

                // cTempaltename
                if (
                    !isset($this->xPostVar_arr['cTemplatename'])
                    || \mb_strlen($this->xPostVar_arr['cTemplatename']) === 0
                ) {
                    $this->xPlausiVar_arr['cTemplatename'] = 1;
                }

                return true;
        }

        return false;
    }
}
