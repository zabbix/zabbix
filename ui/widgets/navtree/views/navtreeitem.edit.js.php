<?php declare(strict_types = 0);
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
?>


window.navtreeitem_edit_popup = new class {

	init() {
		const $sysmap = jQuery('#sysmapid');
		const name_input = document.getElementById('name');

		$sysmap.on('change', () => {
			if (name_input.value === '') {
				const sysmaps = $sysmap.multiSelect('getData');

				name_input.value = sysmaps.length ? sysmaps[0]['name'] : '';
			}
		});
	}
};
