// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * ESLint configuration for the NaaS Vue 3 widget.
 *
 * Same plugins and rule levels as the NaaS pnpm workspace
 * (pnpm-workspace/eslint.config.mjs), without Storybook.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import js from "@eslint/js";
import globals from "globals";
import pluginVue from "eslint-plugin-vue";
import tseslint from "typescript-eslint";
import vueParser from "vue-eslint-parser";
import prettierConfig from "eslint-config-prettier";
import pluginPrettier from "eslint-plugin-prettier";
import pluginSonarjs from "eslint-plugin-sonarjs";
import unusedImports from "eslint-plugin-unused-imports";

const plugins = {
  sonarjs: pluginSonarjs,
  prettier: pluginPrettier,
  "unused-imports": unusedImports,
};

const sharedRules = {
  "no-console": process.env.NODE_ENV === "production" ? "warn" : "off",
  "no-debugger": process.env.NODE_ENV === "production" ? "warn" : "off",
  complexity: ["warn", 10],
  "max-lines-per-function": [
    "warn",
    { max: 80, skipBlankLines: true, skipComments: true },
  ],
  "max-depth": ["warn", 3],
  "max-nested-callbacks": ["warn", 2],
  "sonarjs/cognitive-complexity": ["warn", 8],
  "sonarjs/no-duplicate-string": ["warn", { threshold: 2 }],
  "sonarjs/no-identical-functions": "warn",
  "prettier/prettier": "warn",
  "no-unused-vars": "off",
  "@typescript-eslint/no-unused-vars": "off",
  "unused-imports/no-unused-imports": "error",
  "unused-imports/no-unused-vars": [
    "warn",
    {
      vars: "all",
      varsIgnorePattern: "^_",
      args: "after-used",
      argsIgnorePattern: "^_",
    },
  ],
};

export default [
  { ignores: ["node_modules/**", "coverage/**", "dist/**"] },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  ...pluginVue.configs["flat/recommended"],
  {
    files: ["**/*.{js,mjs,cjs,ts}"],
    languageOptions: {
      ecmaVersion: 2020,
      sourceType: "module",
      parser: tseslint.parser,
      globals: {
        ...globals.browser,
        ...globals.node,
        ...globals.es2021,
      },
    },
    plugins,
    rules: sharedRules,
  },
  {
    files: ["**/*.vue"],
    languageOptions: {
      ecmaVersion: 2020,
      sourceType: "module",
      parser: vueParser,
      parserOptions: {
        parser: tseslint.parser,
        extraFileExtensions: [".vue"],
      },
      globals: {
        ...globals.browser,
        ...globals.es2021,
      },
    },
    plugins,
    rules: {
      ...sharedRules,
      "vue/block-order": [
        "error",
        { order: ["template", "script", "i18n", "style"] },
      ],
      "vue/multi-word-component-names": "off",
      "no-useless-assignment": "off",
    },
  },
  {
    // Specs nest describe/it callbacks, repeat literals and declare stub
    // components inline: these size and repetition rules only add noise there.
    files: ["**/*.spec.ts", "src/test/**"],
    rules: {
      "max-lines-per-function": "off",
      "max-nested-callbacks": "off",
      "sonarjs/no-duplicate-string": "off",
      "vue/one-component-per-file": "off",
      "vue/require-prop-types": "off",
    },
  },
  prettierConfig,
];
