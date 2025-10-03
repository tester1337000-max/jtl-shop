<?php

declare(strict_types=1);

namespace JTL;

/**
 * Class PlausiKundenfeld
 * @package JTL
 */
class PlausiKundenfeld extends Plausi
{
    /**
     * @inheritdoc
     */
    public function doPlausi(?string $type = null, bool $update = false): bool
    {
        if (\count($this->xPostVar_arr) === 0) {
            return false;
        }
        if (!isset($this->xPostVar_arr['cName']) || \mb_strlen($this->xPostVar_arr['cName']) === 0) {
            $this->xPlausiVar_arr['cName'] = 1;
        }
        if (!isset($this->xPostVar_arr['cWawi']) || \mb_strlen($this->xPostVar_arr['cWawi']) === 0) {
            $this->xPlausiVar_arr['cWawi'] = 1;
        }
        if (!isset($this->xPostVar_arr['cTyp']) || \mb_strlen($this->xPostVar_arr['cTyp']) === 0) {
            $this->xPlausiVar_arr['cTyp'] = 1;
        }
        if (!isset($this->xPostVar_arr['nSort'])) {
            $this->xPlausiVar_arr['nSort'] = 1;
        }
        if (!isset($this->xPostVar_arr['nPflicht'])) {
            $this->xPlausiVar_arr['nPflicht'] = 1;
        }
        if (!isset($this->xPostVar_arr['nEdit'])) {
            $this->xPlausiVar_arr['nEdit'] = 1;
        }
        if ($type === 'auswahl') {
            if (\is_array($this->xPostVar_arr['cfValues'])) {
                foreach ($this->xPostVar_arr['cfValues'] as $szFieldValue) {
                    // empty value are not allowed
                    if (empty($szFieldValue['cWert'])) {
                        $this->xPlausiVar_arr['cWert'] = 1;
                    }
                }
            } else {
                // empty arrays should not be savable
                $this->xPlausiVar_arr['cWert'] = 1;
            }
        } elseif (!$update) {
            $field = Shop::Container()->getDB()->select(
                'tkundenfeld',
                'kSprache',
                (int)$_SESSION['kSprache'],
                'cName',
                $this->xPostVar_arr['cName']
            );
            if ($field !== null && $field->kKundenfeld > 0) {
                $this->xPlausiVar_arr['cName'] = 2;
            }
        }

        return true;
    }
}
