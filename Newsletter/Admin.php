<?php

declare(strict_types=1);

namespace JTL\Newsletter;

use DateTime;
use JTL\Backend\Revision;
use JTL\Campaign;
use JTL\Customer\CustomerGroup;
use JTL\DB\DbInterface;
use JTL\DB\SqlObject;
use JTL\Exceptions\EmptyResultSetException;
use JTL\Helpers\Request;
use JTL\Helpers\Text;
use JTL\Media\Image;
use JTL\Optin\Optin;
use JTL\Optin\OptinNewsletter;
use JTL\Services\JTL\AlertServiceInterface;
use JTL\Shop;
use JTL\Smarty\JTLSmarty;
use stdClass;

/**
 * Class Admin
 * @package JTL\Newsletter
 */
final class Admin
{
    private int $currentId = 0;

    public function __construct(private readonly DbInterface $db, private readonly AlertServiceInterface $alertService)
    {
    }

    public function getDateData(string $input): stdClass
    {
        $res = new stdClass();

        if (\mb_strlen($input) > 0) {
            [$date, $time]        = \explode(' ', $input);
            [$year, $month, $day] = \explode('-', $date);
            [$hour, $minute]      = \explode(':', $time);

            $res->dZeit     = $day . '.' . $month . '.' . $year . ' ' . $hour . ':' . $minute;
            $res->cZeit_arr = [$day, $month, $year, $hour, $minute];
        }

        return $res;
    }

    /**
     * @param string $name
     * @param array<mixed> $customerGroups
     * @param string $subject
     * @param string $type
     * @return array<string, int>
     */
    public function checkDefaultTemplate(string $name, array $customerGroups, string $subject, string $type): array
    {
        $checks = [];
        if (empty($name)) {
            $checks['cName'] = 1;
        }
        if (\count($customerGroups) === 0) {
            $checks['kKundengruppe_arr'] = 1;
        }
        if (empty($subject)) {
            $checks['cBetreff'] = 1;
        }
        if (empty($type)) {
            $checks['cArt'] = 1;
        }

        return $checks;
    }

    public function mapFileType(string $type): string
    {
        return match ($type) {
            'image/gif' => '.gif',
            'image/png' => '.png',
            'image/bmp' => '.bmp',
            default     => '.jpg',
        };
    }

    /**
     * @param string      $text
     * @param array|mixed $stdVars
     * @param bool        $noHTML
     * @return string
     */
    public function mapDefaultTemplate(string $text, mixed $stdVars, bool $noHTML = false): string
    {
        if (!\is_array($stdVars) || \count($stdVars) === 0) {
            return $text;
        }
        /** @var stdClass $stdVar */
        foreach ($stdVars as $stdVar) {
            if ($stdVar->cTyp === 'TEXT') {
                $text = \str_replace('$#' . $stdVar->cName . '#$', $stdVar->cInhalt, $text);
                if ($noHTML) {
                    $text = \strip_tags($this->br2nl($text));
                }
            } elseif ($stdVar->cTyp === 'BILD') {
                // Bildervorlagen auf die URL SHOP umbiegen
                $stdVar->cInhalt = \str_replace(
                    \NEWSLETTER_STD_VORLAGE_URLSHOP,
                    Shop::getURL() . '/',
                    $stdVar->cInhalt
                );
                if ($noHTML) {
                    $text = \strip_tags(
                        $this->br2nl(\str_replace('$#' . $stdVar->cName . '#$', $stdVar->cInhalt, $text))
                    );
                } else {
                    $altTag = '';
                    if (isset($stdVar->cAltTag) && \mb_strlen($stdVar->cAltTag) > 0) {
                        $altTag = $stdVar->cAltTag;
                    }

                    if (isset($stdVar->cLinkURL) && \mb_strlen($stdVar->cLinkURL) > 0) {
                        $text = \str_replace(
                            '$#' . $stdVar->cName . '#$',
                            '<a href="' .
                            $stdVar->cLinkURL .
                            '"><img src="' .
                            $stdVar->cInhalt . '" alt="' . $altTag . '" title="' .
                            $altTag .
                            '" /></a>',
                            $text
                        );
                    } else {
                        $text = \str_replace(
                            '$#' . $stdVar->cName . '#$',
                            '<img src="' .
                            $stdVar->cInhalt .
                            '" alt="' .
                            $altTag . '" title="' . $altTag . '" />',
                            $text
                        );
                    }
                }
            }
        }

        return $text;
    }

    public function br2nl(string $text): string
    {
        return \str_replace(['<br>', '<br />', '<br/>'], "\n", $text);
    }

