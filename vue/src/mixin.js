// Global functions
import handleAxiosError from "./http/axios-error-handler";

const cache = new WeakMap();
import axios from "axios";
const axiosClient = axios.create({ baseURL: NAAS.moodle_url });
import NaasHttpError from "./http/NaasHttpError";
import ProxyHttpError from "./http/ProxyHttpError";
import translateError from "./error-message";
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

      return translateError(this.proxyError)
    }
  },
  methods: {
    $id(thing) {
      return this._uid + "." + thing;
    },

    // Queries the proxy
    proxy(action, params) {
      if (cache.has( { action, params })) {
        return Promise.resolve(
            cache.get({ action, params })
        );
      }
      this.proxyError = null
      return axiosClient
        .get("/mod/naas/proxy.php", { params: { action, ...params } })
        .then((response) => {
          if(!response.data.success) {
            throw new NaasHttpError(response.data.error.code, response.data.error.message)
          }

          const payload = response.data.payload;
          cache.set( { action, params }, payload );
          return payload;
        })
          .catch((error) => {
            handleAxiosError(error)

            if(error instanceof NaasHttpError) {
                this.proxyError = error
            } else {
              this.proxyError = new ProxyHttpError(error.statusCode, error.message)
            }

            return Promise.reject(this.proxyError)
          });
    },
    xapi(params) {
      let info = axiosClient
        .get("/mod/naas/xapi.php", { params })
        .then((response) => {
          info = response.data.payload;
        });
      return info;
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
      return this.proxy("get-nugget", { nuggetId, courseId: this.config.courseId }).then(
        async (nugget) => {
          let promises = this.make_nugget_promises(nugget);
          await Promise.all(promises);
          return nugget;
        }
      );
    },
    getPerson(personKey) {
      return this.proxy("get-person", { personKey, courseId: this.config.courseId });
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
          "get-domain", { domainKey: key, courseId: this.config.courseId });
    },
    getDomainLabel(key) {
      return this.getDomain(key).then((entry) => (entry ? entry.label : key));
    },
    getStructure(key) {
      return this.proxy("get-structure", { structureKey: key, courseId: this.config.courseId });
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
