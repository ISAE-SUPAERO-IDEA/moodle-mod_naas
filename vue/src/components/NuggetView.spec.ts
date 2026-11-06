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
 * Tests for the learner view of a Nugget activity.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import NuggetView from "./NuggetView.vue";
import { mountComponent, type Mounted } from "@/test/mountComponent";
import {
  makeConfig,
  makeNugget,
  makeService,
  providers,
  type FakeService,
} from "@/test/widgetFixtures";

const iframeResize = vi.hoisted(() => vi.fn());
vi.mock("iframe-resizer/js/iframeResizer", () => ({ default: iframeResize }));

type Callback = (entries: Array<{ isIntersecting: boolean }>) => void;
const observers: Array<{
  callback: Callback;
  observe: ReturnType<typeof vi.fn>;
  disconnect: ReturnType<typeof vi.fn>;
}> = [];

let mounted: Mounted | null = null;

function viewService(overrides: Partial<FakeService> = {}): FakeService {
  return makeService({
    viewNugget: vi.fn().mockResolvedValue(
      makeNugget({
        multilanguages: [{ language: "fr" }] as never,
        authors_data: undefined,
        domains_data: undefined,
      })
    ),
    getPerson: vi.fn().mockResolvedValue({
      firstname: "Ada",
      lastname: "Lovelace",
      email: "ada@example.com",
    }),
    getDomain: vi.fn().mockResolvedValue({ id: "d1", label: "Aeronautics" }),
    ...overrides,
  });
}

async function mountView(service = viewService()) {
  mounted = mountComponent(
    NuggetView,
    {},
    providers(makeConfig({ component: "NuggetView" }), service)
  );
  await mounted.flush();
  return { service, ...mounted };
}

function buttonByText(el: HTMLElement, text: string): HTMLButtonElement {
  const found = Array.from(el.querySelectorAll("button")).find(
    (b) => b.textContent?.trim() === text
  );
  if (!found) throw new Error(`No "${text}" button`);
  return found;
}

