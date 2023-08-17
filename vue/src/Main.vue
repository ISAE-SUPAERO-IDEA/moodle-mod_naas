<template>
  <div>
    <component :is="config.component"></component>
  </div>
</template>
<script>
import NuggetSearchWidget from "@/components/NuggetSearchWidget";
import NuggetInfoWidget from "@/components/NuggetInfoWidget";
import NuggetCompletionModal from "@/components/NuggetCompletionModal";
export default {
  name: "Main",
  props: ["post", "visible"],
  components: { NuggetSearchWidget, NuggetInfoWidget, NuggetCompletionModal },
};
</script>
