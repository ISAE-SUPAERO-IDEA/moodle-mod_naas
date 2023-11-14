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
                class="btn btn-sm btn-outline-secondary"
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
          <div class="nugget-modal-footer row justify-content-between">
            <a :href="backLink" class="btn btn-link"
              >◀︎ {{ config.labels.back_to_course }}</a
            >
            <a v-if="nextUnitLink" :href="nextUnitLink" class="btn btn-link"
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
