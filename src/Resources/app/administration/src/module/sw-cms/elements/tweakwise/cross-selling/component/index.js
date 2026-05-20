import template from './sw-cms-el-tweakwise-cross-selling.html.twig';
import './sw-cms-el-tweakwise-cross-selling.scss';
import tweakwiseIcon from '../../assets/tweakwise-icon.png';

const { Mixin } = Shopware;

Shopware.Component.register('sw-cms-el-tweakwise-cross-selling', {
    template,

    mixins: [
        Mixin.getByName('cms-element'),
        Mixin.getByName('placeholder'),
    ],

    computed: {
        tweakwiseCrossSelling() {
            return {
                name: 'Tweakwise Cross Selling',
            };
        },

        tweakwiseIcon() {
            return tweakwiseIcon;
        }
    }
});