<template>
  <div id="detail-modal" v-show="visible">
    <transition name="modal-fade">
      <div class="nugget-modal-backdrop" @click="closeNuggetModal()">
        <div class="nugget-modal" @click.stop.prevent>
          <div class="container">
            <div class="nugget-modal-header row justify-content-between align-items-start">
              <h2>{{ config.labels.about }} : {{ nugget.name }}</h2>
              <button
                type="button"
                class="btn-close"
                @click="closeNuggetModal()"
              >
                ✕
              </button>
            </div>
          </div>
          <div class="container nugget-modal-body">
            <!-- Main content area with improved responsive layout -->
            <div class="row metadata-field">
              <!-- Main content column - will take full width on mobile, 8 columns on larger screens -->
              <div class="col-12 col-md-8 mb-4">
                <!-- Description -->
                <div v-show="is_shown(nugget.resume)" class="mb-4">
                  <h3>{{ config.labels.metadata.description }}</h3>
                  <span
                    v-html="nugget.resume"
                    class="nugget-modal-description"
                  ></span>
                </div>
                <!-- About author -->
                <div v-show="is_shown(nugget.authors_data)" class="mb-4">
                  <h3>{{ config.labels.metadata.about_author }}</h3>
                  <div
                    v-for="author in nugget.authors_data"
                    :key="author.email"
                    class="mb-3"
                  >
                    <h5>{{ author.firstname }} {{ author.lastname }}</h5>
                    <span
                      v-html="author.bio"
                      class="nugget-modal-description"
                    ></span>
                  </div>
                </div>
              </div>
              
              <!-- Sidebar column - will stack below main content on mobile -->
              <div v-show="in_brief_shown" class="col-12 col-md-4 mb-4">
                <div class="card p-3 h-100">
                  <h3>{{ config.labels.metadata.in_brief }}</h3>
                  <div>
                    <ul class="metadata-list">
                      <li v-show="is_shown(nugget.duration)" class="mb-2">
                        <i class="icon fa fa-clock-o"></i>
                        {{ config.labels.metadata.duration }}:
                        <strong id="formatage-duration">
                          {{ nugget.duration }} minutes
                        </strong>
                      </li>
                      <li v-show="is_shown(nugget.language)" class="mb-2">
                        <i class="icon fa fa-globe"></i>
                        {{ config.labels.metadata.language }}:
                        <strong>{{
                          config.labels.metadata[nugget.language]
                        }}</strong>
                      </li>
                      <li v-show="is_shown(nugget.level)" class="mb-2">
                        <i class="icon fa fa-arrow-up"></i>
                        {{ config.labels.metadata.level }}:
                        <strong>{{
                          config.labels.metadata[nugget.level]
                        }}</strong>
                      </li>
                      <li v-show="is_shown(nugget.domainsData)" class="mb-2">
                        <i class="icon fa fa-home"></i>
                        {{ config.labels.metadata.field_of_study }}:<br />
                        <div class="d-flex flex-wrap mt-1">
                          <span
                            v-for="item in nugget.domainsData"
                            :key="item.id"
                            class="metadata-list-item me-1 mb-1"
                          >
                            <span class="badge badge-pill badge-primary">{{
                              item.label
                            }}</span>
                          </span>
                        </div>
                      </li>
                      <li v-show="is_shown(nugget.tags)" class="mb-2">
                        <i class="icon fa fa-tag"></i>
                        {{ config.labels.metadata.tags }}:<br />
                        <div class="d-flex flex-wrap mt-1">
                          <span
                            v-for="item in nugget.tags"
                            :key="item"
                            class="metadata-list-item me-1 mb-1"
                          >
                            <span class="badge badge-pill badge-primary">{{
                              item
                            }}</span>
                          </span>
                        </div>
                      </li>
                      <li v-show="is_shown(nugget.domains_data)" class="mb-2">
                        <i class="icon fa fa-tag"></i>
                        {{ config.labels.metadata.related_domains }}:<br />
                        <div class="d-flex flex-wrap mt-1">
                          <span
                            v-for="domain in nugget.domains_data"
                            :key="domain.id"
                            class="metadata-list-item me-1 mb-1"
                          >
                            <span class="badge badge-pill badge-primary">{{
                              domain.label
                            }}</span>
                          </span>
                        </div>
                      </li>
                      <li v-show="is_shown(nugget.publication_date)" class="mb-2">
                        <i class="icon fa fa-calendar"></i>
                        {{ config.labels.metadata.publication_date }}:
                        <strong>
                          {{ nugget.publication_date | formatDate }}
                        </strong>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Additional sections below the two columns -->
            <!-- Prerequisites -->
            <div
              v-show="is_shown(nugget.prerequisites)"
              class="row metadata-field mb-4"
            >
              <div class="col-12">
                <div class="card p-3">
                  <h3>{{ config.labels.metadata.prerequisites }}</h3>
                  <ul class="about-list ul-position">
                    <li v-for="item in nugget.prerequisites" :key="item">
                      <p>{{ item }}</p>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
            
            <!-- Learning outcomes -->
            <div
              v-show="is_shown(nugget.learning_outcomes)"
              class="row metadata-field mb-4"
            >
              <div class="col-12">
                <div class="card p-3">
                  <h3>{{ config.labels.metadata.learning_outcomes }}</h3>
                  <ul class="about-list ul-position">
                    <li v-for="item in nugget.learning_outcomes" :key="item">
                      <p>{{ item }}</p>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
            
            <!-- References -->
            <div
              v-show="is_shown(nugget.references)"
              class="row metadata-field mb-4"
            >
              <div class="col-12">
                <div class="card p-3">
                  <h3>{{ config.labels.metadata.references }}</h3>
                  <ul class="about-list ul-position">
                    <li v-for="item in nugget.references" :key="item">
                      <p>{{ item }}</p>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>
