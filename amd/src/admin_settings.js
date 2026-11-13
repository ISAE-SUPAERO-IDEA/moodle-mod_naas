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
 * Move administration-page descriptions into help buttons.
 *
 * Hover opens the note. Click pins it so links inside can be used.
 *
 * @module     mod_naas/admin_settings
 * @copyright  2026 ISAE-SUPAERO (https://www.isae-supaero.fr/)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('mod_naas/admin_settings', [], function() {

    /**
     * @param {HTMLElement|null} node
     * @return {boolean}
     */
    function hasText(node) {
        return !!(node && (node.textContent || '').replace(/\s+/g, ' ').trim());
    }

    /**
     * @param {HTMLElement} source Description node moved into the panel.
     * @param {string} label Accessible name of the button.
     * @return {{wrap: HTMLElement, button: HTMLButtonElement, panel: HTMLElement, pinned: boolean}}
     */
    function buildHelp(source, label) {
        var id = 'naas-admin-help-' + buildHelp.seq;
        buildHelp.seq += 1;

        var wrap = document.createElement('span');
        wrap.className = 'naas-help-wrap';

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'naas-help';
        button.setAttribute('aria-label', label);
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-controls', id);
        button.setAttribute('aria-pressed', 'false');
        button.innerHTML = '<span aria-hidden="true">?</span>';

        var panel = document.createElement('div');
        panel.id = id;
        panel.className = 'naas-help-panel';
        panel.hidden = true;
        source.classList.remove('mt-3');
        panel.appendChild(source);

        wrap.appendChild(button);
        wrap.appendChild(panel);
        return {wrap: wrap, button: button, panel: panel, pinned: false};
    }

    buildHelp.seq = 1;

    return {
        /**
         * @param {string} label
         */
        init: function(label) {
            var root = document.getElementById('adminsettings');
            if (!root || root.dataset.naasAdminHelp === '1') {
                return;
            }
            root.dataset.naasAdminHelp = '1';

            /** @type {Array} */
            var controls = [];

            /**
             * @param {Object} current
             * @param {boolean} open
             */
            function setOpen(current, open) {
                current.panel.hidden = !open;
                current.button.setAttribute('aria-expanded', open ? 'true' : 'false');
                current.wrap.classList.toggle('is-open', open);
                if (!open) {
                    current.pinned = false;
                    current.button.setAttribute('aria-pressed', 'false');
                }
            }

            /**
             * @param {Object|null} except
             */
            function closeAll(except) {
                controls.forEach(function(current) {
                    if (current !== except) {
                        setOpen(current, false);
                    }
                });
            }

            /**
             * @param {HTMLElement} anchor Element the button sits next to.
             * @param {HTMLElement} source
             * @param {boolean} inside Place the button inside the anchor (section titles).
             */
            function attach(anchor, source, inside) {
                var current = buildHelp(source, label || 'More information');
                var closeTimer = 0;
                controls.push(current);
                if (inside) {
                    anchor.appendChild(current.wrap);
                } else {
                    anchor.insertAdjacentElement('afterend', current.wrap);
                }

                current.button.addEventListener('click', function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    if (current.pinned) {
                        setOpen(current, false);
                        return;
                    }
                    closeAll(current);
                    current.pinned = true;
                    current.button.setAttribute('aria-pressed', 'true');
                    setOpen(current, true);
                });

                current.wrap.addEventListener('mouseenter', function() {
                    window.clearTimeout(closeTimer);
                    if (current.panel.hidden) {
                        closeAll(current);
                        setOpen(current, true);
                    }
                });

                current.wrap.addEventListener('mouseleave', function() {
                    if (current.pinned) {
                        return;
                    }
                    closeTimer = window.setTimeout(function() {
                        setOpen(current, false);
                    }, 180);
                });

                current.button.addEventListener('focus', function() {
                    closeAll(current);
                    setOpen(current, true);
                });

                current.wrap.addEventListener('focusout', function(event) {
                    if (current.pinned || current.wrap.contains(event.relatedTarget)) {
                        return;
                    }
                    setOpen(current, false);
                });

                current.button.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape') {
                        setOpen(current, false);
                        current.button.focus();
                    }
                });
            }

            root.querySelectorAll('h3.main').forEach(function(heading) {
                var box = heading.nextElementSibling;
                if (!box || !box.classList.contains('formsettingheading') || !hasText(box)) {
                    return;
                }
                if (box.querySelector('strong')) {
                    box.classList.add('naas-admin-warning');
                    return;
                }
                attach(heading, box, true);
            });

            root.querySelectorAll('.form-item').forEach(function(row) {
                var description = row.querySelector('.form-description');
                var title = row.querySelector('.form-label label, .form-label p');
                if (!description || !title) {
                    return;
                }
                if (!hasText(description)) {
                    description.remove();
                    return;
                }
                attach(title, description);
            });

            document.addEventListener('click', function(event) {
                if (event.target.closest && event.target.closest('.naas-help-wrap')) {
                    return;
                }
                closeAll(null);
            });

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    closeAll(null);
                }
            });
        }
    };
});
