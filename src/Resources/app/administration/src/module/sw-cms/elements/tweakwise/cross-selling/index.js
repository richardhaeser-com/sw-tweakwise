import './component';
import './preview';
import './config';

Shopware.Service('cmsService').registerCmsElement({
    name: 'tweakwise-cross-selling',
    label: 'sw-cms.elements.tweakwiseCrossSelling.label',
    component: 'sw-cms-el-tweakwise-cross-selling',
    configComponent: 'sw-cms-el-config-tweakwise-cross-selling',
    previewComponent: 'sw-cms-el-preview-tweakwise-cross-selling',
    defaultConfig: {
        groupKey: {
            required: true,
            source: 'static',
            value: ''
        },
        itemsDesktop: {
            source: 'static',
            value: null
        },
        itemsTablet: {
            source: 'static',
            value: null
        },
        itemsMobile: {
            source: 'static',
            value: null
        },
        viewDesktop: {
            source: 'static',
            value: null
        },
        viewTablet: {
            source: 'static',
            value: null
        },
        viewMobile: {
            source: 'static',
            value: null
        }
    }
});
