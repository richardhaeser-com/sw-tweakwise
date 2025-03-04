import TwAddToCartPlugin from "./tw-add-to-cart/tw-add-to-cart-plugin";
import TwAddToFavoritesPlugin from "./tw-add-to-favorites/tw-add-to-favorites-plugin";

const PluginManager = window.PluginManager;
try {
    if (!PluginManager.getPlugin('TwAddToCartPlugin')) {
        PluginManager.register('TwAddToCartPlugin', TwAddToCartPlugin)
    }
} catch (error) {
    PluginManager.register('TwAddToCartPlugin', TwAddToCartPlugin)
}

try {
    if (!PluginManager.getPlugin('TwAddToFavoritesPlugin')) {
        PluginManager.register('TwAddToFavoritesPlugin', TwAddToFavoritesPlugin)
    }
} catch (error) {
    PluginManager.register('TwAddToFavoritesPlugin', TwAddToFavoritesPlugin)
}
