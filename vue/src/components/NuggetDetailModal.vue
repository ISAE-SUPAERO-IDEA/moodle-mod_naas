<template>
  <transition name="modal-fade">
    <div class="modal-backdrop">
      <div class="modal">
        <header class="modal-header">
          <h3>Preview : {{ post.name }}</h3>
          <button
            type="button"
            class="btn-close"
            @click="closeNuggetModal()"
          >
            x
          </button>
        </header>
        <section class="modal-body">
          <div class="row row_item">
            <div style="float: left; width: 69%;" >
              <!-- Resume -->
              <div v-show="is_shown(post.resume)">
                <h3>Resume</h3>
                <p class="p-position">{{ post.resume }}</p>
              </div>
              <!-- About author -->
              <div v-show="is_shown(post.authors_name)">
                <h3>About the author</h3>
                <h5>{{ post.authors_name.join(", ") }}</h5>
              </div>
            </div>
            <div style="width: 2%;"></div>
            <!-- In brief -->
            <div v-show="in_brief_shown" style="float: right; width: 29%;">
              <h3>In Brief</h3>
              <div>
                <ul style="list-style: none">
                  <li v-show="is_shown(post.duration)">
                    <i class="icon fa fa-clock"></i>
                    Learning time:
                    <strong id="formatage-duration">
                      {{ post.duration }} minutes
                    </strong>
                  </li>
                  <li v-show="is_shown(post.language)">
                    <i class="icon fa fa-globe"></i>
                    Language:
                    <strong>{{ post.language }}</strong>
                  </li>
                  <li v-show="is_shown(post.level)">
                    <i class="icon fa fa-arrows-up-down"></i>
                    Level:
                    <strong>{{ post.level }}</strong>
                  </li>
                  <li v-show="is_shown(post.domainsData)">
                    <i class="icon fa fa-octagon"></i>
                    Fields of study<br />
                    <span v-for="item in post.domainsData" :key="item.id">
                      <span class="badge badge-pill badge-primary">{{
                        item.label
                      }}</span>
                      <br
                    /></span>
                  </li>
                  <li v-show="is_shown(post.tags)">
                    <i class="icon fa fa-tag"></i>
                    Tags<br />
                    <span v-for="item in post.tags" :key="item">
                      <span class="badge badge-pill badge-primary">{{
                        item
                      }}</span>
                      <br
                    /></span>
                  </li>
                </ul>
              </div>
            </div>
          </div>
          <!-- Prerequisites -->
          <div v-show="is_shown(post.prerequisites)" class="row row_item">
            <div class="w-100">
              <h3>Prerequisites</h3>
              <ul class="about-list ul-position">
                <li v-for="item in post.prerequisites" :key="item">
                  <p>{{ item }}</p>
                </li>
              </ul>
            </div>
          </div>
          <!-- Learning outcomes -->
          <div v-show="is_shown(post.learning_outcomes)" class="row row_item">
            <div class="w-100">
              <h3>Learning outcomes</h3>
              <ul class="about-list ul-position">
                <li v-for="item in post.learning_outcomes" :key="item">
                  <p>{{ item }}</p>
                </li>
              </ul>
            </div>
          </div>
          <!-- References -->
          <div v-show="is_shown(post.references)" class="row row_item">
            <div class="w-100">
              <h3>References</h3>
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
  .modal-backdrop {
    background-color: rgba(0, 0, 0, 0.3);
    position: fixed;
    top: 0;
    left: 0;
  }
  .modal {
    overflow-x: auto;
    display: flex;
    height: 50%;
    width: 50%;
    position: absolute;
    left: 50%;
    top: 50%;
  }
  @media (max-width: 1250px) {
    .modal {
      height: 75%;
      width: 75%;
    }
  }
  .modal-header {
    padding: 15px 15px 0 15px;
    display: flex;
    border-bottom: 1px solid #eeeeee;
    justify-content: space-between;
  }
  .modal-body {
    position: relative;
    padding: 0;
  }
  .row_item {
    margin: 0 15px;
  }
</style>