<template>
  <div>
    <div>
      <a
        href="javascript:;"
        class="btn btn-primary"
        v-on:click="showNuggetDetailModal()">
        {{ config.labels.see_nugget_details }}
      </a>
    </div>
    <NuggetDetailModal
      :visible="isNuggetDetailModalVisible"
      :nugget="nugget"
      @close="closeNuggetDetailModal()"
    />
  </div>
</template>
<script>
  import NuggetDetailModal from './NuggetDetailModal.vue';
  export default {
    name: "NuggetPost",
    components: {
      NuggetDetailModal,
    },
    data() {
      return {
        isNuggetDetailModalVisible: false,
        nugget_id: undefined,
        nugget: {}
      };
    },
    async mounted() {
      this.nugget = await this.get_nugget_default_version(this.config.nugget_id);
    },
    methods: {
      showNuggetDetailModal() {
        this.isNuggetDetailModalVisible = true;
      },
      closeNuggetDetailModal() {
        this.isNuggetDetailModalVisible = false;
      }
    }
  }
</script>
