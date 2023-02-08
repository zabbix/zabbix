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
?>


window.widget_problems_form = new class {

	init({sort_with_enabled_show_timeline}) {
		this._sort_with_enabled_show_timeline = sort_with_enabled_show_timeline;

		this._show_tags = document.getElementById('show_tags');
		this._show_tags.addEventListener('change', () => this.updateForm());

		this._sort_triggers = document.getElementById('sort_triggers');
		this._sort_triggers.addEventListener('change', () => this.updateForm());

		this._show_timeline = document.getElementById('show_timeline');
		this._show_timeline_value = this._show_timeline.checked;

		this.updateForm();
	}

	updateForm() {
		const show_tags = this._show_tags.querySelector('input:checked').value != <?= SHOW_TAGS_NONE ?>;

		document.getElementById('tag_priority').disabled = !show_tags;

		for (const radio of document.querySelectorAll('#tag_name_format input')) {
			radio.disabled = !show_tags;
		}

		if (this._sort_with_enabled_show_timeline[this._sort_triggers.value]) {
			this._show_timeline.disabled = false;
			this._show_timeline.checked = this._show_timeline_value;
		}
		else {
			this._show_timeline.disabled = true;
			this._show_timeline_value = this._show_timeline.checked;
			this._show_timeline.checked = false;
		}
	}
};
