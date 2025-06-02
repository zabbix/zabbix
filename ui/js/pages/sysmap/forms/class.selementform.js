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
 * Form for elements.
 *
 * @param {object} form_container  jQuery object.
 * @param {object} sysmap
 */
class SelementForm {
	constructor(form_container, sysmap) {
		const formTplData = {sysmapid: sysmap.sysmapid},
			tpl = new Template(document.getElementById('mapElementFormTpl').innerHTML),
			form_actions = [
				{
					action: 'show',
					value: '#subtypeRow, #hostGroupSelectRow',
					cond: [{
						elementType: SVGMapElement.TYPE_HOST_GROUP
					}]
				},
				{
					action: 'show',
					value: '#hostSelectRow',
					cond: [{
						elementType: SVGMapElement.TYPE_HOST
					}]
				},
				{
					action: 'show',
					value: '#triggerSelectRow, #triggerListRow',
					cond: [{
						elementType: SVGMapElement.TYPE_TRIGGER
					}]
				},
				{
					action: 'show',
					value: '#mapSelectRow',
					cond: [{
						elementType: SVGMapElement.TYPE_MAP
					}]
				},
				{
					action: 'show',
					value: '#areaTypeRow, #areaPlacingRow',
					cond: [{
						elementType: SVGMapElement.TYPE_HOST_GROUP,
						subtypeHostGroupElements: 'checked'
					}]
				},
				{
					action: 'show',
					value: '#areaSizeRow',
					cond: [{
						elementType: SVGMapElement.TYPE_HOST_GROUP,
						subtypeHostGroupElements: 'checked',
						areaTypeCustom: 'checked'
					}]
				},
				{
					action: 'hide',
					value: '#iconProblemRow, #iconMainetnanceRow, #iconDisabledRow',
					cond: [{
						elementType: SVGMapElement.TYPE_IMAGE
					}]
				},
				{
					action: 'disable',
					value: '#iconid_off, #iconid_on, #iconid_maintenance, #iconid_disabled',
					cond: [
						{
							use_iconmap: 'checked',
							elementType: SVGMapElement.TYPE_HOST
						},
						{
							use_iconmap: 'checked',
							elementType: SVGMapElement.TYPE_HOST_GROUP,
							subtypeHostGroupElements: 'checked'
						}
					]
				},
				{
					action: 'show',
					value: '#useIconMapRow',
					cond: [
						{
							elementType: SVGMapElement.TYPE_HOST
						},
						{
							elementType: SVGMapElement.TYPE_HOST_GROUP,
							subtypeHostGroupElements: 'checked'
						}
					]
				},
				{
					action: 'show',
					value: '#tags-select-row',
					cond: [
						{
							elementType: SVGMapElement.TYPE_HOST
						},
						{
							elementType: SVGMapElement.TYPE_HOST_GROUP
						}
					]
				}
			];

		this.active = false;
		this.sysmap = sysmap;
		this.form_container = form_container;

		// create form
		this.domNode = $(tpl.evaluate(formTplData)).appendTo(form_container);

		// populate icons selects
		const select_icon_off = document.getElementById('iconid_off'),
			select_icon_on = document.getElementById('iconid_on'),
			select_icon_maintenance = document.getElementById('iconid_maintenance'),
			select_icon_disabled = document.getElementById('iconid_disabled'),
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

		$('#elementNameHost').multiSelectHelper({
			id: 'elementNameHost',
			object_name: 'hosts',
			name: 'elementValue',
			selectedLimit: 1,
			popup: {
				parameters: {
					srctbl: 'hosts',
					srcfld1: 'hostid',
					dstfrm: 'selementForm',
					dstfld1: 'elementNameHost'
				}
			}
		});

		$('#elementNameMap').multiSelectHelper({
			id: 'elementNameMap',
			object_name: 'sysmaps',
			name: 'elementValue',
			selectedLimit: 1,
			popup: {
				parameters: {
					srctbl: 'sysmaps',
					srcfld1: 'sysmapid',
					dstfrm: 'selementForm',
					dstfld1: 'elementNameMap'
				}
			}
		});

		$('#elementNameTriggers').multiSelectHelper({
			id: 'elementNameTriggers',
			object_name: 'triggers',
			name: 'elementValue',
			objectOptions: {
				real_hosts: true
			},
			popup: {
				parameters: {
					srctbl: 'triggers',
					srcfld1: 'triggerid',
					dstfrm: 'selementForm',
					dstfld1: 'elementNameTriggers',
					with_triggers: '1',
					real_hosts: '1',
					multiselect: '1'
				}
			}
		});

		$('#elementNameHostGroup').multiSelectHelper({
			id: 'elementNameHostGroup',
			object_name: 'hostGroup',
			name: 'elementValue',
			selectedLimit: 1,
			popup: {
				parameters: {
					srctbl: 'host_groups',
					srcfld1: 'groupid',
					dstfrm: 'selementForm',
					dstfld1: 'elementNameHostGroup'
				}
			}
		});

		this.actionProcessor = new ActionProcessor(form_actions);
		this.actionProcessor.process();
	}

