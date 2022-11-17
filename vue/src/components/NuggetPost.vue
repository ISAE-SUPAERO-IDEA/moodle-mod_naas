<template>
  <div class="nugget-post" style="height: 100%; margin-bottom: 25px;">
    <div @click="SelectClickHandler(post)" style="height: 100%; cursor: pointer;">
      <img style="width: 100%;" :src="post.nugget_thumbnail_url.concat('?width=700&height=394')" alt="">
      <h3>{{ post.name | truncate(35, "...") }}</h3>
      <h4 v-if="post.authors_name">by {{ post.authors_name.join(", ") }}</h4>
      <h5>{{ post.resume | truncate(190, "...") }}</h5>
      <h5>{{ post.displayinfo }}</h5>
    </div>
    <div style="position: absolute; right: 30px; bottom: 0px;">
      <a
        href="javascript:;"
        class="btn btn-primary"
        style="margin-right: 5px;"
        v-on:click="showNuggetViewModal()">
        Preview
      </a>
      <a
        href="javascript:;"
        class="btn btn-primary"
        style="margin-right: 5px;"
        v-on:click="showNuggetDetailModal()">
        Details
      </a>
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
