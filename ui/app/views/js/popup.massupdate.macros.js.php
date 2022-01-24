<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 */
?>

<script>
if (typeof MassUpdateMacros != 'function') {
	class MassUpdateMacros {
		constructor() {
			this.eventHandler = this.controlEventHandle.bind(this);

			[...document.querySelectorAll('[name=mass_update_macros]')]
				.map((el) => el.addEventListener('click', this.eventHandler));

			// Select proper checkbox blocks after form update.
			this.eventHandler();
		}

		controlEventHandle() {
			const elem = document.getElementById('mass_update_macros');
			const value = elem.querySelector('input:checked').value;
			const macro_table = document.getElementById('tbl_macros');

			macro_table.classList.remove('massupdate-remove');
			macro_table.style.display = 'table';

			this.showCheckboxBlock(value);

			// Hide value and description cell from table.
			if (value == <?= ZBX_ACTION_REMOVE ?>) {
				macro_table.classList.add('massupdate-remove');
			}

			// Hide macros table.
			if (value == <?= ZBX_ACTION_REMOVE_ALL ?>) {
				macro_table.style.display = 'none';
			}

			// Resize popup after change checkbox tab.
			$(window).resize();
		}

		showCheckboxBlock(type) {
			// Hide all checkboxes.
			[...document.querySelectorAll('.<?= ZBX_STYLE_CHECKBOX_BLOCK ?>')].map((el) => {
				el.style.display = 'none';
			});

			// Show proper checkbox.
			document.querySelector(`[data-type='${type}']`).style.display = '';
		}

		destroy() {
			[...document.querySelectorAll('[name=mass_update_macros]')]
				.map((el) => el.removeEventListener('click', this.eventHandler));
		}
	}
	new MassUpdateMacros();
}
</script>