	/**
	 * Sorting triggers by severity.
	 */
	static recalculateTriggerSortOrder() {
		const triggers_list = document.querySelectorAll('input[name^="element_id"]');

		if (triggers_list.length != 0) {
			const triggers = [];

			triggers_list.forEach((input) => {
				const triggerid = input.value,
					priority = document.querySelector(`input[name^="element_priority[${triggerid}]"]`).value,
					html = document.getElementById(`triggerrow_${triggerid}`).outerHTML;

				if (!triggers[priority]) {
					triggers[priority] = {priority, html};
				}
				else {
					triggers[priority].html += html;
				}
			});

			triggers.sort((a, b) => b.priority - a.priority);

			const container = document.querySelector('#triggerContainer tbody');

			if (container) {
				container.innerHTML = '';
				triggers.forEach((trigger) => container.insertAdjacentHTML('beforeend', trigger.html));
			}
		}
	}

	/**
	 * Shows element form.
	 */
	show() {
		this.form_container.draggable('option', 'handle', '#formDragHandler');
		this.form_container.show();
		this.domNode.show();
		// Element must first be visible so that outerWidth() and outerHeight() are correct.
		this.form_container.positionOverlayDialogue();
		this.active = true;

		addToOverlaysStack('map-window', document.activeElement, 'map-window');

		document.getElementById('elementType').focus();
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
	 * Adds element urls to form.
	 *
	 * @param {object} urls
	 */
	addUrls(urls) {
		const tpl = new Template(document.getElementById('selementFormUrls').innerHTML),
			tbody = document.querySelector('#urlContainer tbody');

		let urlid = tbody.querySelectorAll('tr[id^="urlrow"]').length;

		if (urls === undefined || Object.keys(urls).length == 0) {
			urls = {empty: {}};
		}

		Object.values(urls).forEach((url) => {
			while (document.getElementById(`urlrow_${urlid}`)) {
				urlid++;
			}
			url.selementurlid = urlid;
			tbody.insertAdjacentHTML('beforeend', tpl.evaluate(url));
		});
	}

	/**
	 * Append form tag field options.
	 *
	 * @param {array} tags
	 */
	#addTags(tags) {
		const tpl = new Template(document.getElementById('tag-row-tmpl').innerHTML),
			add_btn_row = document.querySelector('#selement-tags .element-table-add').closest('tr');

		let counter = $('#selement-tags').data('dynamicRows').counter;

		for (const i in tags) {
			const tag = jQuery.extend({tag: '', operator: 0, value: '', rowNum: ++counter}, tags[i]),
				$row = $(tpl.evaluate(tag));

			$row.insertBefore(add_btn_row);

			['tag', 'operator', 'value'].forEach((field) =>
				$row
					.find(`[name="tags[${tag.rowNum}][${field}]"]`)
					.val(tag[field])
			);

			new CTagFilterItem($row[0]);
		}

		$('#selement-tags').data('dynamicRows').counter = counter;
	}

