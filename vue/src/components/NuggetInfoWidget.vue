<template>
  <div>
    <div>
      <a
        href="javascript:;"
        class="btn btn-primary"
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
  </div>
</template>
<script>
import NuggetAboutModal from "./NuggetAboutModal.vue";
export default {
  name: "NuggetPost",
  components: {
    NuggetAboutModal,
  },
  data() {
    return {
      isNuggetAboutModalVisible: false,
      nugget_id: undefined,
      nugget: {},
    };
  },
  async mounted() {
    this.nugget = await this.get_nugget_default_version(this.config.nugget_id);
  },
  methods: {
    showNuggetAboutModal() {
      this.isNuggetAboutModalVisible = true;
    },
    closeNuggetAboutModal() {
      this.isNuggetAboutModalVisible = false;
    },
  },
};
</script>
