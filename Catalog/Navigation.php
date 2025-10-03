<?php

declare(strict_types=1);

namespace JTL\Catalog;

use JTL\Catalog\Category\KategorieListe;
use JTL\Catalog\Product\Artikel;
use JTL\Filter\ProductFilter;
use JTL\Helpers\Request;
use JTL\Language\LanguageHelper;
use JTL\Link\Link;
use JTL\Link\LinkInterface;
use JTL\Services\JTL\LinkServiceInterface;
use JTL\Session\Frontend;
use JTL\Shop;

/**
 * Class Navigation
 * @package JTL\Catalog
 */
class Navigation
{
    private int $pageType = \PAGE_UNBEKANNT;

    private ?KategorieListe $categoryList = null;

    private string $baseURL;

    private ?Artikel $product = null;

    private ?LinkInterface $link = null;

    private ?string $linkURL = null;

    private ?ProductFilter $productFilter = null;

    private ?NavigationEntry $customNavigationEntry = null;

    public function __construct(
        private readonly LanguageHelper $language,
        private readonly LinkServiceInterface $linkService
    ) {
        $this->baseURL = Shop::getURL() . '/';
    }

    public function getPageType(): int
    {
        return $this->pageType;
    }

    public function setPageType(int $pageType): void
    {
        $this->pageType = $pageType;
    }

    public function getCategoryList(): ?KategorieListe
    {
        return $this->categoryList;
    }

    public function setCategoryList(KategorieListe $categoryList): void
    {
        $this->categoryList = $categoryList;
    }

    public function getBaseURL(): string
    {
        return $this->baseURL;
    }

    public function setBaseURL(string $baseURL): void
    {
        $this->baseURL = $baseURL;
    }

    public function getProduct(): ?Artikel
    {
        return $this->product;
    }

    public function setProduct(Artikel $product): void
    {
        $this->product = $product;
    }

    public function getLink(): ?LinkInterface
    {
        return $this->link;
    }

    public function setLink(LinkInterface $link): void
    {
        $this->link = $link;
    }

    public function getLinkURL(): ?string
    {
        return $this->linkURL;
    }

    public function setLinkURL(string $url): void
    {
        $this->linkURL = $url;
    }

    public function getProductFilter(): ?ProductFilter
    {
        return $this->productFilter;
    }

    public function setProductFilter(ProductFilter $productFilter): void
    {
        $this->productFilter = $productFilter;
    }

    public function getCustomNavigationEntry(): ?NavigationEntry
    {
        return $this->customNavigationEntry;
    }

    public function setCustomNavigationEntry(NavigationEntry $customNavigationEntry): void
    {
        $this->customNavigationEntry = $customNavigationEntry;
    }

    private function getProductFilterName(): string
    {
        if ($this->productFilter === null) {
            return '';
        }

        return match (true) {
            $this->productFilter->getBaseState()->isNotFound()      =>
            Shop::Container()->getLinkService()->getSpecialPage(\LINKTYP_404)->getName(),
            $this->productFilter->hasCategory()                     =>
                $this->productFilter->getCategory()->getName() ?? '',
            $this->productFilter->hasManufacturer()                 =>
                Shop::Lang()->get('productsFrom') . ' ' . $this->productFilter->getManufacturer()->getName(),
            $this->productFilter->hasCharacteristicValue()          =>
                Shop::Lang()->get('productsWith') . ' ' . $this->productFilter->getCharacteristicValue()->getName(),
            $this->productFilter->hasSearchSpecial()                =>
                $this->productFilter->getSearchSpecial()->getName() ?? '',
            $this->productFilter->hasSearch()                       =>
                Shop::Lang()->get('for') . ' ' . $this->productFilter->getSearch()->getName(),
            $this->productFilter->getSearchQuery()->isInitialized() =>
                Shop::Lang()->get('for') . ' ' . $this->productFilter->getSearchQuery()->getName(),
            default                                                 => ''
        };
    }

