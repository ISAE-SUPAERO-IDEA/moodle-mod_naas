<template>
<div ref="completionModal" v-show="visible">
  <div class="nugget-modal-backdrop" @click="closeModal()">
    <div class="nugget-modal" @click.stop.prevent>
      <div class="container">
        <div class="nugget-modal-header row justify-content-end">
          <button type="button" class="btn-close" @click="closeModal()">
            ✕
          </button>
        </div>
        <div class="nugget-modal-body row">
          <!-- Rating -->
          <div class="text-center col">
            <h2>Rate this nugget</h2>
            <p class="rating saved">
              <span
                v-for="i in max"
                v-bind:key="i"
                @click="saved_rating=max+1-i"
                class="star"
                :class="{ checked: saved_rating === max + 1 - i }"
              >
                <i class="icon fa fa-star"></i>
              </span>
            </p>
            <button id="send-rating" type="button" class="btn btn-sm btn-outline-secondary" 
              :disabled='ratingSent' @click="rate(saved_rating, $event)">Send</button>
            <p class="rating-description">Your rating will be used to improve the quality of our content</p>
          </div>
        </div>
        <!-- Learning Outcomes -->
        <div v-if="nugget.learning_outcomes.length" class="row" >
            <h5>You have completed this nugget. The learning objectives were:</h5>
            <ul>
              <li class="align-self-start" v-for="item in nugget.learning_outcomes" :key="item">
                {{ item }}
              </li>
            </ul>
        </div>
        <div class="nugget-modal-footer row justify-content-between">
          <a :href="backLink" class="btn btn-link">◀︎ Back to Course Index</a>
          <a :href="nextUnitLink" class="btn btn-link">Next Unit ▶︎</a>
        </div>
      </div>
    </div>
  </div>
</div>
</template>
<script>
// Maximum and minimum score
//const MinScore = 1;
const MaxScore = 5;

export default {
  props: ["nugget", "visible"],
  name: "NuggetCompletionModal",
  data() {
    return {
      max: MaxScore,
      ratingSent: false,
      saved_rating: null,
      backLink: "#",
      nextUnitLink: "#"
    };
  },
  methods: {
    closeModal() {
      this.$emit("close");
    },
    rate(score, event) {
      event.target.innerHTML = 'Sent ✔';
      console.log("rating sauvé :");
      console.log(score);
      this.ratingSent = true;
    }
  },
  mounted() {
    console.log(this.config.nugget_id);
    console.log(document.querySelector("a.course-button"));
  }
};
</script>