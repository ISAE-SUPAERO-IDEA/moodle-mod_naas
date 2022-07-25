<template>
  <div class="nugget-post">
    <img style="width:100%" :src="post.nugget_thumbnail_url.concat('?width=700&height=394')" alt="">
    <h3>{{ post.name }}</h3>
    <h5>by {{ post.authors_name.join(", ") }}</h5>
    <p>{{ post.resume | truncate(190, "...") }}</p>
    <p>{{ post.displayinfo }}</p>
  </div>
</template>
<script>
  export default {
    name: "NuggetPost",
    props: ["post"]
  }
</script>