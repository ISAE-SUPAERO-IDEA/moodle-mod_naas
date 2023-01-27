<template>
    <img v-show="loading" v-bind:src="'../mod/naas/assets/loading.gif'" width="35" height="35">
</template>
<script>
export default {
    name: "Loading",
    props: ["loading"]
}

</script>