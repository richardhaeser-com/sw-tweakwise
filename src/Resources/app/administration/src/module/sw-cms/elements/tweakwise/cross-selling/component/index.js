import template from './sw-cms-el-tweakwise-cross-selling.html.twig';
import './sw-cms-el-tweakwise-cross-selling.scss';

const { Mixin } = Shopware;

Shopware.Component.register('sw-cms-el-tweakwise-cross-selling', {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
        Mixin.getByName('placeholder'),
    ],

    computed: {
        demoProductElement() {
            return {
                config: {
                    boxLayout: {
                        source: 'static',
                        value: 'standard',
                    },
                    displayMode: {
                        source: 'static',
                        value: 'standard',
                    },
                    elMinWidth: {
                        source: 'static',
                        value: '200px',
                    },
                },
            };
        },
        tweakwiseCrossSelling() {
            return {
                name: 'Tweakwise Cross Selling',
            };
        },
    }
});
