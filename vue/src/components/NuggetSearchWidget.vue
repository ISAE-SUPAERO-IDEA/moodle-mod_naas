<!--
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
 * Nugget search widget component for NAAS Vue application.
 *
 * @copyright  2019 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
<template>
  <div>

    <div v-if="proxyError" class="alert alert-danger">{{ errorUserMessage }}</div>
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
          :placeholder="config.labels.nugget_search_here"
        />
        <img
          v-bind:src="'../mod/naas/assets/search_icon.png'"
          class="search-center"
          width="35"
          height="35"
        /><br />
        <loading :loading="loading"></loading>
      </div>
      <!-- Search filter -->
      <div class="col-md-3">
        <nugget-search-filter
          :query="filter_options"
          v-on:filters="onFilters"
        ></nugget-search-filter>
      </div>
      <!-- Nugget list -->
      <div class="col-md-9">
        <div class="row">
          <div
            class="col-6 col-lg-6 col-xl-6 nugget-post-selection"
            v-for="(nugget, index) in nuggets"
            :key="index"
          >
            <nugget-post
              v-bind:key="index"
              v-bind:nugget="nugget"
              :selection="true"
              v-bind:class="{
                'nugget-post-selected': nugget.nugget_id == selected_id,
              }"
              @SelectButton="clickOnNugget"
            ></nugget-post>
          </div>
          <div
            class="col-md-9 form-inline align-items-start felement"
            v-if="nuggets.length == 0"
          >
            {{ config.labels.nugget_search_no_result }}
          </div>
        </div>
        <div class="row">
          <div class="show-more-nugget">
            <a
              href="javascript:;"
              v-if="show_more_nugget_button"
              v-on:click="show_more_nugget()"
              class="btn btn-primary nugget-button"
            >
              {{ config.labels.show_more_nugget_button }}
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
            class="btn btn-primary btn-replace"
          >
            {{ config.labels.click_to_replace }}
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
      show_more_nugget_button: false,
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
      return Object.assign(
        {},
        {
          page_size: this.default_page_size,
          fulltext: this.debounced_typed,
        }
      );
    },
    search_options() {
      return Object.assign({}, this.filter_options, this.filters);
    },
  },
  methods: {
    async initialize() {
      this.selected_id = document.getElementsByName("nugget_id")[0].value;
      if (this.selected_id != "" && this.selected_id != "nugget_id") {
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
      } else {
        this.search();
      }
    },
    show_more_nugget() {
      this.default_page_size = this.default_page_size + this.add_page_item;
      this.search();
    },
    search() {
      this.show_more_nugget_button = false;
      if (this.search_options) {
        this.loading++;

        this.proxy("mod_naas_search_nuggets",  { searchOptions: this.search_options, courseId: this.config.courseId })
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
                this.show_more_nugget_button = true;
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
