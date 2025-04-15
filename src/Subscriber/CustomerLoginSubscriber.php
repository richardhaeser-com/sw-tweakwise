<?php declare(strict_types=1);

namespace RH\Tweakwise\Subscriber;

use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CustomerLoginSubscriber implements EventSubscriberInterface
{
    private RequestStack $requestStack;
    private EntityRepository $customerRepository;

    public function __construct(RequestStack $requestStack, EntityRepository $customerRepository)
    {
        $this->requestStack = $requestStack;
        $this->customerRepository = $customerRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerLoginEvent::class => 'onCustomerLogin'
        ];
    }

    public function onCustomerLogin(CustomerLoginEvent $event): void
    {
        $session = $this->requestStack->getCurrentRequest()->getSession();
        $profileKey = $session->get('tweakwise_profile_key');

        if (!$profileKey) {
            $profileKey = Uuid::randomHex();
            $session->set('tweakwise_profile_key', $profileKey);
        }

        $customer = $event->getCustomer();
        $customFields = $customer->getCustomFields() ?? [];

        // Als het custom field nog niet gevuld is, vul het met de sessiewaarde
        if (empty($customFields['tweakwise_profile_key'])) {
            $customFields['tweakwise_profile_key'] = $profileKey;

            $this->customerRepository->update([
                [
                    'id' => $customer->getId(),
                    'customFields' => $customFields,
                ]
            ], $event->getContext());
        } else {
            // Als het custom field al gevuld is, pas dan de sessie aan zodat deze de bestaande waarde bevat
            $session->set('tweakwise_profile_key', $customFields['tweakwise_profile_key']);
        }
    }
}
