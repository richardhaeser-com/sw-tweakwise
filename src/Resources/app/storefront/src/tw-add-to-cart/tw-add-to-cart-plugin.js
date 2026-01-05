const Plugin = window.PluginBaseClass;
const PluginManager = window.PluginManager;
import Iterator from 'src/helper/iterator.helper';
import FormSerializeUtil from 'src/utility/form/form-serialize.util';

export default class TwAddToCartPlugin extends Plugin {
    init() {
        this._registerEvents();
    }

    _registerEvents() {
        const twAddToCart = this._addToCart.bind(this);

        window.addEventListener('twAddToCart', twAddToCart);

        document.addEventListener(
            'submit',
            (event) => {
                const form = event.target.closest(
                    'form[data-add-to-cart="true"]'
                );
                if (!form) {
                    return;
                }

                const data = this._extractAddToCartData(form);
                if (data) {
                    this._fireEventTag(data);
                }
            },
            true
        );
    }

    _extractShopwareUUID(input) {
        const match = input.match(/-([a-f0-9]{32})$/i);
        return match ? match[1] : null;
    }
    _addToCart(e) {

        const productId = this._extractShopwareUUID(e.detail.data.itemno);
        if (!productId) {
            console.log('Not a valid product id given', e.detail.itemno);
            return;
        }

        let form = document.createElement('form');
        form.dataset.addToCart = 'true';
        form.setAttribute('action', e.detail.addToCartAction);
        form.setAttribute('method', 'post');
        form.setAttribute('id', 'twn-add-to-cart-form');
        form.setAttribute('data-add-to-cart', 'true');

        let redirectToField = document.createElement('input');
        redirectToField.setAttribute('type', 'hidden');
        redirectToField.setAttribute('name', 'redirectTo');
        redirectToField.setAttribute('value', 'frontend.cart.offcanvas');

        let redirectParametersField = document.createElement('input');
        redirectParametersField.setAttribute('type', 'hidden');
        redirectParametersField.setAttribute('name', 'redirectParameters');
        redirectParametersField.dataset.redirectParameters = 'true';
        redirectParametersField.setAttribute('value', '{"productId": "' + productId + '"}');

        let lineItemIdField = document.createElement('input');
        lineItemIdField.setAttribute('type', 'hidden');
        lineItemIdField.setAttribute('name', 'lineItems[' + productId + '][id]');
        lineItemIdField.setAttribute('value', productId);

        let lineItemReferenceIdField = document.createElement('input');
        lineItemReferenceIdField.setAttribute('type', 'hidden');
        lineItemReferenceIdField.setAttribute('name', 'lineItems[' + productId + '][referencedId]');
        lineItemReferenceIdField.setAttribute('value', productId);

        let lineItemTypeField = document.createElement('input');
        lineItemTypeField.setAttribute('type', 'hidden');
        lineItemTypeField.setAttribute('name', 'lineItems[' + productId + '][type]');
        lineItemTypeField.setAttribute('value', 'product');

        let lineItemStackableField = document.createElement('input');
        lineItemStackableField.setAttribute('type', 'hidden');
        lineItemStackableField.setAttribute('name', 'lineItems[' + productId + '][stackable]');
        lineItemStackableField.setAttribute('value', '1');

        let lineItemRemovableField = document.createElement('input');
        lineItemRemovableField.setAttribute('type', 'hidden');
        lineItemRemovableField.setAttribute('name', 'lineItems[' + productId + '][removable]');
        lineItemRemovableField.setAttribute('value', '1');

        let lineItemQuantityField = document.createElement('input');
        lineItemQuantityField.setAttribute('type', 'hidden');
        lineItemQuantityField.setAttribute('name', 'lineItems[' + productId + '][quantity]');
        lineItemQuantityField.setAttribute('value', '1');

        let csrfTokenField = document.createElement('input');
        csrfTokenField.setAttribute('type', 'hidden');
        csrfTokenField.setAttribute('name', '_csrf_token');
        csrfTokenField.setAttribute('value', e.detail.csrfToken);

        form.appendChild(redirectToField);
        form.appendChild(redirectParametersField);
        form.appendChild(lineItemIdField);
        form.appendChild(lineItemReferenceIdField);
        form.appendChild(lineItemTypeField);
        form.appendChild(lineItemStackableField);
        form.appendChild(lineItemRemovableField);
        form.appendChild(lineItemQuantityField);
        form.appendChild(csrfTokenField);

        const requestUrl = e.detail.addToCartAction;
        const formData = FormSerializeUtil.serialize(form);

        this.$emitter.publish('beforeFormSubmit', formData);
        this._openOffCanvasCarts(requestUrl, formData);
    }

