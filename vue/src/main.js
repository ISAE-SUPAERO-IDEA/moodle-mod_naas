import Vue from 'vue'
import NuggetSearchWidget from './NuggetSearchWidget.vue'
import axios from "axios"

/*global NAAS*/

Vue.config.productionTip = false

// fonction filter pour couper les strings trop longs
Vue.filter('truncate', function(text, length, suffix) {
  if (text.length > length) {
    return text.substring(0, length) + suffix;
  } else {
    return text;
  }
});

// Global functions
const cache = {}
const client = axios.create({ baseURL: NAAS.proxy_url });
Vue.mixin({
  data() {
    return {
      config: NAAS
    }
  },
  computed: {
    url_root() {
      return this.labels.url_root;
    }
  },
  methods: {
    $id(thing) {
      return this._uid + '.' + thing;
    },
    // Queries the proxy
    proxy(path) {
      if (cache[path]) {
        return Promise.resolve(cache[path]);
      }
      return client.get('/mod/naas/proxy.php', { params: { path } })
      .then(response => {
        cache[path] = response.data.payload;
        return response.data.payload
      });
    },
    // Translation
    $t(text) {
      return NAAS.labels.metadata[text] || text
    }
  }
});

new Vue({
  render: h => h(NuggetSearchWidget),
}).$mount(NAAS.mount_point)
