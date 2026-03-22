# fairy

Experimental research repository for the FAIR Composer integration — decentralized package management via W3C Decentralized Identifiers (DIDs).

## What is this?

This repo is the sandbox where the FAIR protocol integration for Composer is developed and tested. It contains two things:

1. **`src/` — the standalone Composer plugin** (`fair/composer-plugin`) for users running stock Composer
2. **`test-instance/` — the integration test suite** that validates everything end-to-end against a real network and a local DDEV mock server

## How FAIR works

Instead of a central registry like Packagist, FAIR packages are discovered via DIDs:

- A package owner publishes a DID (e.g. `did:plc:afjf7gsjzsqmgc7dlhb553mv` or `did:web:example.com`)
- The DID resolves to a DID document, which contains a `FairPackageManagementRepo` service endpoint
- That endpoint serves a metadata document with release info, download URLs, checksums, and Ed25519 signatures
- Composer fetches, verifies, and installs the package

Two DID methods are supported:
- `did:plc:` — resolved via [https://plc.directory/](https://plc.directory/)
- `did:web:` — resolved via HTTPS `did.json` per the W3C spec

## Repository config

Add a `fair` repository to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "fair",
            "packages": {
                "vendor/package-name": "did:plc:abc123"
            }
        }
    ],
    "require": {
        "vendor/package-name": "*"
    }
}
```

Or use the `fair:require` command to do it in one step:

```
composer fair:require did:plc:afjf7gsjzsqmgc7dlhb553mv
```

## Running the tests

### Unit tests

```bash
php vendor/bin/phpunit --testdox
```

### Integration tests (requires DDEV)

```bash
cd test-instance
make start          # start DDEV (needed for did:web: tests)
make test           # run all 4 scenarios
```

The test suite covers:

| Scenario | DID | Expected |
|---|---|---|
| `plc-real` | `did:plc:afjf7gsjzsqmgc7dlhb553mv` | success — installs `fair/git-updater` |
| `plc-wrong` | `did:plc:fakefakefakefakefakefake` | error — DID not found (HTTP 404) |
| `web-real` | `did:web:fair-mapper.ddev.site` | success — installs `fair/mock-lib` |
| `web-wrong` | `did:web:does-not-exist.fair-mapper.ddev.site` | error — connection refused |

## Relation to other repos

- **[composer fork](https://github.com/kaigrosz/composer)** — the FAIR protocol built directly into Composer core; this is the long-term target
- **[fair-handler](https://github.com/kaigrosz/fair-handler)** — the standalone plugin extracted from this repo, ready to install via `composer global require`