<script>
export default {
  name: "NuggetAboutModal",
  props: ["nugget", "visible"],
  methods: {
    closeNuggetModal() {
      this.$emit("close");
    },
    is_shown(val) {
      if (val == undefined) return false;
      if (val == "") return false;
      if (Array.isArray(val) && val.length == 0) return false;
      if (typeof val === "object") {
        for (const inner_val of Object.values(val)) {
          if (this.is_shown(inner_val)) return true;
        }
        return false;
      }
      return true;
    },
  },
  computed: {
    in_brief_shown() {
      return (
        this.is_shown(this.nugget.duration) ||
        this.is_shown(this.nugget.level) ||
        this.is_shown(this.nugget.tags) ||
        this.is_shown(this.nugget.language)
      );
    },
    authors_info() {
      return this.nugget.authors_data
        ? this.nugget.authors_data.join(", ")
        : "";
    },
  },
};
</script>
<style scoped>
/* Modal backdrop and positioning */
.nugget-modal-backdrop {
  position: fixed;
  top: 0;
  bottom: 0;
  left: 0;
  right: 0;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  justify-content: center;
  align-items: flex-start;
  z-index: 1050;
  overflow-y: auto;
  padding: 60px 0;
}

/* Modal container with fixed positioning */
.nugget-modal {
  background-color: #fff;
  border-radius: 8px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
  width: 85%;
  max-width: 1200px;
  /* We still use transform as mentioned in your comment, but adjust other parameters */
  position: absolute;
  left: 50%;
  top: 100px; /* Fixed distance from top instead of percentage */
  transform: translateX(-50%); /* Only transform X, not Y */
  margin-bottom: 60px; /* Add space at bottom */
  max-height: calc(100vh - 120px); /* Limit height to prevent overflow */
  overflow: scroll !important; /* Allow scrolling within modal */
  overflow-x: hidden;
  display: flex;
  flex-direction: column;
}

/* Header styling */
.nugget-modal-header {
  background-color: #f8f9fa;
  border-bottom: 1px solid #e9ecef;
  border-radius: 8px 8px 0 0;
  padding: 15px 0;
  position: sticky;
  top: 0;
  z-index: 5;
}

.nugget-modal-header h2 {
  margin: 0;
  padding: 5px 0 5px 15px;
  font-size: 1.5rem;
  line-height: 1.4;
  word-break: break-word;
}

/* Close button */
.btn-close {
  position: relative;
  float: right;
  padding: 10px 15px;
  background: transparent;
  border: none;
  font-size: 20px;
  color: #6c757d;
  cursor: pointer;
  margin-right: 10px;
}

.btn-close:hover {
  color: #343a40;
}

/* Modal body */
.nugget-modal-body {
  padding: 20px 10px;
  flex-grow: 1;
}

/* Card styling */
.card {
  border-radius: 8px;
  box-shadow: 0 2px 5px rgba(0,0,0,0.05);
  border: 1px solid #e0e0e0;
  background-color: #fff;
}

/* Enhanced metadata display */
.metadata-list {
  padding-left: 0;
  list-style: none;
}

.metadata-list li {
  margin-bottom: 0.75rem;
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
}

.metadata-list li i {
  margin-right: 0.5rem;
  min-width: 16px;
}

.metadata-list-item {
  margin-left: 0.5rem;
  margin-bottom: 0.5rem;
}

/* Badge styling */
.badge {
  display: inline-block;
  padding: 0.4em 0.8em;
  font-size: 0.75rem;
  font-weight: 600;
  border-radius: 50rem;
}

/* Better content scrolling */
.nugget-modal-description {
  overflow-y: auto;
  max-height: 250px;
  padding-right: 5px;
  line-height: 1.6;
}

/* Responsive adjustments */
@media (max-width: 1024px) {
  .nugget-modal {
    width: 90%;
  }
}

@media (max-width: 768px) {
  .nugget-modal {
    width: 95%;
    top: 30px;
    margin-bottom: 30px;
    max-height: calc(100vh - 60px);
  }
  
  .nugget-modal-backdrop {
    padding: 30px 0;
  }
  
  .nugget-modal-header h2 {
    font-size: 1.3rem;
    padding-left: 10px;
  }
  
  .nugget-modal-body {
    padding: 15px 5px;
  }
  
  .metadata-field {
    margin: 0 5px;
  }
  
  h3 {
    font-size: 1.25rem;
    margin-bottom: 0.75rem;
  }
  
  h5 {
    font-size: 1rem;
  }
  
  /* Improved font sizes for readability */
  .nugget-modal-description,
  .metadata-list li,
  p {
    font-size: 0.95rem;
  }
}

/* Typography */
h3 {
  margin-bottom: 1rem;
  font-size: 1.4rem;
  color: #343a40;
}

h5 {
  margin-top: 0.5rem;
  margin-bottom: 0.5rem;
  font-weight: 600;
  color: #343a40;
}

p {
  margin-bottom: 0.5rem;
}

/* Fix for Firefox scrolling issues */
@-moz-document url-prefix() {
  .nugget-modal {
    max-height: calc(100vh - 140px);
  }
}

/* Ensure ul elements in content have proper styling */
.about-list {
  padding-left: 20px;
}

.about-list li {
  margin-bottom: 0.5rem;
}

.about-list li p {
  margin-bottom: 0;
}

/* Animation for modal */
.modal-fade-enter-active, .modal-fade-leave-active {
  transition: opacity 0.3s;
}

.modal-fade-enter, .modal-fade-leave-to {
  opacity: 0;
}
</style>
