import './component';
import './preview';
import './config';

Shopware.Service('cmsService').registerCmsElement({
    name: 'tweakwise-cross-selling',
    label: 'sw-cms.elements.tweakwiseFeaturedProducts.label',
    component: 'sw-cms-el-tweakwise-cross-selling',
    configComponent: 'sw-cms-el-config-tweakwise-cross-selling',
    previewComponent: 'sw-cms-el-preview-tweakwise-cross-selling',
    defaultConfig: {
        groupKey: {
            required: true,
            source: 'static',
            value: ''
        }
    }
});
