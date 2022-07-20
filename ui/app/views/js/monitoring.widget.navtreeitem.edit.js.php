<?php declare(strict_types = 0);
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

window.navtreeitem_edit_popup = new class {
	init() {
		jQuery('#sysmapname').on('change', (e) => {
			const name_input = document.getElementById('name');

			if (name_input.value === '') {
				name_input.value = e.target.value;
			}
		});

		document.getElementById('select').addEventListener('click', () => {
			return PopUp('popup.generic', {
				srctbl: 'sysmaps',
				srcfld1: 'sysmapid',
				srcfld2: 'name',
				dstfrm: 'widget_dialogue_form',
				dstfld1: 'sysmapid',
				dstfld2: 'sysmapname'
			}, {dialogue_class: 'modal-popup-generic'});
		});
	}
};