	/**
	 * Add triggers to the list.
	 */
	addTriggers(triggers) {
		const tpl = new Template(document.getElementById('selementFormTriggers').innerHTML),
			selected_triggers = $('#elementNameTriggers').multiSelect('getData'),
			triggerids = [],
			triggers_to_insert = [];

		if (triggers === undefined || $.isEmptyObject(triggers)) {
			triggers = [];
		}

		triggers = triggers.concat(selected_triggers);

		if (triggers) {
			triggers.forEach((trigger) => {
				if ($(`input[name^="element_id[${trigger.id}]"]`).length == 0) {
					triggerids.push(trigger.id);
					triggers_to_insert[trigger.id] = {
						id: trigger.id,
						name: trigger.prefix === undefined ? trigger.name : trigger.prefix + trigger.name
					};
				}
			});

			if (triggerids.length != 0) {
				const url = new Curl('jsrpc.php');

				url.setArgument('type', PAGE_TYPE_TEXT_RETURN_JSON);

				$.ajax({
					url: url.getUrl(),
					type: 'post',
					dataType: 'html',
					data: {
						method: 'trigger.get',
						triggerids
					},
					success: (data) => {
						data = JSON.parse(data);
						triggers.forEach((sorted_trigger) => {
							data.result.forEach((trigger) => {
								if (sorted_trigger.id === trigger.triggerid) {
									if ($(`input[name^="element_id[${trigger.triggerid}]"]`).length == 0) {
										trigger.name = triggers_to_insert[trigger.triggerid].name;
										$(tpl.evaluate(trigger)).appendTo('#triggerContainer tbody');

										return false;
									}
								}
							});
						});

						this.constructor.recalculateTriggerSortOrder();
					}
				});
			}

			$('#elementNameTriggers').multiSelect('clean');
		}
	}

	/**
	 * Set form controls with element fields values.
	 *
	 * @param {object} selement
	 */
	setValues(selement) {
		// Set default icon state.
		['iconid_on', 'iconid_disabled', 'iconid_maintenance'].forEach((name) => {
			$(`[name=${name}]`, this.domNode).val(0);
		});

		for (const field in selement) {
			$(`[name=${field}]`, this.domNode).val([selement[field]]);
		}

		// Clear urls.
		document.querySelectorAll('#urlContainer tbody tr').forEach((tr) => tr.remove());
		this.addUrls(selement.urls);

		// Set tag properties.
		let tags = selement.tags;

		if (!tags || Object.getOwnPropertyNames(tags).length == 0) {
			tags = {0: {}};
		}

		this.cleanTagsField();
		this.#addTags(tags);

		// Iconmap.
		if (this.sysmap.data.iconmapid == 0) {
			const use_iconmap = document.getElementById('use_iconmap');

			if (use_iconmap) {
				use_iconmap.checked = false;
				use_iconmap.disabled = true;
			}
		}

		this.actionProcessor.process();

		switch (+selement.elementtype) {
			case SVGMapElement.TYPE_HOST:
				$('#elementNameHost').multiSelect('addData', [{
					'id': selement.elements[0].hostid,
					'name': selement.elements[0].elementName
				}]);
				break;

			case SVGMapElement.TYPE_MAP:
				$('#elementNameMap').multiSelect('addData', [{
					'id': selement.elements[0].sysmapid,
					'name': selement.elements[0].elementName
				}]);
				break;

			case SVGMapElement.TYPE_TRIGGER:
				const triggers = Object.values(selement.elements).map((element) => ({
					id: element.triggerid,
					name: element.elementName
				}));

				this.addTriggers(triggers);
				break;

			case SVGMapElement.TYPE_HOST_GROUP:
				$('#elementNameHostGroup').multiSelect('addData', [{
					'id': selement.elements[0].groupid,
					'name': selement.elements[0].elementName
				}]);
				break;
		}
	}

	/**
	 * Remove tag filter rows from DOM.
	 */
	cleanTagsField() {
		document.querySelectorAll('#selement-tags .form_row').forEach((element) => element.remove());
	}

