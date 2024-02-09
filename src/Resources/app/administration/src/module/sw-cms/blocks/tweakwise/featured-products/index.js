import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'tweakwise-featured-products',
    category: 'tweakwise',
    label: 'Featured products',
    component: 'sw-cms-block-tweakwise-featured-products',
    previewComponent: 'sw-cms-preview-tweakwise-featured-products',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed'
    },
    slots: {
        products: 'tweakwise-featured-products',
    },
});
