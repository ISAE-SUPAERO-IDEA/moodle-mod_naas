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
 * Nugget completion modal component for NAAS plugin.
 *
 * @copyright (C) 2023 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->

<template>
  <div ref="completionModal" v-show="visible">
    <div class="nugget-modal-backdrop">
      <div class="nugget-modal">
        <div class="container">
          <div class="nugget-modal-header row justify-content-end">
            <button type="button" class="btn-close" @click="closeModal()">
              ✕
            </button>
          </div>
          <div class="nugget-modal-body row">
            <!-- Rating -->
            <div class="text-center col">
              <h2>{{ config.labels.rating.title }}</h2>
              <p class="rating saved">
                <span
                  v-for="i in max"
                  v-bind:key="i"
                  @click="savedRating = max + 1 - i"
                  class="star"
                  :class="{ checked: savedRating === max + 1 - i }"
                >
                  <i class="icon fa fa-star"></i>
                </span>
              </p>
              <button
                id="send-rating"
                type="button"
                class="btn btn-sm btn-outline-success mt-2"
                :disabled="ratingSent"
                @click="rate(savedRating, $event)"
              >
                {{ config.labels.rating.send }}
              </button>
              <p class="rating-description">
                {{ config.labels.rating.description }}
              </p>
            </div>
          </div>
          <!-- Learning Outcomes -->
          <div
            v-if="nugget.learning_outcomes && nugget.learning_outcomes.length"
            class="finish-learning-outcomes row"
          >
            <div class="col text-center">
              {{ config.labels.learning_outcomes_desc }}
              <span v-for="item in nugget.learning_outcomes" :key="item"
                >• {{ item }}
              </span>
            </div>
          </div>
          <div class="nugget-modal-footer row justify-content-center">
            <a :href="backLink" class="btn btn-sm btn-primary"
              >◀︎ {{ config.labels.back_to_course }}</a
            >
            <a v-if="nextUnitLink" :href="nextUnitLink" class="ml-2 btn btn-sm btn-primary"
              >{{ config.labels.next_unit }} ▶︎</a
            >
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
// Maximum and minimum score
const MinScore = 1;
const MaxScore = 5;

export default {
  props: ["nugget", "visible"],
  name: "NuggetCompletionModal",
  data() {
    return {
      max: MaxScore,
      ratingSent: false,
      savedRating: null,
      backLink: "#",
      nextUnitLink: null,
    };
  },
  mounted() {
    // We steal the 'back to course' and 'next actvity' links from other elements of the DOM
    this.backLink = document.querySelector(".course-button a").href;

    if (document.querySelector(".next-activity a, #next-activity-link")) {
      this.nextUnitLink = document.querySelector(
        ".next-activity a, #next-activity-link"
      ).href;
    }
  },
  methods: {
    closeModal() {
      this.$emit("close");
    },
    rate(score, event) {
      event.target.innerHTML = this.config.labels.rating.sent;
      let body = {
        // Score
        raw: score,
        min: MinScore,
        max: MaxScore,
      };
      // Sends 'rated' xAPI statement
      this.xapi({
        id: this.config.cm_id,
        verb: "rated",
        version_id: this.nugget.version_id,
        body: JSON.stringify(body),
      });
      this.ratingSent = true;
    },
  },
};
</script>
