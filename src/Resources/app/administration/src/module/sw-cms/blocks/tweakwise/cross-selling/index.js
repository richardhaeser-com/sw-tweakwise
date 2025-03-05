import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'tweakwise-cross-selling',
    category: 'tweakwise',
    label: 'Cross selling',
    component: 'sw-cms-block-tweakwise-cross-selling',
    previewComponent: 'sw-cms-preview-tweakwise-cross-selling',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: '0',
        marginRight: '0',
        sizingMode: 'boxed'
    },
    slots: {
        products: 'tweakwise-cross-selling',
    },
});
