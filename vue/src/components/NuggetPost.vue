<template>
  <div class="nugget-post h-100">
    <div @click="SelectClickHandler(nugget)" class="nugget-post-select h-100">
      <img class="w-100" :src="nugget.nugget_thumbnail_url.concat('?width=700&height=394')" alt="">
      <h4>{{ nugget.name | truncate(50, "...") }}</h4>
      <h5> {{ authors_names }}</h5>
      <h6>{{ nugget.resume | truncate(190, "...") }}</h6>
      <h5>{{ nugget.displayinfo }}</h5>
    </div>
    <div class="nugget-buttons">
      <a
        href="javascript:;"
        class="btn btn-primary nugget-button"
        v-on:click="showNuggetViewModal()">
        {{ config.labels.preview_button }}
      </a>
      <a
        href="javascript:;"
        class="btn btn-primary nugget-button"
        v-on:click="showNuggetDetailModal()">
        {{ config.labels.details_button }}
      </a>
    </div>
    <NuggetDetailModal
      :visible="isNuggetDetailModalVisible"
      :nugget="nugget"
      @close="closeNuggetDetailModal()"
    />
    <NuggetViewModal
      :visible="isNuggetViewModalVisible"
      :nugget="nugget"
      @close="closeNuggetViewModal()"
    />
  </div>
</template>
<script>
  import NuggetDetailModal from './NuggetDetailModal.vue';
  import NuggetViewModal from './NuggetViewModal.vue';
  export default {
    name: "NuggetPost",
    props: ["nugget"],
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
      SelectClickHandler(nugget) {
        this.$emit('SelectButton', nugget);
      }
    },
    computed: {
      authors_names() {
        var authors_names = []
        if (this.nugget.authors_data) {
          this.nugget.authors_data.forEach(author => {
            if (author) {
              authors_names.push(`${author.firstname} ${author.lastname}`)
            }
          });
        }
        return authors_names.join(", ")
      }
    }
  }
</script>
