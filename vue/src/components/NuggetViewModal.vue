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
            <iframe id='lti_frame' :src='NuggetView' style='border:none; width:100%'></iframe>
          </div>
        </section>

      </div>
    </div>
  </transition>
</template>
<script>
  export default {
    name: 'NuggetViewModal',
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
  .modal-backdrop {
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    right: 0;
    background-color: rgba(0, 0, 0, 0.3);
    display: flex;
    justify-content: center;
    align-items: center;
  }
  .modal {
    background: #FFFFFF;
    box-shadow: 2px 2px 20px 1px;
    overflow-x: auto;
    display: flex;
    flex-direction: column;
    border-radius: 5px;

    height: 50%;
    width: 50%;
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
  }
  .modal-header {
    padding: 15px 15px 0 15px;
    display: flex;
    position: relative;
    border-bottom: 1px solid #eeeeee;
    justify-content: space-between;
  }
  .modal-body {
    position: relative;
    overflow-y: auto;
    padding: 0;
  }
  .btn-close {
    position: absolute;
    top: 0;
    right: 0;
    border: none;
    font-size: 20px;
    padding: 10px;
    cursor: pointer;
    font-weight: bold;
    color: #999999;
    background: transparent;
  }

  .row_item {
    margin: 0 15px;
  }
  .NuggetView {
    padding: 0 0 10px 0;
    height: 100%;
  }
</style>