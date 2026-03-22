#!/usr/bin/env bats
#
# FAIR Composer Integration — Test Suite
#
# Prerequisites:
#   brew install bats-core
#   make start            (ddev start — required for did:web: tests)
#
# Run:
#   bats fair.bats
#   bats fair.bats --filter "did:plc"   (run subset)

# Host paths (for file operations)
SCENARIOS="$BATS_TEST_DIRNAME/scenarios"
WORKDIR="$BATS_TEST_DIRNAME/workdir"

# Container paths (DDEV mounts the project at /var/www/html)
CONTAINER_ROOT="/var/www/html/test-instance"
CONTAINER_PHAR="$CONTAINER_ROOT/composer-fair.phar"
CONTAINER_WORKDIR="$CONTAINER_ROOT/workdir"

setup() {
    # Clean on host and inside container (Mutagen sync may lag)
    rm -rf "$WORKDIR/vendor" "$WORKDIR/composer.lock"
    ddev exec rm -rf "$CONTAINER_WORKDIR/vendor" "$CONTAINER_WORKDIR/composer.lock"
    mkdir -p "$WORKDIR"
}

# ── did:plc: ──────────────────────────────────────────────────────────────────

@test "did:plc: real package (git-updater) installs successfully" {
    cp "$SCENARIOS/plc-real/composer.json" "$WORKDIR/composer.json"

    run bash -c "ddev exec -d '$CONTAINER_WORKDIR' php '$CONTAINER_PHAR' install --no-interaction 2>&1"

    [ "$status" -eq 0 ]
}

@test "did:plc: non-existent DID reports resolution error" {
    cp "$SCENARIOS/plc-wrong/composer.json" "$WORKDIR/composer.json"

    run bash -c "ddev exec -d '$CONTAINER_WORKDIR' php '$CONTAINER_PHAR' install --no-interaction 2>&1"

    [ "$status" -ne 0 ]
    [[ "$output" =~ "FAIR: Failed to resolve DID" ]]
}

# ── did:web: ──────────────────────────────────────────────────────────────────

@test "did:web: mock package (fair-mapper.ddev.site) installs successfully" {
    cp "$SCENARIOS/web-real/composer.json" "$WORKDIR/composer.json"

    run bash -c "ddev exec -d '$CONTAINER_WORKDIR' php '$CONTAINER_PHAR' install --no-interaction 2>&1"

    [ "$status" -eq 0 ]
}

@test "did:web: non-existent domain reports connection error" {
    cp "$SCENARIOS/web-wrong/composer.json" "$WORKDIR/composer.json"

    run bash -c "ddev exec -d '$CONTAINER_WORKDIR' php '$CONTAINER_PHAR' install --no-interaction 2>&1"

    [ "$status" -ne 0 ]
    [[ "$output" =~ "FAIR: Failed to resolve DID" ]]
}
