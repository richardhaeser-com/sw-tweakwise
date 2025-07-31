import template from './sw-cms-el-config-product-listing.html.twig';

Shopware.Component.override('sw-cms-el-config-product-listing', {
    template,

    computed: {
        boxLayoutOptions() {
            return [
                {
                    id: 1,
                    value: 'standard',
                    label: this.$tc('sw-cms.elements.productBox.config.label.layoutTypeStandard'),
                },
                {
                    id: 2,
                    value: 'image',
                    label: this.$tc('sw-cms.elements.productBox.config.label.layoutTypeImage'),
                },
                {
                    id: 3,
                    value: 'minimal',
                    label: this.$tc('sw-cms.elements.productBox.config.label.layoutTypeMinimal'),
                },
                {
                    id: 50,
                    value: 'tweakwise',
                    label: this.$tc('sw-cms.elements.productBox.config.label.layoutTypeTweakwise'),
                },
            ];
        }
    }
});
