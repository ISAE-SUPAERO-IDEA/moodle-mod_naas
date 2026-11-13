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
 * Tests for the About modal.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, describe, expect, it, vi } from "vitest";
import { reactive } from "vue";
import NuggetAboutModal from "./NuggetAboutModal.vue";
import { mountComponent, type Mounted } from "@/test/mountComponent";
import { expectTabCycles, openDialog, pressKey } from "@/test/modalKeys";
import {
  makeConfig,
  makeNugget,
  makeService,
  providers,
} from "@/test/widgetFixtures";
import type { Nugget } from "@/types/nugget.types";

let mounted: Mounted | null = null;

async function openAbout(
  nugget: Nugget = makeNugget(),
  config = makeConfig(),
  service = makeService()
) {
  const props = reactive({ nugget, visible: false });
  mounted = mountComponent(NuggetAboutModal, props, providers(config, service));
  props.visible = true;
  await mounted.flush();
  return { props, dialog: openDialog(), ...mounted };
}

function headings(dialog: HTMLElement): string[] {
  return Array.from(dialog.querySelectorAll("h3")).map(
    (h) => h.textContent?.trim() ?? ""
  );
}

const fullNugget = makeNugget({
  level: "beginner",
  tags: ["wings", "lift"],
  publication_date: "2024-03-15",
  prerequisites: ["Basic physics"],
  learning_outcomes: ["Size a wing"],
  references: ["Anderson, Aircraft Performance"],
});

