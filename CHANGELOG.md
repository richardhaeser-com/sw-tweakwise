# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [4.5.0] - 15-09-2025

### Added
- An option is introduced in the feed settings to respect the Shopware setting to hide products after clearance. When enabled, products and variants will be excluded from the feed after clearance.

## [4.4.2] - 11-09-2025

### Fixed
- Fixed issue on cross-sell based on last added product to the shopping cart which doesn't have a productnumber like promotions.

## [4.4.1] - 05-09-2025

### Fixed
- Showing sibling categories in category filters is now working correctly in Shopware 6.5 installations as well

## [4.4.0] - 22-07-2025

### Added
- Besides the property “Selected option”, which contains the chosen options for a specific variant in one attribute, we have now introduced the multi-value property “Selected options” in the feed. This property will store the selected options of a variant as separate attributes.

## [4.3.0] - 17-07-2025

### Added
- Added option to show sibling categories in the category filter on the merchandising pages

## [4.2.3] - 15-07-2025

### Fixed
- Added missing getters and setters in Feed Generation events
- The last generation date is now updated as well when you click the refresh button in the feed admin

### Changed
- Added some missing Dutch translations

## [4.2.2] - 11-07-2025

### Fixed
- Adding products to your wishlist is now working again when you are using grouped products

## [4.2.1] - 26-06-2025

### Fixed
- When you have non-string custom fields in your products (like prices), this will now not result in an error when generating the feed
- No error will show up in the browser console when you do not have activated the wishlist in Shopware

## [4.2.0] - 20-06-2025

### Added
- You can now define the batch-size when generating the product feed. When generating the product feed, products are handled in batches to make sure not too much memory is used. You have now the option to set this batch-size in the advanced settings of the feed. Increasing this number will make the generation process faster, but please be aware that the bigger the batch-size the more memory is used. The "sweetspot" is different in every installation and therefor you need to check what works best in your situation. By increasing the batch-size, you will speed-up the generation massively.
- An option is added in the feed-settings to include custom fields of products. Please be aware that enabling this, will add all the available custom fields to a product in the feed and therefor the feed will be bigger. If you don't need those fields in Tweakwise, it is recommended to not enable this option.
- It is now possible to export all variants to Tweakwise. If you enable this feature in the feed settings, it will not check your settings in Shopware whether the main product is shown or one (or more) of the variants. Tweakwise will define which variant is shown based on the search of the user.

### Changed
- The search function will now respect the Browser history mode as set in the Plugin Studio settings

### Fixed
- For the feed generation, the settings for product selection of a category, is respected now. This should fix the problem with too many categories been added to a product in some cases. 

## [4.1.1] - 02-05-2025

### Changed
- Fixed some unclear labels on collecting data
- Removed preloads and added defer tag to plugin studio JS
- Some small UX enhancements in the admin module to manage Tweakwise feeds

## [4.1.0] - 15-04-2025

### Added
- When configuring the frontend in Settings > Extensions > Tweakwise frontend we will now check your instance key and only show options that are available for the selected instance. 
- The Tweakwise Event tag is now implemented. Gathering basic information by default and more personalised information when approved in the Frontend settings of the plugin.

## [4.0.0] - 13-03-2025

> ### BREAKING
> **This is a breaking change. Like every normal update you need to test the update on your environment, but especially the breaking change needs to be tested thoroughly on a non-production environment.**

### Breaking
- This update will respect the visibility settings per sales channel which can be set on a product level. When you hide a product in listings, the product will not be shown in the category listings, but will show up on search. When set to hide in listings and search, the product will only be shown in featured recommendations and cross- & upsells.
- Removed unnecessary config on the search page. When you have implemented handlers for the success events, you might want to check if they are still working.
- The ID in Tweakwise is changed a bit. It now just have the Shopware ID of the product as well as a hash which will represent the domain. If you have implemented your own handlers for add-to-cart and add-to-favorites or your own way of showing cross-sellings, please test it thoroughly. Also make sure to generate a new feed as soon as the plugin is updated as the new add-to-cart and add-to-favorites will not work with the old data.  

### Added
- If for some reason no SEO-url is available for a category or a product, it will now fall-back to the default urls for categories and products. This way, there will always be an url.

## [3.5.5] - 10-03-2025

### Fixed
- The add to favorites JS plugin was exposing the wrong class name. This is now fixed.

## [3.5.4] - 06-03-2025