    /**
     * Baut eine Vorlage zusammen
     * Falls kNewsletterVorlage angegeben wurde und kNewsletterVorlageStd = 0 ist
     * wurde eine Vorlage editiert, die von einer Std Vorlage stammt.
     */
    public function getDefaultTemplate(int $defaultTemplateID, int $templateID = 0): ?stdClass
    {
        if ($defaultTemplateID === 0 && $templateID === 0) {
            return null;
        }
        $tpl = new stdClass();
        if ($templateID > 0) {
            $tpl = $this->db->select(
                'tnewslettervorlage',
                'kNewsletterVorlage',
                $templateID
            );
            if (isset($tpl->kNewslettervorlageStd) && $tpl->kNewslettervorlageStd > 0) {
                $defaultTemplateID = $tpl->kNewslettervorlageStd;
            }
        }

        $defaultTpl = $this->db->select(
            'tnewslettervorlagestd',
            'kNewslettervorlageStd',
            $defaultTemplateID
        );
        if ($defaultTpl !== null && $defaultTpl->kNewslettervorlageStd > 0) {
            if (isset($tpl->kNewslettervorlageStd) && $tpl->kNewslettervorlageStd > 0) {
                $defaultTpl->kNewsletterVorlage = $tpl->kNewsletterVorlage;
                $defaultTpl->kKampagne          = $tpl->kKampagne;
                $defaultTpl->cName              = $tpl->cName;
                $defaultTpl->cBetreff           = $tpl->cBetreff;
                $defaultTpl->cArt               = $tpl->cArt;
                $defaultTpl->cArtikel           = \mb_substr($tpl->cArtikel, 1, -1);
                $defaultTpl->cHersteller        = \mb_substr($tpl->cHersteller, 1, -1);
                $defaultTpl->cKategorie         = \mb_substr($tpl->cKategorie, 1, -1);
                $defaultTpl->cKundengruppe      = \mb_substr($tpl->cKundengruppe, 1, -1);
                $defaultTpl->dStartZeit         = $tpl->dStartZeit;
            }

            $defaultTpl->oNewslettervorlageStdVar_arr = $this->db->selectAll(
                'tnewslettervorlagestdvar',
                'kNewslettervorlageStd',
                $defaultTemplateID
            );

            foreach ($defaultTpl->oNewslettervorlageStdVar_arr as $j => $nlTplStdVar) {
                $nlTplContent = new stdClass();
                if (isset($nlTplStdVar->kNewslettervorlageStdVar) && $nlTplStdVar->kNewslettervorlageStdVar > 0) {
                    $and = ' AND kNewslettervorlage IS NULL';
                    if ($templateID > 0) {
                        $and = ' AND kNewslettervorlage = ' . $templateID;
                    }

                    $nlTplContent = $this->db->getSingleObject(
                        'SELECT *
                            FROM tnewslettervorlagestdvarinhalt
                            WHERE kNewslettervorlageStdVar = :tid'
                        . $and,
                        ['tid' => (int)$nlTplStdVar->kNewslettervorlageStdVar]
                    );
                }
                if (isset($nlTplContent->cInhalt) && \mb_strlen($nlTplContent->cInhalt) > 0) {
                    $defaultTpl->oNewslettervorlageStdVar_arr[$j]->cInhalt = \str_replace(
                        \NEWSLETTER_STD_VORLAGE_URLSHOP,
                        Shop::getURL() . '/',
                        $nlTplContent->cInhalt
                    );
                    if (isset($nlTplContent->cLinkURL) && \mb_strlen($nlTplContent->cLinkURL) > 0) {
                        $defaultTpl->oNewslettervorlageStdVar_arr[$j]->cLinkURL = $nlTplContent->cLinkURL;
                    }
                    if (isset($nlTplContent->cAltTag) && \mb_strlen($nlTplContent->cAltTag) > 0) {
                        $defaultTpl->oNewslettervorlageStdVar_arr[$j]->cAltTag = $nlTplContent->cAltTag;
                    }
                } else {
                    $defaultTpl->oNewslettervorlageStdVar_arr[$j]->cInhalt = '';
                }
            }
        }

        return $defaultTpl;
    }

