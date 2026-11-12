#!/usr/bin/env bash
# Type-check and build the Vue widget bundle into assets/vue/.
set -euo pipefail
cd "$(dirname "$0")/vue"
pnpm install --frozen-lockfile
pnpm build
