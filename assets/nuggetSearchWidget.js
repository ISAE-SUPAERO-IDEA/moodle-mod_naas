/**
 * @copyright 2021 Alban Dupire <dupire.alban@gmail.com>
 */

// fonction filter pour couper les strings trop longs
Vue.filter('truncate', function(text, length, suffix) {
  if (text.length > length) {
    return text.substring(0, length) + suffix;
  } else {
    return text;
  }
});

// Global functions
Vue.mixin({
  data() {
    return {
      labels: NAAS_LABELS
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
      const myaxios = axios.create({ baseURL: this.url_proxy });
      return myaxios.get('/mod/naas/proxy.php', { params: { path } }).then(response => response.data.payload);
    }
  }
});

// Composant racine
var app = new Vue({
  template: `
        <div>
          <div class="row" v-if="selected_nugget==null">
              <div class="col-md-3 col-form-label d-flex pb-0 pr-md-0">
                  <label class="d-inline word-break" id="nugget_search">{{labels.search}} </label>
              </div>

              <div class="col-md-9 form-inline align-items-start felement" data-fieldtype="text">
                  <input v-model="typed" @input="onInput" @keydown="$event.keyCode === 13 ? $event.preventDefault() : false" size="43" class="form-control" :placeholder="labels.search_here">
                  <img v-bind:src="'../mod/naas/assets/search_icon.png'" class="search_center" width="35" height="35">
              </div>


              <div class="col-md-3">
                <nugget-filter :query="filter_query" v-on:filters="onFilters"></nugget-filter>
              </div>
              <div class="col-md-9">
                <div class="row">
                      <div class="col-md-4" v-for="(post,index) in posts">
                        <nugget-post
                          v-bind:key="index"
                          v-bind:post="post"
                          v-bind:class="{'card-selected':(post.nugget_id == selected_id)}"
                          v-on:click.native="clickOnNugget(post)"
                        ></nugget-post>
                      </div>
                </div>
              </div>
            </div>
            <div class="row" v-else>
              <div class="col-md-3"></div>
              <div class="col-md-9">
                <nugget-post
                    v-bind:post="selected_nugget"
                    v-bind:class="{'card-selected': false}"
                  ></nugget-post>
                <a href="javascript:;" v-on:click="selected_nugget=null;search();">
                {{ labels.click_to_modify }}
                </a>
              </div>
            </div>

        </div>  
      `,
  el: '#app',
  data() {
    return {
      typed: '',
      posts: [],
      selected_id: null,
      selected_nugget: null,
      filters: {},
    }
  },
  watch: {
    selected_id(selected_id) {
      this.checkSelected();
    },
    filters() {
      this.search();
    }
  },
  computed: {
    searching() {
      return true;
      //return this.typed.length >= 3;
    },
    filter_options() {
      return Object.assign({}, {
        is_default_version: true,
        page_size: 12,
        fulltext: this.typed
      });
    },
    search_options() {
      return Object.assign({}, this.filter_options,this.filters);
    },
    filter_query() {
      return this.searching ? this.searchQuery(this.filter_options) : null;
    },
    search_query() {
      return this.searchQuery(this.search_options);
    }
  },
  methods: {
    initialize() {
      this.selected_id = document.getElementsByName('nugget_id')[0].value;
      if (this.selected_id != '') {
        // Nugget_id in memory -> Retrieve from id
        this.proxy(`/nuggets/${this.selected_id}/default_version`).then(
          (payload) => {
            this.selected_nugget = payload;
          }
        );
      }
    },
    search() {
      this.proxy(this.search_query).then(
          (payload) => {
            this.posts = payload ? payload.items : [];
          })
    },
    searchQuery(params) {
      var params_str = new URLSearchParams(params).toString();
      return `/nuggets/search?${params_str}`; 

    },
    onFilters(filters) {
      this.filters = filters;
    },
    onInput() {
      if (this.searching) {
        this.search();
      } else {
        //this.initialize();
      }
    },
    clickOnNugget: function(post) {
      event.preventDefault();
      this.selected_id = (this.selected_id == post.nugget_id) ? null : post.nugget_id;
    },
    checkSelected() {
      for (let post of this.posts) {
        if (post.nugget_id == this.selected_id) {
          document.getElementById('id_name').value = post.name;
          document.getElementsByName('nugget_id')[0].value = post.nugget_id;
          break;
        } else {
          document.getElementById('id_name').value = '';
          document.getElementsByName('nugget_id')[0].value = '';
        }
      }
    }
  },
  mounted: function() {
    this.initialize();
  }
});