    /**
     * @param stdClass|null $defaultTpl
     * @param int           $defaultTplID
     * @param array<mixed>  $post - pre-filtered POST
     * @param int           $templateID
     * @return array<string, int>
     * @throws \Exception
     */
    public function saveDefaultTemplate(?stdClass $defaultTpl, int $defaultTplID, array $post, int $templateID): array
    {
        $checks = [];
        if ($defaultTplID <= 0 || $defaultTpl === null) {
            return $checks;
        }
        if (!isset($post['kKundengruppe'])) {
            $post['kKundengruppe'] = null;
        }
        $checks = $this->checkDefaultTemplate(
            $post['cName'] ?? '',
            $post['kKundengruppe'] ?? [],
            $post['cBetreff'] ?? '',
            $post['cArt'] ?? ''
        );

        if (\count($checks) !== 0) {
            return $checks;
        }
        $day         = $post['dTag'];
        $month       = $post['dMonat'];
        $year        = $post['dJahr'];
        $hour        = $post['dStunde'];
        $minute      = $post['dMinute'];
        $dbFormatted = $year . '-' . $month . '-' . $day . ' ' . $hour . ':' . $minute . ':00';
        $timeData    = $this->getDateData($dbFormatted);

        $productIDs       = ';' . $post['cArtikel'] . ';';
        $manufacturerIDs  = ';' . $post['cHersteller'] . ';';
        $categoryIDs      = ';' . $post['cKategorie'] . ';';
        $customerGroupIDs = ';' . \implode(';', $post['kKundengruppe']) . ';';
        if (
            isset($defaultTpl->oNewslettervorlageStdVar_arr)
            && \is_array($defaultTpl->oNewslettervorlageStdVar_arr)
            && \count($defaultTpl->oNewslettervorlageStdVar_arr) > 0
        ) {
            foreach ($defaultTpl->oNewslettervorlageStdVar_arr as $i => $tplVar) {
                if ($tplVar->cTyp === 'TEXT') {
                    $defaultTpl->oNewslettervorlageStdVar_arr[$i]->cInhalt =
                        $post['kNewslettervorlageStdVar_' . $tplVar->kNewslettervorlageStdVar];
                }
                if ($tplVar->cTyp === 'BILD') {
                    $defaultTpl->oNewslettervorlageStdVar_arr[$i]->cLinkURL = $post['cLinkURL'];
                    $defaultTpl->oNewslettervorlageStdVar_arr[$i]->cAltTag  = $post['cAltTag'];
                }
            }
        }

        $tpl                        = new stdClass();
        $tpl->kNewslettervorlageStd = $defaultTplID;
        $tpl->kKampagne             = (int)$post['kKampagne'];
        $tpl->kSprache              = (int)($_SESSION['editLanguageID'] ?? $_SESSION['kSprache']);
        $tpl->cName                 = Text::filterXSS($post['cName']);
        $tpl->cBetreff              = Text::filterXSS($post['cBetreff']);
        $tpl->cArt                  = $post['cArt'];
        $tpl->cArtikel              = $productIDs;
        $tpl->cHersteller           = $manufacturerIDs;
        $tpl->cKategorie            = $categoryIDs;
        $tpl->cKundengruppe         = $customerGroupIDs;
        $tpl->cInhaltHTML           = $this->mapDefaultTemplate(
            $defaultTpl->cInhaltHTML,
            $defaultTpl->oNewslettervorlageStdVar_arr
        );
        $tpl->cInhaltText           = $this->mapDefaultTemplate(
            $defaultTpl->cInhaltText,
            $defaultTpl->oNewslettervorlageStdVar_arr,
            true
        );

        $dt  = new DateTime($timeData->dZeit);
        $now = new DateTime();

        $tpl->dStartZeit = ($dt > $now)
            ? $dt->format('Y-m-d H:i:s')
            : $now->format('Y-m-d H:i:s');

        if ($templateID > 0) {
            $revision = new Revision($this->db);
            $revision->addRevision('newsletterstd', $templateID, true);

            $upd                = new stdClass();
            $upd->cName         = $tpl->cName;
            $upd->cBetreff      = $tpl->cBetreff;
            $upd->kKampagne     = $tpl->kKampagne;
            $upd->cArt          = $tpl->cArt;
            $upd->cArtikel      = $tpl->cArtikel;
            $upd->cHersteller   = $tpl->cHersteller;
            $upd->cKategorie    = $tpl->cKategorie;
            $upd->cKundengruppe = $tpl->cKundengruppe;
            $upd->cInhaltHTML   = $tpl->cInhaltHTML;
            $upd->cInhaltText   = $tpl->cInhaltText;
            $upd->dStartZeit    = $tpl->dStartZeit;
            $this->db->update(
                'tnewslettervorlage',
                'kNewsletterVorlage',
                $templateID,
                $upd
            );
        } else {
            $templateID = $this->db->insert('tnewslettervorlage', $tpl);
        }
        if (
            $templateID > 0
            && isset($defaultTpl->oNewslettervorlageStdVar_arr)
            && \is_array($defaultTpl->oNewslettervorlageStdVar_arr)
            && \count($defaultTpl->oNewslettervorlageStdVar_arr) > 0
        ) {
            $this->db->delete(
                'tnewslettervorlagestdvarinhalt',
                'kNewslettervorlage',
                $templateID
            );
            $uploadDir = \PFAD_ROOT . \PFAD_BILDER . \PFAD_NEWSLETTERBILDER;
            foreach ($defaultTpl->oNewslettervorlageStdVar_arr as $i => $tplVar) {
                $imageExists = false;
                if ($tplVar->cTyp === 'BILD') {
                    $currentDir = $uploadDir . $templateID;
                    if (!\is_dir($currentDir) && !\mkdir($currentDir) && !\is_dir($currentDir)) {
                        throw new \RuntimeException(\sprintf('Directory "%s" was not created', $currentDir));
                    }
                    $idx = 'kNewslettervorlageStdVar_' . $tplVar->kNewslettervorlageStdVar;
                    if (
                        isset($_FILES[$idx]['name'])
                        && \mb_strlen($_FILES[$idx]['name']) > 0
                        && Image::isImageUpload($_FILES[$idx])
                    ) {
                        $file = $uploadDir . $templateID
                            . '/kNewslettervorlageStdVar_' . $tplVar->kNewslettervorlageStdVar
                            . $this->mapFileType(
                                $_FILES['kNewslettervorlageStdVar_'
                                . $tplVar->kNewslettervorlageStdVar]['type']
                            );
                        if (\file_exists($file)) {
                            \unlink($file);
                        }
                        \move_uploaded_file(
                            $_FILES['kNewslettervorlageStdVar_' . $tplVar->kNewslettervorlageStdVar]['tmp_name'],
                            $file
                        );
                        if (isset($post['cLinkURL']) && \mb_strlen($post['cLinkURL']) > 0) {
                            $defaultTpl->oNewslettervorlageStdVar_arr[$i]->cLinkURL = $post['cLinkURL'];
                        }
                        if (isset($post['cAltTag']) && \mb_strlen($post['cAltTag']) > 0) {
                            $defaultTpl->oNewslettervorlageStdVar_arr[$i]->cAltTag = $post['cAltTag'];
                        }
                        $defaultTpl->oNewslettervorlageStdVar_arr[$i]->cInhalt =
                            Shop::getURL() . '/' . \PFAD_BILDER . \PFAD_NEWSLETTERBILDER . $templateID .
                            '/kNewslettervorlageStdVar_' . $tplVar->kNewslettervorlageStdVar .
                            $this->mapFileType(
                                $_FILES['kNewslettervorlageStdVar_' .
                                $tplVar->kNewslettervorlageStdVar]['type']
                            );

                        $imageExists = true;
                    }
                }

                $nlTplContent                           = new stdClass();
                $nlTplContent->kNewslettervorlageStdVar = $tplVar->kNewslettervorlageStdVar;
                $nlTplContent->kNewslettervorlage       = $templateID;
                if ($tplVar->cTyp === 'TEXT') {
                    $nlTplContent->cInhalt = $tplVar->cInhalt;
                } elseif ($tplVar->cTyp === 'BILD') {
                    if ($imageExists) {
                        $nlTplContent->cInhalt = $defaultTpl->oNewslettervorlageStdVar_arr[$i]->cInhalt;
                        if (isset($post['cLinkURL']) && \mb_strlen($post['cLinkURL']) > 0) {
                            $nlTplContent->cLinkURL = $post['cLinkURL'];
                        }
                        if (isset($post['cAltTag']) && \mb_strlen($post['cAltTag']) > 0) {
                            $nlTplContent->cAltTag = $post['cAltTag'];
                        }
                        $upd              = new stdClass();
                        $upd->cInhaltHTML = $this->mapDefaultTemplate(
                            $defaultTpl->cInhaltHTML,
                            $defaultTpl->oNewslettervorlageStdVar_arr
                        );
                        $upd->cInhaltText = $this->mapDefaultTemplate(
                            $defaultTpl->cInhaltText,
                            $defaultTpl->oNewslettervorlageStdVar_arr,
                            true
                        );
                        $this->db->update(
                            'tnewslettervorlage',
                            'kNewsletterVorlage',
                            $templateID,
                            $upd
                        );
                    } else {
                        $nlTplContent->cInhalt = $tplVar->cInhalt;
                        if (isset($post['cLinkURL']) && \mb_strlen($post['cLinkURL']) > 0) {
                            $nlTplContent->cLinkURL = $post['cLinkURL'];
                        }
                        if (isset($post['cAltTag']) && \mb_strlen($post['cAltTag']) > 0) {
                            $nlTplContent->cAltTag = $post['cAltTag'];
                        }
                    }
                }
                $this->db->insert('tnewslettervorlagestdvarinhalt', $nlTplContent);
            }
        }

        $this->currentId = $templateID;

        return $checks;
    }

