import header from './header.xml.twig';
import body from './body.xml.twig';
import footer from './footer.xml.twig';

Shopware.Service('exportTemplateService').registerProductExportTemplate({
    name: 'tweakwise',
    translationKey: 'sw-sales-channel.detail.productComparison.templates.template-label.tweakwise',
    headerTemplate: header.trim(),
    bodyTemplate: body,
    footerTemplate: footer.trim(),
    fileName: 'tweakwise.xml',
    encoding: 'UTF-8',
    fileFormat: 'xml',
    generateByCronjob: false,
    interval: 86400,
});
