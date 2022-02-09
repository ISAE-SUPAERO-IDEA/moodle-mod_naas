<template>
 <div class="nugget-post">  
            <img style="width:100%" :src="post.nugget_thumbnail_url.concat('?width=700&height=394')" alt="">   
            <h3>{{ post.name }}</h3>        
            <h5>{{post.duration}} min - {{ post.authors.join(', ') }}</h5>                    
            <h5>{{ post.field_of_study}}</h5>
            <p> {{ post.resume | truncate(190, "...")}} </p>
            <p> {{post.displayinfo}} </p>  
        </div>
</template>
<script>
export default {
  name: "NuggetPost",
  props: ["post"]
}
</script>