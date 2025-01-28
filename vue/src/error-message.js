import NaasHttpError from "./http/NaasHttpError";
import ProxyHttpError from "./http/ProxyHttpError";

/**
 * Translate an error from the proxy to a user readable error message.
 * @param proxyError
 * @returns {string}
 */
export default function translateError(proxyError) { // TODO i18n
    if(proxyError.statusCode === 401) {
        return "Unauthorized access ; please ask your platform administrator to check the credentials of the Nugget plugin."
    }

    if(proxyError.statusCode === 404) {
        return "Unable to contact the server ; cannot search the Nuggets. Please try again. If the problem persists, please contact your platform administrator."
    }

    if(proxyError instanceof NaasHttpError) {
        return `An error occurred on the NaaS platform : ${proxyError.message}`
    }

    if(proxyError instanceof ProxyHttpError) {
        return `An error occurred on the Nugget plugin : ${proxyError.message}`
    }

    return `An unexpected error occurred. If the problem persists, please contact your platform administrator.`
}