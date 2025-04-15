-include .env
export

# ------------------------------------------------------------------------------------------------------------
## Docker installation commands


.PHONY: start
start:
	docker-compose up -d

.PHONY: stop
stop:
	docker-compose down


.PHONY: update-host
update-host:
	docker-compose exec app mysql -uroot -proot shopware -e "update sales_channel_domain set url='https://${APP_SUBDOMAIN}.${EXPOSE_HOST}' where url LIKE '%localhost%'"

.PHONY: install
install:
	docker-compose exec app php bin/console plugin:refresh
	docker-compose exec app php bin/console plugin:install --clearCache --activate RhaeTweakwise

.PHONY: phpunit
phpunit:
	docker-compose exec --workdir=/var/www/html app vendor/bin/phpunit --configuration=./custom/plugins/RhaeTweakwise/phpunit.xml.dist

.PHONY: cgl
cgl:
	docker-compose exec --workdir=/var/www/html/custom/plugins/RhaeTweakwise app php -d memory_limit=-1 vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php --dry-run --using-cache=no --diff

.PHONY: cgl-fix
cgl-fix:
	docker-compose exec --workdir=/var/www/html/custom/plugins/RhaeTweakwise app php -d memory_limit=-1 vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php --using-cache=no --diff

.PHONY: phpstan
phpstan:
	docker-compose exec --workdir=/var/www/html/custom/plugins/RhaeTweakwise app php -d memory_limit=-1 vendor/bin/phpstan analyse --configuration phpstan.neon --debug

.PHONY: check-all
check-all: cgl phpstan phpunit

#administration-build:
#	docker-compose exec app php psh.phar administration:build --DB_HOST="127.0.0.1" --DB_USER="root" --DB_PASSWORD="root"
#	mv ./src/Resources/public/administration/js/mltisafemultisafepay.js ./src/Resources/public/administration/js/mltisafe-multi-safepay.js
#	docker-compose exec app php psh.phar cache --DB_HOST="127.0.0.1" --DB_USER="root" --DB_PASSWORD="root"

#.PHONY: storefront-build
#storefront-build:
#	docker-compose exec app php psh.phar storefront:build --DB_HOST="127.0.0.1" --DB_USER="root" --DB_PASSWORD="root"
# ------------------------------------------------------------------------------------------------------------

#.PHONY: composer-production
#composer-production:
#	@composer install --no-dev
#
#.PHONY: composer-dev
#composer-dev:
#	@composer install
#
#.PHONY: activate-plugin
#activate-plugin:
#	@cd ../../.. && php bin/console plugin:install -c -r --activate MltisafeMultiSafepay

# ------------------------------------------------------------------------------------------------------------
