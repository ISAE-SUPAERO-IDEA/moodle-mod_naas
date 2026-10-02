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
 * Bridge between the search widget and the Moodle activity form
 * (name, intro editor, hidden nugget_id).
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import type { Nugget } from "@/types/nugget.types";

export const ACTIVITY_DETAILS_FORM_ATTR = "data-naas-details";

interface TinyMceEditor {
  setContent: (html: string) => void;
}

interface TinyMceGlobal {
  get: (id: string) => TinyMceEditor | null | undefined;
}

function tinyMce(): TinyMceGlobal | undefined {
  return (window as Window & { tinyMCE?: TinyMceGlobal }).tinyMCE;
}

function activityForm(): HTMLFormElement | null {
  return (
    document.getElementById("id_name")?.closest("form") ??
    document.querySelector("form.mform")
  );
}

export function setActivityDetailsVisible(visible: boolean): void {
  const form = activityForm();
  if (!form) {
    return;
  }
  form.setAttribute(
    ACTIVITY_DETAILS_FORM_ATTR,
    visible ? "visible" : "hidden"
  );
}

export function descriptionToHtml(resume: string): string {
  const text = resume.trim();
  if (!text) {
    return "";
  }
  if (/^\s*</.test(text)) {
    return text;
  }
  const escaped = text
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
  return `<p>${escaped
    .replace(/\n\n+/g, "</p><p>")
    .replace(/\n/g, "<br>")}</p>`;
}

export function setMoodleActivityName(name: string): void {
  const nameField = document.getElementById(
    "id_name"
  ) as HTMLInputElement | null;
  if (nameField) {
    nameField.value = name;
  }
}

export function setMoodleActivityDescription(html: string): void {
  const textarea =
    (document.getElementById("id_introeditor") as HTMLTextAreaElement | null) ??
    document.querySelector<HTMLTextAreaElement>('[name="introeditor[text]"]');
  if (textarea) {
    textarea.value = html;
    textarea.dispatchEvent(new Event("change", { bubbles: true }));
  }
  tinyMce()?.get("id_introeditor")?.setContent(html);
  const atto = document.getElementById("id_introeditoreditable");
  if (atto) {
    atto.innerHTML = html;
  }
}

const CGU_FIELD_CLASS = "naas-cgu-field";

interface CguHome {
  parent: Node;
  next: ChildNode | null;
}

let cguHome: CguHome | null = null;

/** The Moodle CGU row, whether or not the theme printed fitem_id_cgu_agreement. */
export function cguAgreementItem(): HTMLElement | null {
  return (
    document.getElementById("fitem_id_cgu_agreement") ??
    document.getElementById("id_cgu_agreement")?.closest(".fitem") ??
    null
  );
}

/**
 * Show the real CGU checkbox under the selected nugget.
 * The input stays inside the activity form, so Moodle still receives it.
 */
/** Hide the CGU row until it is parked under the selected nugget. */
export function concealCguAgreement(): void {
  cguAgreementItem()?.classList.add(CGU_FIELD_CLASS);
}

export function placeCguAgreement(anchor: HTMLElement | null): void {
  const item = cguAgreementItem();
  if (!item || !anchor) {
    return;
  }
  item.classList.add(CGU_FIELD_CLASS);
  const parent = item.parentNode;
  if (parent && parent !== anchor && (!cguHome || !cguHome.parent.isConnected)) {
    cguHome = { parent, next: item.nextSibling };
  }
  if (item.parentElement !== anchor) {
    anchor.appendChild(item);
  }
}

/** Put the CGU row back where Moodle rendered it. */
export function restoreCguAgreement(): void {
  const item = cguAgreementItem();
  if (!item || !cguHome) {
    return;
  }
  const { parent, next } = cguHome;
  if (item.parentNode === parent) {
    return;
  }
  if (next && next.parentNode === parent) {
    parent.insertBefore(item, next);
  } else {
    parent.appendChild(item);
  }
}

/** Fill (or clear) Moodle fields when a nugget is selected or replaced. */
export function syncMoodleNuggetFields(nugget: Nugget | null): void {
  setActivityDetailsVisible(!!nugget);
  setMoodleActivityName(nugget?.name ?? "");
  setMoodleActivityDescription(
    nugget ? descriptionToHtml(nugget.resume ?? "") : ""
  );

  const nuggetIdField = document.getElementsByName(
    "nugget_id"
  )[0] as HTMLInputElement | null;
  if (nuggetIdField) {
    nuggetIdField.value = nugget?.nugget_id ?? "";
  }
}
