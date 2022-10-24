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

        <div class="row" style="margin: 0px 5px 15px 5px;">
          <button v-on:click="showNuggetView = false" class="btn" style="width: 50%; background: #F5F5F5; border: solid black 1px;">
            Detail
          </button>
          <button v-on:click="showNuggetView = true" class="btn" style="width: 50%; background: #F5F5F5; border: solid black 1px;">
            View
          </button>
        </div>

        <section class="modal-body" v-show="!showNuggetView">
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
            <div style="width: 100%">
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
            <div style="width: 100%">
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
            <div style="width: 100%">
              <h3>References</h3>
              <ul class="about-list ul-position">
                <li v-for="item in post.references" :key="item">
                  <p>{{ item }}</p>
                </li>
              </ul>
            </div>
          </div>
         </section>

         <section class="modal-body" v-show="showNuggetView">
          <div class="row row_item NuggetView">           
            <iframe id='lti_frame' :src='NuggetView' style='border:none; width:100%'></iframe>
          </div>
        </section>
      </div>
    </div>
  </transition>
</template>

<script>
  export default {
    name: 'NuggetModal',
    props: ["post"],
    data() {      
      return {
        showNuggetView: false,
        NuggetView: ""
      }
    },
    mounted() {
      this.initialize();
    },
    methods: {
      initialize() {
        this.proxy(`/versions/` + this.post.version_id + `/preview_url`).then(
          (payload) => {
            this.NuggetView = payload;
          }
        );
      },
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

<style>
  .modal-fade-enter,
  .modal-fade-leave-to {
    opacity: 0;
  }
  .modal-fade-enter-active,
  .modal-fade-leave-active {
    transition: opacity .3s ease;
  }
  .modal-backdrop {
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    right: 0;
    background-color: rgba(0, 0, 0, 0.3);
    display: flex;
    justify-content: center;
    align-items: center;
  }
  .modal {
    background: #FFFFFF;
    box-shadow: 2px 2px 20px 1px;
    overflow-x: auto;
    display: flex;
    flex-direction: column;
    border-radius: 5px;

    height: 50%;
    width: 50%;
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
  }
  .modal-header {
    padding: 15px 15px 0px 15px;
    display: flex;
  }
  .modal-header {
    position: relative;
    border-bottom: 1px solid #eeeeee;
    justify-content: space-between;
  }
  .modal-body {
    position: relative;
    overflow-y: auto;
    padding: 0px;
  }
  .btn-close {
    position: absolute;
    top: 0;
    right: 0;
    border: none;
    font-size: 20px;
    padding: 10px;
    cursor: pointer;
    font-weight: bold;
    color: #999999;
    background: transparent;
  }

  .row_item {
    margin: 0px 10px 0px 10px;
  }
  .NuggetView {
    padding: 0px 0px 10px 0px;
    height: 100%;
  }
</style>