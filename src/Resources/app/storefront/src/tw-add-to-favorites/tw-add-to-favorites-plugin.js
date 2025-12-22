import Plugin from 'src/plugin-system/plugin.class';
import DomAccess from 'src/helper/dom-access.helper';
export default class TwAddToFavoritesPlugin extends Plugin {
    init() {
        var wishlistLoaded = 'WishlistStorage' in window.PluginManager.getPluginList();
        if (wishlistLoaded) {
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
    }

    _registerEvents() {
        const twNavigationSuccess = this._navigationSuccess.bind(this);
        const twAddToFavorites = this._addToFavorites.bind(this);
        const fireEventTag = this._fireEventTag.bind(this);

        document.$emitter.subscribe('Wishlist/onProductsLoaded', () => {
            wishlistProductsLoaded = true;
        })
        window.addEventListener('twAddToFavorites', twAddToFavorites);
        window.addEventListener('twInitFavorites', twNavigationSuccess);

        document.querySelector('button[data-add-to-wishlist]').addEventListener('click', fireEventTag)
    }


    _navigationSuccess(e) {
        if (e.detail.data.hasOwnProperty('items')) {
            var products = e.detail.data.items;

            for (var product of products) {
                this._checkProductFavorite(product);
            }
        }
        if (e.detail.data.hasOwnProperty('groups')) {
            for (var group of e.detail.data.groups) {
                for (var product of group.items) {
                    this._checkProductFavorite(product);
                }
            }
        }
    }

    _checkProductFavorite(product) {
        const productId = this._extractShopwareUUID(product.itemno);
        if (!productId) {
            console.log('Not a valid product id given', product.itemno);
            return;
        }
        if (!this._wishlistStorage.has(productId)) return;

        var elements = document.querySelectorAll(`[data-item-id="${product.itemno}"]`);
        elements.forEach(element => {
            element.classList.add('in-wishlist');
        });
    }
    _extractShopwareUUID(input) {
        const match = input.match(/-([a-f0-9]{32})$/i);
        return match ? match[1] : null;
    }

    _addToFavorites(e) {
        const productId = this._extractShopwareUUID(e.detail.data.itemno);
        if (!productId) {
            console.log('Not a valid product id given', e.event.detail.itemno);
            return;
        }

        const routerAdd = {'path': e.detail.routerAddPath.replace('idPlaceholder', productId), 'afterLoginPath': e.detail.routerAddAfterLoginPath.replace('idPlaceholder', productId)};
        const routerRemove = {'path': e.detail.routerRemovePath.replace('idPlaceholder', productId)};

        var elements = document.querySelectorAll(`[data-item-id="${e.detail.data.itemno}"]`);

        if (this._wishlistStorage.has(productId)) {
            this._wishlistStorage.remove(productId, routerRemove);
            elements.forEach(element => {
                element.classList.remove('in-wishlist');
            })
        } else {
            this._wishlistStorage.add(productId, routerAdd);
            elements.forEach(element => {
                element.classList.add('in-wishlist');
            })
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

    _fireEventTag() {
        if (typeof tweakwiseProductId === 'undefined' || !tweakwiseProductId) {
            return;
        }

        tweakwiseLayer.push({
            event: 'addtowishlist',
            data: {
                productKey: tweakwiseProductId,
            }
        });
    }
}
