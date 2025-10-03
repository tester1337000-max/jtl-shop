<?php

declare(strict_types=1);

namespace JTL\Newsletter;

use Exception;
use JTL\Campaign;
use JTL\Catalog\Category\Kategorie;
use JTL\Catalog\Hersteller;
use JTL\Catalog\Product\Artikel;
use JTL\Customer\Customer;
use JTL\Customer\CustomerGroup;
use JTL\DB\DbInterface;
use JTL\Helpers\Text;
use JTL\Mail\Mail\Mail;
use JTL\Session\Frontend;
use JTL\Shop;
use JTL\Smarty\ContextType;
use JTL\Smarty\JTLSmarty;
use JTL\Smarty\Smarty4ResourceNiceDB;
use JTL\Smarty\SmartyResourceNiceDB;
use stdClass;

/**
 * Class Newsletter
 * @package JTL\Newsletter
 */
class Newsletter
{
    private ?JTLSmarty $smarty = null;

    /**
     * @param array<mixed> $config
     */
    public function __construct(private readonly DbInterface $db, private readonly array $config)
    {
    }

    /**
     * @throws \SmartyException
     */
    public function initSmarty(): JTLSmarty
    {
        $this->smarty = new JTLSmarty(true, ContextType::NEWSLETTER);
        $this->smarty->setCaching(0)
            ->setDebugging(false)
            ->setCompileDir(\PFAD_ROOT . \PFAD_COMPILEDIR)
            ->registerResource(
                'db',
                \SMARTY_LEGACY_MODE
                    ? new Smarty4ResourceNiceDB($this->db, ContextType::NEWSLETTER)
                    : new SmartyResourceNiceDB($this->db, ContextType::NEWSLETTER)
            )
            ->assign('Firma', $this->db->getSingleObject('SELECT *  FROM tfirma'))
            ->assign('URL_SHOP', Shop::getURL())
            ->assign('Einstellungen', $this->config);

        return $this->smarty;
    }

    /**
     * @param array<mixed> $products
     * @param array<mixed> $manufacturers
     * @param array<mixed> $categories
     */
    public function getStaticHtml(
        stdClass $newsletter,
        array $products,
        array $manufacturers,
        array $categories,
        Campaign $campaign,
        stdClass $recipient,
        stdClass $customer
    ): string {
        if ($this->smarty === null) {
            throw new Exception('Newsletter smarty not initialized');
        }
        $this->smarty->assign('Emailempfaenger', $recipient)
            ->assign('Kunde', $customer)
            ->assign('Artikelliste', $products)
            ->assign('Herstellerliste', $manufacturers)
            ->assign('Kategorieliste', $categories)
            ->assign('Kampagne', $campaign);

        $cTyp = 'VL';
        $nKey = $newsletter->kNewsletterVorlage ?? null;
        if ($newsletter->kNewsletter > 0) {
            $cTyp = 'NL';
            $nKey = $newsletter->kNewsletter;
        }

        return $this->smarty->fetch('db:' . $cTyp . '_' . $nKey . '_html');
    }

    public function getRecipients(int $newsletterID): stdClass
    {
        if ($newsletterID <= 0) {
            return new stdClass();
        }
        $data = $this->db->select('tnewsletter', 'kNewsletter', $newsletterID);
        if ($data === null) {
            return (object)['nAnzahl' => 0, 'cKundengruppe_arr' => []];
        }
        $groupIDs = Text::parseSSKint($data->cKundengruppe ?? '');
        $cSQL     = '';
        if (\count($groupIDs) > 0) {
            $noGroup = \in_array(0, $groupIDs, true);
            if ($noGroup === false || \count($groupIDs) > 1) {
                $cSQL = 'AND (tkunde.kKundengruppe IN (' . \implode(',', $groupIDs) . ')';
                if ($noGroup === true) {
                    $cSQL .= ' OR tkunde.kKundengruppe IS NULL';
                }
                $cSQL .= ')';
            } elseif ($noGroup === true) {
                $cSQL .= ' AND tkunde.kKundengruppe IS NULL';
            }
        }

        $recipients = $this->db->getSingleObject(
            'SELECT COUNT(*) AS nAnzahl
                FROM tnewsletterempfaenger
                LEFT JOIN tsprache
                    ON tsprache.kSprache = tnewsletterempfaenger.kSprache
                LEFT JOIN tkunde
                    ON tkunde.kKunde = tnewsletterempfaenger.kKunde
                WHERE tnewsletterempfaenger.kSprache = :lid
                    AND tnewsletterempfaenger.nAktiv = 1 ' . $cSQL,
            ['lid' => (int)$data->kSprache]
        );
        if ($recipients === null || $this->db->getErrorCode() !== 0) {
            $recipients = new stdClass();
        }
        $recipients->cKundengruppe_arr = $groupIDs;

        return $recipients;
    }