describe("NuggetView", () => {
  beforeEach(() => {
    observers.length = 0;
    iframeResize.mockClear();
    vi.stubGlobal(
      "IntersectionObserver",
      vi.fn(function (callback: Callback) {
        const observer = { callback, observe: vi.fn(), disconnect: vi.fn() };
        observers.push(observer);
        return observer;
      })
    );
  });

  afterEach(() => {
    mounted?.unmount();
    mounted = null;
    document.body.innerHTML = "";
    vi.unstubAllGlobals();
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  it("shows a skeleton until the Nugget is loaded", async () => {
    let resolve: (value: unknown) => void = () => {};
    const service = viewService({
      viewNugget: vi.fn(() => new Promise((r) => (resolve = r))),
    });
    mounted = mountComponent(NuggetView, {}, providers(makeConfig(), service));
    await mounted.flush();
    expect(mounted.el.querySelector(".nugget-view-skeleton")).not.toBeNull();
    expect(mounted.el.querySelector("#lti-frame")).toBeNull();

    resolve(makeNugget());
    await mounted.flush();
    await mounted.flush();
    expect(mounted.el.querySelector(".nugget-view-skeleton")).toBeNull();
    expect(mounted.el.querySelector("#lti-frame")).not.toBeNull();
    expect(mounted.el.querySelector("#lti-frame")?.getAttribute("title")).toBe(
      makeNugget().name
    );
  });

  it("launches the Nugget in its own language and offers the others", async () => {
    const { el, service, flush } = await mountView();
    expect(service.viewNugget).toHaveBeenCalledWith(42);
    const frame = el.querySelector<HTMLIFrameElement>("#lti-frame")!;
    expect(frame.getAttribute("src")).toBe(
      "launch.php?id=42&triggerview=0&language=en"
    );

    const select = el.querySelector<HTMLSelectElement>(".language-select")!;
    expect(
      Array.from(select.options).map((o) => o.textContent?.trim())
    ).toEqual(["English", "French"]);
    select.value = "fr";
    select.dispatchEvent(new Event("change"));
    await flush();
    expect(frame.getAttribute("src")).toBe(
      "launch.php?id=42&triggerview=0&language=fr"
    );
  });

  it("opens the About dialog with the resolved authors", async () => {
    const { el, flush } = await mountView();
    buttonByText(el, "About").click();
    await flush();
    const dialog = document.querySelector('[role="dialog"]')!;
    expect(dialog.textContent).toContain("About: Aircraft design basics");
    expect(dialog.querySelector("h5")?.textContent?.trim()).toBe(
      "Ada Lovelace"
    );
  });

  it("opens About from Moodle's secondary navigation instead of its own button", async () => {
    document.body.insertAdjacentHTML(
      "beforeend",
      '<div class="secondary-navigation"><nav><ul><li data-key="about"><a href="#">About</a></li></ul></nav></div>'
    );
    const { el, flush } = await mountView();
    expect(
      Array.from(el.querySelectorAll("button")).some(
        (b) => b.textContent?.trim() === "About"
      )
    ).toBe(false);

    const link = document.querySelector<HTMLAnchorElement>(
      "li[data-key=about] a"
    )!;
    const click = new MouseEvent("click", { bubbles: true, cancelable: true });
    link.dispatchEvent(click);
    await flush();
    expect(click.defaultPrevented).toBe(true);
    expect(document.querySelector('[role="dialog"]')?.textContent).toContain(
      "About: Aircraft design basics"
    );

    // Once the view is gone, the link no longer drives it.
    mounted?.unmount();
    mounted = null;
    const later = new MouseEvent("click", { bubbles: true, cancelable: true });
    link.dispatchEvent(later);
    expect(later.defaultPrevented).toBe(false);
  });

  it("shows an error banner whose Retry calls Moodle again", async () => {
    vi.spyOn(console, "error").mockImplementation(() => {});
    const service = viewService({
      viewNugget: vi
        .fn()
        .mockRejectedValueOnce(new Error("NaaS down"))
        .mockResolvedValueOnce(makeNugget()),
    });
    const { el, flush } = await mountView(service);
    const banner = el.querySelector('[role="alert"]')!;
    expect(banner.textContent).toContain("Something went wrong");
    expect(el.querySelector("#lti-frame")).toBeNull();

    buttonByText(el, "Retry").click();
    await flush();
    await flush();
    expect(service.viewNugget).toHaveBeenCalledTimes(2);
    expect(el.querySelector('[role="alert"]')).toBeNull();
    expect(el.querySelector("#lti-frame")).not.toBeNull();
  });

  it("records completion and opens the rating dialog", async () => {
    const { el, service, flush } = await mountView();
    buttonByText(el, "Complete").click();
    await flush();
    expect(service.postXapiStatement).toHaveBeenCalledWith({
      id: 42,
      verb: "completed",
      version_id: "v-aircraft",
    });
    expect(document.querySelector('[role="dialog"]')?.textContent).toContain(
      "Rate this nugget"
    );
  });

  it("reports 'experienced' after 10 s of the frame being visible", async () => {
    vi.useFakeTimers();
    const service = viewService();
    mounted = mountComponent(NuggetView, {}, providers(makeConfig(), service));
    await vi.advanceTimersByTimeAsync(600);
    expect(iframeResize).toHaveBeenCalledWith(
      expect.objectContaining({ checkOrigin: false }),
      "#lti-frame"
    );
    expect(observers).toHaveLength(1);
    const [observer] = observers;
    expect(observer.observe).toHaveBeenCalledWith(
      mounted.el.querySelector("#lti-frame")
    );

    // Scrolled away before 10 s: nothing is recorded.
    observer.callback([{ isIntersecting: true }]);
    vi.advanceTimersByTime(5000);
    observer.callback([{ isIntersecting: false }]);
    vi.advanceTimersByTime(10000);
    expect(service.postXapiStatement).not.toHaveBeenCalled();

    observer.callback([{ isIntersecting: true }]);
    vi.advanceTimersByTime(10000);
    expect(service.postXapiStatement).toHaveBeenCalledWith({
      id: 42,
      verb: "experienced",
      version_id: "v-aircraft",
    });
    expect(observer.disconnect).toHaveBeenCalled();
  });

  it("ignores an observer report without entries", async () => {
    vi.useFakeTimers();
    const service = viewService();
    mounted = mountComponent(NuggetView, {}, providers(makeConfig(), service));
    await vi.advanceTimersByTimeAsync(600);
    const [observer] = observers;

    observer.callback([]);
    vi.advanceTimersByTime(20000);
    expect(service.postXapiStatement).not.toHaveBeenCalled();
    expect(observer.disconnect).not.toHaveBeenCalled();
  });

  it("keeps the preload URL when the Nugget has no language", async () => {
    const service = viewService({
      viewNugget: vi.fn().mockResolvedValue(makeNugget({ language: "" })),
    });
    const { el } = await mountView(service);
    expect(el.querySelector("#lti-frame")?.getAttribute("src")).toBe(
      "launch.php?id=42&triggerview=0"
    );
  });

  it("does nothing on Complete before the Nugget is loaded", async () => {
    const service = viewService({
      viewNugget: vi.fn(() => new Promise(() => {})),
    });
    const { el, flush } = await mountView(service);
    buttonByText(el, "Complete").click();
    await flush();
    expect(service.postXapiStatement).not.toHaveBeenCalled();
    expect(document.querySelector('[role="dialog"]')).toBeNull();
  });

  it("closes the About and rating dialogs", async () => {
    const { el, flush } = await mountView();
    buttonByText(el, "About").click();
    await flush();
    document
      .querySelector<HTMLButtonElement>('[role="dialog"] .btn-close')!
      .click();
    await vi.waitFor(() =>
      expect(document.querySelector('[role="dialog"]')).toBeNull()
    );

    buttonByText(el, "Complete").click();
    await flush();
    document
      .querySelector<HTMLButtonElement>('[role="dialog"] .btn-close')!
      .click();
    await vi.waitFor(() =>
      expect(document.querySelector('[role="dialog"]')).toBeNull()
    );
  });

  it("stops visibility tracking when unmounted", async () => {
    vi.useFakeTimers();
    const service = viewService();
    mounted = mountComponent(NuggetView, {}, providers(makeConfig(), service));
    await vi.advanceTimersByTimeAsync(600);
    const [observer] = observers;
    observer.callback([{ isIntersecting: true }]);

    mounted.unmount();
    mounted = null;
    vi.advanceTimersByTime(20000);
    expect(observer.disconnect).toHaveBeenCalled();
    expect(service.postXapiStatement).not.toHaveBeenCalled();
  });
});