    /**
     * @param array<mixed> $post - pre-filtered POST
     * @return int[]|stdClass
     * @throws \Exception
     */
    public function saveTemplate(array $post): array|stdClass
    {
        foreach (['cName', 'cBetreff', 'cHtml', 'cText'] as $key) {
            $post[$key] = \trim($post[$key]);
        }

        $alertHelper = Shop::Container()->getAlertService();
        $checks      = $this->checkTemplate(
            $post['cName'] ?? '',
            $post['kKundengruppe'] ?? [],
            $post['cBetreff'] ?? '',
            $post['cArt'] ?? '',
            $post['cHtml'] ?? '',
            $post['cText'] ?? ''
        );
        if (\count($checks) > 0) {
            return $checks;
        }
        $day         = $post['dTag'];
        $month       = $post['dMonat'];
        $year        = $post['dJahr'];
        $hour        = $post['dStunde'];
        $minute      = $post['dMinute'];
        $dbFromatted = $year . '-' . $month . '-' . $day . ' ' . $hour . ':' . $minute . ':00';
        $timeData    = $this->getDateData($dbFromatted);

        $templateID       = (int)($post['kNewsletterVorlage'] ?? 0);
        $campaignID       = (int)$post['kKampagne'];
        $productIDs       = $post['cArtikel'];
        $manufacturerIDs  = $post['cHersteller'];
        $categoryIDs      = $post['cKategorie'];
        $customerGroupIDs = ';' . \implode(';', $post['kKundengruppe']) . ';';
        $productIDs       = ';' . $productIDs . ';';
        $manufacturerIDs  = ';' . $manufacturerIDs . ';';
        $categoryIDs      = ';' . $categoryIDs . ';';
        $tpl              = new stdClass();
        if ($templateID > 0) {
            $tpl->kNewsletterVorlage = $templateID;
        }
        $tpl->kSprache      = (int)($_SESSION['editLanguageID'] ?? $_SESSION['kSprache']);
        $tpl->kKampagne     = $campaignID;
        $tpl->cName         = Text::filterXSS((string)$post['cName']);
        $tpl->cBetreff      = Text::filterXSS((string)$post['cBetreff']);
        $tpl->cArt          = $post['cArt'];
        $tpl->cArtikel      = $productIDs;
        $tpl->cHersteller   = $manufacturerIDs;
        $tpl->cKategorie    = $categoryIDs;
        $tpl->cKundengruppe = $customerGroupIDs;
        $tpl->cInhaltHTML   = $post['cHtml'];
        $tpl->cInhaltText   = $post['cText'];

        $dt              = new DateTime($timeData->dZeit);
        $now             = new DateTime();
        $tpl->dStartZeit = ($dt > $now)
            ? $dt->format('Y-m-d H:i:s')
            : $now->format('Y-m-d H:i:s');
        if ((int)($post['kNewsletterVorlage'] ?? 0) > 0) {
            $revision = new Revision($this->db);
            $revision->addRevision('newsletter', $templateID, true);
            $upd                = new stdClass();
            $upd->cName         = $tpl->cName;
            $upd->kKampagne     = $tpl->kKampagne;
            $upd->cBetreff      = $tpl->cBetreff;
            $upd->cArt          = $tpl->cArt;
            $upd->cArtikel      = $tpl->cArtikel;
            $upd->cHersteller   = $tpl->cHersteller;
            $upd->cKategorie    = $tpl->cKategorie;
            $upd->cKundengruppe = $tpl->cKundengruppe;
            $upd->cInhaltHTML   = $tpl->cInhaltHTML;
            $upd->cInhaltText   = $tpl->cInhaltText;
            $upd->dStartZeit    = $tpl->dStartZeit;
            $this->db->update('tnewslettervorlage', 'kNewsletterVorlage', $templateID, $upd);
            $alertHelper->addSuccess(
                \sprintf(\__('successNewsletterTemplateEdit'), $tpl->cName),
                'successNewsletterTemplateEdit'
            );
        } else {
            $templateID = $this->db->insert('tnewslettervorlage', $tpl);
            $alertHelper->addSuccess(
                \sprintf(\__('successNewsletterTemplateSave'), $tpl->cName),
                'successNewsletterTemplateSave'
            );
        }
        $tpl->kNewsletterVorlage = $templateID;
        $this->currentId         = $templateID;

        return $tpl;
    }

    /**
     * @param string       $name
     * @param array<mixed> $customerGroups
     * @param string       $subject
     * @param string       $type
     * @param string       $html
     * @param string       $text
     * @return array<string, int>
     */
    public function checkTemplate(
        string $name,
        array $customerGroups,
        string $subject,
        string $type,
        string $html,
        string $text
    ): array {
        $checks = [];
        if (empty($name)) {
            $checks['cName'] = 1;
        }
        if (\count($customerGroups) === 0) {
            $checks['kKundengruppe_arr'] = 1;
        }
        if (empty($subject)) {
            $checks['cBetreff'] = 1;
        }
        if (empty($type)) {
            $checks['cArt'] = 1;
        }
        if (empty($html)) {
            $checks['cHtml'] = 1;
        }
        if (empty($text)) {
            $checks['cText'] = 1;
        }

        return $checks;
    }

    public function getProductData(string $productString): stdClass
    {
        $productIDs                = \explode(';', $productString);
        $productData               = new stdClass();
        $productData->kArtikel_arr = [];
        $productData->cArtNr_arr   = [];
        if (\count($productIDs) === 0) {
            return $productData;
        }
        foreach ($productIDs as $item) {
            if ($item) {
                $productData->kArtikel_arr[] = $item;
            }
        }
        // hole zu den kArtikeln die passende cArtNr
        foreach ($productData->kArtikel_arr as $kArtikel) {
            $cArtNr = $this->getProductNo((int)$kArtikel);
            if (\mb_strlen($cArtNr) > 0) {
                $productData->cArtNr_arr[] = $cArtNr;
            }
        }

        return $productData;
    }

