<?php declare(strict_types=1);

namespace RH\Tweakwise\Controller;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class TweakwiseSearchController extends StorefrontController
{
    private GenericPageLoader $pageLoader;

    public function __construct(GenericPageLoader $pageLoader)
    {
        $this->pageLoader = $pageLoader;
    }

    #[Route(path: '/search-results', name: "storefront.tweakwise.search", methods: ['GET'])]
    public function search(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->pageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/search.html.twig', [
            'page' => $page
        ]);
    }
}
