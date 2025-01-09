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


jQuery(function($) {
	var $layout_mode_btn = $('.layout-mode');

	if ($layout_mode_btn.length) {
		$layout_mode_btn.on('click', function(e) {
			e.stopPropagation();
			updateUserProfile('web.layout.mode', $layout_mode_btn.data('layout-mode'), []).always(function(){
				var url = new Curl('');
				url.unsetArgument('kiosk');
				history.replaceState(history.state, '', url.getUrl());
				location.reload();
			});
		});

		const header_kioskmode_controls = document.querySelector('.header-kioskmode-controls');

		if (header_kioskmode_controls !== null) {
			let timeout_id = null;

			const show_header_kioskmode_controls = () => {
				if (timeout_id !== null) {
					clearTimeout(timeout_id);
				}

				header_kioskmode_controls.classList.remove('hidden');

				timeout_id = setTimeout(() => {
					header_kioskmode_controls.classList.add('hidden');
				}, 2000);
			};

			for (const event_name of ['mousemove', 'mousedown', 'keydown', 'wheel']) {
				window.addEventListener(event_name, show_header_kioskmode_controls);
			}

			show_header_kioskmode_controls();
		}
	}
});