### Added
- Added the support of add-to-wishlist. If you have a button with an add-to-wishlist / add-to-favorites event on your product tile, it will add the product to the Shopware wishlist.
- The URL of a category is now added to the feed as well. This will make it possible to automatically link to a category in for example category suggestions.
- Added the possibility to add a Cross-Sell element in the Shopping Experience editor in Shopware. The element is only working on a product page and will show the cross-sells based on the groupkey you have set in the element configuration

### Changed
- Rolled back using `Symfony\Component\Routing\Annotation\Route` for controller routes to make sure Shopware 6.5.7 and lower 6.5 versions are also supported.

### Fixed
- Made sure that if you compile the frontend javascript in Shopware 6.5, you won't get errors about the frontend plugins already being registered

## [3.5.3] - 18-02-2025

### Fixed
- Added right locale to the twig rendering context so translations will be handled correctly

## [3.5.2] - 14-02-2025

### Fixed
- If you have expanded config in your variant listing config and it is not active, it will now not have a look at those settings anymore. 

## [3.5.1] - 13-02-2025

### Fixed
- Fixed resetting the instant search when choosing suggestions in Plugin Studio mode
- Fixed language issue in Plugin Studio mode
- Fixed search page showing instant search and merchandise

## [3.5.0] - 12-02-2025

### Added
- An option Plugin studio integration is now available in the Frontend configuration of the plugin. Please check with your customer success manager if this setting is needed for your installation. 

### Changed
- Refactored hashing of products to make them unique in Tweakwise. This refactoring made it sure that the same code is used in feed as well as cross-selling.
- Renamed some field to match the naming within Tweakwise

## [3.4.0] - 11-02-2025

> ### SHOPWARE 6.5 BREAKING
> **We now made sure the 3.x version of the Tweakwise for Shopware plugin, is now working with Shopware 6.5 as well. Please be aware updating from version 2.x to 3.x is a major update with breaking changes. Please test it well before using it in production. For installations that already used v3.x of the plugin, this is not a breaking change!** 

### Fixed
- In Shopware 6.6 instances it is now possible to add the Featured Product CMS element again.
- Use proper product id for recommendations and cross-selling when no variants are used.

## [3.3.1] - 06-02-2025

### Changed
- Some twig block definitions are added to the feed template.

## [3.3.0] - 05-02-2025

