<template>
  <transition name="modal-fade">
    <div class="nugget-modal-backdrop">
      <div class="nugget-modal">
        <header class="nugget-modal-header">
          <h3>{{ config.labels.metadata.preview }}{{ post.name }}</h3>
          <button
            type="button"
            class="btn-close"
            @click="closeNuggetModal()"
          >
            x
          </button>
        </header>
        <section class="nugget-modal-body h-100">
          <div class="row nugget_view h-100">
            <iframe id="lti_frame" :src="NuggetView" class="preview-iframe w-100"></iframe>
          </div>
        </section>
      </div>
    </div>
  </transition>
</template>
<script>
  export default {
    name: "NuggetViewModal",
    props: ["post"],
    data() {
      return {
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
      }
    }
  };
</script>