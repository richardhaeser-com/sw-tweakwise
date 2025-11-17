import './page/sw-product-detail';
import './view/sw-product-detail-tweakwise';
import './component/sw-product-cross-selling-form';
import './component/sw-product-variants/sw-products-variants-delivery/sw-products-variants-delivery-listing';


Shopware.Module.register('sw-new-tab-tweakwise', {
    routeMiddleware(next, currentRoute) {
        const customRouteName = 'sw.product.detail.tweakwise';

        if (
            currentRoute.name === 'sw.product.detail'
            && currentRoute.children.every((currentRoute) => currentRoute.name !== customRouteName)
        ) {
            currentRoute.children.push({
                name: customRouteName,
                path: '/sw/product/detail/:id/tweakwise',
                component: 'sw-product-detail-tweakwise',
                meta: {
                    parentPath: 'sw.product.index'
                }
            });
        }
        next(currentRoute);
    }
});