    /**
     * @return string[]
     */
    public function getCustomerGroupData(string $groupString): array
    {
        $groupIDs = [];
        foreach (\explode(';', $groupString) as $item) {
            if (\mb_strlen($item) > 0) {
                $groupIDs[] = $item;
            }
        }

        return $groupIDs;
    }

    private function getProductNo(int $productID): string
    {
        $artNo   = '';
        $product = null;
        if ($productID > 0) {
            $product = $this->db->select('tartikel', 'kArtikel', $productID);
        }

        return $product->cArtNr ?? $artNo;
    }

    /**
     * @param array<numeric-string|int> $recipientIDs
     */
    public function activateSubscribers(array $recipientIDs): bool
    {
        if (\count($recipientIDs) === 0) {
            return false;
        }
        $where      = ' IN (' . \implode(',', \array_map('\intval', $recipientIDs)) . ')';
        $recipients = $this->db->getObjects(
            'SELECT *
                FROM tnewsletterempfaenger
                WHERE kNewsletterEmpfaenger' .
            $where
        );

        if (\count($recipients) === 0) {
            return false;
        }
        $this->db->query(
            'UPDATE tnewsletterempfaenger
                SET nAktiv = 1
                WHERE kNewsletterEmpfaenger' . $where
        );
        foreach ($recipients as $recipient) {
            $recipient->kNewsletterEmpfaenger = (int)$recipient->kNewsletterEmpfaenger;
            $recipient->kSprache              = (int)$recipient->kSprache;
            $recipient->kKunde                = (int)$recipient->kKunde;
            $recipient->nAktiv                = (int)$recipient->nAktiv;

            $hist               = new stdClass();
            $hist->kSprache     = $recipient->kSprache;
            $hist->kKunde       = $recipient->kKunde;
            $hist->cAnrede      = $recipient->cAnrede;
            $hist->cVorname     = $recipient->cVorname;
            $hist->cNachname    = $recipient->cNachname;
            $hist->cEmail       = $recipient->cEmail;
            $hist->cOptCode     = $recipient->cOptCode;
            $hist->cLoeschCode  = $recipient->cLoeschCode;
            $hist->cAktion      = 'Aktiviert';
            $hist->dEingetragen = $recipient->dEingetragen;
            $hist->dAusgetragen = 'NOW()';
            $hist->dOptCode     = '_DBNULL_';

            $this->db->insert('tnewsletterempfaengerhistory', $hist);
        }
        /** @var OptinNewsletter $optin */
        $optin = (new Optin(OptinNewsletter::class))
            ->getOptinInstance();
        $optin->bulkActivateOptins($recipients);

        return true;
    }

    /**
     * @param array<numeric-string|int> $recipientIDs
     */
    public function deleteSubscribers(array $recipientIDs): bool
    {
        if (\count($recipientIDs) === 0) {
            return false;
        }
        $where      = ' IN (' . \implode(',', \array_map('\intval', $recipientIDs)) . ')';
        $recipients = $this->db->getObjects(
            'SELECT *
                FROM tnewsletterempfaenger
                WHERE kNewsletterEmpfaenger' .
            $where
        );

        if (\count($recipients) === 0) {
            return false;
        }
        $this->db->query(
            'DELETE FROM tnewsletterempfaenger
                WHERE kNewsletterEmpfaenger' . $where
        );
        foreach ($recipients as $recipient) {
            $recipient->kNewsletterEmpfaenger = (int)$recipient->kNewsletterEmpfaenger;
            $recipient->kSprache              = (int)$recipient->kSprache;
            $recipient->kKunde                = (int)$recipient->kKunde;
            $recipient->nAktiv                = (int)$recipient->nAktiv;

            $hist               = new stdClass();
            $hist->kSprache     = $recipient->kSprache;
            $hist->kKunde       = $recipient->kKunde;
            $hist->cAnrede      = $recipient->cAnrede;
            $hist->cVorname     = $recipient->cVorname;
            $hist->cNachname    = $recipient->cNachname;
            $hist->cEmail       = $recipient->cEmail;
            $hist->cOptCode     = $recipient->cOptCode;
            $hist->cLoeschCode  = $recipient->cLoeschCode;
            $hist->cAktion      = 'Geloescht';
            $hist->dEingetragen = $recipient->dEingetragen;
            $hist->dAusgetragen = 'NOW()';
            $hist->dOptCode     = '_DBNULL_';

            $this->db->insert('tnewsletterempfaengerhistory', $hist);
        }
        try {
            (new Optin(OptinNewsletter::class))
                ->bulkDeleteOptins($recipients, 'cOptCode');
        } catch (EmptyResultSetException) {
            // suppress exception, because an optin implementation class is not needed here
        }

        return true;
    }

    public function getSubscriberCount(SqlObject $searchSQL): int
    {
        return $this->db->getSingleInt(
            'SELECT COUNT(*) AS cnt
                FROM tnewsletterempfaenger
                WHERE kSprache = :lid' . $searchSQL->getWhere(),
            'cnt',
            \array_merge(
                ['lid' => (int)($_SESSION['editLanguageID'] ?? $_SESSION['kSprache'])],
                $searchSQL->getParams()
            )
        );
    }

