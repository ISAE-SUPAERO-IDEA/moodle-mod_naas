<template>
  <div class="nugget-post">
    <img style="width:100%" :src="post.nugget_thumbnail_url.concat('?width=700&height=394')" alt="">
    <h3>{{ post.name }}</h3>
    <h4 v-if="post.authors_name">by {{ post.authors_name.join(", ") }}</h4>
    <h5>{{ post.resume | truncate(190, "...") }}</h5>
    <h5>{{ post.displayinfo }}</h5>
    
    <button
      class="btn btn-primary"
      @click="showNuggetModal()"
    >
      Preview
    </button>
    
    <button 
      class="btn btn-primary"
      @click="SelectClickHandler(post)"
    >
      Select
    </button>

    <NuggetModal
      v-show="isNuggetModalVisible"
      :post="post"
      @close="closeNuggetModal()"
    />
  </div>
</template>
<script>
  import NuggetModal from './NuggetModal.vue';
  export default {
    name: "NuggetPost",
    props: ["post"],
    components: {
      NuggetModal
    },
    data() {
      return {
        isNuggetModalVisible: false
      };
    },
    methods: {
      showNuggetModal() {
        this.isNuggetModalVisible = true;
      },
      closeNuggetModal() {
        this.isNuggetModalVisible = false;
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