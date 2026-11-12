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
 * Short date formatting with the browser's Intl API, in the page language.
 *
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const SHORT_DATE: Intl.DateTimeFormatOptions = {
  day: "2-digit",
  month: "2-digit",
  year: "numeric",
};

/**
 * Format a date as a short numeric date (day, month, year).
 *
 * @param value ISO date or date-time string from NaaS.
 * @param locale BCP 47 tag; defaults to the Moodle page language (<html lang>).
 * @returns The formatted date, or "" when the value is missing or not a date.
 */
export function formatShortDate(
  value?: string | null,
  locale?: string
): string {
  if (!value) {
    return "";
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "";
  }
  const lang = locale || document.documentElement.lang || undefined;
  try {
    return new Intl.DateTimeFormat(lang, SHORT_DATE).format(date);
  } catch {
    // Invalid locale tag: fall back to the browser default.
    return new Intl.DateTimeFormat(undefined, SHORT_DATE).format(date);
  }
}