    /**
     * @return NavigationEntry[]
     */
    public function createNavigation(): array
    {
        $breadCrumb = [];
        $ele0       = new NavigationEntry();
        $ele0->setName($this->language->get('startpage', 'breadcrumb'));
        $ele0->setURL('/');
        $ele0->setURLFull($this->baseURL);

        $breadCrumb[] = $ele0;
        switch ($this->pageType) {
            case \PAGE_STARTSEITE:
                break;
            case \PAGE_ARTIKEL:
                $breadCrumb = $this->createProductBreadcrumb($breadCrumb);
                break;
            case \PAGE_ARTIKELLISTE:
                $breadCrumb = $this->createProductListBreadcrumb($breadCrumb);
                break;
            case \PAGE_WARENKORB:
                $breadCrumb = $this->createCartBreadcrumb($breadCrumb);
                break;
            case \PAGE_PASSWORTVERGESSEN:
                $breadCrumb = $this->createForgotPasswordBreadcrumb($breadCrumb);
                break;
            case \PAGE_LOGIN:
            case \PAGE_MEINKONTO:
                $breadCrumb = $this->createAccountBreadcrumb($breadCrumb);
                break;
            case \PAGE_BESTELLVORGANG:
                $breadCrumb = $this->createOrderBreadcrumb($breadCrumb);
                break;
            case \PAGE_REGISTRIERUNG:
                $breadCrumb = $this->createRegistractionBreadcrumb($breadCrumb);
                break;
            case \PAGE_KONTAKT:
                $breadCrumb = $this->createContactBreadcrumb($breadCrumb);
                break;
            case \PAGE_WARTUNG:
                $breadCrumb = $this->createMaintenanceBreadcrumb($breadCrumb);
                break;
            case \PAGE_NEWSLETTER:
                $breadCrumb = $this->createNewsletterBreadcrumb($breadCrumb);
                break;
            case \PAGE_NEWSDETAIL:
            case \PAGE_NEWS:
                $breadCrumb = $this->createNewsDetailBreadcrumb($breadCrumb);
                break;
            case \PAGE_NEWSKATEGORIE:
                $breadCrumb = $this->createNewsCategoryBreadcrumb($breadCrumb);
                break;
            case \PAGE_NEWSMONAT:
                $breadCrumb = $this->createNewsarchiveMonthBreadcrumb($breadCrumb);
                break;
            case \PAGE_VERGLEICHSLISTE:
                $breadCrumb = $this->createComparelistBreadcrumb($breadCrumb);
                break;
            case \PAGE_WUNSCHLISTE:
                $breadCrumb = $this->createWishlistBreadcrumb($breadCrumb);
                break;
            case \PAGE_BEWERTUNG:
                $breadCrumb = $this->createReviewBreadcrumb($breadCrumb);
                break;
            default:
                if ($this->link instanceof Link) {
                    $breadCrumb = $this->createLinkBreadcrumb($breadCrumb);
                }
                break;
        }
        if ($this->customNavigationEntry !== null) {
            $breadCrumb[] = $this->customNavigationEntry;
        }
        \executeHook(\HOOK_TOOLSGLOBAL_INC_SWITCH_CREATENAVIGATION, ['navigation' => &$breadCrumb]);

        return $breadCrumb;
    }

    /**
     * @param NavigationEntry[] $breadCrumb
     * @return NavigationEntry[]
     */
    public function createProductBreadcrumb(array $breadCrumb): array
    {
        if (
            $this->categoryList === null
            || $this->product === null
            || \count($this->categoryList->elemente) === 0
        ) {
            return $breadCrumb;
        }
        $langID = $this->language->kSprache;
        foreach (\array_reverse($this->categoryList->elemente) as $item) {
            if ($item->getID() < 1) {
                continue;
            }
            $ele = new NavigationEntry();
            $ele->setID($item->getID());
            $ele->setName($item->getShortName($langID));
            $ele->setURL($item->getURL($langID) ?? '');
            $ele->setURLFull($item->getURL($langID) ?? '');
            $breadCrumb[] = $ele;
        }
        $ele = new NavigationEntry();
        $ele->setID((int)$this->product->getID());
        $ele->setName($this->product->cKurzbezeichnung ?? '');
        $ele->setURL($this->product->cURL ?? '');
        $ele->setURLFull($this->product->cURLFull ?? '');
        if ($this->product->kVaterArtikel > 0) {
            $parent = new Artikel();
            $parent->fuelleArtikel($this->product->kVaterArtikel, Artikel::getDefaultOptions());
            $ele->setName($parent->cKurzbezeichnung ?? '');
            $ele->setURL($parent->cURL ?? '');
            $ele->setURLFull($parent->cURLFull ?? '');
            $ele->setHasChild(true);
        }
        $breadCrumb[] = $ele;

        return $breadCrumb;
    }

