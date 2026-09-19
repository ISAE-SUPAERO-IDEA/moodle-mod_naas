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

/**
 * Vuetify plugin for the Moodle NaaS widget.
 * SVG icons only — the webfont would inflate the IIFE by several megabytes.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { defineComponent, h } from "vue";
import { createVuetify } from "vuetify";
import { aliases } from "vuetify/iconsets/mdi-svg";
import { VSvgIcon } from "vuetify/lib/composables/icons.js";
import {
  mdiArrowLeft,
  mdiArrowRight,
  mdiArrowUp,
  mdiCalendar,
  mdiCardAccountDetailsOutline,
  mdiCheck,
  mdiChevronDown,
  mdiChevronRight,
  mdiChevronUp,
  mdiClockOutline,
  mdiClose,
  mdiEarth,
  mdiHome,
  mdiLock,
  mdiMagnify,
  mdiPlayCircleOutline,
  mdiRefresh,
  mdiStar,
  mdiTag,
  mdiTuneVariant,
  mdiViewGridOutline,
  mdiViewListOutline,
} from "@mdi/js";
import "@/styles/naas-vuetify.css";

const mdiPaths: Record<string, string> = {
  "mdi-arrow-left": mdiArrowLeft,
  "mdi-arrow-right": mdiArrowRight,
  "mdi-arrow-up": mdiArrowUp,
  "mdi-calendar": mdiCalendar,
  "mdi-card-account-details-outline": mdiCardAccountDetailsOutline,
  "mdi-check": mdiCheck,
  "mdi-chevron-down": mdiChevronDown,
  "mdi-chevron-right": mdiChevronRight,
  "mdi-chevron-up": mdiChevronUp,
  "mdi-clock-outline": mdiClockOutline,
  "mdi-close": mdiClose,
  "mdi-earth": mdiEarth,
  "mdi-home": mdiHome,
  "mdi-lock": mdiLock,
  "mdi-magnify": mdiMagnify,
  "mdi-play-circle-outline": mdiPlayCircleOutline,
  "mdi-refresh": mdiRefresh,
  "mdi-star": mdiStar,
  "mdi-tag": mdiTag,
  "mdi-tune-variant": mdiTuneVariant,
  "mdi-view-grid-outline": mdiViewGridOutline,
  "mdi-view-list-outline": mdiViewListOutline,
};

function resolveIcon(icon: unknown): unknown {
  if (typeof icon === "string" && mdiPaths[icon]) {
    return mdiPaths[icon];
  }
  if (typeof icon === "string" && icon.startsWith("svg:")) {
    return icon.slice(4);
  }
  return icon;
}

const NaasMdiIcon = defineComponent({
  name: "NaasMdiIcon",
  props: {
    icon: {
      type: [String, Function, Object, Array],
      default: undefined,
    },
    tag: {
      type: String,
      required: true,
    },
  },
  setup(props) {
    return () =>
      h(VSvgIcon, {
        icon: resolveIcon(props.icon),
        tag: props.tag,
      });
  },
});

export default createVuetify({
  icons: {
    defaultSet: "mdi",
    aliases,
    sets: {
      mdi: { component: NaasMdiIcon },
      svg: { component: VSvgIcon },
    },
  },
  theme: {
    defaultTheme: "light",
    themes: {
      light: {
        dark: false,
        colors: {
          primary: "#0f6cbf",
          "primary-darken-1": "#0a58ca",
          "primary-lighten-1": "#4d94d4",
          secondary: "#6c757d",
          "secondary-darken-1": "#495057",
          surface: "#ffffff",
          background: "#ffffff",
          "on-surface": "#1f2937",
          "on-background": "#1f2937",
          success: "#16a34a",
          warning: "#f59e0b",
          error: "#dc3545",
          info: "#0f6cbf",
        },
        variables: {
          "border-radius": "8px",
          "border-radius-sm": "8px",
        },
      },
    },
  },
  defaults: {
    VBtn: {
      rounded: "lg",
      elevation: 0,
    },
    VCard: {
      rounded: "lg",
      elevation: 0,
      border: true,
    },
    VTextField: {
      variant: "outlined",
      density: "comfortable",
      rounded: "lg",
    },
    VSelect: {
      variant: "outlined",
      density: "comfortable",
      rounded: "lg",
    },
    VChip: {
      elevation: 0,
    },
  },
});