    public function createCode(string $dbField, string $email): string
    {
        $code = \md5($email . \time() . \random_int(123, 456));
        while (!$this->isCodeUnique($dbField, $code)) {
            $code = \md5($email . \time() . \random_int(123, 456));
        }

        return $code;
    }

    public function isCodeUnique(string $dbField, string|int $code): bool
    {
        return $this->db->select('tnewsletterempfaenger', $dbField, $code) === null;
    }

    public function getPreview(stdClass $template): bool|string
    {
        $this->initSmarty();
        if ($this->smarty === null) {
            throw new Exception('Newsletter smarty not initialized');
        }
        $productIDs             = $this->getKeys($template->cArtikel, true);
        $manufacturerIDs        = $this->getKeys($template->cHersteller);
        $categoryIDs            = $this->getKeys($template->cKategorie);
        $campaign               = new Campaign((int)$template->kKampagne, $this->db);
        $products               = $this->getProducts($productIDs, $campaign);
        $manufacturers          = $this->getManufacturers($manufacturerIDs, $campaign);
        $categories             = $this->getCategories($categoryIDs, $campaign);
        $customer               = new stdClass();
        $customer->cAnrede      = 'm';
        $customer->cVorname     = 'Max';
        $customer->cNachname    = 'Mustermann';
        $recipient              = new stdClass();
        $recipient->cEmail      = $this->config['newsletter']['newsletter_emailtest'];
        $recipient->cLoeschCode = 'dc1338521613c3cfeb1988261029fe3058';
        $recipient->cLoeschURL  = Shop::getURL() . '/?' . \QUERY_PARAM_OPTIN_CODE . '=' . $recipient->cLoeschCode;

        $this->smarty->assign('NewsletterEmpfaenger', $recipient)
            ->assign('Emailempfaenger', $recipient)
            ->assign('oNewsletterVorlage', $template)
            ->assign('Kunde', $customer)
            ->assign('Artikelliste', $products)
            ->assign('NettoPreise', 0)
            ->assign('Herstellerliste', $manufacturers)
            ->assign('Kategorieliste', $categories)
            ->assign('Kampagne', $campaign);

        try {
            $template->cInhaltHTML = $this->smarty->fetch('db:VL_' . $template->kNewsletterVorlage . '_html');
            $template->cInhaltText = $this->smarty->fetch('db:VL_' . $template->kNewsletterVorlage . '_text');
        } catch (Exception $e) {
            return $e->getMessage();
        }

        return true;
    }

