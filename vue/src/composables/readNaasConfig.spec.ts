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
 * Tests for readNaasConfig.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import { afterEach, describe, expect, it } from "vitest";
import type { NaasConfig } from "@/types/naas-config.types";
import { NAAS_CONFIG_ELEMENT_ID, readNaasConfig } from "./readNaasConfig";

function addBlock(text: string): void {
  const block = document.createElement("script");
  block.type = "application/json";
  block.id = NAAS_CONFIG_ELEMENT_ID;
  block.textContent = text;
  document.body.appendChild(block);
}

describe("readNaasConfig", () => {
  afterEach(() => {
    document.getElementById(NAAS_CONFIG_ELEMENT_ID)?.remove();
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    delete (window as any).NAAS;
  });

  it("reads the JSON block printed by Moodle", () => {
    addBlock('{"mount_point":"#naas_widget","courseId":5,"labels":{}}');
    const config = readNaasConfig();
    expect(config.mount_point).toBe("#naas_widget");
    expect(config.courseId).toBe(5);
  });

  it("decodes the escaped tags that JSON_HEX_TAG writes", () => {
    addBlock('{"labels":{"x":"\\u003C/script\\u003E"}}');
    expect(readNaasConfig().labels).toEqual({ x: "</script>" });
  });

  it("prefers the JSON block over window.NAAS", () => {
    window.NAAS = { mount_point: "#dev" } as unknown as NaasConfig;
    addBlock('{"mount_point":"#naas_widget"}');
    expect(readNaasConfig().mount_point).toBe("#naas_widget");
  });

  it("falls back to window.NAAS for the dev server", () => {
    window.NAAS = { mount_point: "#dev" } as unknown as NaasConfig;
    expect(readNaasConfig().mount_point).toBe("#dev");
  });

  it("throws when no configuration is available", () => {
    expect(() => readNaasConfig()).toThrow(/no widget configuration/);
  });
});
