<script>
import utils from "../utils";

export default {
  name: "NuggetBadge",
  props: {
    selected: Boolean,
    help: String,
    text: String,
    textLengthMax: {
      type: Number,
      default: 25
    }
  },
  methods: {
    truncate(text, length) {
      return utils.truncate(text, length, "...");
    }
  },
}
</script>

<template>
<span
    class="badge rounded-pill badge-pill badge-margin"
    :class="{ 'badge-primary': selected, 'text-primary': !selected}"
    :title="help"
    @click="$emit('click')">
  {{ truncate(text, textLengthMax) }}
</span>
</template>