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
 * Nugget view modal component for NAAS Vue application.
 *
 * @copyright  2019 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->
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
                    v-if="nuggetPreviewUrl"
                    id="lti-frame"
                    :src="nuggetPreviewUrl"
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
      nuggetPreviewUrl: null,
      initialized: false,
    };
  },
  watch: {
    visible(val) {
      if (val) {
        this.initialize();
      }
    },
  },
  methods: {
    initialize() {
      this.proxy("mod_naas_get_nugget_preview", {versionId: this.nugget.version_id, courseId: this.courseId}).then(
          (payload) => {
            this.nuggetPreviewUrl = payload;
            console.info(payload)
            this.initialized = true;
          }
      );
    },

    closeNuggetModal() {
      this.nuggetPreviewUrl = null;
      this.$emit("close");
    },
  },
};
</script>
