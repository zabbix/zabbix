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
 * Form for editing links.
 *
 * @param {object} formContainer jQuery object.
 * @param {object} sysmap
 */
class LinkForm {
	constructor(formContainer, sysmap) {
		this.sysmap = sysmap;
		this.formContainer = formContainer;
		this.triggerids = {};
		this.domNode = $(new Template(document.getElementById('linkFormTpl').innerHTML).evaluate())
			.appendTo(formContainer);

		this.domNode.find('.color-picker input').colorpicker();
	}

	/**
	 * Show form.
	 */
	show() {
		this.domNode.show();
		document.querySelectorAll('.element-edit-control').forEach((element) => element.disabled = true);
	}

	/**
	 * Hide form.
	 */
	hide() {
		$('#linkForm').hide();
		document.querySelectorAll('.element-edit-control').forEach((element) =>
			element.removeAttribute('disabled')
		);
	}

	/**
	 * Get form values for link fields.
	 */
	getValues() {
		const data = {linktriggers: {}},
			link_trigger_pattern = /^linktrigger_(\w+)_(triggerid|linktriggerid|drawtype|color|desc_exp)$/;

		$('#linkForm').serializeArray().forEach(({name, value}) => {
			const link_trigger = link_trigger_pattern.exec(name);

			value = value.toString();

			if ((name === 'color' || link_trigger?.[2] === 'color') && !isColorHex(`#${value}`)) {
				throw sprintf(t('S_COLOR_IS_NOT_CORRECT'), value);
			}

			if (link_trigger) {
				data.linktriggers[link_trigger[1]] ??= {};
				data.linktriggers[link_trigger[1]][link_trigger[2]] = value;
			}
			else {
				data[name] = value;
			}
		});

		return data;
	}

	/**
	 * Update form controls with values from link.
	 *
	 * @param {object} link
	 */
	setValues(link) {
		// If only one element is selected and no shapes, swap link IDs if needed.
		if (this.sysmap.selection.count.selements == 1 && this.sysmap.selection.count.shapes == 0) {
			const selement1 = this.sysmap.selements[Object.keys(this.sysmap.selection.selements)[0]];

			if (selement1.id !== link.selementid1) {
				[link.selementid1, link.selementid2] = [selement1.id, link.selementid1];
			}
		}

		const connect_to_select = document.createElement('z-select');

		connect_to_select._button.id = 'label-selementid2';
		connect_to_select.id = 'selementid2';
		connect_to_select.name = 'selementid2';

		// Sort by type.
		const optgroups = {};

		Object.values(this.sysmap.selements).forEach((selement) => {
		if (selement.id === link.selementid1) {
				return;
			}

			const type = selement.data.elementtype;

			(optgroups[type] = optgroups[type] || []).push(selement);
		});

		Object.keys(optgroups).forEach((type) => {
			let label;

			switch (+type) {
				case SVGMapElement.TYPE_HOST:
					label = t('S_HOST');
					break;

				case SVGMapElement.TYPE_MAP:
					label = t('S_MAP');
					break;

				case SVGMapElement.TYPE_TRIGGER:
					label = t('S_TRIGGER');
					break;

				case SVGMapElement.TYPE_HOST_GROUP:
					label = t('S_HOST_GROUP');
					break;

				case SVGMapElement.TYPE_IMAGE:
					label = t('S_IMAGE');
					break;
			}

			const optgroup = {
				label,
				options: optgroups[type].map((element) => ({value: element.id, label: element.getName()}))
			};

			connect_to_select.addOptionGroup(optgroup);
		});

		$('#selementid2').replaceWith(connect_to_select);

		// Set values for form elements.
		Object.keys(link).forEach((name) => $(`[name=${name}]`, this.domNode).val(link[name]));

		// Clear triggers.
		this.triggerids = {};
		document.querySelectorAll('#linkTriggerscontainer tbody tr').forEach((tr) => tr.remove());
		this.#addLinkTriggers(link.linktriggers);
	}

	/**
	 * Add link triggers to link form.
	 *
	 * @param {object} triggers
	 */
	#addLinkTriggers(triggers) {
		const tpl = new Template(document.getElementById('linkTriggerRow').innerHTML),
			$table = $('#linkTriggerscontainer tbody');

