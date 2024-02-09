import './component';
import './preview';
import './config';

Shopware.Service('cmsService').registerCmsElement({
    name: 'tweakwise-featured-products',
    label: 'sw-cms.elements.tweakwiseFeaturedProducts.label',
    component: 'sw-cms-el-tweakwise-featured-products',
    configComponent: 'sw-cms-el-config-tweakwise-featured-products',
    previewComponent: 'sw-cms-el-preview-tweakwise-featured-products',
    defaultConfig: {
        groupId: {
            required: true,
            source: 'static',
            value: ''
        }
    }
});
