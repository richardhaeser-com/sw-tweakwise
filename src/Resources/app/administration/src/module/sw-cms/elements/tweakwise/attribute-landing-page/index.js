import './component';
import './preview';
import './config';

Shopware.Service('cmsService').registerCmsElement({
    name: 'tweakwise-attribute-landing-page',
    label: 'sw-cms.elements.tweakwiseAttributeLandingPage.label',
    component: 'sw-cms-el-tweakwise-attribute-landing-page',
    configComponent: 'sw-cms-el-config-tweakwise-attribute-landing-page',
    previewComponent: 'sw-cms-el-preview-tweakwise-attribute-landing-page',
    defaultConfig: {
        category: {
            required: true,
            source: 'static',
            value: ''
        },
        filterTemplate: {
            required: false,
            source: 'static',
            value: ''
        },
        sortTemplate: {
            required: false,
            source: 'static',
            value: ''
        },
        builderTemplate: {
            required: false,
            source: 'static',
            value: ''
        },
        rules: {
            source: 'static',
            value: []
        }
    }
});
