<template>
  <div class="container">
    <div id="nugget-info-button">
      <div>
        <a
          href="javascript:;"
          class="btn btn-primary"
          :class="{hidden: !detailButton}"
          v-on:click="detailModal = true"
        >
          {{ config.labels.see_nugget_details }}
        </a>
      </div>
      <NuggetDetailModal
        :visible="detailModal"
        :nugget="nugget"
        @close="detailModal = false"
      />
    </div>

    <div class="text-center gallery row" id="nugget-learn">
      <iframe
        id="lti-frame"
        height="600px"
        width="100%"
        style="border: none"
        :src="`launch.php?id=${this.config.cm_id}&triggerview=0`"
        webkitallowfullscreen
        mozallowfullscreen
        allowfullscreen
      ></iframe>
    </div>
    <div class="row">
      <div id="completion-modal-button" class="col text-center">
        <button
          href="javascript:;"
          class="btn btn-primary"
          @click="complete()"
        >
          {{ config.labels.complete_nugget }}
        </button>
      </div>
    </div>
    <NuggetCompletionModal
      :visible="completionModal"
      :nugget="nugget"
      :completed="nuggetCompleted"
      @close="completionModal = false"
    />
  </div>
</template>
<script>
import NuggetDetailModal from "./NuggetDetailModal.vue";
import NuggetCompletionModal from "./NuggetCompletionModal.vue";
import iframeResize from "iframe-resizer/js/iframeResizer";

export default {
  name: "NuggetView",
  components: {
    NuggetDetailModal,
    NuggetCompletionModal,
  },
  data() {
    return {
      detailButton: true,
      detailModal: false,
      completionModal: false,
      nugget: {},
      nuggetCompleted: false,
    };
  },
  created() {
    // Only exists in Moodle >= 4.0
    let navDetailsButton = document.querySelector('.secondary-navigation nav ul li[data-key=details]');
    this.detailButton = !navDetailsButton;
  },
  async mounted() {
    this.nugget = await this.get_nugget_default_version(this.config.nugget_id);
    window.setTimeout(() => {
      iframeResize(
        {
          log: false,
          checkOrigin: false,
          heightCalculationMethod: "lowestElement",
        },
        "#lti-frame"
      );
      // Sends 'experienced' xAPI statement
      this.xapi({ 
        'id': this.config.cm_id,
        'verb': 'experienced',
        'version_id': this.nugget.version_id
      });
    }, 100);

  },
  methods: {
    async complete() {
      this.completionModal = true;
      if (!this.nuggetCompleted) {
        // Sends 'completed' xAPI statement
        this.xapi({ 
          'id': this.config.cm_id,
          'verb': 'completed',
          'version_id': this.nugget.version_id
        });
        this.nuggetCompleted = true;
      }
    },
  }
};
</script>
