# FAIR Composer Integration — Test Suite
# =======================================
# Each target runs one scenario and prints the full Composer output.
#
#   make start            start DDEV (required for did:web: tests)
#   make stop             stop DDEV
#
#   make test             run all 4 scenarios
#   make test-plc-real    did:plc: with a correct DID       → expect: success
#   make test-plc-wrong   did:plc: with a wrong DID         → expect: error
#   make test-web-real    did:web: via DDEV mock server      → expect: success
#   make test-web-wrong   did:web: with a non-existent host  → expect: error

PHP       = ddev exec php
PHAR      = /var/www/html/test-instance/composer-fair.phar
WORKDIR   = /tmp/fair-workdir
SCENARIOS = /var/www/html/test-instance/scenarios

.PHONY: start stop test \
        test-plc-real test-plc-wrong \
        test-web-real test-web-wrong

# ── DDEV ──────────────────────────────────────────────────────────────────────

start:
	ddev start

stop:
	ddev stop

# ── Run all scenarios ─────────────────────────────────────────────────────────

test: test-plc-real test-plc-wrong test-web-real test-web-wrong
	@echo ""
	@echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
	@echo "  All scenarios completed."
	@echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# ── Scenarios ─────────────────────────────────────────────────────────────────

test-plc-real:
	@echo ""
	@echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
	@echo "  TEST 1 — did:plc: correct DID  →  expect: successful install"
	@echo "  Package : fair/git-updater"
	@echo "  DID     : did:plc:afjf7gsjzsqmgc7dlhb553mv"
	@echo "  Resolve : https://plc.directory/did:plc:afjf7gsjzsqmgc7dlhb553mv"
	@echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
	@echo ""
	@ddev exec bash -c "rm -rf $(WORKDIR) && mkdir $(WORKDIR)"
	@ddev exec cp $(SCENARIOS)/plc-real/composer.json $(WORKDIR)/composer.json
	ddev exec -d $(WORKDIR) php $(PHAR) install --no-interaction
	@echo ""

test-plc-wrong:
	@echo ""
	@echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
	@echo "  TEST 2 — did:plc: wrong DID  →  expect: resolution error"
	@echo "  Package : fair/ghost-package"
	@echo "  DID     : did:plc:fakefakefakefakefakefake"
	@echo "  Resolve : https://plc.directory/did:plc:fakefakefakefakefakefake → 404"
	@echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
	@echo ""
	@ddev exec bash -c "rm -rf $(WORKDIR) && mkdir $(WORKDIR)"
	@ddev exec cp $(SCENARIOS)/plc-wrong/composer.json $(WORKDIR)/composer.json
	-ddev exec -d $(WORKDIR) php $(PHAR) install --no-interaction
	@echo ""

test-web-real:
	@echo ""
	@echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
	@echo "  TEST 3 — did:web: DDEV mock server  →  expect: successful install"
	@echo "  Package : fair/mock-lib v1.0.0"
	@echo "  DID     : did:web:fair-mapper.ddev.site"
	@echo "  Resolve : https://fair-mapper.ddev.site/.well-known/did.json"
	@echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
	@echo ""
	@ddev exec bash -c "rm -rf $(WORKDIR) && mkdir $(WORKDIR)"
	@ddev exec cp $(SCENARIOS)/web-real/composer.json $(WORKDIR)/composer.json
	ddev exec -d $(WORKDIR) php $(PHAR) install --no-interaction
	@echo ""

test-web-wrong:
	@echo ""
	@echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
	@echo "  TEST 4 — did:web: non-existent domain  →  expect: connection error"
	@echo "  Package : fair/ghost-package"
	@echo "  DID     : did:web:does-not-exist.fair-mapper.ddev.site"
	@echo "  Resolve : https://does-not-exist.fair-mapper.ddev.site/.well-known/did.json"
	@echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
	@echo ""
	@ddev exec bash -c "rm -rf $(WORKDIR) && mkdir $(WORKDIR)"
	@ddev exec cp $(SCENARIOS)/web-wrong/composer.json $(WORKDIR)/composer.json
	-ddev exec -d $(WORKDIR) php $(PHAR) install --no-interaction
	@echo ""
