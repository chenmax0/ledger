up:
	docker compose up -d database

test:
	docker compose run --rm php bin/phpunit

stan:
	docker compose run --rm php vendor/bin/phpstan analyse

cs:
	docker compose run --rm php vendor/bin/php-cs-fixer fix

.PHONY: up test stan cs
