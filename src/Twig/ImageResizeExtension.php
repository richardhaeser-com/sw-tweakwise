<?php
namespace RH\Tweakwise\Twig;

use RH\Tweakwise\Service\ImageResizeService;
use Shopware\Production\Kernel;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class ImageResizeExtension extends AbstractExtension
{
    private ImageResizeService $imageResizer;

    public function __construct(
        ImageResizeService $imageResizer
    ) {
        $this->imageResizer = $imageResizer;
    }

    public function getFilters()
    {
        return [
            new TwigFilter('rh_imageResize', [$this->imageResizer, 'resizeImage']),
        ];
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('rh_imageResize', [$this->imageResizer, 'resizeImage']),
        ];
    }

}