    /**
     * @return stdClass[]
     */
    public function getSubscribers(string $limitSQL, SqlObject $searchSQL): array
    {
        return $this->db->getCollection(
            "SELECT
                tnewsletterempfaenger.*,
                DATE_FORMAT(tnewsletterempfaenger.dEingetragen, '%d.%m.%Y %H:%i') AS dEingetragen_de,
                DATE_FORMAT(tnewsletterempfaenger.dLetzterNewsletter, '%d.%m.%Y %H:%i') AS dLetzterNewsletter_de,
                k.kKundengruppe,
                kg.cName,
                tnewsletterempfaengerhistory.cOptIp,
                IF (tnewsletterempfaengerhistory.dOptCode != '0000-00-00 00:00:00'
                    AND tnewsletterempfaengerhistory.dOptCode IS NOT NULL,
                    DATE_FORMAT(tnewsletterempfaengerhistory.dOptCode, '%d.%m.%Y %H:%i'),
                    DATE_FORMAT(op.dActivated, '%d.%m.%Y %H:%i')
                ) AS optInDate
            FROM
                tnewsletterempfaenger
                LEFT JOIN tkunde k
                    ON k.kKunde = tnewsletterempfaenger.kKunde
                LEFT JOIN tkundengruppe kg
                    ON kg.kKundengruppe = k.kKundengruppe
                LEFT JOIN toptin op
                    ON op.cMail = tnewsletterempfaenger.cEmail
                        AND op.kOptinClass like '%Newsletter'
                        AND op.kOptinCode = tnewsletterempfaenger.cOptCode
                LEFT JOIN tnewsletterempfaengerhistory
                    ON tnewsletterempfaengerhistory.cEmail = tnewsletterempfaenger.cEmail
                        AND tnewsletterempfaengerhistory.cAktion = 'Eingetragen'
                        AND tnewsletterempfaengerhistory.dEingetragen = (
                            SELECT MAX(dEingetragen)
                            FROM tnewsletterempfaengerhistory
                            WHERE cEmail = tnewsletterempfaenger.cEmail
                        )
            WHERE
                tnewsletterempfaenger.kSprache = :lid " . $searchSQL->getWhere() . '
            ORDER BY
                 tnewsletterempfaenger.dEingetragen DESC ' . $limitSQL,
            \array_merge(
                ['lid' => (int)($_SESSION['editLanguageID'] ?? $_SESSION['kSprache'])],
                $searchSQL->getParams()
            )
        )->map(static function (stdClass $item): stdClass {
            $item->kNewsletterEmpfaenger = (int)$item->kNewsletterEmpfaenger;
            $item->kSprache              = (int)$item->kSprache;
            $item->kKunde                = (int)$item->kKunde;
            $item->nAktiv                = (int)$item->nAktiv;
            $item->kKundengruppe         = (int)$item->kKundengruppe;
            $item->cVorname              = Text::filterXSS($item->cVorname);
            $item->cNachname             = Text::filterXSS($item->cNachname);
            $item->cEmail                = Text::filterXSS($item->cEmail);

            return $item;
        })->toArray();
    }

    public function setNewsletterCheckboxStatus(): void
    {
        $active = $_POST['newsletter_active'] === 'Y' ? 1 : 0;

        $this->db->queryPrepared(
            'UPDATE tcheckbox
                LEFT JOIN tcheckboxfunktion USING(kCheckBoxFunktion)
                SET nAktiv = :active
                  WHERE tcheckboxfunktion.cID = :newsletterID',
            [
                'active'       => $active,
                'newsletterID' => 'jtl_newsletter'
            ]
        );
    }

    /**
     * @param array<mixed> $post - pre-filtered POST
     */
    public function addRecipient(Newsletter $instance, array $post): stdClass
    {
        $newsletter               = new stdClass();
        $newsletter->cAnrede      = $post['cAnrede'] ?? '';
        $newsletter->cVorname     = $post['cVorname'] ?? '';
        $newsletter->cNachname    = $post['cNachname'] ?? '';
        $newsletter->cEmail       = $post['cEmail'];
        $newsletter->kSprache     = Request::pInt('kSprache');
        $newsletter->dEingetragen = 'NOW()';
        $newsletter->cOptCode     = $instance->createCode('cOptCode', $newsletter->cEmail);
        $newsletter->cLoeschCode  = $instance->createCode('cLoeschCode', $newsletter->cEmail);
        $newsletter->kKunde       = 0;

        if (empty($newsletter->cEmail)) {
            $this->alertService->addError(\__('errorFillEmail'), 'errorFillEmail');
        } else {
            $exists = $this->db->select('tnewsletterempfaenger', 'cEmail', $newsletter->cEmail);
            if ($exists) {
                $this->alertService->addError(\__('errorEmailExists'), 'errorEmailExists');
            } else {
                $this->db->insert('tnewsletterempfaenger', $newsletter);
                $this->alertService->addSuccess(\__('successNewsletterAboAdd'), 'successNewsletterAboAdd');
            }
        }

        return $newsletter;
    }

    /**
     * @param array<numeric-string|int> $ids
     */
    public function deleteQueue(array $ids): void
    {
        $msg = '';
        foreach (\array_map('\intval', $ids) as $queueID) {
            $entry = $this->db->getSingleObject(
                'SELECT c.foreignKeyID AS newsletterID, c.cronID AS cronID, l.cBetreff
                    FROM tcron c
                    LEFT JOIN tjobqueue j
                        ON j.cronID = c.cronID
                    LEFT JOIN tnewsletter l
                        ON c.foreignKeyID = l.kNewsletter
                    WHERE c.cronID = :cronID',
                ['cronID' => $queueID]
            );
            if ($entry === null) {
                continue;
            }
            $this->db->delete('tnewsletter', 'kNewsletter', (int)$entry->newsletterID);
            $this->db->delete('tcron', 'cronID', $entry->cronID);
            if (!empty($entry->foreignKeyID)) {
                $this->db->delete(
                    'tjobqueue',
                    ['foreignKey', 'foreignKeyID'],
                    ['kNewsletter', (int)$entry->foreignKeyID]
                );
            }
            $msg .= $entry->cBetreff . '", ';
        }
        $this->alertService->addSuccess(
            \sprintf(\__('successNewsletterQueueDelete'), \mb_substr($msg, 0, -2)),
            'successDeleteQueue'
        );
    }

    /**
     * @param array<int|numeric-string> $ids
     */
    public function deleteHistory(array $ids): void
    {
        $noticeTMP = '';
        foreach ($ids as $historyID) {
            $this->db->delete('tnewsletterhistory', 'kNewsletterHistory', (int)$historyID);
            $noticeTMP .= $historyID . ', ';
        }
        $this->alertService->addSuccess(
            \sprintf(\__('successNewsletterHistoryDelete'), \mb_substr($noticeTMP, 0, -2)),
            'successDeleteHistory'
        );
    }

