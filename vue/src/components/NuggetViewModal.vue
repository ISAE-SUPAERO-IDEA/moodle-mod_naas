<template>
  <transition name="modal-fade">
    <div style="position: fixed; top: 0; bottom: 0; left: 0; right: 0; background-color: rgba(0, 0, 0, 0.3); display: flex; justify-content: center; align-items: center; z-index: 2;">
      <div style="overflow-x: auto; display: flex; height: 50%; width: 50%; position: absolute; left: 50%; top: 50%; background: #FFFFFF; box-shadow: 2px 2px 20px 1px; border-radius: 5px; flex-direction: column; transform: translate(-50%, -50%);">
        <header style="padding: 15px 15px 0 15px; display: flex; border-bottom: 1px solid #eeeeee; justify-content: space-between; position: relative;">
          <h3>Preview : {{ post.name }}</h3>
          <button
            type="button"
            style="position: absolute; top: 0; right: 0; border: none; font-size: 20px; padding: 10px; cursor: pointer; font-weight: bold; color: #999999; background: transparent;"
            @click="closeNuggetModal()"
          >
            x
          </button>
        </header>
        <section class="modal-body" style="overflow-y: auto; position: relative; padding: 0;">
          <div class="row" style="margin: 0; padding: 0; height: 100%;">
            <iframe id="lti_frame" :src="NuggetView" style="border:none; width: 100%;"></iframe>
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
  .modal-fade-enter,
  .modal-fade-leave-to {
    opacity: 0;
  }
  .modal-fade-enter-active,
  .modal-fade-leave-active {
    transition: opacity .3s ease;
  }

  @media (max-width: 1250px) {
    .modal {
      height: 75%;
      width: 75%;
    }
  }
</style>