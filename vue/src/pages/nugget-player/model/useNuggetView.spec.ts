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
 * Tests for useNuggetView (load the activity's Nugget on mount).
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, describe, expect, it, vi } from "vitest";
import { defineComponent, h } from "vue";
import { useNuggetView } from "./useNuggetView";
import { mountComponent, type Mounted } from "@/test/mountComponent";
import {
  makeNugget,
  makeService,
  providers,
  makeConfig,
  type FakeService,
} from "@/test/widgetFixtures";

let mounted: Mounted | null = null;
let state: ReturnType<typeof useNuggetView>;

const Harness = defineComponent({
  setup() {
    state = useNuggetView();
    return () => h("div");
  },
});

async function mountWith(service: FakeService) {
  mounted = mountComponent(Harness, {}, providers(makeConfig(), service));
  await mounted.flush();
  return state;
}

describe("useNuggetView", () => {
  afterEach(() => {
    mounted?.unmount();
    mounted = null;
    vi.restoreAllMocks();
  });

  it("loads and enriches the activity's Nugget on mount", async () => {
    const service = makeService({
      viewNugget: vi
        .fn()
        .mockResolvedValue(makeNugget({ authors_data: undefined })),
      getPerson: vi.fn().mockResolvedValue({ firstname: "Ada" }),
      getDomain: vi.fn().mockResolvedValue({ id: "d1", label: "Aero" }),
    });
    const { nugget, loading, error } = await mountWith(service);
    expect(service.viewNugget).toHaveBeenCalledWith(42);
    expect(loading.value).toBe(false);
    expect(error.value).toBeNull();
    expect(nugget.value?.authors_data).toEqual([{ firstname: "Ada" }]);
  });

  it("keeps the message of an Error, or the text of anything else thrown", async () => {
    const log = vi.spyOn(console, "error").mockImplementation(() => {});
    const service = makeService({
      viewNugget: vi
        .fn()
        .mockRejectedValueOnce(new Error("NaaS down"))
        .mockRejectedValueOnce("timeout"),
    });
    const { error, loading, load } = await mountWith(service);
    expect(error.value).toBe("NaaS down");
    expect(loading.value).toBe(false);

    await load();
    expect(error.value).toBe("timeout");
    expect(log).toHaveBeenCalledWith(
      "[NaaS] useNuggetView.load() error:",
      "timeout"
    );
  });
});
