<template>
  <transition name="modal-fade">
    <div style="position: fixed; top: 0; bottom: 0; left: 0; right: 0; background-color: rgba(0, 0, 0, 0.3); display: flex; justify-content: center; align-items: center; z-index: 2;">
      <div style="overflow-x: auto; display: flex; height: 50%; width: 50%; position: absolute; left: 50%; top: 50%; background: #FFFFFF; box-shadow: 2px 2px 20px 1px; border-radius: 5px; flex-direction: column; transform: translate(-50%, -50%);">
        <header style="padding: 15px 15px 0 15px; display: flex; border-bottom: 1px solid #eeeeee; justify-content: space-between; position: relative;">
          <h3>Details : {{ post.name }}</h3>
          <button
            type="button"
            style="position: absolute; top: 0; right: 0; border: none; font-size: 20px; padding: 10px; cursor: pointer; font-weight: bold; color: #999999; background: transparent;"
            @click="closeNuggetModal()"
          >
            x
          </button>
        </header>
        <section style="overflow-y: auto; position: relative; padding: 0;">
          <div class="row" style="margin: 0 15px;">
            <div style="float: left; width: 69%;" >
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
            <div style="width: 2%;"></div>
            <!-- In brief -->
            <div v-show="in_brief_shown" style="float: right; width: 29%;">
              <h3>{{ config.labels.metadata.in_brief }}</h3>
              <div>
                <ul style="list-style: none">
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
                    <span v-for="item in post.domainsData" :key="item.id" style="margin-left: 30px;">
                      <span class="badge badge-pill badge-primary">{{ item.label }}</span>
                      <br/>
                    </span>
                  </li>
                  <li v-show="is_shown(post.tags)">
                    <i class="icon fa fa-tag"></i>
                    {{ config.labels.metadata.tags }}:<br />
                    <span v-for="item in post.tags" :key="item" style="margin-left: 30px;">
                      <span class="badge badge-pill badge-primary">{{ item }}</span>
                      <br/>
                    </span>
                  </li>
                </ul>
              </div>
            </div>
          </div>
          <!-- Prerequisites -->
          <div v-show="is_shown(post.prerequisites)" class="row" style="margin: 0 15px;">
            <div style="width: 100%;">
              <h3>{{ config.labels.metadata.prerequisites }}</h3>
              <ul class="about-list ul-position">
                <li v-for="item in post.prerequisites" :key="item">
                  <p>{{ item }}</p>
                </li>
              </ul>
            </div>
          </div>
          <!-- Learning outcomes -->
          <div v-show="is_shown(post.learning_outcomes)" class="row" style="margin: 0 15px;">
            <div style="width: 100%;">
              <h3>{{ config.labels.metadata.learning_outcomes }}</h3>
              <ul class="about-list ul-position">
                <li v-for="item in post.learning_outcomes" :key="item">
                  <p>{{ item }}</p>
                </li>
              </ul>
            </div>
          </div>
          <!-- References -->
          <div v-show="is_shown(post.references)" class="row" style="margin: 0 15px;">
            <div style="width: 100%;">
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
<style scoped>
  .modal-fade-enter,
  .modal-fade-leave-to {
    opacity: 0;
  }
  .modal-fade-enter-active,
  .modal-fade-leave-active {
    transition: opacity .3s ease;
  }

  @media (max-width: 1250px) {
    .modal {
      height: 75%;
      width: 75%;
    }
  }
</style>