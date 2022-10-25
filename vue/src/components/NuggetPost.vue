<template>
  <div class="nugget-post">
    <div @click="SelectClickHandler(post)" style="height: 100%; cursor: pointer;">
      <img style="width:100%" :src="post.nugget_thumbnail_url.concat('?width=700&height=394')" alt="">
      <h3>{{ post.name | truncate(35, "...") }}</h3>
      <h4 v-if="post.authors_name">by {{ post.authors_name.join(", ") }}</h4>
      <h5>{{ post.resume | truncate(190, "...") }}</h5>
      <h5>{{ post.displayinfo }}</h5>
    </div>
    <div style="position: absolute; left: 35px; bottom: 20px;">
      <button
        class="btn btn-primary"
        @click="showNuggetViewModal()"
      >
        Preview
      </button>
      <button
        class="btn btn-primary"
        @click="showNuggetDetailModal()"
      >
        Detail
      </button>
    </div>

    <NuggetDetailModal
      v-show="isNuggetDetailModalVisible"
      :post="post"
      @close="closeNuggetDetailModal()"
    />
    <NuggetViewModal
      v-show="isNuggetViewModalVisible"
      :post="post"
      @close="closeNuggetViewModal()"
    />
  </div>
</template>
<script>
  import NuggetDetailModal from './NuggetDetailModal.vue';
  import NuggetViewModal from './NuggetViewModal.vue';

  export default {
    name: "NuggetPost",
    props: ["post"],
    components: {
      NuggetDetailModal,
      NuggetViewModal
    },
    data() {
      return {
        isNuggetDetailModalVisible: false,
        isNuggetViewModalVisible: false
      };
    },
    methods: {
      showNuggetDetailModal() {
        this.isNuggetDetailModalVisible = true;
      },
      closeNuggetDetailModal() {
        this.isNuggetDetailModalVisible = false;
      },
      showNuggetViewModal() {
        this.isNuggetViewModalVisible = true;
      },
      closeNuggetViewModal() {
        this.isNuggetViewModalVisible = false;
      },
      SelectClickHandler(post) {
        this.$emit('SelectButton', post);
      }
    }
  }
</script>
<style scoped>
  h3 { font-size: 1.17em; margin-top: 10px; }
  h4 { font-size: 1em; }
  h5 { font-size: 0.83em; }
  .btn { margin-right: 5px; padding: 1px 3px; }
</style>