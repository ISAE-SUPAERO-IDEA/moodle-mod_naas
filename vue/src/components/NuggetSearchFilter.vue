<template>
  <div class="filters" ref="filters">
    <img
      v-show="loading"
      v-bind:src="'../mod/naas/assets/loading.gif'"
      width="35"
      height="35"
    />
    <div v-show="has_aggregations" class="filters-inner">
      <div
        v-for="(aggregation, aggregation_key) in aggregations"
        :v-if="aggregation.buckets"
        :key="aggregation_key"
      >
        <a
          href="javascript:;"
          class="aggregation-title"
          @click="switch_aggregation_visibility(aggregation)"
          data-toggle="dropdown"
        >
          <h6 class="filters-title">
            {{ config.labels.metadata[aggregation_key] }}
            <i v-if="aggregation.visible" class="icon fa fa-arrow-down"></i>
            <i v-else class="icon fa fa-arrow-right"></i>
          </h6>
        </a>

        <div :id="$id(aggregation_key)" v-show="aggregation.visible">
          <span
            v-for="(bucket, id, index) in aggregation.buckets"
            :key="bucket.key"
          >
            <ul
              class="related-domains-list"
              v-if="aggregation_key == 'related_domains'"
            >
              <li class="related-domains-list-element">
                <a href="javascript:;" class="badge badge-margin">
                  <label
                    class="related-domains-label"
                    v-if="bucket.key.length == '2'"
                    :title="bucket.caption"
                  >
                    <input
                      type="checkbox"
                      class="related-domains-checkbox"
                      @click="switch_facet(aggregation_key, bucket.query_value)"
                    />
                    {{ bucket.caption | truncate(33, "...") }}
                  </label>
                </a>
                <ul class="related-domains-list" style="padding-left: 20px">
                  <li class="related-domains-list-element">
                    <a href="javascript:;" class="badge badge-margin">
                      <label
                        class="related-domains-label"
                        v-if="bucket.key.length == '3'"
                        :title="bucket.caption"
                      >
                        <input
                          type="checkbox"
                          class="related-domains-checkbox"
                          @click="
                            switch_facet(aggregation_key, bucket.query_value)
                          "
                        />
                        {{ bucket.caption | truncate(30, "...") }}
                      </label>
                    </a>
                    <ul class="related-domains-list" style="padding-left: 20px">
                      <li class="related-domains-list-element">
                        <a href="javascript:;" class="badge badge-margin">
                          <label
                            class="related-domains-label"
                            v-if="bucket.key.length == '4'"
                            :title="bucket.caption"
                          >
                            <input
                              type="checkbox"
                              class="related-domains-checkbox"
                              @click="
                                switch_facet(
                                  aggregation_key,
                                  bucket.query_value
                                )
                              "
                            />
                            {{ bucket.caption | truncate(27, "...") }}
                          </label>
                        </a>
                      </li>
                    </ul>
                  </li>
                </ul>
              </li>
            </ul>
            <a
              href="javascript:;"
              v-if="aggregation_key != 'related_domains'"
              :class="{
                'hide-authors': aggregation_key == 'authors' && index > 5,
              }"
            >
              <span
                class="badge badge-margin"
                :class="bucket_class(bucket)"
                @click="switch_facet(aggregation_key, bucket.key)"
                >{{ bucket.caption }}</span
              >
            </a>
          </span>
          <a
            href="javascript:;"
            id="show-more-authors"
            class="clear-filters show-more"
            v-if="aggregation_key == 'authors' && has_more(aggregation)"
            @click="show_more_bucket()"
          >
            {{ config.labels.show_more_authors }}
          </a>
        </div>
      </div>
      <div class="clear-filters" v-show="has_filters">
        <a
          href="javascript:;"
          @click="clear_filters()"
          class="btn btn-primary btn-small"
        >
          {{ config.labels.clear_filters }}
        </a>
      </div>
    </div>
  </div>