	/**
	 * Gets form values for element fields.
	 *
	 * @return {object|boolean}
	 */
	getValues() {
		const values = $(':input', '#selementForm')
				.not(this.actionProcessor.hidden)
				.not('[name^="tags"]')
				.serializeArray(),
			data = {
				urls: {}
			},
			url_pattern = /^url_(\d+)_(name|url)$/,
			url_names = {};

		let elements_data = {};

		values.forEach(({name, value}) => {
			const match = url_pattern.exec(name);

			if (match) {
				data.urls[match[1]] = data.urls[match[1]] || {};
				data.urls[match[1]][match[2]] = value;
			}
			else {
				data[name] = value;
			}
		});

		if (data.elementtype == SVGMapElement.TYPE_HOST
				|| data.elementtype == SVGMapElement.TYPE_HOST_GROUP) {
			data.tags = {};

			$('input, z-select', '#selementForm')
				.filter(function() {
					return this.name.match(/tags\[\d+\]\[tag\]/);
				})
				.each(function() {
					if (this.value !== '') {
						const num = parseInt(this.name.match(/^tags\[(\d+)\]\[tag\]$/)[1]);

						data.tags[Object.getOwnPropertyNames(data.tags).length] = {
							tag: this.value,
							operator: $(`[name="tags[${num}][operator]"]`).val(),
							value: $(`[name="tags[${num}][value]"]`).val()
						};
					}
			});
		}

		data.elements = {};

		// Set element ID and name.
		switch (+data.elementtype) {
			case SVGMapElement.TYPE_HOST:
				elements_data = $('#elementNameHost').multiSelect('getData');

				if (elements_data.length != 0) {
					data.elements[0] = {
						hostid: elements_data[0].id,
						elementName: elements_data[0].name
					};
				}
				break;

			case SVGMapElement.TYPE_MAP:
				elements_data = $('#elementNameMap').multiSelect('getData');

				if (elements_data.length != 0) {
					data.elements[0] = {
						sysmapid: elements_data[0].id,
						elementName: elements_data[0].name
					};
				}
				break;

			case SVGMapElement.TYPE_TRIGGER:
				let i = 0;

				const triggers_list = document.querySelectorAll('input[name^="element_id"]');

				triggers_list.forEach((input) => {
					const triggerid = input.value,
						elementName = document.querySelector(`input[name^="element_name[${triggerid}]"]`).value,
						priority = document
							.querySelector(`input[name^="element_priority[${triggerid}]"]`).value;

					data.elements[i++] = {triggerid, elementName, priority};
				});
				break;

			case SVGMapElement.TYPE_HOST_GROUP:
				elements_data = $('#elementNameHostGroup').multiSelect('getData');

				if (elements_data.length != 0) {
					data.elements[0] = {
						groupid: elements_data[0].id,
						elementName: elements_data[0].name
					};
				}
				break;
		}

		// Validate URLs.
		for (const key in data.urls) {
			const {name, url} = data.urls[key];

			if (name === '' && url === '') {
				delete data.urls[key];
				continue;
			}

			if (name === '' || url === '') {
				alert(t('S_INCORRECT_ELEMENT_MAP_LINK'));

				return false;
			}

			if (url_names[name] !== undefined) {
				alert(t('S_EACH_URL_SHOULD_HAVE_UNIQUE') + " '" + name + "'.");

				return false;
			}

			url_names[name] = 1;
		}

		// Validate element ID.
		if ($.isEmptyObject(data.elements) && data.elementtype != SVGMapElement.TYPE_IMAGE) {
			const messages = {
				[SVGMapElement.TYPE_HOST]: t('Host is not selected.'),
				[SVGMapElement.TYPE_MAP]: t('Map is not selected.'),
				[SVGMapElement.TYPE_TRIGGER]: t('Trigger is not selected.'),
				[SVGMapElement.TYPE_HOST_GROUP]: t('Host group is not selected.')
			};

			alert(messages[+data.elementtype]);

			return false;
		}

		return data;
	}
}
