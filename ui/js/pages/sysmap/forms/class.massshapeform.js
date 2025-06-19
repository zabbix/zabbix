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
class MassShapeForm {
	constructor(form_container, sysmap) {
		const form_actions = [],
			mapping = {
				chkboxType: '[name="mass_type"]',
				chkboxText: '#mass_text',
				chkboxFont: '#mass_font',
				chkboxFontSize: '#mass_font_size',
				chkboxFontColor: `.${ZBX_STYLE_COLOR_PICKER}[color-field-name="mass_font_color"]`,
				chkboxTextHalign: '#mass_text_halign',
				chkboxTextValign: '#mass_text_valign',
				chkboxBackground: `.${ZBX_STYLE_COLOR_PICKER}[color-field-name="mass_background_color"]`,
				chkboxBorderType: '#mass_border_type',
				chkboxBorderWidth: '#mass_border_width',
				chkboxBorderColor: `.${ZBX_STYLE_COLOR_PICKER}[color-field-name="mass_border_color"]`
			};

		Object.keys(mapping).forEach((key) => form_actions.push({
			action: 'enable',
			value: mapping[key],
			cond: [{[key]: 'checked'}]
		}));

		this.sysmap = sysmap;
		this.form_container = form_container;
		this.triggerids = {};
		this.domNode = $(new Template(document.getElementById('mapMassShapeFormTpl').innerHTML).evaluate())
			.appendTo(form_container);

		this.actionProcessor = new ActionProcessor(form_actions);
		this.actionProcessor.process();
	}

	/**
	 * Show form.
	 */
	show(figures) {
		const value = figures ? 0 : 2;

		$('.shape_figure_row', this.domNode).toggle(figures);
		$('.switchable-content', this.domNode).each((i, element) => {
			element.textContent = element.hasAttribute(`data-value-${value}`)
				? element.getAttribute(`data-value-${value}`)
				: element.dataset.value;
		});

		this.form_container.draggable('option', 'handle', '#massShapeDragHandler');
		this.form_container.show();
		this.domNode.show();
		// Element must first be visible so that outerWidth() and outerHeight() are correct.
		this.form_container.positionOverlayDialogue();
		this.active = true;

		addToOverlaysStack('map-window', document.activeElement, 'map-window');

		document.getElementById(figures ? 'chkboxType' : 'chkboxBorderType').focus();
	}

	/**
	 * Hides element form.
	 */
	hide() {
		this.domNode.toggle(false);
		this.active = false;
		$(':checkbox', this.domNode).prop('checked', false).prop('disabled', false);
		$('textarea, input[type=text]', this.domNode).each(function() {
			if ($(this).hasClass('js-numericbox')) {
				$(this).val(SYSMAP_SHAPE_BORDER_WIDTH_DEFAULT);
			}
			else {
				$(this).val('');
			}
		});

		this.actionProcessor.process();

		removeFromOverlaysStack('map-window');
	}

	/**
	 * Get values from mass update form that should be updated in all selected shapes.
	 *
	 * @return {object}
	 */
	getValues() {
		return Object.fromEntries($('#massShapeForm').serializeArray()
			.filter((v) => v.name.startsWith('mass_'))
			.map((v) => [v.name.slice(5), v.value.toString()]));
	}
}
