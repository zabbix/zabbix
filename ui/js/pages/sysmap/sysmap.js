/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/


ZABBIX.namespace('apps.map');

ZABBIX.apps.map = (function() {
	function createMap(containerid, data) {
		return new CMap(containerid, data);
	}

	return {
		object: null,
		run: function(containerid, mapData) {
			if (this.object !== null) {
				throw new Error('Map has already been run.');
			}

			this.object = createMap(containerid, mapData);
		}
	}
}(jQuery));

jQuery(function ($) {
	/*
	 * Reposition the overlay dialogue window. The previous position is remembered using offset(). Each time overlay
	 * dialogue is opened, it could have different content (shape form, element form etc) and different size, so the
	 * new top and left position must be calculated. If the overlay dialogue is opened for the first time, position is
	 * set depending on map size and canvas top position. This makes map more visible at first. In case popup window is
	 * dragged outside visible view port or window is resized, popup will again be repositioned so it doesn't go outside
	 * the viewport. In case the popup is too large, position it with a small margin depending on whether is too long
	 * or too wide.
	 */
	$.fn.positionOverlayDialogue = function () {
		const $map = $('#map-area'),
			map_offset = $map.offset(),
			map_margin = 10,
			$dialogue = $(this),
			$dialogue_host = $dialogue.offsetParent(),
			dialogue_host_offset = $dialogue_host.offset(),
			// Usable area relative to host.
			dialogue_host_x_min = $dialogue_host.scrollLeft(),
			dialogue_host_x_max = Math.min($dialogue_host[0].scrollWidth,
				$(window).width() + $(window).scrollLeft() - dialogue_host_offset.left + $dialogue_host.scrollLeft()
			) - 1,
			dialogue_host_y_min = $dialogue_host.scrollTop(),
			dialogue_host_y_max = Math.min($dialogue_host[0].scrollHeight,
				$(window).height() + $(window).scrollTop() - dialogue_host_offset.top + $dialogue_host.scrollTop()
			) - 1,
			// Coordinates of map's top right corner relative to dialogue host.
			pos_x = map_offset.left + $map[0].scrollWidth - dialogue_host_offset.left + $dialogue_host.scrollLeft(),
			pos_y = map_offset.top - map_margin - dialogue_host_offset.top + $dialogue_host.scrollTop();

		return this.css({
			left: Math.max(dialogue_host_x_min, Math.min(dialogue_host_x_max - $dialogue.outerWidth(), pos_x)),
			top: Math.max(dialogue_host_y_min, Math.min(dialogue_host_y_max - $dialogue.outerHeight(), pos_y))
		});
	}
});
