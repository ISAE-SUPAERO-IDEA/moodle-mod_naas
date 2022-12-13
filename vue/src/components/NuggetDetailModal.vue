<template>
  <transition name="modal-fade">
    <div class="nugget-modal-backdrop">
      <div class="nugget-modal">
        <header class="nugget-modal-header">
          <h3>{{ config.labels.metadata.details }}{{ post.name }}</h3>
          <button
            type="button"
            class="btn-close"
            @click="closeNuggetModal()"
          >
            x
          </button>
        </header>
        <section class="nugget-modal-body">
          <div class="row metadata_field">
            <div class="left_element" >
              <!-- Resume -->
              <div v-show="is_shown(post.resume)">
                <h3>{{ config.labels.metadata.resume }}</h3>
                <p class="p-position">{{ post.resume }}</p>
              </div>
              <!-- About author -->
              <div v-show="is_shown(post.authors_name)">
                <h3>{{ config.labels.metadata.about_author }}</h3>
                <h5>{{ post.authors_name.join(", ") }}</h5>
              </div>
            </div>
            <div class="center_element"></div>
            <!-- In brief -->
            <div v-show="in_brief_shown" class="right_element">
              <h3>{{ config.labels.metadata.in_brief }}</h3>
              <div>
                <ul class="metadata_list">
                  <li v-show="is_shown(post.duration)">
                    <i class="icon fa fa-clock-o"></i>
                    {{ config.labels.metadata.duration }}:
                    <strong id="formatage-duration">
                      {{ post.duration }} minutes
                    </strong>
                  </li>
                  <li v-show="is_shown(post.language)">
                    <i class="icon fa fa-globe"></i>
                    {{ config.labels.metadata.language }}:
                    <strong>{{ config.labels.metadata[post.language] }}</strong>
                  </li>
                  <li v-show="is_shown(post.level)">
                    <i class="icon fa fa-arrow-up"></i>
                    {{ config.labels.metadata.level }}:
                    <strong>{{ config.labels.metadata[post.level] }}</strong>
                  </li>
                  <li v-show="is_shown(post.domainsData)">
                    <i class="icon fa fa-home"></i>
                    {{ config.labels.metadata.field_of_study }}:<br />
                    <span v-for="item in post.domainsData" :key="item.id" class="metadata_list_item">
                      <span class="badge badge-pill badge-primary">{{ item.label }}</span>
                      <br/>
                    </span>
                  </li>
                  <li v-show="is_shown(post.tags)">
                    <i class="icon fa fa-tag"></i>
                    {{ config.labels.metadata.tags }}:<br />
                    <span v-for="item in post.tags" :key="item" class="metadata_list_item">
                      <span class="badge badge-pill badge-primary">{{ item }}</span>
                      <br/>
                    </span>
                  </li>
                </ul>
              </div>
            </div>
          </div>
          <!-- Prerequisites -->
          <div v-show="is_shown(post.prerequisites)" class="row metadata_field">
            <div class="w-100">
              <h3>{{ config.labels.metadata.prerequisites }}</h3>
              <ul class="about-list ul-position">
                <li v-for="item in post.prerequisites" :key="item">
                  <p>{{ item }}</p>
                </li>
              </ul>
            </div>
          </div>
          <!-- Learning outcomes -->
          <div v-show="is_shown(post.learning_outcomes)" class="row metadata_field">
            <div class="w-100">
              <h3>{{ config.labels.metadata.learning_outcomes }}</h3>
              <ul class="about-list ul-position">
                <li v-for="item in post.learning_outcomes" :key="item">
                  <p>{{ item }}</p>
                </li>
              </ul>
            </div>
          </div>
          <!-- References -->
          <div v-show="is_shown(post.references)" class="row metadata_field">
            <div class="w-100">
              <h3>{{ config.labels.metadata.references }}</h3>
              <ul class="about-list ul-position">
                <li v-for="item in post.references" :key="item">
                  <p>{{ item }}</p>
                </li>
              </ul>
            </div>
          </div>
        </section>
      </div>
    </div>
  </transition>
</template>
<script>
  export default {
    name: "NuggetDetailModal",
    props: ["post"],
    methods: {
      closeNuggetModal() {
        this.$emit('close');
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
      }
    },
    computed: {
      in_brief_shown() {
        return (
          this.is_shown(this.post.duration) ||
          this.is_shown(this.post.level) ||
          this.is_shown(this.post.tags) ||
          this.is_shown(this.post.language)
        );
      }
    }
  };
</script>