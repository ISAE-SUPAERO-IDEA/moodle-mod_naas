import HttpError from "./HttpError";

/**
 * Http error querying the proxy (as opposed to those concerning the NaaS API)
 */
export default class ProxyHttpError extends HttpError {

    constructor(statusCode, message) {
        super(statusCode, message);
        this.name = "ProxyHttpError";

        Error.captureStackTrace(this, this.constructor);
    }
}