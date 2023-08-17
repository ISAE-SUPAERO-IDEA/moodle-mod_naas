<template>
<div class="container">
  <div class="row">
    <div id="completion-modal-button" class="col text-center">
      <button
        href="javascript:;"
        class="btn btn-primary"
        @click="toggleModal()"
      >
        I Finished My Learning With This Nugget
      </button>
    </div>
    <div ref="completionModal" class="hidden">
      <div class="nugget-modal-backdrop" @click="toggleModal()">
        <div class="nugget-modal" @click.stop.prevent>
          <header class="nugget-modal-header">
            <button type="button" class="btn-close" @click="toggleModal()">
              ✕
            </button>
          </header>
          <section class="nugget-modal-body">
            <!-- Rating -->
            <div class="container text-center">
              <h2>Rate this nugget</h2>
              <p class="rating saved">
              <span
                v-for="i in max"
                v-bind:key="i"
                @click="rate(max + 1 - i)"
                class="star"
                :class="{ checked: saved_rating === max + 1 - i }"
              >
                <i class="icon fa fa-star"></i>
              </span>
            </p>
              <p class="description">Your rating will be used to improve the quality of our content</p>
            </div>

            <!-- Learning Outcomes -->

            <div class="modal-footer">
              <div class="container button">
                <a>Back to Course Index</a> <a>Next Unit</a>
              </div>
            </div>
          </section>
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
  props: ["nugget"],
  name: "NuggetCompletionModal",
  data() {
    return {
      max: MaxScore,
      rating_sent: false,
      saved_rating: null
    };
  },
  methods: {
    toggleModal() {
      this.showModal = !this.showModal;
      this.$refs.completionModal.classList.toggle('hidden');
    },
    rate(score) {
      this.saved_rating = score;
    },
    mounted() {
      console.log(this.config.nugget_id);
    },
  }
};
</script>