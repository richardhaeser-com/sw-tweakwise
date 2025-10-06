<?php declare(strict_types=1);

namespace RH\Tweakwise\Controller;

use RH\Tweakwise\Api\BackendApi;
use RH\Tweakwise\Api\FrontendApi;
use RH\Tweakwise\Core\Content\Frontend\FrontendEntity;
use RH\Tweakwise\Service\ProductDataService;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['administration']])]
class AdminController extends AbstractController
{
    public function __construct(private EntityRepository $frontendRepository, private EntityRepository $productRepository)
    {

    }
    #[Route('/api/_action/rhae-tweakwise/check-possibilities/{token}', name: 'rhae.tweakwise.check_possibilities', methods: ['GET'])]
    public function checkFieldVisible(string $token): JsonResponse
    {
        $frontendApi = new FrontendApi($token);
        $instanceData = $frontendApi->getInstance();
        $validToken = $instanceData['validToken'] ?: false;
        $features = [];
        if (array_key_exists('features', $instanceData)) {
            foreach ($instanceData['features'] as $featureLine) {
                $features[$featureLine['name']] = $featureLine['value'];
            }
        }

        return new JsonResponse([
            'validToken' => $validToken,
            'features' => $features,
            'token' => $token
        ]);
    }

    #[Route('/api/_action/rhae-tweakwise/check-data/{frontendId}/{productId}', name: 'rhae.tweakwise.check_data', methods: ['GET'])]
    public function getTweakwiseProductData(string $frontendId, string $productId, Context $context): JsonResponse
    {
        $product = $this->productRepository->search(new Criteria([$productId]), $context)->first();
        if (!$product instanceof ProductEntity) {
            return new JsonResponse(['frontendId' => $frontendId, 'productId' => $productId, 'error' => true, 'message' => 'Product not found.']);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $frontendId));
        $criteria->addAssociation('salesChannelDomains');

        $frontend = $this->frontendRepository->search($criteria, $context)->first();
        if (!$frontend instanceof FrontendEntity) {
            return new JsonResponse(['frontendId' => $frontendId, 'productId' => $productId, 'product' => $product, 'error' => true, 'message' => 'Frontend not found.']);
        }

        $productIdHash = ProductDataService::getTweakwiseProductId($product, $frontend->getSalesChannelDomains()->first()->getId());
        if (!$frontend->getAccessToken()) {
            return new JsonResponse(['frontendId' => $frontendId, 'productId' => $productIdHash, 'product' => $product, 'error' => true, 'message' => 'No access token.']);
        }


        $backendApi = new BackendApi($frontend->getToken(), $frontend->getAccessToken());
        $productData = $backendApi->getProductData($product, $frontend->getSalesChannelDomains()->first()->getId());

        if (array_key_exists('error', $productData)) {
            return new JsonResponse(['frontendId' => $frontendId, 'productId' => $productIdHash, 'product' => $product, 'error' => true, 'code' => $productData['code'], 'message' => $productData['message']]);
        }
        return new JsonResponse(['frontendId' => $frontendId, 'productId' => $productIdHash, 'product' => $product, 'productData' => $productData]);
    }
}
