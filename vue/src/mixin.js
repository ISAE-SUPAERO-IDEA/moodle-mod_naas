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
 * Global mixin for NAAS Vue components.
 *
 * @copyright  2019 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Global functions
import moodleService from "./http/moodleService";

/*global NAAS*/

export default {
  data() {
    return {
      config: NAAS,

      // Error when querying the plugin proxy
      proxyError: null,
    };
  },
  computed: {
    url_root() {
      return this.labels.url_root;
    },

    // User readable error message when needed
    errorUserMessage() {
      if(!this.proxyError) {
        return null
      }

      return this.config.labels["error_generic_user_message"];
    }
  },
  methods: {
    $id(thing) {
      return this._uid + "." + thing;
    },

    // Queries the proxy
    proxy(action, params) {
      this.proxyError = null

      return moodleService.callWebservice(action, params)
          .catch((error) => {
            this.proxyError = error;
            return Promise.reject(this.proxyError)
          });
    },
    xapi(params) {
      return this.proxy("mod_naas_post_xapi_statement", params)
    },
    // Make promises for an array of keys
    make_data_array_load_promises(nugget, field, handler) {
      const dest_field = `${field}_data`;
      var promises = [];
      nugget[dest_field] = [];
      for (var j = 0; j < nugget[field].length; j++) {
        ((i) => {
          promises.push(
            handler(nugget[field][i]).then((res) => {
              if (res) nugget[dest_field].push(res);
            })
          );
        })(j);
      }
      return promises;
    },
    // Make promises for a nugget
    make_nugget_promises(nugget) {
      var promises = [];
      promises = promises
        .concat(
          this.make_data_array_load_promises(nugget, "authors", (personKey) =>
            this.getPerson(personKey)
          )
        )
        .concat(
          this.make_data_array_load_promises(nugget, "domains", (key) =>
            this.getDomain(key)
          )
        );
      return promises;
    },
    get_nugget_default_version(nuggetId) {
      return this.proxy("mod_naas_get_nugget", { nuggetId, courseId: this.config.courseId }).then(
        async (nugget) => {
          let promises = this.make_nugget_promises(nugget);
          await Promise.all(promises);
          return nugget;
        }
      );
    },
    viewNugget(cmId) {
      return this.proxy("mod_naas_view_nugget", { cmId }).then(
          async (nugget) => {
            let promises = this.make_nugget_promises(nugget);
            await Promise.all(promises);
            return nugget;
          }
      );
    },
    getPerson(personKey) {
      return this.proxy("mod_naas_get_person", { personKey, courseId: this.config.courseId });
    },
    getPersonName(email) {
      return this.getPerson(email).then((author) => {
        return author != undefined &&
          author.firstname != "" &&
          author.lastname != ""
          ? `${author.firstname} ${author.lastname}`.toUpperCase()
          : "";
      });
    },
    getDomain(key) {
      return this.proxy(
          "mod_naas_get_domain", { domainKey: key, courseId: this.config.courseId });
    },
    getDomainLabel(key) {
      return this.getDomain(key).then((entry) => (entry ? entry.label : key));
    },
    getStructure(key) {
      return this.proxy("mod_naas_get_structure", { structureKey: key, courseId: this.config.courseId });
    },
    getStructureAcronym(key) {
      return this.getStructure(key).then((structure) =>
        structure ? structure.acronym : key
      );
    },
    // Translation
    $t(text) {
      return NAAS.labels.metadata[text] || text;
    },
  },
};
