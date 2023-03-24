<?php
declare(strict_types=1);

namespace RH\Tweakwise\Service;

use League\Flysystem\FileNotFoundException;
use Shopware\Core\Kernel;
use Symfony\Component\Asset\UrlPackage;
use function exec;
use function file_exists;
use function getimagesize;
use function pathinfo;
use function preg_replace;
use function rtrim;
use function sha1;
use function str_replace;

class ImageResizeService
{
    /**
     * @var Kernel
     */
    private $kernel;

    /**
     * @var UrlPackage
     */
    private $urlPackage;

    /**
     * @param Kernel $kernel
     * @param UrlPackage $urlPackage
     */
    public function __construct(
        Kernel $kernel,
        UrlPackage $urlPackage
    ) {
        $this->kernel = $kernel;
        $this->urlPackage = $urlPackage;
    }

    public function resizeImage(string $imageUrl, int $width = null, int $height = null, string $format = null, $crop = false)
    {
        $originalImage = $this->getFileFromImageUrl($imageUrl);
        return $this->getDestinationFileName($originalImage, $width, $height, $format, $crop);
    }

    /**
     * @throws FileNotFoundException
     */
    private function getFileFromImageUrl(string $imageUrl): string
    {
        $imagePath = $this->getPublicDirectory() . str_replace($this->urlPackage->getBaseUrl($imageUrl), '', $imageUrl);
        if (!file_exists($imagePath)) {
            throw new FileNotFoundException($imagePath);
        }

        return $imagePath;
    }

    private function getDestinationFileName(string $originalFilename, ?float $width, ?float $height, ?string $extension, ?bool $crop): string
    {
        $fileInfo = pathinfo($originalFilename);
        $destinationDirectory = str_replace($this->getPublicDirectory() . '/media', $this->getPublicDirectory() . '/thumbnail/generated', $fileInfo['dirname']);
        $filename = $fileInfo['filename'] . '-' . sha1($width . 'x' . $height . ($crop ? 'c' : '')) . '.' . ($extension ?: $fileInfo['extension']) ;
        $destinationFilename = $destinationDirectory . '/' . preg_replace("/[^a-z0-9\_\-\.]/i", '', $filename);

        if (file_exists($destinationFilename)) {
            return $this->getPublicImageUrl($destinationFilename);
        }

        list($widthOriginalFile, $heightOriginalFile, $typeOriginalFile, $attrOriginalFile) = getimagesize($originalFilename);
        $ratioOriginalFile = $widthOriginalFile / $heightOriginalFile;

        if ($width) {
            if (!$crop || !$height) {
                $height = $width / $ratioOriginalFile;
            }
        } elseif ($height) {
            $width = $height * $ratioOriginalFile;
        }

        $command = '';
        if ($width < $widthOriginalFile || $height < $heightOriginalFile)
        {
            $command = '-resize ' . (int)$width . 'x' . (int)$height . '^ ';
        }
        if ($extension === 'webp') {
            $command .= '-quality 95 ';
        }
        if ($crop) {
            $command .= '-gravity center -extent ' . (int)$width . 'x' . (int)$height;
        }

        if ($command) {
            $fileInfoDest = pathinfo($destinationFilename);
            exec('mkdir -p ' . $fileInfoDest['dirname']);
            exec('convert "' . $originalFilename . '" ' . $command . ' "' . $destinationFilename . '"', $output, $resultCode);
        }
        if (file_exists($destinationFilename)) {
            return $this->getPublicImageUrl($destinationFilename);
        }

        return $this->getPublicImageUrl($originalFilename);
    }

    private function getPublicImageUrl(string $imageLocation): string
    {
        return str_replace($this->getPublicDirectory(), rtrim($this->urlPackage->getBaseUrl($imageLocation), '/'), $imageLocation);
    }

    private function getPublicDirectory(): string
    {
        return $this->kernel->getProjectDir() . '/public';
    }
}
