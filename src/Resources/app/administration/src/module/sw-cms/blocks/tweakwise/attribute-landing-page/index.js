import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'tweakwise-attribute-landing-page',
    category: 'tweakwise',
    label: 'Attribute Landing Page',
    component: 'sw-cms-block-tweakwise-attribute-landing-page',
    previewComponent: 'sw-cms-preview-tweakwise-attribute-landing-page',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed'
    },
    slots: {
        attributeLandingPage: 'tweakwise-attribute-landing-page',
    },
});
