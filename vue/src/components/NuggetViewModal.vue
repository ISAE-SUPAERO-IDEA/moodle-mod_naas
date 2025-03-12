<template>
  <div v-show="visible">
    <transition name="modal-fade">
      <div class="nugget-modal-backdrop" @click="closeNuggetModal()">
        <div id="nugget-preview-modal" class="nugget-modal" @click.stop.prevent>
          <div class="container h-100">
            <div
              class="nugget-modal-header row justify-content-between align-items-start"
            >
              <h2>{{ config.labels.metadata.preview }}{{ nugget.name }}</h2>
              <button
                type="button"
                class="btn-close"
                @click="closeNuggetModal()"
              >
                ✕
              </button>
            </div>
            <div class="nugget-modal-body row">
              <div class="nugget-view w-100">
                <iframe
                  v-if="iframeVisible"
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
  props: ["nugget", "visible", "courseId"],
  data() {
    return {
      NuggetView: "",
      iframeVisible: true,
      initialized: false,
    };
  },
  watch: {
    visible(val) {
      if (val) {
        this.initialize();
        this.iframeVisible = true;
      }
    },
  },
  methods: {
    initialize() {
      if (!this.initialized) {
        this.proxy("get-nugget-preview", { versionId: this.nugget.version_id, courseId: this.courseId}).then(
          (payload) => {
            this.NuggetView = payload;
            this.initialized = true;
          }
        );
      }
    },
    closeNuggetModal() {
      this.iframeVisible = false;
      this.$emit("close");
    },
  },
};
</script>
