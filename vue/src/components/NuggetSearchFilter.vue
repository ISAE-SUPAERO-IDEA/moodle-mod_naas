<template>
  <div class="filters" ref="filters">
    <div
      v-show="has_aggregations"
      class="filters-inner"
    >
      <div
        v-for="(aggregation, aggregation_key) in aggregations"
        :v-if="aggregation.buckets"
        :key="aggregation_key"
      >
        <a href="javascript:;" class="aggregation-title"
          @click="switch_aggregation_visibility(aggregation)" data-toggle="dropdown">
          <h6 style="margin-top: 10px; margin-bottom: 0px; padding-top: 0px;">
            {{ config.labels.metadata[aggregation_key] }}
            <i v-if="aggregation.visible" class="icon fa fa-arrow-down"></i>
            <i v-else class="icon fa fa-arrow-right"></i>
          </h6>
        </a>


        <div :id="$id(aggregation_key)" v-show="aggregation.visible">
          <span v-for="bucket in aggregation.buckets" :key="bucket.key">
            <a href="javascript:;"
              ><span
                class="badge badge-margin"
                :class="bucket_class(bucket)"
                @click="switch_facet(aggregation_key, bucket.key)"
                >{{ bucket.caption }}</span>
            </a>
          </span>
        </div>
      </div>
      <div class="clear-filters" v-show="has_filters">
        <a href="javascript:;" @click="clear_filters()" class="btn btn-primary btn-small">
          {{config.labels.clear_filters}}
        </a>
      </div>
    </div>
  </div>
</template>
<script>
var NAAS_aggregations_order = [
  "level",
  "field_of_study",
  "tags",
  "structure_id",
  "authors",
  "references"
];
export default {
  name: "NuggetSearchFilter",
  props: ["query"],
  data() {
    return {
      state: {},
      aggregations: {},
      nuggets: undefined,
      filters_collapse: true
    };
  },
  watch: {
    query() {
      this.load();
    }
  },
  mounted() {
    this.load();
  },
  methods: {
    load() {
      if (this.query) {
        this.proxy(this.query).then(
          (payload) => {
            this.handle_aggregations(payload.aggregations);
          });
      }
      else {
        this.aggregations = {};
      }
    },
    // Updates aggregation data from response
    async handle_aggregations(network_aggregations) {
      var __this = this;
      if (network_aggregations) {
        var aggregations = Object.assign({});
        var j = 1;
        for (var i in NAAS_aggregations_order) {
          // going through expected aggregations
          var aggregation_key = NAAS_aggregations_order[i];
          if (network_aggregations[aggregation_key]) {
            var state_buckets = network_aggregations[aggregation_key].buckets;
            // add bucket only if it has content
            if (state_buckets.length > 0) {
              let old_aggregation = this.aggregations[aggregation_key];
              let visible = true;
              if (old_aggregation) visible = old_aggregation.visible;
              aggregations[aggregation_key] = aggregations[aggregation_key] || {
                buckets: {},
                visible
              };
              aggregations[aggregation_key].id = j;
              j = j + 1;
              for (var item_key in state_buckets) {
                var state_bucket = state_buckets[item_key];
                state_bucket.selected = false;
                // Computing captions
                if (aggregation_key == "structure_id") {
                  // For structures we have to fetch data from the API
                  // TODO: Not good, we should call those in parrallel
                  var structure = await __this.get_structure_cached(
                    state_bucket.key
                  );
                  if (structure) state_bucket.caption = structure.name;
                } else {
                  state_bucket.caption = state_bucket.key;
                }
                aggregations[aggregation_key].buckets[
                  state_bucket.key
                ] = state_bucket;
              }
            }
          }
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
          this.aggregations[aggregation_key].buckets[
            bucket_key
          ].selected = selected;
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
      // Unselect all other bucket of this aggregation
      if (this.aggregations[aggregation_key]) {
        for (var other_bucket_key in this.aggregations[aggregation_key]
            .buckets) {
          if (bucket_key != other_bucket_key) {
            this.set_facet_selected(aggregation_key, other_bucket_key, false);
          }
        }
      }
      this.$emit("filters", this.get_extra_params())
    },
    bucket_class(bucket) {
      var mode = bucket.selected ? "primary" : "default";
      var key = `badge-${mode}`;
      var clazz = {};
      clazz[key] = true;
      return clazz;
    },
    clear_filters() {
      // Unselect all buckets in all aggregations
      for (var aggregation_key in this.aggregations) {
        for (var bucket_key in this.aggregations[aggregation_key].buckets) {
          this.set_facet_selected(aggregation_key, bucket_key, false);
        }
      }
      this.$emit("filters", this.get_extra_params())
    },
    switch_aggregation_visibility(aggregation) {
      aggregation.visible = !aggregation.visible;
      this.aggregations = Object.assign({}, this.aggregations);
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
    }
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
          if (this.aggregations[aggregation_key].buckets[bucket_key].selected) return true;
        }
      }
      return false;
    }
  }
};
</script>