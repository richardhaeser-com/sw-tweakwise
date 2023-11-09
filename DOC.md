# Tweakwise for Shopware 6 - Documentation
This document will help you to setup the plugin for the default features of Shopware. Please be aware that if you have custom code, you might need to alter the data that will be passed to Tweakwise. Check the  

- [Create feed config](#feed-config)
- [Generate feed](#generate-feed)
- [Customize feed](#customize-feed)
- [Create frontend config](#frontend-config)

<a name="feed-config"></a>
## Create feed config
As Shopware has the possibility of multiple sales channels as well as multiple languages per sales channel, we need to configure which sales channels and languages need to be exported in the feed for Tweakwise. 

To configure this, you need to follow the next steps:
1. Login in the backend as an administrator
2. Go to Settings > Extensions and click on Tweakwise feed
3. In the top right corner, you will find a button "Create new feed". Click this button
4. You can give the feed a name. This will be only used in the overview of feeds within Shopware and will not be exported. 
5. Select the domains you want to include. The domains you can select are the domains that are connected to a sales channel.
6. Click on Save in the top right corner.
7. After you have created the feed and opened it, you can see the feed URL. This is the URL that needs to be given to your Tweakwise onboarding specialist.

**Please be aware that the feed is only available if it is at least once been generated. See the topic below how to generate the feed**

<a name="generate-feed"></a>
## Generate feed
To generate the feed, a console command needs to be executed. To do so, you need to run the following command on the command line:

```shell
php bin/console tweakwise:generate-feed -q
```
This will generate the feed. Based on the number of products, sales channels and languages, this process might take some time. On very large instances even more than 10 minutes.

It is recommended to run this command in a cron that runs at least once a day.

<a name="customize-feed"></a>
## Customize feed
The feed will be created based on Twig templates. You can override these templates the same way as you override templates of other plugins. The only thing that is different is the path. So you can just add your templates in your own theme extension.

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

<a name="frontend-config"></a>
## Create frontend config
If you have a good feed, it is imported in Tweakwise and you want to use the JavaScript implementation of the Tweakwise plugin, you can follow the next steps:

1. Login in the backend as an administrator
2. Go to Settings > Extensions and click on Tweakwise frontend
3. In the top right corner, you will find a button "Create new frontend". Click this button
4. You can give the frontend a name. This will be only used in the overview of frontends within Shopware and will not be shown anywhere.
5. Next field is the Authorization key. You can ask this to your Tweakwise onboarding specialist, or if you can already login to Tweakwise, you can find this key in Tweakwise > Connectivity > Endpoints.
6. Next thing is to select the way of integration. At the moment, the only supported integration is the JavaScript version. If you want an own implementation, you can just use no integration.
7. Select the domains you want to include. The domains you can select are the domains that are connected to a sales channel. The integration will only be activated on the selected domains.
8. Click on Save in the top right corner. 
9. If you go to the frontend, the search should be replaced by Tweakwise search *

* Please be aware that the search is based on the default templates of Shopware. If you currently have a highly customized search, it might be the case that it is not working out of the box. Please make sure the code in `src/Resources/views/storefront/layout/header/search.html.twig` is incorporated in your own templates in that case.