</template>
<script>
var aggregations_definitions = [
  {
    name: "related_domains",
    bucket_key_to_ui(bucket_key, component) {
      return component.getDomainLabel(bucket_key);
    },
  },
  {
    name: "level",
    bucket_key_to_ui: (bucket_key, component) => component.$t(`${bucket_key}`),
  },
  {
    name: "language",
    bucket_key_to_ui: (bucket_key, component) => component.$t(`${bucket_key}`),
  },
  "tags",
  {
    name: "producers",
    aggregation_key: "producers",
    bucket_key_to_query: (bucket_key) => bucket_key,
    bucket_key_to_ui: (bucket_key, component) =>
      component.getStructureAcronym(bucket_key),
  },
  {
    name: "authors",
    aggregation_key: "authors",
    bucket_key_to_query: (bucket_key) => bucket_key,
    bucket_key_to_ui: (bucket_key, component) =>
      component.getPersonName(bucket_key),
  },
  "references",
  {
    name: "type",
    bucket_key_to_ui: (bucket_key, component) => component.$t(`${bucket_key}`),
  },
];
// Setting default values for simple aggregations
for (var i in aggregations_definitions) {
  var def = aggregations_definitions[i];
  if (typeof def === "string" || def instanceof String) def = { name: def };
  def.aggregation_key = def.aggregation_key || def.name;
  def.bucket_key_filter = def.bucket_key_filter || ((bucket_key) => bucket_key);
  def.bucket_key_to_ui = def.bucket_key_to_ui || ((bucket_key) => bucket_key);
  def.bucket_key_to_ui_help = def.bucket_key_to_ui_help || def.bucket_key_to_ui;
  def.bucket_key_to_query =
    def.bucket_key_to_query || ((bucket_key) => bucket_key);
  aggregations_definitions[i] = def;
}

