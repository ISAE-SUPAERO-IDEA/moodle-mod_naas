<!--
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Nugget view component for NAAS Vue application.
 *
 * @copyright  2019 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
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
        <select class="language-select" @change="language = $event.item.value">
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
        :src="iframeUrl"
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
      language: null,
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
    this.nugget = await this.viewNugget(this.config.cm_id);
    this.language = this.nugget.language

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
  computed: {
    iframeUrl() {
      if(!this.language) {
        return null
      }

      return `launch.php?id=${this.config.cm_id}&triggerview=0&language=${this.language}`
    }
  },
  methods: {
    async complete() {
      this.completionModal = true;
        // Sends 'completed' xAPI statement
        this.xapi({
          id: this.config.cm_id,
          verb: "completed",
          version_id: this.nugget.version_id,
        });
        this.nuggetCompleted = true;
    },
  },
};
</script>
