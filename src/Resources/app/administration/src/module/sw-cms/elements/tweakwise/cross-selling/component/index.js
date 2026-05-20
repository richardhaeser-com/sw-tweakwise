import template from './sw-cms-el-tweakwise-cross-selling.html.twig';
import './sw-cms-el-tweakwise-cross-selling.scss';
import tweakwiseIcon from '../../assets/tweakwise-icon.png';

Shopware.Component.register('sw-cms-el-tweakwise-cross-selling', {
    template,

    computed: {
        tweakwiseIcon() {
            return tweakwiseIcon;
        }
    }
});