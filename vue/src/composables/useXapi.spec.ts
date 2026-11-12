import { describe, it, expect, vi } from "vitest";
import { createApp } from "vue";
import { useXapi } from "./useXapi";
import { NAAS_API_KEY } from "@/plugins/naas-api.plugin";
import type { XapiParams } from "@/types/nugget.types";

describe("useXapi", () => {
  it("should post a statement and log warnings on error", async () => {
    const app = createApp({});
    const mockPost = vi.fn().mockRejectedValue(new Error("Network error"));
    const consoleSpy = vi.spyOn(console, "warn").mockImplementation(() => {});

    app.provide(NAAS_API_KEY, { postXapiStatement: mockPost });

    app.runWithContext(() => {
      const { postStatement } = useXapi();
      postStatement({ verb: "completed" } as unknown as XapiParams);
    });

    expect(mockPost).toHaveBeenCalledWith({ verb: "completed" });

    // Wait a tick for the promise to reject
    await new Promise((resolve) => setTimeout(resolve, 0));
    expect(consoleSpy).toHaveBeenCalledWith(
      "[NaaS xAPI] failed to post statement",
      expect.any(Error)
    );

    consoleSpy.mockRestore();
  });

  it("should post a statement successfully without logging", async () => {
    const app = createApp({});
    const mockPost = vi.fn().mockResolvedValue(true);
    const consoleSpy = vi.spyOn(console, "warn").mockImplementation(() => {});

    app.provide(NAAS_API_KEY, { postXapiStatement: mockPost });

    app.runWithContext(() => {
      const { postStatement } = useXapi();
      postStatement({ verb: "viewed" } as unknown as XapiParams);
    });

    await new Promise((resolve) => setTimeout(resolve, 0));
    expect(consoleSpy).not.toHaveBeenCalled();

    consoleSpy.mockRestore();
  });
});
