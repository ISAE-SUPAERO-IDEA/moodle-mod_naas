import axios from "axios";
import NaasHttpError from "./NaasHttpError";

/**
 * Log information to the console for HTTP errors
 * @param error
 */
export default function handleAxiosError(error) {
    if (axios.isCancel(error)) {
        console.error("Request canceled:", error.message);
        return;
    }

    if (error.response) {
        // 🔴 The server responded with an error status
        console.error("📌 HTTP Error:", error.response.status);
        console.error("🔹 Message:", error.response.data?.message || error.response.statusText);
        console.error("🔹 Headers:", error.response.headers);

        switch (error.response.status) {
            case 400:
                console.warn("⚠️ Bad Request (400) - Check your input.");
                break;
            case 401:
                console.warn("🔐 Unauthorized (401) - Check your credentials");
                break;
            case 403:
                console.warn("⛔ Forbidden (403) - You don’t have permission.");
                break;
            case 404:
                console.warn("🔍 Not Found (404) - The resource does not exist.");
                break;
            case 500:
                console.warn("🔥 Server Error (500) - Try again later.");
                break;
            default:
                console.warn(`⚠️ Unexpected error (${error.response.status})`);
        }
    } else if (error.request) {
        // 🟡 The request was sent but no response was received
        console.error("📌 Network Error: No response received.");
        console.error("🔹 Details:", error.request);
    } else if (error.message.includes("timeout")) {
        // 🕒 Request timeout
        console.error("⏳ Error: The request timed out.");
    } else if (error instanceof NaasHttpError) {
        // 🛠 Custom business logic error
        console.error("🚨 Error reported by the NaaS platform:", error.message);
    } else {
        // ❌ Unknown error (e.g., bug in the code)
        console.error("💥 Unknown Error:", error.message);
    }
}
