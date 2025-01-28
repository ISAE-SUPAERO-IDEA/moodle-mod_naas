// Global functions
import handleAxiosError from "./http/axios-error-handler";

const cache = {};
const client = axios.create({ baseURL: NAAS.proxy_url });
import axios from "axios";
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
    proxy(path) {
      if (cache[path]) {
        return Promise.resolve(cache[path]);
      }
      this.proxyError = null
      return client
        .get("/mod/naas/proxy.php", { params: { path } })
        .then((response) => {
          if(!response.data.success) {
            throw new NaasHttpError(response.data.error.code, response.data.error.message)
          }

          const payload = response.data.payload;
          cache[path] = payload;
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
      let info = axios
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
          this.make_data_array_load_promises(nugget, "authors", (email) =>
            this.getPerson(email)
          )
        )
        .concat(
          this.make_data_array_load_promises(nugget, "domains", (key) =>
            this.getDomain(key)
          )
        );
      return promises;
    },
    get_nugget_default_version(nugget_id) {
      return this.proxy(`/nuggets/${nugget_id}/default_version`).then(
        async (nugget) => {
          var promises = this.make_nugget_promises(nugget);
          await Promise.all(promises);
          return nugget;
        }
      );
    },
    getPerson(email) {
      return this.proxy(`/persons/${email}`);
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
      return this.proxy(`/vocabularies/nugget_domains_vocabulary/${key}`);
    },
    getDomainLabel(key) {
      return this.getDomain(key).then((entry) => (entry ? entry.label : key));
    },
    getStructure(key) {
      return this.proxy(`/structures/${key}`);
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
