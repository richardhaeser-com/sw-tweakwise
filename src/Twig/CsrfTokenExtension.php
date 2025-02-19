<?php declare(strict_types=1);

namespace RH\Tweakwise\Twig;

use Shopware\Core\Framework\Feature;
use Shopware\Storefront\Framework\Csrf\CsrfPlaceholderHandler;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use function sprintf;
use function version_compare;

class CsrfTokenExtension extends AbstractExtension
{
    private string $shopwareVersion;

    public function __construct(string $shopwareVersion)
    {
        $this->shopwareVersion = $shopwareVersion;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('twCsrfToken', [$this, 'getToken']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('twCsrfToken', [$this, 'getToken']),
        ];
    }

    public function getToken(string $intent, array $parameters = []): string
    {
        if (version_compare($this->shopwareVersion, '6.5.0', '>=')) {
            return '';
        }

        return $this->createCsrfPlaceholder($intent, $parameters);
    }

    public function createCsrfPlaceholder(string $intent, array $parameters = []): string
    {
        $mode = $parameters['mode'] ?? 'input';

        if ($mode === 'input') {
            return $this->createInput($intent);
        }

        return CsrfPlaceholderHandler::CSRF_PLACEHOLDER . $intent . '#';
    }

    private function createInput(string $intent): string
    {
        return sprintf(
            '<input type="hidden" name="_csrf_token" value="%s">',
            CsrfPlaceholderHandler::CSRF_PLACEHOLDER . $intent . '#'
        );
    }
}
