<?php declare(strict_types=1);

namespace RH\Tweakwise\Subscriber;

use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CustomerLoginSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly RequestStack $requestStack, private readonly EntityRepository $customerRepository)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerLoginEvent::class => 'onCustomerLogin'
        ];
    }

    public function onCustomerLogin(CustomerLoginEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request || !$request->hasSession()) {
            return;
        }

        $route = (string) $request->attributes->get('_route', '');
        if ($route === 'payment.finalize.transaction') {
            return;
        }

        $session = $request->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        $customer = $event->getCustomer();
        $customFields = $customer->getCustomFields() ?? [];

        $customerProfileKey = $customFields['tweakwise_profile_key'] ?? null;
        $sessionProfileKey = $session->get('tweakwise_profile_key');

        // Bron van waarheid:
        // 1) bestaande customer key
        // 2) bestaande session key
        // 3) nieuwe key
        $finalProfileKey = $customerProfileKey ?: ($sessionProfileKey ?: Uuid::randomHex());

        // Sessie altijd gelijk trekken (overschrijft dus indien nodig)
        $session->set('tweakwise_profile_key', $finalProfileKey);

        // Customer custom field vullen als die nog leeg is
        if (!$customerProfileKey) {
            $customFields['tweakwise_profile_key'] = $finalProfileKey;

            $this->customerRepository->update([[
                'id' => $customer->getId(),
                'customFields' => $customFields,
            ]], $event->getContext());
        }
    }
}
