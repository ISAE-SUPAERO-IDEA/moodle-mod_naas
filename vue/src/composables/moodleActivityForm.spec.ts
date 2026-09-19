import { afterEach, describe, expect, it, vi } from "vitest";
import {
  ACTIVITY_DETAILS_FORM_ATTR,
  descriptionToHtml,
  setActivityDetailsVisible,
  setMoodleActivityName,
  syncMoodleNuggetFields,
} from "./moodleActivityForm";
import type { Nugget } from "@/types/nugget.types";

function nugget(overrides: Partial<Nugget> = {}): Nugget {
  return {
    nugget_id: "n1",
    name: "A skilled approach",
    resume: "Short summary of the nugget.",
    authors: [],
    domains: [],
    language: "en",
    multilanguages: [],
    version_id: "v1",
    nugget_thumbnail_url: "https://example.test/t.png",
    ...overrides,
  };
}

afterEach(() => {
  document.body.innerHTML = "";
  delete (window as Window & { tinyMCE?: unknown }).tinyMCE;
});

describe("descriptionToHtml", () => {
  it("wraps plain text in a paragraph and escapes HTML", () => {
    expect(descriptionToHtml("Hello <script>")).toBe(
      "<p>Hello &lt;script&gt;</p>"
    );
  });

  it("keeps existing markup", () => {
    expect(descriptionToHtml("<p>Already html</p>")).toBe(
      "<p>Already html</p>"
    );
  });
});

describe("Moodle activity form bridge", () => {
  it("toggles activity-detail fields on the Moodle form", () => {
    document.body.innerHTML = `<form class="mform" ${ACTIVITY_DETAILS_FORM_ATTR}="hidden"><input id="id_name" /></form>`;
    setActivityDetailsVisible(true);
    expect(
      document.querySelector("form.mform")?.getAttribute(ACTIVITY_DETAILS_FORM_ATTR)
    ).toBe("visible");
    setActivityDetailsVisible(false);
    expect(
      document.querySelector("form.mform")?.getAttribute(ACTIVITY_DETAILS_FORM_ATTR)
    ).toBe("hidden");
  });

  it("fills name, description, hidden ids, and shows the panel on select", () => {
    const setContent = vi.fn();
    (
      window as Window & {
        tinyMCE: { get: () => { setContent: typeof setContent } };
      }
    ).tinyMCE = {
      get: () => ({ setContent }),
    };
    document.body.innerHTML = `
      <form class="mform" ${ACTIVITY_DETAILS_FORM_ATTR}="hidden">
        <input id="id_name" />
        <textarea id="id_introeditor"></textarea>
        <div id="id_introeditoreditable"></div>
        <input name="nugget_id" />
      </form>
    `;
    syncMoodleNuggetFields(nugget());
    expect(
      document.querySelector("form.mform")?.getAttribute(ACTIVITY_DETAILS_FORM_ATTR)
    ).toBe("visible");
    expect((document.getElementById("id_name") as HTMLInputElement).value).toBe(
      "A skilled approach"
    );
    expect(
      (document.getElementById("id_introeditor") as HTMLTextAreaElement).value
    ).toBe("<p>Short summary of the nugget.</p>");
    expect(document.getElementById("id_introeditoreditable")?.innerHTML).toBe(
      "<p>Short summary of the nugget.</p>"
    );
    expect(setContent).toHaveBeenCalledWith(
      "<p>Short summary of the nugget.</p>"
    );
    expect(
      (document.getElementsByName("nugget_id")[0] as HTMLInputElement).value
    ).toBe("n1");
  });

  it("clears fields and hides the panel when the nugget is removed", () => {
    document.body.innerHTML = `
      <form class="mform" ${ACTIVITY_DETAILS_FORM_ATTR}="visible">
        <input id="id_name" value="keep" />
        <textarea id="id_introeditor">keep</textarea>
        <input name="nugget_id" value="n1" />
      </form>
    `;
    syncMoodleNuggetFields(null);
    expect(
      document.querySelector("form.mform")?.getAttribute(ACTIVITY_DETAILS_FORM_ATTR)
    ).toBe("hidden");
    expect((document.getElementById("id_name") as HTMLInputElement).value).toBe(
      ""
    );
    expect(
      (document.getElementById("id_introeditor") as HTMLTextAreaElement).value
    ).toBe("");
    expect(
      (document.getElementsByName("nugget_id")[0] as HTMLInputElement).value
    ).toBe("");
  });

  it("writes the name field in isolation", () => {
    document.body.innerHTML = '<input id="id_name" />';
    setMoodleActivityName("Title");
    expect((document.getElementById("id_name") as HTMLInputElement).value).toBe(
      "Title"
    );
  });
});
