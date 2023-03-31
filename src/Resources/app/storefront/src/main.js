import TwAddToCartPlugin from "./tw-add-to-cart/tw-add-to-cart-plugin";

const PluginManager = window.PluginManager;
PluginManager.register('TwAddToCartPlugin', TwAddToCartPlugin)
