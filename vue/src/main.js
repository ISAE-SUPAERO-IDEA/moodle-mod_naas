import Vue from "vue";
import Main from "@/Main";
import mixin from "@/mixin";
import utils from "@/utils";
import moment from 'moment';
/*global NAAS*/

Vue.config.productionTip = false;

// fonction filter pour couper les strings trop longs
Vue.filter("truncate", utils.truncate);

// formatting date values
Vue.filter('formatDate', function(value) {
    if (value) {
        return moment(String(value)).format('DD/MM/YYYY')
    }
});

Vue.mixin(mixin);

new Vue({
  render: (h) => h(Main),
}).$mount(NAAS.mount_point);
