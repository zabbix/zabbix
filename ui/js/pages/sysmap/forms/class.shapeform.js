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


/**
 * Form for shape editing.
 *
 * @param {object} form_container jQuery object.
 * @param {object} sysmap
 */
class ShapeForm {
	constructor(form_container, sysmap) {
		this.sysmap = sysmap;
		this.form_container = form_container;
		this.triggerids = {};
		this.domNode = $(new Template(document.getElementById('mapShapeFormTpl').innerHTML).evaluate())
			.appendTo(form_container);
	}

	/**
	 * Show form.
	 */
	show() {
		this.form_container.draggable('option', 'handle', '#shapeDragHandler');
		this.form_container.show();
		this.domNode.show();
		// Element must first be visible so that outerWidth() and outerHeight() are correct.
		this.form_container.positionOverlayDialogue();
		this.active = true;

		addToOverlaysStack('map-window', document.activeElement, 'map-window');

		document.querySelector('#shapeForm [name="type"]:checked').focus();
	}

	/**
	 * Hides element form.
	 */
	hide() {
		this.domNode.toggle(false);
		this.active = false;

		removeFromOverlaysStack('map-window');
	}

	/**
	 * Set form controls with shape fields values.
	 *
	 * @param {object} shape
	 */
	setValues(shape) {
		for (const field in shape) {
			const color_picker = this.domNode[1].querySelector(
				`.${ZBX_STYLE_COLOR_PICKER}[color-field-name="${field}"]`
			);

			if (color_picker !== null) {
				color_picker.color = shape[field];
			}
			else {
				$(`[name=${field}]`, this.domNode).val([shape[field]]);
			}
		}

		document.getElementById('border_type').dispatchEvent(new Event('change'));
		document.getElementById('last_shape_type').value = shape.type;
		document.querySelector('input[type=radio][name=type]:checked').dispatchEvent(new Event('change'));
	}

	/**
	 * Get values from shape update form that should be updated.
	 *
	 * @return {object}
	 */
	getValues() {
		const values = $('#shapeForm').serializeArray(),
			width = parseInt(this.sysmap.data.width),
			height = parseInt(this.sysmap.data.height),
			data = values.reduce((acc, {name, value}) => {
				acc[name] = value.toString();

				return acc;
			}, {});

		data.x = parseInt(data.x, 10);
		data.y = parseInt(data.y, 10);
		data.width = parseInt(data.width, 10);
		data.height = parseInt(data.height, 10);

		data.x = isNaN(data.x) ? 0 : Math.min(Math.max(0, data.x), width);
		data.y = isNaN(data.y) ? 0 : Math.min(Math.max(0, data.y), height);

		const min_size = data.type != SVGMapShape.TYPE_LINE ? 1 : 0;

		data.width = isNaN(data.width) ? min_size : Math.min(Math.max(min_size, data.width), width);
		data.height = isNaN(data.height) ? min_size : Math.min(Math.max(min_size, data.height), height);

		return data;
	}
}
