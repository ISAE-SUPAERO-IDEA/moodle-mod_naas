<template>
  <div class="modal modal-bg modal-nugget-completion" v-show="visible">
    <transition name="modal-fade">
      <div class="nugget-modal-backdrop"  @click="closeNuggetModal()">
        <div class="nugget-modal" @click.stop.prevent>
          <section class="nugget-modal-body">
            <button type="button" class="btn-close" @click="closeNuggetModal()">
              x
            </button>
            <!-- Rating -->

            <!-- Learning Outcomes -->
            <div v-if="nugget.learning_outcomes.length == 1">
              <h5>{{ $t("nugget.rating.finish_learning_outcome") }}</h5>
            </div>
            <div v-if="nugget.learning_outcomes.length > 1">
              <h5>{{ $t("nugget.rating.finish_learning_outcomes") }}</h5>
            </div>
            <li v-for="item in nugget.learning_outcomes" :key="item">
              {{ item }}
            </li>

            <div class="modal-footer">
              <div class="container button">
                <!-- Link to course -->
              </div>
            </div>
          </section>
        </div>
      </div>
    </transition>
  </div>
</template>
<script>
export default {
  name: "NuggetCompletionModal",
  methods: {
    closeNuggetModal() {
      this.$emit("close");
    }
  }
};
</script>
