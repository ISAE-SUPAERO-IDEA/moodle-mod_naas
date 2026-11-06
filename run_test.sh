#!/usr/bin/env bash
# Type-check and run the Vue widget unit tests.
set -euo pipefail
cd "$(dirname "$0")/vue"
yarn install --frozen-lockfile
yarn type-check
CI=true yarn test
