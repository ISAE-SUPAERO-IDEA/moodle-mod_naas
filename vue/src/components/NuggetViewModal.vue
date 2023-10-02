<template>
  <div v-show="visible">
    <transition name="modal-fade">
      <div class="nugget-modal-backdrop" @click="closeNuggetModal()">
        <div id="nugget-preview-modal" class="nugget-modal" @click.stop.prevent>
          <div class="container h-100">
            <div class="nugget-modal-header row justify-content-between align-items-start">
              <h2>{{ config.labels.metadata.preview }}{{ nugget.name }}</h2>
              <button type="button" class="btn-close" @click="closeNuggetModal()">
                ✕
              </button>
            </div>
            <div class="nugget-modal-body row">
              <div class="nugget-view w-100">
                <iframe
                  id="lti-frame"
                  :src="NuggetView"
                  class="preview-iframe h-100 w-100"
                ></iframe>
              </div>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>
<script>
export default {
  name: "NuggetViewModal",
  props: ["nugget", "visible"],
  data() {
    return {
      NuggetView: "",
      initialized: false,
    };
  },
  watch: {
    visible(val) {
      if (val) this.initialize();
    },
  },
  methods: {
    initialize() {
      if (!this.initialized) {
        this.proxy(`/versions/${this.nugget.version_id}/preview_url`).then(
          (payload) => {
            this.NuggetView = payload;
          }
        );
      }
    },
    closeNuggetModal() {
      this.$emit("close");
    },
  },
};
</script>