		Object.values(triggers).forEach((trigger) => {
			this.triggerids[trigger.triggerid] = true;
			$(tpl.evaluate(trigger)).appendTo($table);
			$(`#linktrigger_${trigger.linktriggerid}_drawtype`).val(trigger.drawtype);
		});

		$table.find('.color-picker input').colorpicker();
		$('.color-picker input', this.domNode).change();
	}

	/**
	 * Add new triggers which were selected in popup to trigger list.
	 *
	 * @param {object} triggers
	 */
	addNewTriggers(triggers) {
		const tpl = new Template(document.getElementById('linkTriggerRow').innerHTML),
			linkTrigger = {color: 'DD0000'},
			$table = $('#linkTriggerscontainer tbody');

		for (let i = 0, ln = triggers.length; i < ln; i++) {
			if (this.triggerids[triggers[i].triggerid] !== undefined) {
				continue;
			}

			const linktriggerid = getUniqueId();

			// Store linktriggerid to generate every time unique one.
			this.sysmap.allLinkTriggerIds[linktriggerid] = true;

			// Store triggerid to forbid selecting same trigger twice.
			this.triggerids[triggers[i].triggerid] = linktriggerid;
			linkTrigger.linktriggerid = linktriggerid;
			linkTrigger.desc_exp = triggers[i].description;
			linkTrigger.triggerid = triggers[i].triggerid;

			$(tpl.evaluate(linkTrigger)).appendTo($table);
		}

		$table.find('.color-picker input').colorpicker();
		$('.color-picker input', this.domNode).change();
	}

	/**
	 * Updates links list for element.
	 *
	 * @param {string} selementids
	 */
	updateList(selementids) {
		const links = this.sysmap.getLinksBySelementIds(selementids),
			list = [];

		let	$link_table,
			row_tpl;

		$('.element-links').hide();
		$('.element-links tbody').empty();

		if (links.length) {
			$('#mapLinksContainer').show();

			if (objectSize(selementids) > 1) {
				row_tpl = 'massElementLinkTableRowTpl';
				$link_table = $('#mass-element-links');
			}
			else {
				row_tpl = 'elementLinkTableRowTpl';
				$link_table = $('#element-links');
			}

			row_tpl = new Template(document.getElementById(row_tpl).innerHTML);

			links.forEach((linkid) => {
				const link = this.sysmap.links[linkid].data;

				/*
					* If one element selected and it's not link.selementid1, we need to swap link.selementid1
					* and link.selementid2 in order that sorting works correctly.
					*/
				if (objectSize(selementids) == 1 && !selementids[link.selementid1]) {
					const selected = this.sysmap.selements[Object.keys(this.sysmap.selection.selements)[0]];

					if (selected.id !== link.selementid1) {
						[link.selementid1, link.selementid2] = [selected.id, link.selementid1];
					}
				}

				const linktriggers = Object.values(link.linktriggers).map((trigger) => trigger.desc_exp),
					fromElementName = this.sysmap.selements[link.selementid1].getName(),
					toElementName = this.sysmap.selements[link.selementid2].getName();

				list.push({fromElementName, toElementName, linkid: link.linkid, linktriggers});
			});

			// Sort by "From" element, then by "To" element and then by "linkid".
			list.sort((a, b) => a.fromElementName.toLowerCase().localeCompare(b.fromElementName.toLowerCase())
					|| a.toElementName.toLowerCase().localeCompare(b.toElementName.toLowerCase())
					|| a.linkid.localeCompare(b.linkid)
			);

			list.forEach((item) => {
				const row = $(row_tpl.evaluate(item)),
					row_urls = $('.element-urls', row);

				item.linktriggers.forEach((trigger, index) => {
					if (index != 0) {
						row_urls.append($('<br>'));
					}

					row_urls.append($('<span>').text(trigger));
				});

				row.appendTo($link_table.find('tbody'));
			});

			$link_table.closest('.element-links').show();
		}
		else {
			$('#mapLinksContainer').hide();
		}
	}
}
