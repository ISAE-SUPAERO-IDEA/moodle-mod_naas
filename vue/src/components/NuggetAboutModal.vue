<template>
  <div v-show="visible">
    <transition name="modal-fade">
      <div class="nugget-modal-backdrop">
        <div class="nugget-modal">
          <header class="nugget-modal-header">
            <h3>{{ config.labels.metadata.about_title }}{{ nugget.name }}</h3>
            <button type="button" class="btn-close" @click="closeNuggetModal()">
              x
            </button>
          </header>
          <section class="nugget-modal-body">
            <div class="row metadata_field">
              <div class="left_element">
                <!-- Resume -->
                <div v-show="is_shown(nugget.resume)">
                  <h3>{{ config.labels.metadata.resume }}</h3>
                  <p class="p-position">{{ nugget.resume }}</p>
                </div>
                <!-- About author -->
                <div v-show="is_shown(nugget.authors_data)">
                  <h3>{{ config.labels.metadata.about_author }}</h3>
                  <div
                    v-for="author in nugget.authors_data"
                    :key="author.email"
                  >
                    <h5>{{ author.firstname }} {{ author.lastname }}</h5>
                    <span v-html="author.bio"></span>
                  </div>
                </div>
              </div>
              <div class="center_element"></div>
              <!-- In brief -->
              <div v-show="in_brief_shown" class="right_element">
                <h3>{{ config.labels.metadata.in_brief }}</h3>
                <div>
                  <ul class="metadata_list">
                    <li v-show="is_shown(nugget.duration)">
                      <i class="icon fa fa-clock-o"></i>
                      {{ config.labels.metadata.duration }}:
                      <strong id="formatage-duration">
                        {{ nugget.duration }} minutes
                      </strong>
                    </li>
                    <li v-show="is_shown(nugget.language)">
                      <i class="icon fa fa-globe"></i>
                      {{ config.labels.metadata.language }}:
                      <strong>{{
                        config.labels.metadata[nugget.language]
                      }}</strong>
                    </li>
                    <li v-show="is_shown(nugget.level)">
                      <i class="icon fa fa-arrow-up"></i>
                      {{ config.labels.metadata.level }}:
                      <strong>{{
                        config.labels.metadata[nugget.level]
                      }}</strong>
                    </li>
                    <li v-show="is_shown(nugget.domainsData)">
                      <i class="icon fa fa-home"></i>
                      {{ config.labels.metadata.field_of_study }}:<br />
                      <span
                        v-for="item in nugget.domainsData"
                        :key="item.id"
                        class="metadata_list_item"
                      >
                        <span class="badge badge-pill badge-primary">{{
                          item.label
                        }}</span>
                        <br />
                      </span>
                    </li>
                    <li v-show="is_shown(nugget.tags)">
                      <i class="icon fa fa-tag"></i>
                      {{ config.labels.metadata.tags }}:<br />
                      <span
                        v-for="item in nugget.tags"
                        :key="item"
                        class="metadata_list_item"
                      >
                        <span class="badge badge-pill badge-primary">{{
                          item
                        }}</span>
                        <br />
                      </span>
                    </li>
                    <li v-show="is_shown(nugget.domains_data)">
                      <i class="icon fa fa-tag"></i>
                      {{ config.labels.metadata.related_domains }}:<br />
                      <span
                        v-for="domain in nugget.domains_data"
                        :key="domain.id"
                        class="metadata_list_item"
                      >
                        <span class="badge badge-pill badge-primary">{{
                          domain.label
                        }}</span>
                        <br />
                      </span>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
            <!-- Prerequisites -->
            <div
              v-show="is_shown(nugget.prerequisites)"
              class="row metadata_field"
            >
              <div class="w-100">
                <h3>{{ config.labels.metadata.prerequisites }}</h3>
                <ul class="about-list ul-position">
                  <li v-for="item in nugget.prerequisites" :key="item">
                    <p>{{ item }}</p>
                  </li>
                </ul>
              </div>
            </div>
            <!-- Learning outcomes -->
            <div
              v-show="is_shown(nugget.learning_outcomes)"
              class="row metadata_field"
            >
              <div class="w-100">
                <h3>{{ config.labels.metadata.learning_outcomes }}</h3>
                <ul class="about-list ul-position">
                  <li v-for="item in nugget.learning_outcomes" :key="item">
                    <p>{{ item }}</p>
                  </li>
                </ul>
              </div>
            </div>
            <!-- References -->
            <div
              v-show="is_shown(nugget.references)"
              class="row metadata_field"
            >
              <div class="w-100">
                <h3>{{ config.labels.metadata.references }}</h3>
                <ul class="about-list ul-position">
                  <li v-for="item in nugget.references" :key="item">
                    <p>{{ item }}</p>
                  </li>
                </ul>
              </div>
            </div>
          </section>
        </div>
      </div>
    </transition>
  </div>
</template>
<script>
export default {
  name: "NuggetAboutModal",
  props: ["nugget", "visible"],
  methods: {
    closeNuggetModal() {
      this.$emit("close");
    },
    is_shown(val) {
      if (val == undefined) return false;
      if (val == "") return false;
      if (Array.isArray(val) && val.length == 0) return false;
      if (typeof val === "object") {
        for (const inner_val of Object.values(val)) {
          if (this.is_shown(inner_val)) return true;
        }
        return false;
      }
      return true;
    },
  },
  computed: {
    in_brief_shown() {
      return (
        this.is_shown(this.nugget.duration) ||
        this.is_shown(this.nugget.level) ||
        this.is_shown(this.nugget.tags) ||
        this.is_shown(this.nugget.language)
      );
    },
    authors_info() {
      return this.nugget.authors_data
        ? this.nugget.authors_data.join(", ")
        : "";
    },
  },
};
</script>
