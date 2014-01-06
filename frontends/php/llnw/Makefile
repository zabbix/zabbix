.PHONY: help lint phpcs test composer cleangit

help: # Show help
	@grep "^[[:alnum:]]*:" Makefile

lint: # Lint all PHP files
	find ./lib -name "*.php" -exec php -l {} \;

phpcs: # PHP Codesniffer across project files
	./vendor/bin/phpcs --standard=PSR1 --standard=PSR2 --ignore=vendor/* --report=summary .

test: lint phpcs

composer: # Get composer and install or update vendor
	composer install

cleangit: # https://github.com/composer/composer/issues/892
	find ./vendor -name ".git" -exec rm -rf {} \; 2>/dev/null || true
