<!--
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
-->

<!--
/**
 * Related domain component for NAAS plugin.
 *
 * @copyright (C) 2019 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
-->

<template>
  <ul class="related-domains-list">
    <li class="related-domains-list-element">
      <a
        href="javascript:;"
        class="badge badge-pill badge-margin"
        :class="bucket_class(bucket)"
        :title="bucket.caption"
        @click="bucket_click(bucket.query_value)"
      >
        {{ truncate_mobile_mode(bucket.caption, 20) }}
      </a>
      <span
        v-if="has_children"
        :class="{
          'tree-view-caret': has_children,
          'tree-view-caret-down': showChildren,
        }"
        @click="toggle_children($event)"
      ></span>
    </li>

    <li
      class="related-domains-list-element related-domains-child"
      v-show="showChildren"
    >
      <ul
        v-for="children in bucket.children"
        :key="children.key"
        class="related-domains-list"
        style="margin: 0 0 0 20px"
      >
        <li class="related-domains-list-element">
          <RelatedDomain
            :bucket="children"
            :truncate_mobile_mode="truncate_mobile_mode"
            :bucket_class="bucket_class"
            @bucket-click="bucket_click"
          ></RelatedDomain>
        </li>
      </ul>
    </li>
  </ul>
</template>
<script>
export default {
  name: "RelatedDomain",
  props: {
    bucket: {},
    truncate_mobile_mode: {
      type: Function,
      required: true,
    },
    bucket_class: {
      type: Function,
      required: true,
    },
  },
  data() {
    return {
      showChildren: this.child_selected(this.bucket.children),
    };
  },
  computed: {
    has_children() {
      return Object.keys(this.bucket.children).length > 0;
    },
  },
  methods: {
    child_selected(bucket) {
      if (Array.isArray(bucket)) return bucket.some(this.child_selected);
      else if (typeof bucket === "object")
        return Object.values(bucket).some(this.child_selected);
      else return bucket === true;
    },
    toggle_children(event) {
      event.target.classList.toggle("tree-view-caret-down");
      this.showChildren = !this.showChildren;
    },
    bucket_click(bucket_key) {
      this.$emit("bucket-click", bucket_key);
    },
  },
};
</script>
