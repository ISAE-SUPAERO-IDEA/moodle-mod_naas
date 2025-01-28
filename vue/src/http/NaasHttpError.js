import HttpError from "./HttpError";

/**
 * Http error querying the NaaS API (as opposed to those concerning the proxy)
 */
export default class NaasHttpError extends HttpError {

    constructor(statusCode, message) {
        super(statusCode, message);
        this.name = "NaasHttpError";

        Error.captureStackTrace(this, this.constructor);
    }
}