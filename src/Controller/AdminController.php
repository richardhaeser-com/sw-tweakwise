<?php declare(strict_types=1);

namespace RH\Tweakwise\Controller;

use RH\Tweakwise\Api\FrontendApi;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route(defaults: ['_routeScope' => ['administration']])]
class AdminController extends AbstractController
{
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
}
