import Vue from "vue";
import Main from "@/Main";
import mixin from "@/mixin";
import utils from "@/utils";
/*global NAAS*/

Vue.config.productionTip = false;

// fonction filter pour couper les strings trop longs
Vue.filter("truncate", utils.truncate);

Vue.mixin(mixin);

new Vue({
  render: (h) => h(Main),
}).$mount(NAAS.mount_point);