    _openOffCanvasCarts(requestUrl, formData) {
        const offCanvasCartInstances = window.PluginManager.getPluginInstances('OffCanvasCart');
        Iterator.iterate(offCanvasCartInstances, instance => this._openOffCanvasCart(instance, requestUrl, formData));
    }

    _openOffCanvasCart(instance, requestUrl, formData) {
        instance.openOffCanvas(requestUrl, formData, () => {
            this.$emitter.publish('openOffCanvasCart');
        });
    }

    _fireEventTag({ quantity, unitPrice, total, tweakwiseProductId }) {
        tweakwiseLayer.push({
            event: 'addtocart',
            data: {
                productKey: tweakwiseProductId,
                quantity: quantity,
                totalAmount: total
            }
        });
    }

    _extractAddToCartData(form) {
        if (typeof tweakwiseProductId === 'undefined' || !tweakwiseProductId) {
            const tweakwiseProductIdElement = form.querySelector('input[name="tweakwiseProductId"]');
            if (tweakwiseProductIdElement) {
                tweakwiseProductId = tweakwiseProductIdElement.value;
            } else {
                return;
            }
        }

        const qtyInput = form.querySelector('input[name^="lineItems["][name$="[quantity]"]');

        let quantity = 1;

        if (qtyInput) {
            quantity = parseInt(qtyInput.value || '1', 10);
            if (Number.isNaN(quantity) || quantity < 1) quantity = 1;
        }

        let unitPrice = this._getUnitPriceFromDom();
        if (!unitPrice) {
            const tweakwisePriceElement = form.querySelector('input[name="tweakwiseProductPrice"]');
            if (tweakwisePriceElement) {
                unitPrice = tweakwisePriceElement.value;
            } else {
                unitPrice = 0;
            }
        }

        const total = unitPrice !== null ? this._roundMoney(unitPrice * quantity) : 0;

        return {
            quantity,
            unitPrice,
            total,
            tweakwiseProductId
        };
    }

    _getUnitPriceFromDom() {
        const priceEl =
            document.querySelector('.product-detail-price') ||
            document.querySelector('[data-product-price]') ||
            document.querySelector('meta[property="product:price:amount"]');

        if (!priceEl) return null;

        const dataValue = priceEl.getAttribute('data-product-price');
        if (dataValue && !Number.isNaN(parseFloat(dataValue))) {
            return parseFloat(dataValue);
        }

        const metaPropertyValue = priceEl.getAttribute('content');
        if (metaPropertyValue  && !Number.isNaN(parseFloat(metaPropertyValue))) {
            return parseFloat(metaPropertyValue);
        }

        // Fallback: parse uit tekst (bv "€ 19,95")
        const text = (priceEl.textContent || '').trim();
        const normalized = text
            .replace(/\s/g, '')
            .replace(/[^\d,.-]/g, '')
            .replace(/\.(?=\d{3}(\D|$))/g, '')
            .replace(',', '.');

        const value = parseFloat(normalized);
        return Number.isFinite(value) ? value : null;
    }

    _roundMoney(value) {
        return Math.round((value + Number.EPSILON) * 100) / 100;
    }
}
