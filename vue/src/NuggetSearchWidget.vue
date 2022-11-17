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
          <div class="col-md-4" style="margin-bottom: 20px;" v-for="(post,index) in posts" :key="index">
            <nugget-post
              v-bind:key="index"
              v-bind:post="post"
              v-bind:class="{'card-selected': post.nugget_id == selected_id}"
              @SelectButton="clickOnNugget"
            ></nugget-post>
          </div>
        </div>
      </div>
    </div>
    <div class="row" v-else>
      <div class="col-md-3"></div>
      <div class="col-md-9" style="margin-bottom: 75px;">
        <nugget-post
          v-bind:post="selected_nugget"
          v-bind:class="{'card-selected': false}"
        ></nugget-post>
        <a href="javascript:;" v-on:click="selected_nugget=null; search();">
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
      posts: [],
      selected_nugget: null,
      filters: {},
      selected_id: null
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
        page_size: 12,
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
    initialize() {
      this.selected_id = document.getElementsByName('nugget_id')[0].value;
      if (this.selected_id != '') {
        // Nugget_id in memory -> Retrieve from id
        this.proxy(`/nuggets/${this.selected_id}/default_version`).then(
          async (payload) => {
            var authors = payload['authors'];
            var promises = [];
            for (var j = 0; j < authors.length; j++) {
              ( (iauthor) => {
                promises.push(this.getAuthorsName(authors[iauthor]).then(
                  (AuthorsName) => {
                      payload["authors_name"] = payload["authors_name"] || [];
                      payload["authors_name"].push(AuthorsName.toUpperCase());
                    }
                  ));
              } )(j);
            }
            await Promise.all(promises);
            this.selected_nugget = payload;
          }
        );
      }
    },
    search() {
      this.proxy(this.search_query).then(
        async (payload) => {
          var posts = payload ? payload.items : [];
          var promises = [];
          for (var i = 0; i < posts.length; i++) {
            var authors = posts[i]['authors'];
            for (var j = 0; j < authors.length; j++) {
              ( (ipost, iauthor) => {
                promises.push(this.getAuthorsName(authors[iauthor]).then(
                  (AuthorsName) => {
                      posts[ipost]["authors_name"] = posts[ipost]["authors_name"] || [];
                      posts[ipost]["authors_name"].push(AuthorsName.toUpperCase());
                    }
                  ));
              } )(i, j);
            }
          }
          await Promise.all(promises);
          this.posts = [...posts];
        });
    },
    searchQuery(params) {
      var params_str = new URLSearchParams(params).toString();
      return `/nuggets/search?${params_str}`;
    },
    getAuthorsName(email) {
      return this.proxy(`/persons/` + email).then(
        (payload) => {
          return payload['firstname'] + " " + payload['lastname'];
        });
    },
    onFilters(filters) {
      this.filters = filters;
    },
    onInput: debounce(function() {
      this.debounced_typed = this.typed;
    }, 500),
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
}
</script>