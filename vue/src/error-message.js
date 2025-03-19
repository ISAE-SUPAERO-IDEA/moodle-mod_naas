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
 * Error handling for NAAS plugin.
 *
 * @module     mod_naas/error-message
 * @copyright  2023 NAAS Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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