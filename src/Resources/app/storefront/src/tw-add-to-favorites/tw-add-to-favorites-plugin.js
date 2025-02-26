import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
export default class TwAddToCartPlugin extends Plugin {
    init() {
        this.classList = {
            isLoading: 'product-wishlist-loading',
            addedState: 'product-wishlist-added',
            notAddedState: 'product-wishlist-not-added',
        };

        this._getWishlistStorage();

        if (!this._wishlistStorage) {
            console.warn('No wishlist storage found');
        }
        this._registerEvents();
    }

    _registerEvents() {
        const twNavigationSuccess = this._navigationSuccess.bind(this);
        const twAddToFavorites = this._addToFavorites.bind(this);

        document.$emitter.subscribe('Wishlist/onProductsLoaded', () => {
            wishlistProductsLoaded = true;
        })
        window.addEventListener('twAddToFavorites', twAddToFavorites);
        window.addEventListener('twInitFavorites', twNavigationSuccess);
    }


    _navigationSuccess(e) {
        var products = e.detail.data.items;

        for (var product of products) {
            let shopwareIdAttribute = product.attributes.find(attribute => {
                return attribute.name === 'product_id'
            });
            if (shopwareIdAttribute === undefined) {
                console.log('An error occurred');
            }
            var productId = shopwareIdAttribute.values[0];
            if (!this._wishlistStorage.has(productId)) continue;

            var element = document.getElementById(`twn-${product.itemno}`);
            if (!element) continue;

            element.classList.add('in-wishlist');
        }
    }

    _addToFavorites(e) {
        let shopwareIdAttribute = e.detail.data.attributes.find(attribute => {
            return attribute.name === 'product_id'
        });
        if (shopwareIdAttribute === undefined) {
            console.log('An error occurred');
        }
        const productId = shopwareIdAttribute.values[0];

        const routerAdd = {'path': e.detail.routerAddPath.replace('idPlaceholder', productId), 'afterLoginPath': e.detail.routerAddAfterLoginPath.replace('idPlaceholder', productId)};
        const routerRemove = {'path': e.detail.routerRemovePath.replace('idPlaceholder', productId)};

        var element = document.getElementById(`twn-${e.detail.data.itemno}`);

        if (this._wishlistStorage.has(productId)) {
            this._wishlistStorage.remove(productId, routerRemove);
            if (element) {
                element.classList.remove('in-wishlist');
            }
        } else {
            this._wishlistStorage.add(productId, routerAdd);
            if (element) {
                element.classList.add('in-wishlist');
            }
        }
    }

    _getWishlistStorage() {
        const wishlistBasketElement = DomAccess.querySelector(document, '#wishlist-basket', false);

        if (!wishlistBasketElement) {
            return;
        }

        this._wishlistStorage = window.PluginManager.getPluginInstanceFromElement(wishlistBasketElement, 'WishlistStorage');
        this._wishlistStorage.load();
    }
}
