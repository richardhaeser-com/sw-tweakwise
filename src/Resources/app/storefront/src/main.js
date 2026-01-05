import './tw-event-tag/tw-profile-key.bootstrap';
import './tw-event-tag/tw-event-tag.init';
import TwAddToCartPlugin from "./tw-add-to-cart/tw-add-to-cart-plugin";
import TwAddToFavoritesPlugin from "./tw-add-to-favorites/tw-add-to-favorites-plugin";

const PluginManager = window.PluginManager;
const PluginList = PluginManager.getPluginList();

try {
    if (!"TwAddToCartPlugin" in PluginList || !PluginManager.getPlugin('TwAddToCartPlugin')) {
        PluginManager.register('TwAddToCartPlugin', TwAddToCartPlugin)
    }
} catch (error) {
    PluginManager.register('TwAddToCartPlugin', TwAddToCartPlugin)
}

try {
    if (!"TwAddToFavoritesPlugin" in PluginList || !PluginManager.getPlugin('TwAddToFavoritesPlugin')) {
        PluginManager.register('TwAddToFavoritesPlugin', TwAddToFavoritesPlugin)
    }
} catch (error) {
    PluginManager.register('TwAddToFavoritesPlugin', TwAddToFavoritesPlugin)
}
