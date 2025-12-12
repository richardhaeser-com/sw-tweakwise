<?php declare(strict_types=1);

namespace RH\Tweakwise\Twig;

use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CustomFieldValueExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('tw_custom_field_value', [$this, 'getCustomFieldValue']),
        ];
    }

    public function getCustomFieldValue($fieldValue): string
    {
        try {
            if ($fieldValue instanceof PriceCollection) {
                /** @var Price $firstPrice */
                $firstPrice = $fieldValue->first();

                if ($firstPrice instanceof Price) {
                    return (string)$firstPrice->getNet();
                } else {
                    return '';
                }
            } elseif (is_iterable($fieldValue)) {
                $flat = [];
                array_walk_recursive($fieldValue, function($v) use (&$flat) {
                    $flat[] = $v;
                });
                
                return implode(', ', $flat);
            } else {
                return (string)$fieldValue;
            }
        } catch (\Exception $exception) {
            return '';
        }
    }

}
