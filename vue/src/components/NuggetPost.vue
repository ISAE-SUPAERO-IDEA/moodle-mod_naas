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
-->

<!--
/**
 * Nugget post component for NAAS plugin.
 *
 * @copyright (C) 2019 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->

<template>
  <div class="nugget-post h-100">
    <div class="h-100">
      <img
        class="w-100"
        :src="nugget.nugget_thumbnail_url.concat('?width=700&height=394')"
        alt=""
      />
      <h4>{{ nugget.name | truncate(50, "...") }}</h4>
      <h5>{{ authors_names }}</h5>
      <div class="description" v-html="nugget.resume"></div>
      <h5>{{ nugget.displayinfo }}</h5>
    </div>
    <div class="nugget-buttons">
      <a
        href="javascript:;"
        class="btn btn-primary nugget-button nugget-button-selection"
        v-on:click="SelectClickHandler(nugget)"
        v-show="selection"
      >
        {{ config.labels.select_button }}
      </a>
      <a
        href="javascript:;"
        class="btn btn-primary nugget-button"
        :class="{ 'nugget-button-selection': selection }"
        v-on:click="showNuggetViewModal()"
      >
        {{ config.labels.preview_button }}
      </a>
      <a
        href="javascript:;"
        class="btn btn-primary nugget-button"
        :class="{ 'nugget-button-selection': selection }"
        v-on:click="showNuggetAboutModal()"
      >
        {{ config.labels.about }}
      </a>
    </div>
    <NuggetAboutModal
      :visible="isNuggetAboutModalVisible"
      :nugget="nugget"
      @close="closeNuggetAboutModal()"
    />
    <NuggetViewModal
      :visible="isNuggetViewModalVisible"
      :nugget="nugget"
      @close="closeNuggetViewModal()"
    />
  </div>
</template>
<script>
import NuggetAboutModal from "./NuggetAboutModal.vue";
import NuggetViewModal from "./NuggetViewModal.vue";
export default {
  name: "NuggetPost",
  props: ["nugget", "selection"],
  components: {
    NuggetAboutModal,
    NuggetViewModal,
  },
  data() {
    return {
      isNuggetAboutModalVisible: false,
      isNuggetViewModalVisible: false,
    };
  },
  methods: {
    showNuggetAboutModal() {
      this.isNuggetAboutModalVisible = true;
    },
    closeNuggetAboutModal() {
      this.isNuggetAboutModalVisible = false;
    },
    showNuggetViewModal() {
      this.isNuggetViewModalVisible = true;
    },
    closeNuggetViewModal() {
      this.isNuggetViewModalVisible = false;
    },
    SelectClickHandler(nugget) {
      this.$emit("SelectButton", nugget);
    },
  },
  computed: {
    authors_names() {
      var authors_names = [];
      if (this.nugget.authors_data) {
        this.nugget.authors_data.forEach((author) => {
          if (author)
            authors_names.push(`${author.firstname} ${author.lastname}`);
        });
      }
      return authors_names.join(", ");
    },
  },
};
</script>
