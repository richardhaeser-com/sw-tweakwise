import template from './sw-cms-el-tweakwise-featured-products.html.twig';
import './sw-cms-el-tweakwise-featured-products.scss';
import tweakwiseIcon from '../../assets/tweakwise-icon.png';

Shopware.Component.register('sw-cms-el-tweakwise-featured-products', {
    template,

    computed: {
        tweakwiseIcon() {
            return tweakwiseIcon;
        }
    }
});
