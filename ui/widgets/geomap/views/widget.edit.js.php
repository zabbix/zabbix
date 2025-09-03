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


use Widgets\Geomap\Widget;

?>

window.widget_form = new class extends CWidgetForm {

	init() {
		this._form = this.getForm();
		document.getElementById('clustering_mode').addEventListener('change', () => this.#updateForm());

		this.#updateForm();
		this.ready();
	}

	#updateForm() {
		const is_clustering_mode_auto = this._form.querySelector('[name="clustering_mode"]:checked')
			.value === '<?= Widget::CLUSTERING_MODE_AUTO ?>';

		this._form.querySelector('.js-zoom-level-field').hidden = is_clustering_mode_auto;
		document.getElementById('clustering_zoom_level').disabled = is_clustering_mode_auto;
	}
}