    /**
     * @param array<mixed> $products
     * @param array<mixed> $manufacturers
     * @param array<mixed> $categories
     * @throws Exception
     * @return string|true
     */
    public function send(
        stdClass $newsletter,
        stdClass $recipients,
        array $products,
        array $manufacturers,
        array $categories,
        Campaign $campaign,
        Customer|stdClass|null $customer
    ): string|bool {
        if ($this->smarty === null) {
            throw new Exception('Newsletter smarty not initialized');
        }
        $this->smarty->assign('oNewsletter', $newsletter)
            ->assign('Emailempfaenger', $recipients)
            ->assign('Kunde', $customer)
            ->assign('Artikelliste', $products)
            ->assign('Herstellerliste', $manufacturers)
            ->assign('Kategorieliste', $categories)
            ->assign('Kampagne', $campaign)
            ->assign(
                'cNewsletterURL',
                Shop::Container()->getLinkService()->getStaticRoute('newsletter.php')
                . '?show=' . ($newsletter->kNewsletter ?? '0')
            );
        $net      = 0;
        $bodyHtml = '';
        if ($customer !== null && isset($customer->kKunde) && $customer->kKunde > 0) {
            $customergGroup = $this->db->getSingleObject(
                'SELECT tkundengruppe.nNettoPreise
                    FROM tkunde
                    JOIN tkundengruppe
                        ON tkundengruppe.kKundengruppe = tkunde.kKundengruppe
                    WHERE tkunde.kKunde = :cid',
                ['cid' => (int)$customer->kKunde]
            );
            if ($customergGroup !== null && isset($customergGroup->nNettoPreise)) {
                $net = $customergGroup->nNettoPreise;
            }
        }

        $this->smarty->assign('NettoPreise', $net);

        $cPixel = '';
        if (isset($campaign->kKampagne) && $campaign->kKampagne > 0) {
            $cPixel = '<br /><img src="' . Shop::getURL() . '/' . \PFAD_INCLUDES .
                'newslettertracker.php?kK=' . $campaign->kKampagne .
                '&kN=' . ($newsletter->kNewsletter ?? 0) . '&kNE=' .
                ($recipients->kNewsletterEmpfaenger ?? 0) . '" alt="Newsletter" />';
        }

        $cTyp = 'VL';
        $nKey = $newsletter->kNewsletterVorlage ?? 0;
        if (isset($newsletter->kNewsletter) && $newsletter->kNewsletter > 0) {
            $cTyp = 'NL';
            $nKey = $newsletter->kNewsletter;
        }
        if ($newsletter->cArt === 'text/html' || $newsletter->cArt === 'html') {
            try {
                $bodyHtml = $this->smarty->fetch('db:' . $cTyp . '_' . $nKey . '_html') . $cPixel;
            } catch (Exception $e) {
                Shop::Smarty()->assign('oSmartyError', $e->getMessage());

                return $e->getMessage();
            }
        }
        try {
            $bodyText = $this->smarty->fetch('db:' . $cTyp . '_' . $nKey . '_text');
        } catch (Exception $e) {
            Shop::Smarty()->assign('oSmartyError', $e->getMessage());

            return $e->getMessage();
        }
        $toName = ($recipients->cVorname ?? '') . ' ' . ($recipients->cNachname ?? '');
        if (isset($customer->kKunde) && $customer->kKunde > 0) {
            $toName = ($customer->cVorname ?? '') . ' ' . ($customer->cNachname ?? '');
        }
        $mailer                 = Shop::Container()->getMailer();
        $config                 = [
            'email_methode'               => $this->config['newsletter']['newsletter_emailmethode'],
            'email_sendmail_pfad'         => $this->config['newsletter']['newsletter_sendmailpfad'],
            'email_smtp_hostname'         => $this->config['newsletter']['newsletter_smtp_host'],
            'email_smtp_port'             => $this->config['newsletter']['newsletter_smtp_port'],
            'email_smtp_auth'             => $this->config['newsletter']['newsletter_smtp_authnutzen'],
            'email_smtp_user'             => $this->config['newsletter']['newsletter_smtp_benutzer'],
            'email_smtp_pass'             => $this->config['newsletter']['newsletter_smtp_pass'],
            'email_smtp_verschluesselung' => $this->config['newsletter']['newsletter_smtp_verschluesselung']
        ];
        $mailerConfig['emails'] = $config;
        $mailer->setConfig($mailerConfig);
        $mailNL = (new Mail())
            ->setToMail($recipients->cEmail)
            ->setToName($toName)
            ->setFromMail($this->config['newsletter']['newsletter_emailadresse'])
            ->setFromName($this->config['newsletter']['newsletter_emailabsender'])
            ->setReplyToMail($this->config['newsletter']['newsletter_emailadresse'])
            ->setReplyToName($this->config['newsletter']['newsletter_emailabsender'])
            ->setSubject($newsletter->cBetreff)
            ->setBodyText($bodyText)
            ->setBodyHTML($bodyHtml)
            ->setLanguage(Shop::Lang()->getLanguageByID((int)$newsletter->kSprache));
        $mailer->send($mailNL);

        return true;
    }

    /**
     * Braucht ein String von Keys oder Nummern und gibt ein Array mit kKeys zurueck
     * Der String muss ';' separiert sein z.b. '1;2;3'
     *
     * @return int[]
     */
    public function getKeys(string $keyString, bool $asProductNo = false): array
    {
        $res  = [];
        $keys = \explode(';', $keyString);
        if (\count($keys) === 0) {
            return $res;
        }
        $res = \array_filter($keys, static fn(string $e): bool => \mb_strlen($e) > 0);
        if ($asProductNo) {
            $res = \array_map(static fn(string $e): string => "'" . $e . "'", $res);
            if (\count($res) > 0) {
                $res = $this->db->getInts(
                    'SELECT kArtikel
                        FROM tartikel
                        WHERE cArtNr IN (' . \implode(',', $res) . ')
                            AND kEigenschaftKombi = 0',
                    'kArtikel'
                );
            }
        } else {
            $res = \array_map('\intval', $res);
        }

        return $res;
    }

