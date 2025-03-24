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
 * Main entry point for the Vue application.
 *
 * @copyright (C) 2019 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Vue from "vue";
import Main from "@/Main";
import mixin from "@/mixin";
import utils from "@/utils";
import moment from "moment";
/*global NAAS*/

Vue.config.productionTip = false;

// fonction filter pour couper les strings trop longs
Vue.filter("truncate", utils.truncate);

// formatting date values
Vue.filter("formatDate", function (value) {
  if (value) {
    return moment(String(value)).format("DD/MM/YYYY");
  }
});

Vue.mixin(mixin);

new Vue({
  render: (h) => h(Main),
}).$mount(NAAS.mount_point);
