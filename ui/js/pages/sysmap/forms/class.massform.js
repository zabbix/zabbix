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
 * Elements mass update form.
 *
 * @param {object} form_container  jQuery object.
 * @param {object} sysmap
 */
class MassForm {
	constructor(form_container, sysmap) {
		const tpl = new Template(document.getElementById('mapMassFormTpl').innerHTML),
			form_actions = [
				{
					action: 'enable',
					value: '#massLabel',
					cond: [{
						chkboxLabel: 'checked'
					}]
				},
				{
					action: 'enable',
					value: '#massLabelLocation',
					cond: [{
						chkboxLabelLocation: 'checked'
					}]
				},
				{
					action: 'enable',
					value: '#mass_show_label [name=show_label]',
					cond: [{
						checkbox_show_label: 'checked'
					}]
				},
				{
					action: 'enable',
					value: '#massUseIconmap',
					cond: [{
						chkboxMassUseIconmap: 'checked'
					}]
				},
				{
					action: 'enable',
					value: '#massIconidOff',
					cond: [{
						chkboxMassIconidOff: 'checked'
					}]
				},
				{
					action: 'enable',
					value: '#massIconidOn',
					cond: [{
						chkboxMassIconidOn: 'checked'
					}]
				},
				{
					action: 'enable',
					value: '#massIconidMaintenance',
					cond: [{
						chkboxMassIconidMaintenance: 'checked'
					}]
				},
				{
					action: 'enable',
					value: '#massIconidDisabled',
					cond: [{
						chkboxMassIconidDisabled: 'checked'
					}]
				}
			];

		this.sysmap = sysmap;
		this.form_container = form_container;

		// create form
		this.domNode = $(tpl.evaluate()).appendTo(form_container);

		// populate icons selects
		const select_icon_off = document.getElementById('massIconidOff'),
			select_icon_on = document.getElementById('massIconidOn'),
			select_icon_maintenance = document.getElementById('massIconidMaintenance'),
			select_icon_disabled = document.getElementById('massIconidDisabled'),
			default_option = {
				label: t('S_DEFAULT'),
				value: '0',
				class_name: ZBX_STYLE_DEFAULT_OPTION,
			};

		[select_icon_on, select_icon_maintenance, select_icon_disabled]
			.forEach((select) => select.addOption(default_option));

		Object.values(this.sysmap.iconList).forEach((icon) => {
			const option = {label: icon.name, value: icon.imageid};

			[select_icon_off, select_icon_on, select_icon_maintenance, select_icon_disabled]
				.forEach((select) => select.addOption(option));
		});

		document.getElementById('massLabelLocation').selectedIndex = 0;

		select_icon_off.selectedIndex = 0;
		select_icon_on.selectedIndex = 0;
		select_icon_maintenance.selectedIndex = 0;
		select_icon_disabled.selectedIndex = 0;

		this.actionProcessor = new ActionProcessor(form_actions);
		this.actionProcessor.process();
	}

	/**
	 * Show mass update form.
	 */
	show() {
		this.form_container.draggable('option', 'handle', '#massDragHandler');
		this.form_container.show();
		this.domNode.show();

		// Element must first be visible so that outerWidth() and outerHeight() are correct.
		this.form_container.positionOverlayDialogue();
		this.#updateList();

		addToOverlaysStack('map-window', document.activeElement, 'map-window');

		document.getElementById('chkboxLabel').focus();
	}

	/**
	 * Hide mass update form.
	 */
	hide() {
		this.domNode.toggle(false);
		$(':checkbox', this.domNode).prop('checked', false);
		$('z-select', this.domNode).each(function() {
			this.selectedIndex = 0;
		});
		$('textarea', this.domNode).val('');
		this.actionProcessor.process();

		removeFromOverlaysStack('map-window');
	}

	/**
	 * Get values from mass update form that should be updated in all selected elements.
	 *
	 * @return array
	 */
	getValues() {
		const values = $('#massForm').serializeArray(),
			data = {};

		for (const {name, value} of values) {
			// Special case for use iconmap checkbox, because unchecked checkbox is not submitted with form.
			if (name === 'chkbox_use_iconmap') {
				data.use_iconmap = SYSMAP_ELEMENT_USE_ICONMAP_OFF;
			}

			if (/^chkbox_/.test(name)) {
				continue;
			}

			data[name] = value;
		}

		return data;
	}

	/**
	 * Updates list of selected elements in mass update form.
	 */
	#updateList() {
		const tpl = new Template(document.getElementById('mapMassFormListRow').innerHTML);

		$('#massList tbody').empty();

		const list = Object.values(this.sysmap.selection.selements).map((id) => {
			const element = this.sysmap.selements[id],
				type = +element.data.elementtype;

			let text;

			switch (type) {
				case SVGMapElement.TYPE_HOST:
					text = t('S_HOST');
					break;

				case SVGMapElement.TYPE_MAP:
					text = t('S_MAP');
					break;

				case SVGMapElement.TYPE_TRIGGER:
					text = t('S_TRIGGER');
					break;

				case SVGMapElement.TYPE_HOST_GROUP:
					text = t('S_HOST_GROUP');
					break;

				case SVGMapElement.TYPE_IMAGE:
					text = t('S_IMAGE');
					break;
			}

			return {
				elementType: text,
				elementName: element.getName()
					.replace(/&/g, '&amp;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;')
					.replace(/"/g, '&quot;')
					.replace(/'/g, '&apos;')
			};
		});

		// Sort by element type and then by element name.
		list.sort((a, b) =>
			a.elementType.toLowerCase().localeCompare(b.elementType.toLowerCase())
				|| a.elementName.toLowerCase().localeCompare(b.elementName.toLowerCase())
		);

		list.forEach((item) => $(tpl.evaluate(item)).appendTo('#massList tbody'));
	}
}
