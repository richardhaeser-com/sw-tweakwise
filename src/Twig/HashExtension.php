<?php declare(strict_types=1);

namespace RH\Tweakwise\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class HashExtension extends AbstractExtension
{

    public function getFunctions(): array
    {
        return [
            new TwigFunction('md5', [$this, 'md5']),
            new TwigFunction('crc32', [$this, 'crc32'])
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('md5', [$this, 'md5']),
            new TwigFilter('crc32', [$this, 'crc32'])
        ];
    }

    public function md5(string $text): string
    {
        return md5($text);
    }

    public function crc32(string $text): string
    {
        return hash('crc32b', $text);
    }
}
