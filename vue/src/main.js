import Vue from "vue";
import Main from "@/Main";
import mixin from "@/mixin";
/*global NAAS*/

Vue.config.productionTip = false;

// fonction filter pour couper les strings trop longs
Vue.filter("truncate", function (text, length, suffix) {
  if (text.length > length) return text.substring(0, length) + suffix;
  else return text;
});

Vue.mixin(mixin);

new Vue({
  render: (h) => h(Main),
}).$mount(NAAS.mount_point);
