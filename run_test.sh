#!/usr/bin/env bash
# Lint, type-check and run the Vue widget unit tests.
set -euo pipefail
cd "$(dirname "$0")/vue"
pnpm install --frozen-lockfile
pnpm lint:check
pnpm type-check
CI=true pnpm test
