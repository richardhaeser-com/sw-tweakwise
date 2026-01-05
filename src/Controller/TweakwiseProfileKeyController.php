<?php declare(strict_types=1);

namespace RH\Tweakwise\Controller;

use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class TweakwiseProfileKeyController extends StorefrontController
{
    #[Route(
        path: '/tweakwise/profile-key',
        name: 'frontend.tweakwise.profile_key',
        defaults: [
            'XmlHttpRequest' => true,
            '_httpCache' => false
        ],
        methods: ['GET']
    )]
    public function profileKey(Request $request): JsonResponse
    {
        if (!$request->hasSession()) {
            $response = new JsonResponse(['error' => 'No session available'], 400);
            $response->headers->set('Cache-Control', 'no-store');
            return $response;
        }

        $session = $request->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        $profileKey = $session->get('tweakwise_profile_key');
        if (!$profileKey) {
            $profileKey = Uuid::randomHex();
            $session->set('tweakwise_profile_key', $profileKey);
        }

        $response = new JsonResponse(['profileKey' => $profileKey]);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
