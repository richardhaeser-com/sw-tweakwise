import TwAddToCartPlugin from "./tw-add-to-cart/tw-add-to-cart-plugin";
import TwAddToFavoritesPlugin from "./tw-add-to-favorites/tw-add-to-favorites-plugin";

const PluginManager = window.PluginManager;
PluginManager.register('TwAddToCartPlugin', TwAddToCartPlugin)
PluginManager.register('TwAddToFavoritesPlugin', TwAddToFavoritesPlugin)