    /**
     * Benoetigt ein Array von kArtikel und gibt ein Array mit Artikelobjekten zurueck
     * @param array<numeric-string|int> $productIDs
     * @param Campaign                  $campaign
     * @param int                       $customerGroupID
     * @param int                       $langID
     * @return Artikel[]
     */
    public function getProducts(array $productIDs, Campaign $campaign, int $customerGroupID = 0, int $langID = 0): array
    {
        if (\count($productIDs) === 0) {
            return [];
        }
        $products       = [];
        $imageBaseURL   = Shop::getImageBaseURL();
        $db             = Shop::Container()->getDB();
        $cache          = Shop::Container()->getCache();
        $defaultOptions = Artikel::getDefaultOptions();
        $currency       = Frontend::getCurrency();
        $customerGroup  = CustomerGroup::reset($customerGroupID);
        $customerGroup->setMayViewPrices(1);
        foreach ($productIDs as $id) {
            $id = (int)$id;
            if ($id <= 0) {
                continue;
            }
            $product = new Artikel($db, $customerGroup, $currency, $cache);
            $product->fuelleArtikel($id, $defaultOptions, $customerGroupID, $langID);
            if ($product->kArtikel <= 0) {
                Shop::Container()->getLogService()->notice(
                    'Newsletter Cron konnte den Artikel {pid} für Kundengruppe {cgid}'
                    . ' und Sprache {lid} nicht laden (Sichtbarkeit?)',
                    [
                        'pid'  => $id,
                        'cgid' => $customerGroupID,
                        'lid'  => $langID
                    ]
                );
                continue;
            }
            $product->cURL = $product->cURLFull;
            if (isset($product->cURL, $campaign->cParameter) && \mb_strlen($campaign->cParameter) > 0) {
                $product->cURL .= (\str_contains($product->cURL, '.php') ? '&' : '?') .
                    $campaign->cParameter . '=' . $campaign->cWert;
            }
            foreach ($product->Bilder as $image) {
                $image->cPfadMini   = $imageBaseURL . $image->cPfadMini;
                $image->cPfadKlein  = $imageBaseURL . $image->cPfadKlein;
                $image->cPfadNormal = $imageBaseURL . $image->cPfadNormal;
                $image->cPfadGross  = $imageBaseURL . $image->cPfadGross;
            }
            $product->cVorschaubild = $imageBaseURL . $product->cVorschaubild;

            $products[] = $product;
        }

        return $products;
    }

    /**
     * Benoetigt ein Array von kHersteller und gibt ein Array mit Herstellerobjekten zurueck
     * @param array<numeric-string|int> $manufacturerIDs
     * @param Campaign                  $campaign
     * @param int                       $langID
     * @return Hersteller[]
     */
    public function getManufacturers(array $manufacturerIDs, Campaign $campaign, int $langID = 0): array
    {
        if (\count($manufacturerIDs) === 0) {
            return [];
        }
        $manufacturers = [];
        $shopURL       = Shop::getURL() . '/';
        $langID        = $langID ?: Shop::getLanguageID();
        foreach ($manufacturerIDs as $id) {
            $id = (int)$id;
            if ($id <= 0) {
                continue;
            }
            $manufacturer = new Hersteller($id, $langID);
            if ($manufacturer->getID() <= 0) {
                continue;
            }
            if (!\str_contains($manufacturer->getURL($langID) ?? '', $shopURL)) {
                $manufacturer->setURL($shopURL . $manufacturer->getURL($langID), $langID);
            }
            if (isset($campaign->cParameter) && \mb_strlen($campaign->cParameter) > 0) {
                $sep = \str_contains($manufacturer->getURL($langID) ?? '', '.php') ? '&' : '?';
                $manufacturer->setURL(
                    $manufacturer->getURL($langID) . $sep . $campaign->cParameter . '=' . $campaign->cWert,
                    $langID
                );
            }
            $manufacturers[] = $manufacturer;
        }

        return $manufacturers;
    }

    /**
     * Benoetigt ein Array von kKategorie und gibt ein Array mit Kategorieobjekten zurueck
     * @param array<numeric-string|int> $categoryIDs
     * @param Campaign                  $campaign
     * @return Kategorie[]
     */
    public function getCategories(array $categoryIDs, Campaign $campaign): array
    {
        if (\count($categoryIDs) === 0) {
            return [];
        }
        $categories = [];
        $shopURL    = Shop::getURL() . '/';
        foreach ($categoryIDs as $id) {
            $id = (int)$id;
            if ($id <= 0) {
                continue;
            }
            $category = new Kategorie($id, 0, 0, false, $this->db);
            $url      = $category->getURL();
            if ($category->getID() <= 0 || $url === null) {
                continue;
            }
            if (!\str_contains($url, $shopURL)) {
                $url = $shopURL . $url;
                $category->setURL($url);
            }
            if (isset($campaign->cParameter) && \mb_strlen($campaign->cParameter) > 0) {
                $sep = '?';
                if (\str_contains($url, '.php')) {
                    $sep = '&';
                }
                $category->setURL($url . $sep . $campaign->cParameter . '=' . $campaign->cWert);
            }
            $categories[] = $category;
        }

        return $categories;
    }
}
