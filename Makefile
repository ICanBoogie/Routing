# customization

PHPUNIT = vendor/bin/phpunit

# do not edit the following lines

vendor:
	@composer install

# testing

.PHONY: test-dependencies
test-dependencies: vendor test-cleanup

.PHONY: test
test: test-dependencies
	@$(PHPUNIT) $(ARGS)

.PHONY: test-coverage
test-coverage: test-dependencies
	@mkdir -p build/coverage
	@XDEBUG_MODE=coverage $(PHPUNIT) --coverage-html build/coverage

.PHONY: test-coveralls
test-coveralls: test-dependencies
	@mkdir -p build/logs
	@XDEBUG_MODE=coverage $(PHPUNIT) --coverage-clover build/logs/clover.xml

.PHONY: test-cleanup
test-cleanup:
	@rm -rf tests/sandbox/*

.PHONY: test-container
test-container: test-container-84

.PHONY: test-container-84
test-container-84:
	@-docker-compose run --rm app84 bash
	@docker-compose down -v

.PHONY: test-container-85
test-container-85:
	@-docker-compose run --rm app85 bash
	@docker-compose down -v

.PHONY: benchmark
benchmark: vendor
	@php -d opcache.enable_cli=1 -d pcre.jit=1 benchmarks/run.php $(ARGS)

.PHONY: benchmark-dispatch
benchmark-dispatch: vendor
	@php -d opcache.enable_cli=1 -d pcre.jit=1 benchmarks/dispatch.php $(ARGS)

.PHONY: lint
lint:
#	@XDEBUG_MODE=off phpcs -s
	@XDEBUG_MODE=off vendor/bin/phpstan
