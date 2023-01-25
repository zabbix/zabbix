<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


use Widgets\ProblemsBySv\Widget;

?>

window.widget_problemsbysv_form = new class {

	init() {
		this._show_type = document.getElementById('show_type');
		this._show_type.addEventListener('change', () => this.updateForm());

		this.updateForm();
	}

	updateForm() {
		const show_type_totals = this._show_type.querySelector('input:checked').value == <?= Widget::SHOW_TOTALS ?>;

		document.getElementById('hide_empty_groups').disabled = show_type_totals;

		for (const radio of document.querySelectorAll('#layout input')) {
			radio.disabled = !show_type_totals;
		}
	}
};
