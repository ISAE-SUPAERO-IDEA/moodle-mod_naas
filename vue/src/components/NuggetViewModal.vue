<template>
  <transition name="modal-fade">
    <div class="modal-backdrop">
      <div class="modal">
        <header class="modal-header">
          <h3>Preview : {{ post.name }}</h3>
          <button
            type="button"
            class="btn-close"
            @click="closeNuggetModal()"
          >
            x
          </button>
        </header>
        <section class="modal-body">
          <div class="row row_item NuggetView">
            <iframe id="lti_frame" :src="NuggetView" class="w-100" style="border:none;"></iframe>
          </div>
        </section>
      </div>
    </div>
  </transition>
</template>
<script>
  export default {
    name: "NuggetViewModal",
    props: ["post"],
    data() {
      return {
        NuggetView: ""
      }
    },
    mounted() {
      this.initialize();
    },
    methods: {
      initialize() {
        this.proxy(`/versions/` + this.post.version_id + `/preview_url`).then(
          (payload) => {
            this.NuggetView = payload;
          }
        );
      },
      closeNuggetModal() {
        this.$emit('close');
      }
    }
  };
</script>
<style scoped>
  .modal-backdrop {
    background-color: rgba(0, 0, 0, 0.3);
    position: fixed;
    top: 0;
    left: 0;
  }
  .modal {
    overflow-x: auto;
    display: flex;
    height: 50%;
    width: 50%;
    position: absolute;
    left: 50%;
    top: 50%;
  }
  @media (max-width: 1250px) {
    .modal {
      height: 75%;
      width: 75%;
    }
  }
  .modal-header {
    padding: 15px 15px 0 15px;
    display: flex;
    border-bottom: 1px solid #eeeeee;
    justify-content: space-between;
  }
  .modal-body {
    position: relative;
    padding: 0;
  }
  .row_item {
    margin: 0 15px;
  }
  .NuggetView {
    padding: 0 0 10px 0;
    height: 100%;
  }
</style>