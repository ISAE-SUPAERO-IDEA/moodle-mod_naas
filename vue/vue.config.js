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
 * Vue configuration for NAAS plugin.
 *
 * @copyright (C) 2019 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const InjectPlugin = require("webpack-inject-plugin").default;
let dev_config;
const path = require("path");

if (process.env.NODE_ENV != "production") {
  dev_config = require("./dev_config");
}

module.exports = {
  publicPath: "/vue/",
  outputDir: path.resolve(__dirname, "../assets/vue"),
  devServer: {
    disableHostCheck: true,
    public: "http://moodle.local.isae.fr/vue/",
  },
  configureWebpack: (config) => {
    if (process.env.NODE_ENV === "production") {
      // Configure output file
      config.optimization.splitChunks = false;
      config.output.filename = "naas_widget-232.js";
    } else {
      // Inject developpement configuration
      config.plugins.push(
        new InjectPlugin(function () {
          return "NAAS =" + JSON.stringify(dev_config);
        })
      );
    }
  },
  chainWebpack: (config) => {
    if (process.env.NODE_ENV === "production") {
      // Do not output html file
      config.plugin("html").tap((args) => {
        args[0].template = "public/blank.html";
        args[0].inject = false;
        args[0].cache = false;
        args[0].minify = false;
        args[0].filename = "blank.html";
        return args;
      });
    } else {
      // Inject initial nugget_id for developpement
      config.plugin("html").tap((args) => {
        args[0]["nugget_id"] = dev_config.nugget_id;
        return args;
      });
    }
  },
};
