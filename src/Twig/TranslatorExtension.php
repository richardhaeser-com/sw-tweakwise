<?php declare(strict_types=1);

namespace RH\Tweakwise\Twig;

use Shopware\Core\Framework\Adapter\Translation\Translator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TranslatorExtension extends AbstractExtension
{
    public function __construct(private readonly Translator $translator)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('rh_translate', $this->translate(...)),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('rh_translate', $this->translate(...)),
        ];
    }

    public function translate(string $text, string $locale): string
    {
        return $this->translator->trans($text, [], null, $locale);
    }
}
