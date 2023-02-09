<template>
  <div>
    <div
      class="row"
      v-if="selected_nugget == null && selected_nugget_loading == 0"
    >
      <div class="col-md-3 col-form-label d-flex pb-0 pr-md-0">
        <label class="d-inline word-break" id="nugget_search"
          >{{ config.labels.search }}
        </label>
      </div>

      <!-- Search input -->
      <div
        class="col-md-9 form-inline align-items-start felement"
        data-fieldtype="text"
      >
        <input
          v-model="typed"
          @input="onInput"
          @keydown="$event.keyCode === 13 ? $event.preventDefault() : false"
          size="43"
          class="form-control"
          :placeholder="config.search_here"
        />
        <img
          v-bind:src="'../mod/naas/assets/search_icon.png'"
          class="search_center"
          width="35"
          height="35"
        /><br />
        <loading :loading="loading"></loading>
      </div>
      <!-- Search filter -->
      <div class="col-md-3">
        <nugget-search-filter
          :query="filter_query"
          v-on:filters="onFilters"
        ></nugget-search-filter>
      </div>
      <!-- Nugget list -->
      <div class="col-md-9">
        <div class="row">
          <div
            class="col-6 col-lg-6 col-xl-4 nugget-post-selection"
            v-for="(nugget, index) in nuggets"
            :key="index"
          >
            <nugget-post
              v-bind:key="index"
              v-bind:nugget="nugget"
              v-bind:class="{
                'nugget-post-selected': nugget.nugget_id == selected_id,
              }"
              @SelectButton="clickOnNugget"
            ></nugget-post>
          </div>
        </div>
        <div class="row">
          <div class="show_more">
            <a
              href="javascript:;"
              v-if="show_more_button"
              v-on:click="show_more()"
              class="btn btn-primary nugget-button show_more_button"
            >
              {{ config.labels.show_more_button }}
            </a>
          </div>
        </div>
      </div>
    </div>
    <!-- Selected nugget -->
    <div class="row" v-else>
      <div class="col-md-3"></div>
      <div class="col-md-9 nugget-selected">
        <loading :loading="selected_nugget_loading"></loading>
        <div v-if="!selected_nugget_loading">
          <nugget-post
            v-bind:nugget="selected_nugget"
            v-bind:class="{ 'nugget-post-selected': false }"
          ></nugget-post>
          <a
            href="javascript:;"
            v-on:click="
              selected_nugget = null;
              search();
            "
            class="btn btn-primary btn-modify"
          >
            {{ config.labels.click_to_modify }}
          </a>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import NuggetSearchFilter from "./NuggetSearchFilter";
import NuggetPost from "./NuggetPost";
import Loading from "./Loading";
import debounce from "debounce";
export default {
  name: "NuggetSearchWidget",
  components: { NuggetSearchFilter, NuggetPost, Loading },
  data() {
    return {
      typed: "",
      debounced_typed: "",
      nuggets: [],
      selected_nugget: null,
      default_nugget_list: [],
      filters: {},
      selected_id: null,
      show_more_button: false,
      default_page_size: 6,
      add_page_item: 6,
      loading: 0,
      selected_nugget_loading: 0,
    };
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
    },
  },
  computed: {
    filter_options() {
      if (this.debounced_typed == "") return null;
      return Object.assign(
        {},
        {
          is_default_version: true,
          page_size: this.default_page_size,
          fulltext: this.debounced_typed,
        }
      );
    },
    search_options() {
      if (this.debounced_typed == "") return null;
      return Object.assign({}, this.filter_options, this.filters);
    },
    filter_query() {
      return this.searchQuery(this.filter_options);
    },
    search_query() {
      return this.searchQuery(this.search_options);
    },
  },
  methods: {
    async initialize() {
      this.selected_id = document.getElementsByName("nugget_id")[0].value;
      if (this.selected_id != "") {
        // Nugget_id in memory -> Retrieve from id
        this.selected_nugget_loading++;
        try {
          this.selected_nugget = await this.get_nugget_default_version(
            this.selected_id
          );
          this.default_nugget_list = [this.selected_nugget];
        } catch (e) {
          this.selected_nugget_loading--;
          throw e;
        }
        this.selected_nugget_loading--;
      }
    },
    show_more() {
      this.default_page_size = this.default_page_size + this.add_page_item;
      this.search();
    },
    search() {
      this.show_more_button = false;
      if (this.search_query) {
        this.loading++;
        this.proxy(this.search_query)
          .then(async (payload) => {
            if (payload) {
              var nuggets = payload.items;
              var promises = [];
              for (var i = 0; i < nuggets.length; i++) {
                const nugget = nuggets[i];
                promises = promises.concat(this.make_nugget_promises(nugget));
              }
              await Promise.all(promises);
              this.nuggets = [...nuggets];
              if (payload.results_count > this.default_page_size)
                this.show_more_button = true;
            } else {
              this.nuggets = [];
            }
            this.loading--;
          })
          .catch(() => {
            this.loading--;
          });
      } else this.nuggets = this.default_nugget_list;
    },
    searchQuery(params) {
      if (params) {
        var params_str = new URLSearchParams(params).toString();
        return `/nuggets/search?${params_str}`;
      }
      return null;
    },
    onFilters(filters) {
      this.filters = filters;
    },
    onInput: debounce(function () {
      this.debounced_typed = this.typed;
      this.default_page_size = 6;
    }, 500),
    clickOnNugget: function (nugget) {
      event.preventDefault();
      this.selected_id =
        this.selected_id == nugget.nugget_id ? null : nugget.nugget_id;
    },
    checkSelected() {
      for (let nugget of this.nuggets) {
        if (nugget.nugget_id == this.selected_id) {
          document.getElementById("id_name").value = nugget.name;
          document.getElementsByName("nugget_id")[0].value = nugget.nugget_id;
          break;
        } else {
          document.getElementById("id_name").value = "";
          document.getElementsByName("nugget_id")[0].value = "";
        }
      }
    },
  },
  mounted: function () {
    this.initialize();
  },
};
</script>