### Added
- Added the option to be able to add logic to the data of variants which is added to the feed. Check the [documentation](https://github.com/richardhaeser-com/sw-tweakwise/blob/main/DOC.md#add-data-of-variants) for more information.

## [3.2.0] - 28-01-2025

### Added
- If no specific variant is selected for inclusion in the feed, all variants' options, attributes, and stock levels are now included by default.

## [3.1.0] - 14-01-2025

### Added
- The fields Min. order quantity and Max. order quantity are now added to the feed

### Changed
- Added some twig blocks in the product feed template

### Fixed
- In some cases cross-selling on products with variants didn't work. This update fixes this behaviour and will show cross-sellings based on the variant that is within the Tweakwise feed.
- Fixed search on 404 pages

## [3.0.7] - 24-12-2024

### Fixed
- Fixed issue with categories from dynamic product groups not showing up in feed

## [3.0.6] - 18-12-2024

### Changed
- Added the type of feed and domains of the feed in the admin module

### Fixed
- Fixed issue with categories from other sales channels assigned to a product

## [3.0.5] - 17-12-2024

### Added
- Added events to be able to alter criteria and results before writing the feed to the XML

### Fixed
- Fixed issue with advanced pricing not using the rules attached to the price tier
- Resolved a problem setting the group key while adding a cross-sell to a product
- Made sure all categories are used including categories based on dynamic product groups

### Changed
- Added more twig blocks in templates

## [3.0.4] - 09-09-2024

### Added
- Use cron notation for scheduling feed generation
- Add possibility to start import task after feed generation completed

### Fixed
- Fixed an issue with categories containing products but are set to stream afterwards
- Fixed performance issue with high number of child products causing memory issues

### Changed
- Set default recurring on once a day at 3am

## [3.0.3] - 27-06-2024

### Added
- Added tag as separate attributes in feed

## [3.0.2] - 14-06-2024

### Fixed
- Fixed issue creating new feeds


## [3.0.1] - 14-06-2024

### Added
- In the administration on Settings > Extensions > Tweakwise feed, you can now set the interval for a feed. Besides that, you can force regenerating the specific feed.

### Changed
- Feeds will now be generated by a scheduled task. You can still use the console command which will force a regeneration of the feeds. 

## [3.0.0] - 10-06-2024

### Added
- Support for Shopware 6.6. v3 will be only available for Shopware 6.6. v2.x will still be available for Shopware 6.5 

## [2.8.1] - 05-06-2024

### Fixed
- Fixed wrongly generated JS


## [2.8.0] - 05-06-2024

### Added
- Added possibility to set the way of pagination

### Fixed
- Fixed issue with wrong path when generating feed

## [2.7.2] - 11-04-2024

### Fixed
- Some fixes for PHP 7.4 compatibility 

## [2.7.1] - 11-04-2024

### Fixed
- Some fixes for PHP 7.4 compatibility 

## [2.7.0] - 28-03-2024

### Added
- From now on products that are bound to categories based on dynamic product groups, are also exported. 

## [2.6.1] - 21-03-2024

### Fixed
- If for whatever reason no price can be retrieved, this will not cause an error anymore when the feed is generated
- Fixed extensionkey when resolving the path for the feed templates
- The add to cart button is now also working on cross- and upsell and recommendations

## [2.6.0] - 15-03-2024

### Added
- In the feed configuration you can now define if categories which are hidden in the navigation should be exported for Tweakwise or not.

## [2.5.0] - 12-03-2024

### Added
- In the Tweakwise frontend config, you can now enable checkout sales option. This can be either a featured product group or a cross-sell based on the last product that is added to the cart.
- Added the possibility to set the number of products per row in the frontend. You can set a value for desktop, tablet and mobile devices.

### Fixed
- The settings for feed and frontend are now available again in Shopware 6.4 settings

## [2.4.4] - 13-02-2024

### Added
- You can now use the CMS element Tweakwise Featured Products in the Shopping Experience. With this element you can show featured products as specified in the Tweakwise App in your shop.

### Changed
- The add to cart button is now using the offcanvas cart instead of forwarding to the PDP after adding the product to the shopping cart.
 
### Fixed
- Rules will now be applied to advanced pricing options when generating the feed

## [2.4.3] - 25-01-2024

> ### Important
> **Due to the regulation prices, we had to update the minimal Shopware version to 6.4.10** 

### Added
- Add list-price and regulation-price to lowest and highest advanced pricing
- Properties of included variants are now added to the feed as well

## [2.4.2] - 15-12-2023

### Fixed
- Fixed issue cross-sellings not showing up


## [2.4.1] - 14-12-2023

### Fixed
- Fixed issue with older PHP-versions and extra comma in class header

## [2.4.0] - 12-12-2023

### Added
- Added cross-sell recommendations feature

## [2.3.0] - 01-12-2023

### Added
- Added category and facet options to the suggestions
- Added sales channel information to product feed

### Changed
- The template of the dedicated search page is changed a bit 

### Fixed
- Now the right domain of a category is showing up in the feed

## [2.2.1] - 23-11-2023

### Added
- Added a block in feed for labels so it is easier to override the labels set in the feed

## [2.2.0] - 22-11-2023

### Added
- Added the option to use suggestions. You can configure the way of search in your Tweakwise Frontend configuration.

### Fixed
- Fixed issue with not-showing icons in the admin.

## [2.1.8] - 16-11-2023

### Added
- The following fields are added to the feed: `sw-price-lowest-net`, `sw-price-lowest-gross`, `sw-price-lowest-quantity-start`, `sw-price-lowest-quantity-end`, `sw-price-highest-net`, `sw-price-highest-gross`, `sw-price-highest-quantity-start`, `sw-price-lowest-highest-end`, `sw-media-1`. See [docs](DOC.md) for more information.

## [2.1.7] - 14-11-2023

### Added
- The following fields are added to the feed: `sw-weight`, `sw-width`, `sw-height`, `sw-length`, `sw-is-closeout`, `sw-delivery-time`, `sw-description`, `sw-purchase-unit`, `sw-reference-unit` 

### Changed
- Made it more clear in the docs which fields are in the feed. It will now show both label as in the admin as well as the name of the field how it will be exported to Tweakwise.

### Fixed
- Fixed the compatibility with 6.4. You can now use this version again in your Shopware 6.5 installation.

## [2.1.6] - 09-11-2023

### Changed
- The plugin now supports Shopware 6.5!!

## [2.1.5] - 27-10-2023

### Added
- Added documentation at [GitHub](https://github.com/richardhaeser-com/sw-tweakwise/blob/main/DOC.md)

### Fixed
- By adding a sorting in the feed, no products are skipped in the feed.
- The net price in the feed is now also working properly if the price if you have a minimum order quantity

## [2.1.4] - 28-09-2023

### Fixed
- Use proper context to calculate advanced pricing 

### Changed
- Removed own way of generating thumbnails and fall back on generated thumbnails of Shopware

### Added
- Added tags of product in feed property sw-tags. This is a comma seperated list of all tags assigned to the product.

## [2.1.3] - 31-08-2023

### Changed
- Added check if variants are active before adding it to the feed

## [2.1.2] - 28-08-2023

### Changed
- Added twig block around product image to be able to override the image handling

## [2.1.1] - 28-08-2023

### Fixed
- Export right variants of products based on the settings of the products

## [2.1.0] - 05-07-2023

### Added
- Add net price as default attribute (thnx @ruudwelten)

### Fixed
- Add absolute feed path for execution from anywhere (thnx @ruudwelten)

### Changed
- Changed the way the feed is generated and respect the variant listing settings again
- Check if discount is actually present (thnx @ruudwelten)
- Remove check on age of feed when rendering in frontend

## [2.0.1] - 14-06-2023

### Fixed
- You can now create new feeds again after fixing a JavaScript error in the admin module.

## [2.0.0] - 13-06-2023

> ### BREAKING
> **Be aware that this is a breaking change! Besides that you need to reconfigure the frontend (see changed), the URL of your feed will also change. You can find the right URL of the feed in the Feed settingw**

### Added
- You can now define one or more feeds and decide which sales channel domains should be included. With this option you can make sure only the information that is needed will be exported to Tweakwise.
- Besides having the option to create one or more feeds, you can also create multiple frontends. These frontends holds the configuration with the instance key as well as the way of frontend implementation. As well as with feeds, you can also select sales channel domains to enable the implementation only for certain languages.

### Changed
- Some massive performance updates are done. Especially feeds with 25k products or more will benefit from this update
- The configuration of the plugin is moved from the custom fields in the sales channel properties, to the Settings > Extension module. You will find two new modules there to manage the feeds and the frontends. 

## [1.0.4] - 24-04-2023

### Fixed
- Added fallback in progressbar content to prevent critical error

### Changed
- Changed the way of defining the categories so the order of categories is as it is defined and disabled categories will not show up in the feed.
- Feed will only be generated by console command to prevent realtime generation of feed and causing performance issues

## [1.0.3] - 20-04-2023

### Changed
- Optimized the feed to only link products to categories that are exported
- Improved performance of feed generation

## [1.0.2] - 12-04-2023

### Changed
- Make the extension work from Shopware v6.4.6

### Fixed
- Removed wrong association to customField
- In the template of the feed, the price was rounded while this was already done in the generation of data which could lead to wrongly rounded prices. In the template we don't do any rounding of prices anymore.

## [1.0.1] - 31-03-2023

### Fixed
- Search is now only searching in the root category of the current shop

### Changed
- Labels on the result pages are now translated based on the language of the domain
- By changing a bit of code, we are now supporting Shopware 6.4.10 and higher
- Some inline Javascript is moved towards a dedicated JS file


## [1.0.0] - 28-03-2023

### Added
- Added automatic checks to find bugs earlier
- Made ready for Shopware store

## [0.9.6] - 28-03-2023

### Changed
- **[BREAKING]** The extension key is changed from RhTweakwise to RhaeTweakwise to comply to the rules of the Shopware store. Please make sure to install and activate the right plugin after you update the extension. 

## [0.9.5] - 27-03-2023

### Fixed
- Added right annotation for translator to prevent errors in logs 

## [0.9.4] - 27-03-2023

### Added
- Added translator to make sure labels can be translated even when the feed is built on CLI

## [0.9.3] - 27-03-2023

### Changed
- Generation of the feed is done different now. The feed is not based on a product comparison feed, but it is based on code within the plugin. If you are using the old way, this will still work, but please be aware of the new possibility of the feed. See the documentation for more information.

### Fixed
- Added some checks if we can add the custom fields of the sales channel to the page

## [0.9.2] - 07-03-2023

### Added
- A label property is now added to the product feed

### Fixed
- Fixed issue that was hiding results when submitting search form with basic javascript integration
- Resolved an issue with the subscriber throwing an error on async reloads of the frontend

## [0.9.1] - 03-03-2023

### Added
- Possibility to enable standard javascript implementation
- Added attributes new, discount and topseller to product feed

### Changed
- Added link to this changelog to the readme of the extension
- **[BREAKING]** Changed extension key from Tweakwise to RhTweakwise

### Fixed
- Exclude property group when one of the properties of that group was already set as a variant option.

## [0.9.0] - 24-02-2023

### Added
- Sales channel templates for Tweakwise product feed