    public function save(int $defaultTplID, JTLSmarty $smarty): string
    {
        $step = 'uebersicht';
        if ($defaultTplID <= 0) {
            return $step;
        }
        $filteredPost = $_POST;
        $step         = 'vorlage_std_erstellen';
        $templateID   = 0;
        if (Request::verifyGPCDataInt('kNewsletterVorlage') > 0) {
            $templateID = Request::verifyGPCDataInt('kNewsletterVorlage');
        }
        $tpl    = $this->getDefaultTemplate($defaultTplID, $templateID);
        $checks = $this->saveDefaultTemplate(
            $tpl,
            $defaultTplID,
            $filteredPost,
            $templateID
        );
        if (\count($checks) > 0) {
            $smarty->assign('cPlausiValue_arr', $checks)
                ->assign('cPostVar_arr', $filteredPost)
                ->assign('oNewslettervorlageStd', $tpl);
        } else {
            $step = 'uebersicht';
            $smarty->assign('cTab', 'newslettervorlagen');
            if ($templateID > 0) {
                $this->alertService->addSuccess(
                    \sprintf(
                        \__('successNewsletterTemplateEdit'),
                        $filteredPost['cName']
                    ),
                    'successNewsletterTemplateEdit'
                );
            } else {
                $this->alertService->addSuccess(
                    \sprintf(
                        \__('successNewsletterTemplateSave'),
                        $filteredPost['cName']
                    ),
                    'successNewsletterTemplateSave'
                );
            }
        }

        return $step;
    }

    public function edit(int $templateID, JTLSmarty $smarty): string
    {
        $step = 'vorlage_std_erstellen';
        $tpl  = $this->getDefaultTemplate(0, $templateID);
        if ($tpl === null) {
            return $step;
        }
        $tpl->oZeit   = $this->getDateData($tpl->dStartZeit);
        $productData  = $this->getProductData($tpl->cArtikel);
        $cgroup       = $this->getCustomerGroupData($tpl->cKundengruppe);
        $revisionData = [];
        foreach ($tpl->oNewslettervorlageStdVar_arr as $item) {
            $revisionData[$item->kNewslettervorlageStdVar] = $item;
        }
        $smarty->assign('oNewslettervorlageStd', $tpl)
            ->assign('kArtikel_arr', $productData->kArtikel_arr)
            ->assign('cArtNr_arr', $productData->cArtNr_arr)
            ->assign('revisionData', $revisionData)
            ->assign('kKundengruppe_arr', $cgroup);

        return $step;
    }

    public function saveAndContinue(?stdClass $newsletterTPL, JTLSmarty $smarty): bool
    {
        $conf         = Shop::getSettings([\CONF_NEWSLETTER]);
        $instance     = new Newsletter($this->db, $conf);
        $filteredPost = $_POST;
        $checks       = $this->saveTemplate($filteredPost);
        if (\is_array($checks)) {
            $smarty->assign('cPlausiValue_arr', $checks)
                ->assign('cPostVar_arr', $filteredPost)
                ->assign('oNewsletterVorlage', $newsletterTPL);

            return false;
        }
        $newsletter                = new stdClass();
        $newsletter->kSprache      = $checks->kSprache;
        $newsletter->kKampagne     = $checks->kKampagne;
        $newsletter->cName         = $checks->cName;
        $newsletter->cBetreff      = $checks->cBetreff;
        $newsletter->cArt          = $checks->cArt;
        $newsletter->cArtikel      = $checks->cArtikel;
        $newsletter->cHersteller   = $checks->cHersteller;
        $newsletter->cKategorie    = $checks->cKategorie;
        $newsletter->cKundengruppe = $checks->cKundengruppe;
        $newsletter->cInhaltHTML   = $checks->cInhaltHTML;
        $newsletter->cInhaltText   = $checks->cInhaltText;
        $newsletter->dStartZeit    = $checks->dStartZeit;
        $newsletter->kNewsletter   = $this->db->insert('tnewsletter', $newsletter);

        $dao = new NewsletterCronDAO();
        $dao->setForeignKeyID($newsletter->kNewsletter);
        $dao->setStartDate($newsletter->dStartZeit);
        $dao->setStartTime(\explode(' ', $newsletter->dStartZeit)[1]);
        $this->db->insert('tcron', $dao->getData());
        $productIDs                 = $instance->getKeys($checks->cArtikel, true);
        $manufacturerIDs            = $instance->getKeys($checks->cHersteller);
        $categoryIDs                = $instance->getKeys($checks->cKategorie);
        $campaign                   = new Campaign($checks->kKampagne, $this->db);
        $products                   = $instance->getProducts($productIDs, $campaign);
        $manufacturers              = $instance->getManufacturers($manufacturerIDs, $campaign);
        $categories                 = $instance->getCategories($categoryIDs, $campaign);
        $customer                   = new stdClass();
        $customer->cAnrede          = 'm';
        $customer->cVorname         = 'Max';
        $customer->cNachname        = 'Mustermann';
        $mailRecipient              = new stdClass();
        $mailRecipient->cEmail      = $conf['newsletter']['newsletter_emailtest'];
        $mailRecipient->cLoeschCode = 'dc1338521613c3cfeb1988261029fe3058';
        $mailRecipient->cLoeschURL  = Shop::getURL() . '/?'
            . \QUERY_PARAM_OPTIN_CODE . '=' . $mailRecipient->cLoeschCode;

        $instance->initSmarty();
        $recipient   = $instance->getRecipients($newsletter->kNewsletter);
        $groupString = '';
        $cgroupKey   = '';
        if (\is_array($recipient->cKundengruppe_arr) && \count($recipient->cKundengruppe_arr) > 0) {
            $cgCount = [0 => 0, 1 => 0];
            foreach ($recipient->cKundengruppe_arr as $cKundengruppeTMP) {
                if (!empty($cKundengruppeTMP)) {
                    $tmpGroup = new CustomerGroup((int)$cKundengruppeTMP, $this->db);
                    if (\mb_strlen($tmpGroup->getName() ?? '') > 0) {
                        if ($cgCount[0] > 0) {
                            $groupString .= ', ' . $tmpGroup->getName();
                        } else {
                            $groupString .= $tmpGroup->getName();
                        }
                        $cgCount[0]++;
                    }
                    if ($tmpGroup->getID() > 0) {
                        if ($cgCount[1] > 0) {
                            $cgroupKey .= ';' . $tmpGroup->getID();
                        } else {
                            $cgroupKey .= $tmpGroup->getID();
                        }
                        $cgCount[1]++;
                    }
                } else {
                    if ($cgCount[0] > 0) {
                        $groupString .= ', Newsletterempfänger ohne Kundenkonto';
                    } else {
                        $groupString .= 'Newsletterempfänger ohne Kundenkonto';
                    }
                    if ($cgCount[1] > 0) {
                        $cgroupKey .= ';0';
                    } else {
                        $cgroupKey .= '0';
                    }
                    $cgCount[0]++;
                    $cgCount[1]++;
                }
            }
        }
        if (\mb_strlen($groupString) > 0) {
            $groupString = \mb_substr($groupString, 0, -2);
        }
        $hist                   = new stdClass();
        $hist->kSprache         = $newsletter->kSprache;
        $hist->nAnzahl          = $recipient->nAnzahl;
        $hist->cBetreff         = $newsletter->cBetreff;
        $hist->cHTMLStatic      = $instance->getStaticHtml(
            $newsletter,
            $products,
            $manufacturers,
            $categories,
            $campaign,
            $mailRecipient,
            $customer
        );
        $hist->cKundengruppe    = $groupString;
        $hist->cKundengruppeKey = ';' . $cgroupKey . ';';
        $hist->dStart           = $checks->dStartZeit;
        $this->db->insert('tnewsletterhistory', $hist); // --TODO-- why already history here ?!?!
        $this->alertService->addSuccess(
            \sprintf(\__('successNewsletterPrepared'), $newsletter->cName),
            'successNewsletterPrepared'
        );

        return true;
    }

