#!/usr/bin/env bash
# Type-check and build the Vue widget bundle into assets/vue/.
set -euo pipefail
cd "$(dirname "$0")/vue"
yarn install --frozen-lockfile
yarn build
