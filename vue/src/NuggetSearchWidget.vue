<template>
  <div>
    <div class="row" v-if="selected_nugget==null">
      <div class="col-md-3 col-form-label d-flex pb-0 pr-md-0">
        <label class="d-inline word-break" id="nugget_search">{{config.labels.search}} </label>
      </div>

      <div class="col-md-9 form-inline align-items-start felement" data-fieldtype="text">
        <input v-model="typed" @input="onInput" @keydown="$event.keyCode === 13 ? $event.preventDefault() : false" size="43" class="form-control" :placeholder="config.search_here">
        <img v-bind:src="'../mod/naas/assets/search_icon.png'" class="search_center" width="35" height="35">
      </div>

      <div class="col-md-3">
        <nugget-search-filter :query="filter_query" v-on:filters="onFilters"></nugget-search-filter>
      </div>
      <div class="col-md-9">
        <div class="row">
          <div class="col-md-4 nugget-post-selection" v-for="(nugget,index) in nuggets" :key="index">
            <nugget-post
              v-bind:key="index"
              v-bind:post="nugget"
              v-bind:class="{'card-selected': nugget.nugget_id == selected_id}"
              @SelectButton="clickOnNugget"
            ></nugget-post>
          </div>
        </div>
        <div class="row">
          <div class="show_more">
            <a href="javascript:;" v-if="show_more_button" v-on:click="show_more()" class="btn btn-primary nugget-button show_more_button">
            Show more ...
            </a>
          </div>
        </div>
      </div>
    </div>
    <div class="row" v-else>
      <div class="col-md-3"></div>
      <div class="col-md-9 nugget-post-selected">
        <nugget-post
          v-bind:post="selected_nugget"
          v-bind:class="{'card-selected': false}"
        ></nugget-post>
        <a href="javascript:;" v-on:click="selected_nugget=null; search();" class="btn btn-primary">
        {{ config.labels.click_to_modify }}
        </a>
      </div>
    </div>
  </div>
</template>
<script>
import NuggetSearchFilter from "./components/NuggetSearchFilter"
import NuggetPost from "./components/NuggetPost"
import debounce from "debounce"
export default {
  name: 'NuggetSearchWidget',
  components: { NuggetSearchFilter, NuggetPost },
  data() {
    return {
      typed: '',
      debounced_typed: "",
      nuggets: [],
      selected_nugget: null,
      filters: {},
      selected_id: null,
      show_more_button: false,
      default_page_size: 6,
      add_page_item: 6
    }
  },
  watch: {
    selected_id() {
      this.checkSelected();
    },
    debounced_typed() {
      this.search();
    },
    filters() {
      this.search();
    }
  },
  computed: {
    searching() {
      return true;
    },
    filter_options() {
      return Object.assign({}, {
        is_default_version: true,
        page_size: this.default_page_size,
        fulltext: this.debounced_typed
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
    make_data_array_load_promises(nugget, field, handler) {
      const dest_field = `${field}_data`;
      var promises = [];
      nugget[dest_field] = [];
      for (var j = 0; j < nugget[field].length; j++) {
        ( (i) => {
          promises.push(handler(nugget[field][i])
            .then((res) => {
                if (res) nugget[dest_field].push(res);
              }
            ));
        } )(j);
      }
      return promises;
    },
    make_nugget_promises(nugget) {
      var promises = [];
      promises = promises
        .concat(this.make_data_array_load_promises(nugget, "authors", (email) => this.getAuthorsName(email)))
        .concat(this.make_data_array_load_promises(nugget, "domains", (key) => this.getDomain(key)));
      return promises;
    },
    initialize() {
      this.selected_id = document.getElementsByName('nugget_id')[0].value;
      if (this.selected_id != '') {
        // Nugget_id in memory -> Retrieve from id
        this.proxy(`/nuggets/${this.selected_id}/default_version`).then(
          async (nugget) => {
            var promises = this.make_nugget_promises(nugget);
            await Promise.all(promises);
            this.selected_nugget = nugget;
          }
        );
      }
    },
    show_more() {
      this.default_page_size = this.default_page_size + this.add_page_item;
      this.search();
    },
    search() {
      this.proxy(this.search_query).then(
        async (payload) => {
          if (payload) {
            var nuggets = payload.items;
            var promises = [];
            for (var i = 0; i < nuggets.length; i++) {
              const nugget = nuggets[i]; 
              promises = promises.concat(this.make_nugget_promises(nugget));
            }
            await Promise.all(promises);
            this.nuggets = [...nuggets];
            if (payload.results_count > this.default_page_size) this.show_more_button = true;
            else this.show_more_button = false;
          }
          else this.nuggets = [];
        });
    },
    searchQuery(params) {
      var params_str = new URLSearchParams(params).toString();
      return `/nuggets/search?${params_str}`;
    },
    getAuthorsName(email) {
      return this.proxy(`/persons/${email}`).then(
        (payload) => {
          return ((payload != undefined && payload.firstname != "" && payload.lastname != "") ? 
          `${payload.firstname} ${payload.lastname}`.toUpperCase() : "");
        });
    },
    getDomain(key) {
      return this.proxy(`/vocabularies/nugget_domains_vocabulary/${key}`).then(
        (payload) => {
          return payload;
        });
    },
    onFilters(filters) {
      this.filters = filters;
    },
    onInput: debounce(function() {
      this.debounced_typed = this.typed;
      this.default_page_size = 6;
    }, 500),
    clickOnNugget: function(nugget) {

      event.preventDefault();
      this.selected_id = (this.selected_id == nugget.nugget_id) ? null : nugget.nugget_id;
    },
    checkSelected() {
      for (let nugget of this.nuggets) {
        if (nugget.nugget_id == this.selected_id) {
          document.getElementById('id_name').value = nugget.name;
          document.getElementsByName('nugget_id')[0].value = nugget.nugget_id;
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
}
</script>