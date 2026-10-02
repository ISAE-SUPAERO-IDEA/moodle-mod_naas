#!/bin/bash
cd vue
CI=true npm run test > test.log 2>&1
