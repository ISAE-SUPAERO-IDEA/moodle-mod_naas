<template>
  <div class="container">
    <div id="nugget-info-button">
      <div>
        <a
          href="javascript:;"
          class="btn btn-primary"
          :class="{ hidden: !aboutButton }"
          v-on:click="aboutModal = true"
        >
          {{ config.labels.about }}
        </a>
        <select class="language-select" @change="changeLanguage">
          <option selected :value="this.nugget.language">
            {{ config.labels.metadata[nugget.language] }}
          </option>
          <option
            v-for="item in this.nugget.multilanguages"
            :key="item.language"
            :value="item.language"
          >
            {{ config.labels.metadata[item.language] }}
          </option>
        </select>
      </div>
      <NuggetAboutModal
        :visible="aboutModal"
        :nugget="nugget"
        @close="aboutModal = false"
      />
    </div>

    <div class="text-center gallery row" id="nugget-learn">
      <iframe
        id="lti-frame"
        height="600px"
        width="100%"
        style="border: none"
        :src="`launch.php?id=${this.config.cm_id}&triggerview=0&language=${this.nugget.language}`"
        webkitallowfullscreen
        mozallowfullscreen
        allowfullscreen
      ></iframe>
    </div>
    <div class="row">
      <div id="completion-modal-button" class="col text-center">
        <button href="javascript:;" class="btn btn-primary" @click="complete()">
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
import NuggetAboutModal from "./NuggetAboutModal.vue";
import NuggetCompletionModal from "./NuggetCompletionModal.vue";
import iframeResize from "iframe-resizer/js/iframeResizer";

export default {
  name: "NuggetView",
  components: {
    NuggetAboutModal,
    NuggetCompletionModal,
  },
  data() {
    return {
      aboutButton: true,
      aboutModal: false,
      completionModal: false,
      nugget: {},
      nuggetCompleted: false,
    };
  },
  created() {
    // Only exists in Moodle >= 4.0
    let navAboutButton = document.querySelector(
      ".secondary-navigation nav ul li[data-key=about]"
    );
    this.aboutButton = !navAboutButton;
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
        id: this.config.cm_id,
        verb: "experienced",
        version_id: this.nugget.version_id,
      });
    }, 100);
  },
  methods: {
    async complete() {
      this.completionModal = true;
      if (!this.nuggetCompleted) {
        // Sends 'completed' xAPI statement
        this.xapi({
          id: this.config.cm_id,
          verb: "completed",
          version_id: this.nugget.version_id,
        });
        this.nuggetCompleted = true;
      }
    },
    changeLanguage(event) {
      const iframe = document.getElementById("lti-frame");
      if (iframe)
        iframe.src = `launch.php?id=${this.config.cm_id}&triggerview=0&language=${event.target.value}`;
    },
  },
};
</script>