    public function saveAndTest(?stdClass $newsletterTPL, JTLSmarty $smarty): bool
    {
        $conf     = Shop::getSettings([\CONF_NEWSLETTER]);
        $instance = new Newsletter($this->db, $conf);
        $instance->initSmarty();
        $filteredPost = $_POST;
        $checks       = $this->saveTemplate($filteredPost);
        if (\is_array($checks)) {
            $smarty->assign('cPlausiValue_arr', $checks)
                ->assign('cPostVar_arr', $filteredPost)
                ->assign('oNewsletterVorlage', $newsletterTPL);

            return false;
        }
        $productIDs      = $instance->getKeys($checks->cArtikel, true);
        $manufacturerIDs = $instance->getKeys($checks->cHersteller);
        $categoryIDs     = $instance->getKeys($checks->cKategorie);
        $campaign        = new Campaign($checks->kKampagne, $this->db);
        $products        = $instance->getProducts($productIDs, $campaign);
        $manufacturers   = $instance->getManufacturers($manufacturerIDs, $campaign);
        $categories      = $instance->getCategories($categoryIDs, $campaign);
        // dummy customer
        $customer            = new stdClass();
        $customer->cAnrede   = 'm';
        $customer->cVorname  = 'Max';
        $customer->cNachname = 'Mustermann';
        // dummy recipient
        $mailRecipient              = new stdClass();
        $mailRecipient->cEmail      = $conf['newsletter']['newsletter_emailtest'];
        $mailRecipient->cLoeschCode = 'dc1338521613c3cfeb1988261029fe3058';
        $mailRecipient->cLoeschURL  = Shop::getURL() . '/?'
            . \QUERY_PARAM_OPTIN_CODE . '=' . $mailRecipient->cLoeschCode;
        if (empty($mailRecipient->cEmail)) {
            $result = \__('errorTestTemplateEmpty');
        } else {
            $result = $instance->send(
                $checks,
                $mailRecipient,
                $products,
                $manufacturers,
                $categories,
                $campaign,
                $customer
            );
        }
        if ($result !== true) {
            $this->alertService->addError($result, 'errorNewsletter');
        } else {
            $this->alertService->addSuccess(
                \sprintf(\__('successTestEmailTo'), $checks->cName, $mailRecipient->cEmail),
                'successNewsletterPrepared'
            );
        }

        return true;
    }

    /**
     * @param array<numeric-string|int> $templateIDs
     * @return bool
     */
    public function deleteTemplates(array $templateIDs): bool
    {
        if (\count($templateIDs) === 0) {
            $this->alertService->addError(\__('errorAtLeastOneNewsletter'), 'errorAtLeastOneNewsletter');

            return false;
        }
        foreach (\array_map('\intval', $templateIDs) as $tplID) {
            $tpl = $this->db->getSingleObject(
                'SELECT kNewsletterVorlage, kNewslettervorlageStd
                    FROM tnewslettervorlage
                    WHERE kNewsletterVorlage = :tplID',
                ['tplID' => $tplID]
            );
            if ($tpl === null || $tpl->kNewsletterVorlage <= 0) {
                continue;
            }
            if (($tpl->kNewslettervorlageStd ?? 0) > 0) {
                $this->db->queryPrepared(
                    'DELETE tnewslettervorlage, tnewslettervorlagestdvarinhalt
                        FROM tnewslettervorlage
                        LEFT JOIN tnewslettervorlagestdvarinhalt
                            ON tnewslettervorlagestdvarinhalt.kNewslettervorlage =
                               tnewslettervorlage.kNewsletterVorlage
                        WHERE tnewslettervorlage.kNewsletterVorlage = :tplID',
                    ['tplID' => $tplID]
                );
            } else {
                $this->db->delete(
                    'tnewslettervorlage',
                    'kNewsletterVorlage',
                    $tplID
                );
            }
        }
        $this->alertService->addSuccess(\__('successNewsletterTemplateDelete'), 'successNewsletterTemplateDelete');

        return true;
    }

    public function getCurrentId(): int
    {
        return $this->currentId;
    }
}
