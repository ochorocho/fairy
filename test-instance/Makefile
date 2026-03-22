# FAIR Composer Integration
# Run "make help" to see available targets.

.PHONY: help start stop test clean

help:
	@echo ""
	@echo "  make start   ddev start  — serves https://fair-mapper.ddev.site"
	@echo "  make stop    ddev stop"
	@echo "  make test    run all tests  (bats fair.bats)"
	@echo "  make clean   remove workdir/vendor and workdir/composer.lock"
	@echo ""

start:
	@cd .. && ddev start

stop:
	@cd .. && ddev stop

test:
	@bats fair.bats

clean:
	@rm -rf workdir/vendor workdir/composer.lock
	@echo "  ✓ Cleaned"
