<template>
    <div>
        <component :is="config.component"></component>
    </div>
  </template>
  <script>
    import NuggetSearchWidget from "@/components/NuggetSearchWidget";
    import NuggetInfoWidget from "@/components/NuggetInfoWidget";
    export default {
      name: "Main",
      props: ["post", "visible"],
      components: { NuggetSearchWidget, NuggetInfoWidget },
    };
  </script>