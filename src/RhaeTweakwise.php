<?php declare(strict_types=1);

namespace RH\Tweakwise;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetEntity;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationEntity;
use Shopware\Core\System\CustomField\CustomFieldEntity;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use function array_search;

class RhaeTweakwise extends Plugin
{
    public const CUSTOM_FIELD_TWEAKWISE_FIELD_SET = 'rh_tweakwise';
    public const CUSTOM_FIELD_TWEAKWISE_INSTANCEKEY = 'rh_tweakwise_instancekey';

    public function install(InstallContext $installContext): void
    {
        $this->createCustomFields($installContext->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
        $this->createCustomFields($updateContext->getContext());
    }

    private function createCustomFields($context): void
    {
        /** @var EntityRepositoryInterface $customFieldSetRepository */
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        foreach ($this->getCustomFields() as $customFieldSet) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('name', $customFieldSet['name']));
            $criteria->addAssociation('relations');
            $criteria->addAssociation('customFields');
            $existingCustomFieldSet = $customFieldSetRepository->search($criteria, $context)->first();

            if ($existingCustomFieldSet) {
                /** @var CustomFieldSetEntity $existingCustomFieldSet */
                $customFieldSet['id'] = $existingCustomFieldSet->getId();
                if (!empty($customFieldSet['relations'])) {
                    $existingRelations = [];
                    foreach ($existingCustomFieldSet->getRelations() as $field) {
                        /** @var CustomFieldSetRelationEntity $field */
                        $existingRelations[$field->getId()] = $field->getEntityName();
                    }
                    $relations = [];
                    foreach ($customFieldSet['relations'] as $relation) {
                        if (array_search($relation['entityName'], $existingRelations)) {
                            $relation['id'] = array_search($relation['entityName'], $existingRelations);
                        }
                        $relations[] = $relation;
                    }
                    $customFieldSet['relations'] = $relations;
                }

                if (!empty($customFieldSet['customFields'])) {
                    $existingCustomFields = [];
                    foreach ($existingCustomFieldSet->getCustomFields() as $field) {
                        /** @var CustomFieldEntity $field */
                        $existingCustomFields[$field->getId()] = $field->getName();
                    }
                    $customFields = [];
                    foreach ($customFieldSet['customFields'] as $customField) {
                        if (array_search($customField['name'], $existingCustomFields)) {
                            $customField['id'] = array_search($customField['name'], $existingCustomFields);
                        }
                        $customFields[] = $customField;
                    }
                    $customFieldSet['customFields'] = $customFields;
                }
            }
            $customFieldSetRepository->upsert([$customFieldSet], $context);
        }
    }

    private function getCustomFields()
    {
        $customFields = [
            'rh_tweakwise' => [
                'name' => 'rh_tweakwise',
                'config' => [
                    'label' => [
                        'de-DE' => 'Tweakwise',
                        'en-GB' => 'Tweakwise',
                        'nl-NL' => 'Tweakwise'
                    ]
                ],
                'relations' => [['entityName' => 'sales_channel']],
                'customFields' => [
                    [
                        'name' => 'rh_tweakwise_instancekey',
                        'type' => CustomFieldTypes::TEXT,
                        'config' => [
                            'label' => [
                                'en-GB' => 'Instance key',
                                'de-DE' => 'Instance key',
                                'nl-NL' => 'Instance key'
                            ],
                            'helpText' => [
                                'en-GB' => 'You can find your instance key in the Tweakwise Navigator in Connectivity > Endpoints',
                                'de-DE' => 'Es werden die Produkte in Ihrem Warenkorb angezeigt.',
                                'nl-NL' => 'Je kunt de instance key vinden in de Tweakwise Navigator onder Connectivity > Endpoints'
                            ],
                            'customFieldPosition' => 1
                        ]
                    ],
                    [
                        'name' => 'rh_tweakwise_exclude_from_feed',
                        'type' => CustomFieldTypes::CHECKBOX,
                        'config' => [
                            'label' => [
                                'en-GB' => 'Exclude sales-channel from product feed',
                                'de-DE' => 'Schließen Sie den Vertriebskanal aus dem Produkt-Feed aus',
                                'nl-NL' => 'Verkoopkanaal uitsluiten van product feed'
                            ],
                            'customFieldPosition' => 2
                        ]
                    ],
                    [
                        'name' => 'rh_tweakwise_integration_type',
                        'type' => CustomFieldTypes::SELECT,
                        'config' => [
                            'componentName' => 'sw-single-select',
                            'customFieldType' => 'select',
                            'label' => [
                                'en-GB' => 'Way of integration',
                                'de-DE' => 'Weg der Integration',
                                'nl-NL' => 'Manier van integratie'
                            ],
                            'customFieldPosition' => 3,
                            'options' => [
                                [
                                    'value' => 'no-integration',
                                    'label' => [
                                        'en-GB'=> 'No integration (only product feed)',
                                        'de-DE' => 'Keine Integration (nur Produktfeed)',
                                        'nl-NL' => 'Geen integratie (alleen product feed)'
                                    ]
                                ],
                                [
                                    'value' => 'js-basic',
                                    'label' => [
                                        'en-GB'=> 'Basic JavaScript integration',
                                        'de-DE' => 'Standard JavaScript integration',
                                        'nl-NL' => 'Standaard JavaScript integratie'
                                    ]
                                ],
                            ]
                        ]
                    ],
                ]
            ]
        ];

        return $customFields;
    }

}