import Loading from "./Loading";
export default {
  name: "NuggetSearchFilter",
  props: ["query"],
  component: { Loading },
  data() {
    return {
      state: {},
      aggregations: {},
      nuggets: undefined,
      filters_collapse: true,
      loading: false,
    };
  },
  watch: {
    query() {
      this.load();
    },
  },
  mounted() {
    this.load();
  },
  methods: {
    async load() {
      if (this.query) {
        this.proxy(this.query).then(async (payload) => {
          if (payload) this.loading = true;
          await this.handle_aggregations(payload.aggregations);
          this.loading = false;
        });
      } else {
        this.aggregations = {};
      }
    },
    // Updates aggregation data from response
    async handle_aggregations(network_aggregations) {
      if (network_aggregations) {
        var aggregations = Object.assign({});
        let aggregations_to_sort = new Array();
        var j = 1;
        for (var i in aggregations_definitions) {
          // going through expected aggregations
          var aggregation_definition = aggregations_definitions[i];
          var aggregation_key = aggregation_definition.aggregation_key;
          var name = aggregation_definition.name;
          if (network_aggregations[aggregation_key]) {
            var state_buckets = network_aggregations[aggregation_key].buckets;
            // add bucket only if it has content
            if (state_buckets.length > 0) {
              let old_aggregation = this.aggregations[name];
              // Aggregations are not visible by default on smaller devices
              let visible = true;
              if (old_aggregation) visible = old_aggregation.visible;
              aggregations[name] = aggregations[name] || {
                buckets: {},
                visible,
                name: aggregation_definition.name,
              };
              aggregations[name].id = j;
              j = j + 1;
              let aggregation_array = new Array();
              // Convert all buckets into data structures adapted to the UI
              for (var item_key in state_buckets) {
                var state_bucket = state_buckets[item_key];
                if (
                  aggregation_definition.bucket_key_filter(
                    state_bucket.key,
                    this
                  )
                ) {
                  // Bucket is not selected by default
                  state_bucket.selected = false;
                  // Convert key to UI readable string and add document count
                  state_bucket.caption =
                    await aggregation_definition.bucket_key_to_ui(
                      state_bucket.key,
                      this
                    );
                  state_bucket.caption = `${state_bucket.caption} (${state_bucket.docCount})`;
                  // Convert key to a help text
                  state_bucket.help =
                    await aggregation_definition.bucket_key_to_ui_help(
                      state_bucket.key,
                      this
                    );
                  // Convert key to query key (for actual search)
                  state_bucket.query_value =
                    aggregation_definition.bucket_key_to_query(
                      state_bucket.key,
                      this
                    );

                  // Do not sort related domains otherwise it's will break the tree
                  if (name == "related_domains")
                    aggregations[name].buckets[state_bucket.query_value] =
                      state_bucket;
                  else aggregation_array.push(state_bucket);
                }
              }
              aggregations_to_sort[name] = aggregation_array;
            }
          }
        }

        for (var aggregation_title in aggregations_to_sort) {
          let sorted_aggregation = aggregations_to_sort[aggregation_title].sort(
            (a, b) => {
              if (a.caption < b.caption) return -1;
              if (a.caption > b.caption) return 1;
              return 0;
            }
          );

          for (var index in sorted_aggregation)
            aggregations[aggregation_title].buckets[
              sorted_aggregation[index].key
            ] = sorted_aggregation[index];
        }

        this.aggregations = aggregations;
      }
    },
    facet_exists(aggregation_key, bucket_key) {
      return (
        this.aggregations[aggregation_key] !== undefined &&
        this.aggregations[aggregation_key].buckets[bucket_key] !== undefined
      );
    },
    get_facet_selected(aggregation_key, bucket_key) {
      if (this.facet_exists(aggregation_key, bucket_key)) {
        return this.aggregations[aggregation_key].buckets[bucket_key].selected;
      }
      return false;
    },
    set_facet_selected(aggregation_key, bucket_key, selected) {
      if (this.facet_exists(aggregation_key, bucket_key)) {
        if (this.get_facet_selected(aggregation_key, bucket_key) != selected) {
          this.aggregations[aggregation_key].buckets[bucket_key].selected =
            selected;
          this.aggregations = Object.assign({}, this.aggregations);
        }
      }
    },
    switch_facet(aggregation_key, bucket_key) {
      // Toogle the value of this bucket
      this.set_facet_selected(
        aggregation_key,
        bucket_key,
        !this.get_facet_selected(aggregation_key, bucket_key)
      );
      /*
      // Unselect all other bucket of this aggregation
      if (this.aggregations[aggregation_key]) {
        for (var other_bucket_key in this.aggregations[aggregation_key]
          .buckets) {
          if (bucket_key != other_bucket_key) {
            // this.set_facet_selected(aggregation_key, other_bucket_key, false);
          }
        }
      }
      */
      this.$emit("filters", this.get_extra_params());
    },
    // Synchronize navigation with UI selection
    get_extra_params() {
      var query = {};
      for (var aggregation_key in this.aggregations) {
        for (var item_key in this.aggregations[aggregation_key].buckets) {
          var item = this.aggregations[aggregation_key].buckets[item_key];
          if (item.selected) {
            query[aggregation_key] = query[aggregation_key] || [];
            query[aggregation_key].push(item.key);
          }
        }
      }
      return query;
    },
    bucket_class(bucket) {
      var mode = bucket.selected ? "primary" : "default";
      var key = `badge-${mode}`;
      var clazz = {};
      clazz[key] = true;
      return clazz;
    },
    clear_filters() {
      // Uncheck all checkbox
      var checkbox = document.getElementsByClassName(
        "related-domains-checkbox"
      );
      for (var i = 0; i < checkbox.length; i++) checkbox[i].checked = false;

      // Unselect all buckets in all aggregations
      for (var aggregation_key in this.aggregations) {
        for (var bucket_key in this.aggregations[aggregation_key].buckets) {
          this.set_facet_selected(aggregation_key, bucket_key, false);
        }
      }
      this.$emit("filters", this.get_extra_params());
    },
    switch_aggregation_visibility(aggregation) {
      aggregation.visible = !aggregation.visible;
      this.aggregations = Object.assign({}, this.aggregations);
    },
    has_more(aggregation) {
      if (Object.keys(aggregation["buckets"]).length > 5) {
        return true;
      } else {
        return false;
      }
    },
    show_more_bucket() {
      var hide_button = document.getElementsByClassName("hide-authors");
      var is_visible = hide_button[0].style.display == "inline";

      for (var i = 0; i < hide_button.length; i++) {
        if (is_visible) {
          hide_button[i].style.display = "none";
          document.getElementById("show-more-authors").innerHTML =
            this.config.labels.show_more_authors;
        } else {
          hide_button[i].style.display = "inline";
          document.getElementById("show-more-authors").innerHTML =
            this.config.labels.hide_authors;
        }
      }
    },
  },
  computed: {
    has_aggregations() {
      for (var key in this.aggregations) {
        if (this.aggregations[key].buckets) return true;
      }
      return false;
    },
    has_filters() {
      for (var aggregation_key in this.aggregations) {
        for (var bucket_key in this.aggregations[aggregation_key].buckets) {
          if (this.aggregations[aggregation_key].buckets[bucket_key].selected)
            return true;
        }
      }
      return false;
    },
  },
};
</script>