    /**
     * @param NavigationEntry[] $breadCrumb
     * @return NavigationEntry[]
     */
    public function createProductListBreadcrumb(array $breadCrumb): array
    {
        $langID    = $this->language->kSprache;
        $elemCount = \count($this->categoryList->elemente ?? []);
        foreach (\array_reverse($this->categoryList->elemente ?? []) as $item) {
            if ($item->getID() < 1) {
                continue;
            }
            $ele = new NavigationEntry();
            $ele->setName($item->getShortName($langID) ?? '');
            $ele->setURL($item->getURL($langID) ?? '');
            $ele->setURLFull($item->getURL($langID) ?? '');
            $breadCrumb[] = $ele;
        }
        if ($elemCount === 0 && $this->productFilter !== null) {
            $ele = new NavigationEntry();
            $ele->setName($this->getProductFilterName());
            $ele->setURL($this->productFilter->getFilterURL()->getURL());
            $ele->setURLFull($this->productFilter->getFilterURL()->getURL());
            $breadCrumb[] = $ele;
        }

        return $breadCrumb;
    }

    /**
     * @param NavigationEntry[] $breadCrumb
     * @return NavigationEntry[]
     */
    public function createCartBreadcrumb(array $breadCrumb): array
    {
        $ele = new NavigationEntry();
        $ele->setHasChild(false);
        $ele->setName($this->language->get('basket', 'breadcrumb'));
        $ele->setURL($this->linkService->getStaticRoute('warenkorb.php', false));
        $ele->setURLFull($this->linkService->getStaticRoute('warenkorb.php'));
        $breadCrumb[] = $ele;

        return $breadCrumb;
    }

    /**
     * @param NavigationEntry[] $breadCrumb
     * @return NavigationEntry[]
     */
    public function createForgotPasswordBreadcrumb(array $breadCrumb): array
    {
        $ele = new NavigationEntry();
        $ele->setHasChild(false);
        $ele->setName($this->language->get('forgotpassword', 'breadcrumb'));
        $ele->setURL($this->linkService->getStaticRoute('pass.php', false));
        $ele->setURLFull($this->linkService->getStaticRoute('pass.php'));
        $breadCrumb[] = $ele;

        return $breadCrumb;
    }

    /**
     * @param NavigationEntry[] $breadCrumb
     * @return NavigationEntry[]
     */
    public function createAccountBreadcrumb(array $breadCrumb): array
    {
        $ele = new NavigationEntry();
        $ele->setHasChild(false);
        $name = Frontend::getCustomer()->getID() > 0
            ? $this->language->get('account', 'breadcrumb')
            : $this->language->get('login', 'breadcrumb');
        $ele->setName($name);
        $ele->setURL($this->linkService->getStaticRoute('jtl.php', false));
        $ele->setURLFull($this->linkService->getStaticRoute('jtl.php'));
        $breadCrumb[] = $ele;

        if (Request::verifyGPCDataInt('accountPage') !== 1) {
            $childPages = [
                'bestellungen'         => ['name' => $this->language->get('myOrders')],
                'editRechnungsadresse' => ['name' => $this->language->get('myPersonalData')],
                'editLieferadresse'    => [
                    'name' => $this->language->get('myShippingAddresses', 'account data')
                ],
                'wllist'               => ['name' => $this->language->get('myWishlists')],
                'del'                  => ['name' => $this->language->get('deleteAccount', 'login')],
                'bestellung'           => [
                    'name'   => $this->language->get('bcOrder', 'breadcrumb'),
                    'parent' => 'bestellungen'
                ],
                'wl'                   => ['name' => $this->language->get('bcWishlist', 'breadcrumb')],
                'pass'                 => ['name' => $this->language->get('changePassword', 'login')],
                'returns'              => ['name' => $this->language->get('myReturns', 'rma')],
                'newRMA'               => [
                    'name'   => $this->language->get('saveReturn', 'rma'),
                    'parent' => 'returns'
                ],
                'showRMA'              => [
                    'name'   => $this->language->get('rma'),
                    'parent' => 'returns'
                ],
                'twofa'                => [
                    'name' => $this->language->get('manageTwoFA', 'account data')
                ],
            ];
            foreach ($childPages as $childPageKey => $childPageData) {
                if (Request::hasGPCData($childPageKey) === false) {
                    continue;
                }
                $currentId = Request::verifyGPCDataInt($childPageKey);
                $hasParent = isset($childPageData['parent']);
                $childPage = $hasParent ? $childPageData['parent'] : $childPageKey;
                $url       = $this->linkService->getStaticRoute('jtl.php', false) . '?' . $childPage . '=1';
                $urlFull   = $this->linkService->getStaticRoute('jtl.php') . '?' . $childPage . '=1';
                $ele       = new NavigationEntry();
                $ele->setName($childPages[$childPage]['name']);
                $ele->setURL($url);
                $ele->setURLFull($urlFull);
                $breadCrumb[] = $ele;
                if ($hasParent) {
                    $url     = $this->linkService->getStaticRoute('jtl.php', false) . '?' . $childPageKey . '='
                        . $currentId;
                    $urlFull = $this->linkService->getStaticRoute('jtl.php') . '?' . $childPageKey . '='
                        . $currentId;
                    $ele     = new NavigationEntry();
                    $ele->setName($childPageData['name']);
                    $ele->setURL($url);
                    $ele->setURLFull($urlFull);
                    $breadCrumb[] = $ele;
                }
            }
        }

        return $breadCrumb;
    }

