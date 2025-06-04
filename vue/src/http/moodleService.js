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
 * JavaScript module to call External Services provided by the Nugget plugin.
 *
 * @copyright  2023 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import cache from "../cache-service";

class MoodleService {
    async waitForRequirejs() {
        return new Promise((resolve) => {
            if (window.require) {
                resolve(window.require);
            } else {
                const checkRequire = setInterval(() => {
                    if (window.require) {
                        clearInterval(checkRequire);
                        resolve(window.require);
                    }
                }, 100);
            }
        });
    }

    async callWebservice(methodname, args = {}, useCache = false) {
        if (useCache && cache.has( { methodname, args })) {
            return Promise.resolve(
                cache.get({ methodname, args })
            );
        }

        const require = await this.waitForRequirejs();

        return new Promise((resolve, reject) => {
            require(['core/ajax'], (ajax) => {
                ajax.call([{ methodname, args }])[0]
                    .then(response => {
                        const payload = (typeof response === "string") ?
                            JSON.parse(response).payload :
                            response;

                        if(useCache) {
                            cache.set( { methodname, args }, payload );
                        }
                        resolve(payload);
                    })
                    .catch(reject);
            });
        });

    }
}

export default new MoodleService();
