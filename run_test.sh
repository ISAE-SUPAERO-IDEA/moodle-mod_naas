#!/usr/bin/env bash
# Lint, check the architecture, type-check and run the Vue widget unit tests.
set -euo pipefail
cd "$(dirname "$0")/vue"
pnpm install --frozen-lockfile
pnpm lint:check
pnpm lint:fsd
pnpm type-check
CI=true pnpm test
