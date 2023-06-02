<template>
  <div class="nugget-post h-100">
    <div @click="SelectClickHandler(nugget)" class="nugget-post-select h-100">
      <img
        class="w-100"
        :src="nugget.nugget_thumbnail_url.concat('?width=700&height=394')"
        alt=""
      />
      <h4>{{ nugget.name | truncate(50, "...") }}</h4>
      <h5>{{ authors_names }}</h5>
      <span>{{ nugget.resume | truncate(190, "...") }}</span>
      <h5>{{ nugget.displayinfo }}</h5>
    </div>
    <div class="nugget-buttons">
      <a
        href="javascript:;"
        class="btn btn-primary nugget-button"
        v-on:click="showNuggetViewModal()"
      >
        {{ config.labels.preview_button }}
      </a>
      <a
        href="javascript:;"
        class="btn btn-primary nugget-button"
        v-on:click="showNuggetAboutModal()"
      >
        {{ config.labels.about_button }}
      </a>
    </div>
    <NuggetAboutModal
      :visible="isNuggetAboutModalVisible"
      :nugget="nugget"
      @close="closeNuggetAboutModal()"
    />
    <NuggetViewModal
      :visible="isNuggetViewModalVisible"
      :nugget="nugget"
      @close="closeNuggetViewModal()"
    />
  </div>
</template>
<script>
import NuggetAboutModal from "./NuggetAboutModal.vue";
import NuggetViewModal from "./NuggetViewModal.vue";
export default {
  name: "NuggetPost",
  props: ["nugget"],
  components: {
    NuggetAboutModal,
    NuggetViewModal,
  },
  data() {
    return {
      isNuggetAboutModalVisible: false,
      isNuggetViewModalVisible: false,
    };
  },
  methods: {
    showNuggetAboutModal() {
      this.isNuggetAboutModalVisible = true;
    },
    closeNuggetAboutModal() {
      this.isNuggetAboutModalVisible = false;
    },
    showNuggetViewModal() {
      this.isNuggetViewModalVisible = true;
    },
    closeNuggetViewModal() {
      this.isNuggetViewModalVisible = false;
    },
    SelectClickHandler(nugget) {
      this.$emit("SelectButton", nugget);
    },
  },
  computed: {
    authors_names() {
      var authors_names = [];
      if (this.nugget.authors_data) {
        this.nugget.authors_data.forEach((author) => {
          if (author)
            authors_names.push(`${author.firstname} ${author.lastname}`);
        });
      }
      return authors_names.join(", ");
    },
  },
};
</script>
