// Global functions
const cache = {};
const client = axios.create({ baseURL: NAAS.proxy_url });
import axios from "axios";
/*global NAAS*/

export default {
  data() {
    return {
      config: NAAS,
    };
  },
  computed: {
    url_root() {
      return this.labels.url_root;
    },
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
      cache[path] = client
        .get("/mod/naas/proxy.php", { params: { path } })
        .then((response) => {
          cache[path] = response.data.payload;
          return response.data.payload;
        });
      return cache[path];
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