describe("NuggetAboutModal", () => {
  afterEach(() => {
    mounted?.unmount();
    mounted = null;
    document.body.innerHTML = "";
  });

  it("renders nothing while hidden", () => {
    mounted = mountComponent(
      NuggetAboutModal,
      { nugget: makeNugget(), visible: false },
      providers()
    );
    expect(document.querySelector('[role="dialog"]')).toBeNull();
  });

  it("is a modal dialog labelled by its title", async () => {
    const { dialog } = await openAbout();
    expect(dialog.getAttribute("aria-modal")).toBe("true");
    const title = document.getElementById(
      dialog.getAttribute("aria-labelledby")!
    );
    expect(title?.tagName).toBe("H2");
    expect(title?.textContent?.trim()).toBe("About: Aircraft design basics");
  });

  it("shows every filled section, with HTML stripped", async () => {
    const { dialog } = await openAbout(fullNugget);
    expect(headings(dialog)).toEqual([
      "Description",
      "About the author",
      "In brief",
      "Prerequisites",
      "Learning outcomes",
      "References",
    ]);
    expect(dialog.textContent).toContain("Wings, lift and drag.");
    expect(dialog.textContent).not.toContain("<b>");
    expect(dialog.querySelector("h5")?.textContent?.trim()).toBe(
      "Ada Lovelace"
    );
    expect(dialog.textContent).toContain("Mathematician");

    const brief = Array.from(
      dialog.querySelectorAll(".metadata-list > li")
    ).map((li) => li.textContent?.replace(/\s+/g, " ").trim());
    expect(brief[0]).toBe("Duration: 5 min");
    expect(brief[1]).toBe("Language: English");
    expect(brief[2]).toBe("Level: Beginner");
    expect(brief[3]).toBe("License: CC BY");
    expect(brief[4]).toBe("Field of study: Aeronautics");
    expect(brief[5]).toMatch(/^Tags: /);
    const tags = Array.from(dialog.querySelectorAll(".meta-tag")).map((t) =>
      t.textContent?.trim()
    );
    expect(tags).toEqual(["Aeronautics", "wings", "lift"]);
    expect(brief[6]).toMatch(/^Publication date: \S/);
  });

  it("hides empty sections, including the whole in-brief panel", async () => {
    const { dialog } = await openAbout(
      makeNugget({
        resume: "",
        authors_data: [],
        duration: undefined,
        language: "",
        license: undefined,
        domains_data: [],
      })
    );
    expect(headings(dialog)).toEqual([]);
    expect(dialog.querySelector(".about-brief")).toBeNull();
  });

  it("lists only the filled in-brief facts", async () => {
    const { dialog } = await openAbout(
      makeNugget({
        language: "",
        license: undefined,
        domains_data: [],
      })
    );
    const brief = Array.from(
      dialog.querySelectorAll(".metadata-list > li")
    ).map((li) => li.textContent?.replace(/\s+/g, " ").trim());
    expect(brief).toEqual(["Duration: 5 min"]);
  });

  it("reads lists that PHP sent as objects, and hides empty ones", async () => {
    const { dialog } = await openAbout(
      makeNugget({
        prerequisites: {} as unknown as string[],
        references: {
          0: "Anderson, Aircraft Performance",
        } as unknown as string[],
      })
    );
    expect(headings(dialog)).not.toContain("Prerequisites");
    expect(headings(dialog)).toContain("References");
    expect(dialog.querySelector(".about-list")?.textContent?.trim()).toBe(
      "Anderson, Aircraft Performance"
    );
  });

  it("shows an author without a bio by name only", async () => {
    const { dialog } = await openAbout(
      makeNugget({
        authors_data: [
          { firstname: "Grace", lastname: "Hopper", email: "g@example.com" },
        ],
      })
    );
    expect(dialog.querySelector("h5")?.textContent?.trim()).toBe(
      "Grace Hopper"
    );
    expect(
      dialog.querySelector("h5 + .nugget-modal-description")?.textContent
    ).toBe("");
  });

  it("shows an unknown license code as is", async () => {
    const { dialog } = await openAbout(makeNugget({ license: 9 }));
    expect(dialog.textContent?.replace(/\s+/g, " ")).toContain("License: 9");
  });

  it("shows the licence rights granted to the configured institute", async () => {
    const config = makeConfig({ structure_id: "isae" });
    config.labels.metadata.license_right_by = "Attribution";
    config.labels.metadata.license_right_nc = "Non-commercial use";
    const nugget = makeNugget({
      license: undefined,
      access_licences: [
        { structure_id: "other", rights: ["by", "co"] },
        { structure_id: "isae", rights: ["by", "nc"] },
      ],
    });
    const { dialog } = await openAbout(nugget, config);
    expect(dialog.textContent?.replace(/\s+/g, " ")).toContain(
      "License: Attribution · Non-commercial use"
    );
  });

  it("names the producing institutes", async () => {
    const service = makeService({
      getStructure: vi
        .fn()
        .mockImplementation((key: string) =>
          Promise.resolve({ structure_id: key, acronym: key.toUpperCase() })
        ),
    });
    const config = makeConfig();
    config.labels.metadata.producer = "Producer";
    const { dialog, flush } = await openAbout(
      makeNugget({ managing_structures: ["isae", "enac", "isae"] }),
      config,
      service
    );
    await flush();
    expect(dialog.textContent?.replace(/\s+/g, " ")).toContain(
      "Producer: ISAE, ENAC"
    );
  });

  it("ignores producer names that arrive for a Nugget already replaced", async () => {
    let answer: (value: unknown) => void = () => {};
    const service = makeService({
      getStructure: vi.fn(() => new Promise((resolve) => (answer = resolve))),
    });
    const config = makeConfig();
    config.labels.metadata.producer = "Producer";
    const { props, dialog, flush } = await openAbout(
      makeNugget({ managing_structures: ["slow"] }),
      config,
      service
    );
    props.nugget = makeNugget({ name: "Other", managing_structures: [] });
    await flush();
    answer({ acronym: "SLOW" });
    await flush();
    expect(dialog.textContent).not.toContain("SLOW");
  });

  it("shows the partner, linked only to a web address", async () => {
    const config = makeConfig();
    config.labels.metadata.partner = "Partner";
    const { dialog } = await openAbout(
      makeNugget({ partner: { name: "ONERA", url: "https://onera.fr" } }),
      config
    );
    const link = dialog.querySelector<HTMLAnchorElement>(
      'a[href="https://onera.fr"]'
    );
    expect(link?.textContent).toBe("ONERA");
    expect(link?.getAttribute("rel")).toBe("noopener noreferrer");
    mounted?.unmount();
    document.body.innerHTML = "";

    const unsafe = await openAbout(
      makeNugget({ partner: { name: "Evil", url: "javascript:alert(1)" } }),
      config
    );
    expect(unsafe.dialog.textContent?.replace(/\s+/g, " ")).toContain(
      "Partner: Evil"
    );
    expect(unsafe.dialog.querySelector('a[href^="javascript"]')).toBeNull();
    mounted?.unmount();
    document.body.innerHTML = "";

    const nolink = await openAbout(
      makeNugget({ partner: { name: "CNES", url: null } }),
      config
    );
    expect(nolink.dialog.textContent?.replace(/\s+/g, " ")).toContain(
      "Partner: CNES"
    );
    expect(nolink.dialog.querySelector(".metadata-list a")).toBeNull();
  });

  it("closes on Escape, the close button and the backdrop only", async () => {
    const { dialog, emitted } = await openAbout();
    expect(document.activeElement).toBe(dialog);

    dialog.click();
    expect(emitted.close).toBeUndefined();
    pressKey("Escape");
    expect(emitted.close).toHaveLength(1);
    dialog.querySelector<HTMLButtonElement>(".btn-close")!.click();
    expect(emitted.close).toHaveLength(2);
    document.querySelector<HTMLElement>(".nugget-modal-backdrop")!.click();
    expect(emitted.close).toHaveLength(3);
  });

  it("traps Tab on its only control", async () => {
    const { dialog } = await openAbout(fullNugget);
    const close = dialog.querySelector<HTMLElement>(".btn-close")!;
    expect(expectTabCycles(dialog, close, close)).toEqual({
      forward: true,
      backward: true,
    });
  });

  it("gives focus back to the opener when it closes", async () => {
    const opener = document.createElement("button");
    document.body.appendChild(opener);
    opener.focus();
    const { props, flush } = await openAbout();
    expect(document.activeElement).not.toBe(opener);

    props.visible = false;
    await flush();
    expect(document.activeElement).toBe(opener);
  });
});
