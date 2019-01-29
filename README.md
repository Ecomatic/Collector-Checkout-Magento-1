# Collector-Checkout-Magento-1
Collector Checkout for Magento 1

## Firewall
If you are using a firewall some urls need to be opened to be able to use this plugin, those are:
* ecommercetest.collector.se
* ecommerce.collector.se
* checkout-api-uat.collector.se
* checkout-api.collector.se

## Settings
Enter Settings into System -> Configuration -> Ecomatic -> Collector Bank

	note: include protocol in terms url, i.e. https://example.com/terms
	
	
System -> Configuration -> General -> General -> Countries Options -> Default Country must be set to either "Sverige" or "Norge"


Currencies must be set to either Swedish or Norwegian Crowns and must align with country setting

	if default country is Sweden currency must be set to Swedish Crowns

	if default country is Norway currency must be set to Norwegian Crowns

	
System -> Configuration -> Genral -> Web -> Session Validation Settings -> Use SID on Frontend to "No".

## Version 1.0
### Changed the checkout flow
The new checkout flow requires that the callbacks arrive. 
Meaning if firewalls, .htaccess locks and other things which prevents users from directly accessing the site will cause orders not to be created.
