# Tweakwise for Shopware 6 - Documentation
This document will help you to setup the plugin for the default features of Shopware. Please be aware that if you have custom code, you might need to alter the data that will be passed to Tweakwise. Check the  

- [Installation](#installation)
- [Create feed config](#feed-config)
- [Generate feed](#generate-feed)
- [Content of feed](#content-feed)
- [Customize feed](#customize-feed)
- [Create frontend config](#frontend-config)
- [Tweakwise Merchandise / Product Listing Page](#merchandise)
- [Cross-sell recommendations](#cross-sell)
- [Featured products](#featured-products)
- [Events](#events)

<a name="installation"></a>
## Installation
The preferred way to install this plugin is by using [Composer](https://getcomposer.org/). You can easily install the plugin with the following command:

```shell
composer require richardhaeser/sw-tweakwise
```

After you have included the plugin, you can either install and enable the plugin by CLI or by using the admin interface for extension management.

<a name="feed-config"></a>
## Create feed config
As Shopware has the possibility of multiple sales channels as well as multiple languages per sales channel, we need to configure which sales channels and languages need to be exported in the feed for Tweakwise. 

To configure this, you need to follow the next steps:
1. Login in the backend as an administrator
2. Go to Settings > Extensions and click on Tweakwise feed
3. In the top right corner, you will find a button "Create new feed". Click this button
4. You can give the feed a name. This will be only used in the overview of feeds within Shopware and will not be exported. 
5. Select the domains you want to include. The domains you can select are the domains that are connected to a sales channel.
6. You can also define if categories that are excluded from the navigation are added to the Tweakwise feed. Be aware that this will export all categories even if that category does not contain any product. 
7. Click on Save in the top right corner.
8. After you have created the feed and opened it, you can see the feed URL. This is the URL that needs to be given to your Tweakwise onboarding specialist.

> [!IMPORTANT]  
> Please be aware that the feed is only available if it is at least once been generated. See the topic below how to generate the feed.

> [!IMPORTANT]  
> If you are using dynamic product groups, please make sure your indexes are up-to-date. Especially your product_stream_mapping.indexer. You can update your indexes in the admin in `Settings > System > Caches & Indexes` or use the command line method `bin/console dal:refresh:index`.  

<a name="generate-feed"></a>
## Generate feed
To generate the feed, a console command needs to be executed. To do so, you need to run the following command on the command line:

```shell
php bin/console tweakwise:generate-feed -q
```
This will generate the feed. Based on the number of products, sales channels and languages, this process might take some time. On very large instances even more than 10 minutes.

It is recommended to run this command in a cron that runs at least once a day.

<a name="content-feed"></a>
## Content of feed
By default, the feed contains all the basic fields and all set properties of a product. It will **not** include set custom_fields. You need to add the necessary custom fields yourself. See [the chapter about customizing the feed](#customize-feed).

### Categories
For categories, the feed contains all included sales channels and languages you have chosen in the feed configuration. Of each category the name and rank (sorting) will be included in the feed.

### Products
For each product in the feed the following fields are in the feed by default. The marked `fieldnames` are the names used in Tweakwise:
- Name `name`
- Price `price` (based on setting of Shopware, it will show the gross or net price)
- Available stock `stock`
- Manufacturer `brand`
- Url `url` (canonical url)
- Cover image `image` (link to largest thumbnail) 
- Categories `categories` (one or more)
- Selected options `Selected option` (if you have a variant with choosen option size L and color Black, it will return Size: L | Color: black)
- All given attributes `all with their own name`
- Attributes of other variants `all with their own name` (if there are more variants available but you only show 1 variant, the attributes of the other variants will be added as well)
- New product yes / no `sw-new` (based on the settings in Shopware)
- Has discount yes / no `sw-has-discount` (if listPrice and unitPrice differs)
- Popular product yes / no `sw-is-topseller` (based on the popular checkbox of Shopware)
- Search keywords `sw-keywords`
- Tags `sw-tags`
- Product number `sw-product-number`
- Label `sw-label` (sold out, topseller, discount or new in that order)
- GTIN/EAN `sw-ean`
- Manufacturer product number `sw-manufacturer-productnumber` 
- Release date `sw-release-date`
- List price `sw-price-from` (price from ...)
- Net price `sw-price-net`
- Average rating `sw-avg-rating`
- Weight `sw-weight`
- Width `sw-width`
- Height `sw-height`
- Length `sw-length`
- Selling unit `sw-purchase-unit`
- Basic unit `sw-reference-unit`
- Clearance sale `sw-is-closeout`
- Delivery time `sw-delivery-time`
- Description `sw-description` (html stripped, only first 400 chars)
- Lowest price net `sw-price-lowest-net`
- Lowest price gross `sw-price-lowest-gross`
- Lowest price start quantity `sw-price-lowest-quantity-start`
- Lowest price end quantity `sw-price-lowest-quantity-end`
- Highest price net `sw-price-highest-net`
- Highest price gross `sw-price-highest-gross`
- Highest price start quantity `sw-price-highest-quantity-start`
- Highest price end quantity `sw-price-highest-quantity-end`
- Media `sw-media-1` (link to the largest thumbnail of all media starting with number 1. So if you have 2 images for your product, you will have `sw-media-1` and `sw-media-2`. Be aware that the cover image is also one of those images).

As said, you can add your own fields, but always think about do I need that information in my listings or within filtering.


<a name="customize-feed"></a>
## Customize feed
The feed will be created based on Twig templates. You can override these templates the same way as you override templates of other plugins. The only thing that is different is the path. So you can just add your templates in your own theme extension.

### Add product attributes
For example if you want to add an extra attribute to a product within the feed:

Path: _/src/Resources/views/tweakwise/product.xml.twig_
```html
{% sw_extends '@Storefront/tweakwise/product.xml.twig' %}

{% block feed_item_attributes_inner %}
    {{ parent() }}

    <attribute>
        <name>MyCustomAttribute</name>
        <value>{{ product.productNumber }} - {{ product.ean }}</value>
    </attribute>
{% endblock %}
```

### Add data of variants
You can also add some additional information of other variants that are not included in the feed. By default the fields stock, options and properties are added to the feed, but if you, for example, want to add the product numbers of the other variants, you do something like:

Path: _src/Resources/views/tweakwise/otherVariants.xml.twig_
```html
<attribute>
    <name>sw-product-number</name>
    <value>{{ variant.productNumber }}</value>
</attribute>
```

You are also able to add logic to the attributes if you want to add the data to the feed or not. 

If you want to remove the data of variants that are added by default, you can just create an empty file in the path _src/Resources/views/tweakwise/variantAttributes.xml.twig_.

<a name="frontend-config"></a>
## Create frontend config
If you have a good feed, it is imported in Tweakwise and you want to use the JavaScript implementation of the Tweakwise plugin, you can follow the next steps:

1. Login in the backend as an administrator
2. Go to Settings > Extensions and click on Tweakwise frontend
3. In the top right corner, you will find a button "Create new frontend". Click this button
4. You can give the frontend a name. This will be only used in the overview of frontends within Shopware and will not be shown anywhere.
5. Next field is the Authorization key. You can ask this to your Tweakwise onboarding specialist, or if you can already login to Tweakwise, you can find this key in Tweakwise > Connectivity > Endpoints.
6. Next thing is to select the way of integration. At the moment, the only supported integration is the JavaScript version. If you want an own implementation, you can just use no integration.
7. You can choose which way of search you would like to activate. With instant search, you get an overview of the results directly. With suggestions, you get an overview of possible options and a dedicated search page.  
8. Select the domains you want to include. The domains you can select are the domains that are connected to a sales channel. The integration will only be activated on the selected domains.
9. Click on Save in the top right corner. 
10. If you go to the frontend, the search should be replaced by Tweakwise search *

* Please be aware that the search is based on the default templates of Shopware. If you currently have a highly customized search, it might be the case that it is not working out of the box. Please make sure the code in `src/Resources/views/storefront/layout/header/search.html.twig` is incorporated in your own templates in that case.

<a name="merchandise"></a>
## Tweakwise Merchandise / Product Listing Page
To enable the Tweakwise Merchandise feature on your product listing page, you need to alter the Layout in the Shopping Experience module of Shopware. As you can not alter the out-of-the-box product listing page, you need to make a duplicate of the default (or create one from scratch). 

On the product listing page, you have an element called Category listing. If you go to the settings of this element, you see the option `Layout type`. Select the option `Tweakwise Merchandise` and save the element and layout. If you go to the frontend (and you have configured a frontend like described in the paragraph above) you will get the results from Tweakwise. For sure this only works when the products are already imported into Tweakwise. 

<a name="cross-sell"></a>
## Cross-sell
It is possible to use the cross-sell recommendations of Tweakwise in your Shopware shop. To do so, you can create a cross-sell in your product as you would normally do. When adding a cross selling, you can choose for the type `Tweakwise recommendations`. If you have choosen that type, you will get the possibility to add the `Tweakwise Group key` which can be obtained from the Tweakwise app. Be aware that you need to configure the recommendations in Tweakwise first. The output of the cross-sells needs to be styled by the implementation partner as well. 

<a name="featured-products"></a>
## Featured products
If you have configured featured products in your Tweakwise app, you can show those featured products by adding a CMS element called Tweakwise Featured Products. You only have to give the group ID, which can be found in the Tweakwise app, and you are ready to go. The styling of the products, is done by either Tweakwise or you can do it yourselves. You can not change the HTML template though.

<a name="events"></a>
## Events
If you want to alter the criteria or the results of the product query, you can now subscribe to the events below. 

### TweakwiseProductFeedCriteriaEvent
With this event, you are able to alter the criteria that are used querying the products.

### TweakwiseProductFeedResultEvent
To enrich or alter the results of the product query, you are now able to use this event.