    /**
     * @param NavigationEntry[] $breadCrumb
     * @return NavigationEntry[]
     */
    public function createOrderBreadcrumb(array $breadCrumb): array
    {
        $ele = new NavigationEntry();
        $ele->setHasChild(false);
        $ele->setName($this->language->get('checkout', 'breadcrumb'));
        $ele->setURL($this->linkService->getStaticRoute('jtl.php', false));
        $ele->setURLFull($this->linkService->getStaticRoute('jtl.php'));
        $breadCrumb[] = $ele;

        return $breadCrumb;
    }

    /**
     * @param NavigationEntry[] $breadCrumb
     * @return NavigationEntry[]
     */
    public function createRegistractionBreadcrumb(array $breadCrumb): array
    {
        $ele = new NavigationEntry();
        $ele->setHasChild(false);
        $ele->setName($this->language->get('register', 'breadcrumb'));
        $ele->setURL($this->linkService->getStaticRoute('registrieren.php', false));
        $ele->setURLFull($this->linkService->getStaticRoute('registrieren.php'));
        $breadCrumb[] = $ele;

        return $breadCrumb;
    }

    /**
     * @param NavigationEntry[] $breadCrumb
     * @return NavigationEntry[]
     */
    public function createContactBreadcrumb(array $breadCrumb): array
    {
        $ele = new NavigationEntry();
        $ele->setHasChild(false);
        $ele->setName($this->language->get('contact', 'breadcrumb'));
        $ele->setURL($this->linkService->getStaticRoute('kontakt.php', false));
        $ele->setURLFull($this->linkService->getStaticRoute());
        $breadCrumb[] = $ele;

        return $breadCrumb;
    }

    /**
     * @param NavigationEntry[] $breadCrumb
     * @return NavigationEntry[]
     */
    public function createMaintenanceBreadcrumb(array $breadCrumb): array
    {
        $ele = new NavigationEntry();
        $ele->setHasChild(false);
        $ele->setName($this->language->get('maintainance', 'breadcrumb'));
        $ele->setURL($this->linkService->getStaticRoute('wartung.php', false));
        $ele->setURLFull($this->linkService->getStaticRoute('wartung.php'));
        $breadCrumb[] = $ele;

        return $breadCrumb;
    }

    /**
     * @param NavigationEntry[] $breadCrumb
     * @return NavigationEntry[]
     */
    public function createNewsletterBreadcrumb(array $breadCrumb): array
    {
        if ($this->link === null) {
            return $breadCrumb;
        }
        $ele = new NavigationEntry();
        $ele->setHasChild(false);
        $ele->setName($this->link->getName());
        $ele->setURL($this->link->getURL());
        $ele->setURLFull($this->link->getURL());
        $breadCrumb[] = $ele;

        return $breadCrumb;
    }

    /**
     * @param NavigationEntry[] $breadCrumb
     * @return NavigationEntry[]
     */
    public function createReviewBreadcrumb(array $breadCrumb): array
    {
        $ele = new NavigationEntry();
        if ($this->product !== null) {
            $ele->setName($this->product->cKurzbezeichnung ?? '');
            $ele->setURL($this->product->cURL ?? '');
            $ele->setURLFull($this->product->cURLFull ?? '');
            if ($this->product->kVaterArtikel > 0) {
                $parent = new Artikel();
                $parent->fuelleArtikel($this->product->kVaterArtikel, Artikel::getDefaultOptions());
                $ele->setName($parent->cKurzbezeichnung ?? '');
                $ele->setURL($parent->cURL ?? '');
                $ele->setURLFull($parent->cURLFull ?? '');
                $ele->setHasChild(true);
            }
            $breadCrumb[] = $ele;
            $ele          = new NavigationEntry();
            $ele->setName($this->language->get('bewertung', 'breadcrumb'));
            $ele->setURL(
                $this->linkService->getStaticRoute('bewertung.php')
                . '?a=' . $this->product->kArtikel . '&bfa=1'
            );
            $ele->setURLFull(
                $this->linkService->getStaticRoute('bewertung.php')
                . '?a=' . $this->product->kArtikel . '&bfa=1'
            );
        } else {
            $ele->setName($this->language->get('bewertung', 'breadcrumb'));
            $ele->setURL('');
            $ele->setURLFull('');
        }
        $breadCrumb[] = $ele;

        return $breadCrumb;
    }

