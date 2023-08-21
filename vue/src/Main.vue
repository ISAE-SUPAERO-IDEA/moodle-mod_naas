<template>
  <div>
    <component :is="config.component"></component>
  </div>
</template>
<script>
import NuggetSearchWidget from "@/components/NuggetSearchWidget";
import NuggetView from "@/components/NuggetView";
export default {
  name: "Main",
  props: ["post", "visible"],
  components: { NuggetSearchWidget, NuggetView },
};
</script>