    /**
     * @param NavigationEntry[] $breadCrumb
     * @return NavigationEntry[]
     */
    public function createWishlistBreadcrumb(array $breadCrumb): array
    {
        $ele = new NavigationEntry();
        $ele->setHasChild(false);
        $ele->setName($this->language->get('wishlist'));
        $ele->setURL($this->linkService->getStaticRoute('wunschliste.php', false));
        $ele->setURLFull($this->linkService->getStaticRoute('wunschliste.php'));
        $breadCrumb[] = $ele;

        return $breadCrumb;
    }

    /**
     * @param NavigationEntry[] $breadCrumb
     * @return NavigationEntry[]
     */
    public function createComparelistBreadcrumb(array $breadCrumb): array
    {
        $ele = new NavigationEntry();
        $ele->setHasChild(false);
        $ele->setName($this->language->get('compare'));
        $ele->setURL($this->linkService->getStaticRoute('vergleichsliste.php', false));
        $ele->setURLFull($this->linkService->getStaticRoute('vergleichsliste.php'));
        $breadCrumb[] = $ele;

        return $breadCrumb;
    }

    /**
     * @param NavigationEntry[] $breadCrumb
     * @return NavigationEntry[]
     */
    public function createLinkBreadcrumb(array $breadCrumb): array
    {
        if ($this->link === null) {
            return $breadCrumb;
        }
        $ele = new NavigationEntry();
        $ele->setHasChild(false);
        $elems = $this->linkService->getParentLinks($this->link->getID())
            ->map(static function (LinkInterface $l): NavigationEntry {
                $res = new NavigationEntry();
                $res->setName($l->getName());
                $res->setURL($l->getURL());
                $res->setURLFull($l->getURL());

                return $res;
            })->reverse()->all();

        $breadCrumb = \array_merge($breadCrumb, $elems);
        $ele->setName($this->link->getName());
        $ele->setURL($this->link->getURL());
        $ele->setURLFull($this->link->getURL());
        $breadCrumb[] = $ele;

        return $breadCrumb;
    }

    /**
     * @param NavigationEntry[] $breadCrumb
     * @return NavigationEntry[]
     */
    public function createNewsarchiveMonthBreadcrumb(array $breadCrumb): array
    {
        $ele = new NavigationEntry();
        $ele->setHasChild(false);
        $ele->setName($this->language->get('newsmonat', 'breadcrumb'));
        $ele->setURL($this->linkService->getStaticRoute('news.php', false));
        $ele->setURLFull($this->linkService->getStaticRoute('news.php'));
        $breadCrumb[] = $ele;

        return $breadCrumb;
    }

    /**
     * @param NavigationEntry[] $breadCrumb
     * @return NavigationEntry[]
     */
    public function createNewsCategoryBreadcrumb(array $breadCrumb): array
    {
        $ele = new NavigationEntry();
        $ele->setHasChild(false);
        $ele->setName($this->language->get('newskat', 'breadcrumb'));
        $ele->setURL($this->linkService->getStaticRoute('news.php', false));
        $ele->setURLFull($this->linkService->getStaticRoute('news.php'));
        $breadCrumb[] = $ele;

        return $breadCrumb;
    }

    /**
     * @param NavigationEntry[] $breadCrumb
     * @return NavigationEntry[]
     */
    public function createNewsDetailBreadcrumb(array $breadCrumb): array
    {
        $ele = new NavigationEntry();
        $ele->setHasChild(false);
        $ele->setName($this->language->get('news', 'breadcrumb'));
        $ele->setURL($this->linkService->getStaticRoute('news.php', false));
        $ele->setURLFull($this->linkService->getStaticRoute('news.php'));
        $breadCrumb[] = $ele;

        return $breadCrumb;
    }
}
