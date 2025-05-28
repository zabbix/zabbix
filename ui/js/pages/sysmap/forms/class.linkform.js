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
 * @param {object} form_container  jQuery object.
 * @param {object} sysmap
 */
class LinkForm {
	constructor(form_container, sysmap) {
		this.sysmap = sysmap;
		this.triggerids = {};
		this.item_type = null;
		this.domNode = $(new Template(document.getElementById('linkFormTpl').innerHTML).evaluate())
			.appendTo(form_container);

		document.getElementById('indicator_type').addEventListener('change', () => {
			this.#handleIndicatorTypeChange();
		});

		document.getElementById('link-thresholds-field').addEventListener('change', e => {
			const input = e.target.closest('.js-threshold-input');

			if (input !== null) {
				input.value = input.value.trim();
			}
		});

		const allowed_item_value_types = [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_TEXT, ITEM_VALUE_TYPE_LOG,
			ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_STR
		];

		$('#itemid')
			.multiSelectHelper({
				id: 'itemid',
				object_name: 'items',
				name: 'itemid',
				selectedLimit: 1,
				objectOptions: {
					filter: {
						value_type: allowed_item_value_types
					},
					real_hosts: true,
					resolve_macros: true
				},
				popup: {
					parameters: {
						srctbl: 'items',
						srcfld1: 'itemid',
						dstfrm: 'linkForm',
						dstfld1: 'itemid',
						value_types: allowed_item_value_types,
						real_hosts: 1,
						resolve_macros: 1
					}
				}
			})
			.on('change', () => this.#onMultiSelectChange());

		document.getElementById('threshold-add').addEventListener('click', () => this.#addNewThreshold());
		document.getElementById('highlight-add').addEventListener('click', () => this.#addNewHighlight());

		colorPalette.setThemeColors(LinkForm.DEFAULT_COLOR_PALETTE);
	}

	static DEFAULT_COLOR_PALETTE = [
		'E65660', 'FCCB1D', '3BC97D', '2ED3B7', '19D0D7', '29C2FA', '58B0FE', '5D98FE', '859AFA', 'E580FA',
		'F773C7', 'FC5F7E', 'FC738E', 'FF6D2E', 'F48D48', 'F89C3A', 'FBB318', 'FECF62', '87CE40', 'A3E86D'
	];

	/**
	 * Fetch type of item by itemid.
	 *
	 * @param {string|null} itemid
	 *
	 * @returns {Promise<any>}  Resolved promise will contain item type, or null in case of error or if no item is
	 *                          currently selected.
	 */
	static promiseGetItemType(itemid) {
		if (itemid === null) {
			return Promise.resolve(null);
		}

		const url_params = objectToSearchParams({
			method: 'item_value_type.get',
			type: PAGE_TYPE_TEXT_RETURN_JSON,
			itemid
		});

		const url = new URL(`jsrpc.php?${url_params}`, location.href);

		return fetch(url)
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				return parseInt(response.result);
			})
			.catch((exception) => {
				console.log('Could not get item type', exception);

				return null;
			});
	}

	/**
	 * @param {array} threshold_values
	 *
	 * @returns {Promise<any>}  Resolved promise will contain object with error or empty object if thresholds are
	 *                          correct.
	 */
	static promiseValidateThresholdValues(threshold_values) {
		const url_params = objectToSearchParams({
			method: 'link_thresholds.validate',
			type: PAGE_TYPE_TEXT_RETURN_JSON,
			thresholds: threshold_values
		});

		const url = new URL(`jsrpc.php?${url_params}`, location.href);

		return fetch(url)
			.then((response) => response.json())
			.then((response) => {
				if ('result' in response) {
					return response.result;
				}

				throw response;
			})
			.catch((exception) => {
				console.log('Could not validate link thresholds', exception);

				return {
					error: 'Could not validate link thresholds'
				};
			});
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
		document.querySelectorAll('.element-edit-control').forEach((element) => element.removeAttribute('disabled'));
		$('#itemid').multiSelect('addData', [], false);
	}

	/**
	 * Get form values for link fields.
	 */
	getValues() {
		return new Promise((resolve, reject) => {
			const values = $('#linkForm').serializeArray(),
				link_trigger_pattern = /^linktrigger_(\w+)_(triggerid|drawtype|color|desc_exp)$/,
				link_threshold_pattern = /^threshold_(\w+)_(linkid|drawtype|color|threshold)$/,
				link_highlights_pattern = /^highlight_(\w+)_(linkid|drawtype|color|pattern)$/;

			let data = {
					indicator_type: Link.INDICATOR_TYPE_STATIC_LINK,
					linktriggers: {},
					itemid: null,
					thresholds: {},
					highlights: {}
				},
				link_trigger,
				link_threshold,
				link_highlight;

			const ms_data = $('#itemid').multiSelect('getData');

			if (ms_data.length > 0) {
				this.sysmap.items[ms_data[0].id] = ms_data[0];
			}

			for (let i = 0, ln = values.length; i < ln; i++) {
				link_trigger = link_trigger_pattern.exec(values[i].name);
				link_threshold = link_threshold_pattern.exec(values[i].name);
				link_highlight = link_highlights_pattern.exec(values[i].name);

				if (link_highlight !== null) {
					if (data.highlights[link_highlight[1]] === undefined) {
						data.highlights[link_highlight[1]] = {};
					}

					if (link_highlight[2] === 'color' && !isColorHex(`#${values[i].value.toString()}`)) {
						throw sprintf(t('S_INCORRECT_VALUE'),
							`/highlights/${Object.keys(data.highlights).length}/color`,
							t('S_EXPECTING_COLOR_CODE')
						);
					}

					if (link_highlight[2] === 'pattern' && !values[i].value) {
						throw sprintf(t('S_INCORRECT_VALUE'),
							`/highlights/${Object.keys(data.highlights).length}/pattern`,
							t('S_CANNOT_BE_EMPTY')
						);
					}

					data.highlights[link_highlight[1]][link_highlight[2]] = values[i].value.toString();
				}
				else if (link_threshold !== null) {
					if (data.thresholds[link_threshold[1]] === undefined) {
						data.thresholds[link_threshold[1]] = {};
					}

					if (link_threshold[2] === 'color' && !isColorHex(`#${values[i].value.toString()}`)) {
						throw sprintf(t('S_INCORRECT_VALUE'),
							`/thresholds/${Object.keys(data.thresholds).length}/color`,
							t('S_EXPECTING_COLOR_CODE')
						);
					}

					if (link_threshold[2] === 'threshold') {
						if (!values[i].value) {
							throw sprintf(t('S_INCORRECT_VALUE'),
								`/thresholds/${Object.keys(data.thresholds).length}/threshold`,
								t('S_CANNOT_BE_EMPTY')
							);
						}
					}

					data.thresholds[link_threshold[1]][link_threshold[2]] = values[i].value.toString();
				}
				else if (link_trigger !== null) {
					if (data.linktriggers[link_trigger[1]] === undefined) {
						data.linktriggers[link_trigger[1]] = {};
					}

					if (link_trigger[2] === 'color' && !isColorHex(`#${values[i].value.toString()}`)) {
						throw sprintf(t('S_INCORRECT_VALUE'),
							`/linktriggers/${Object.keys(data.linktriggers).length}/color`,
							t('S_EXPECTING_COLOR_CODE')
						);
					}

					data.linktriggers[link_trigger[1]][link_trigger[2]] = values[i].value.toString();
				}
				else {
					if (values[i].name === 'color' && !isColorHex(`#${values[i].value.toString()}`)) {
						throw sprintf(t('S_INCORRECT_VALUE'), t('S_COLOR_OK'), t('S_EXPECTING_COLOR_CODE'));
					}

					data[values[i].name] = values[i].value.toString();
				}
			}

			if (data.indicator_type == Link.INDICATOR_TYPE_TRIGGER) {
				if (Object.keys(data.linktriggers).length == 0) {
					throw sprintf(t('S_INVALID_PARAMETER'), t('S_INDICATORS'), t('S_LINK_TRIGGER_IS_REQUIRED'));
				}
			}
			else if (data.indicator_type == Link.INDICATOR_TYPE_ITEM_VALUE) {
				if (data.itemid == null) {
					throw sprintf(t('S_INVALID_PARAMETER'), t('S_ITEM'), t('S_CANNOT_BE_EMPTY'));
				}

				if (this.item_type == ITEM_VALUE_TYPE_LOG || this.item_type == ITEM_VALUE_TYPE_TEXT
						|| this.item_type == ITEM_VALUE_TYPE_STR) {
					if (Object.keys(data.highlights).length == 0) {
						throw sprintf(t('S_INVALID_PARAMETER'), t('S_INDICATORS'), t('S_LINK_HIGHLIGHT_IS_REQUIRED'));
					}
				}
				else if (this.item_type == ITEM_VALUE_TYPE_FLOAT || this.item_type == ITEM_VALUE_TYPE_UINT64) {
					if (Object.keys(data.thresholds).length == 0) {
						throw sprintf(t('S_INVALID_PARAMETER'), t('S_INDICATORS'), t('S_LINK_THRESHOLD_IS_REQUIRED'));
					}

					this.#checkThresholds(data.thresholds)
						.then((response) => {
							if ('error' in response) {
								throw response.error;
							}

							resolve(data);
						})
						.catch((e) => reject(e));

					return;
				}
				else {
					throw sprintf(t('S_INVALID_PARAMETER'), t('S_ITEM'), t('S_INCORRECT_ITEM_VALUE_TYPE'));
				}
			}

			resolve(data);
		});
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
		Object.keys(link).forEach((name) => {
			if (name === 'itemid') {
				return;
			}

			const color_picker = this.domNode[2].querySelector(
				`.${ZBX_STYLE_COLOR_PICKER}[color-field-name="${name}"]`
			);

			if (color_picker !== null) {
				color_picker.color = link[name];
			}
			else {
				$(`[name=${name}]`, this.domNode).val([link[name]]);
			}
		});

		let item_data = [];

		if ('itemid' in link && link.itemid !== '' && link.itemid in this.sysmap.items) {
			item_data = [this.sysmap.items[link.itemid]];
		}

		$('#itemid').multiSelect('addData', item_data, false);

		this.#handleIndicatorTypeChange();

		// Clear triggers.
		this.triggerids = {};
		document.querySelectorAll('#linkTriggerscontainer tbody tr').forEach((tr) => tr.remove());
		this.#addLinkTriggers(link.linktriggers);

		// Clear thresholds.
		document.querySelectorAll('#link-thresholds-container tbody tr').forEach((tr) => tr.remove());
		this.#addLinkThresholds(link.thresholds);

		// Clear highlights.
		document.querySelectorAll('#link-highlights-container tbody tr').forEach((tr) => tr.remove());
		this.#addLinkHighlights(link.highlights);
	}

	/**
	 * Add link triggers to link form.
	 *
	 * @param {object} triggers
	 */
	#addLinkTriggers(triggers) {
		const tpl = new Template(document.getElementById('linkTriggerRow').innerHTML),
			$table = $('#linkTriggerscontainer tbody');

		Object.values(triggers).forEach((trigger, index) => {
			this.triggerids[trigger.triggerid] = index;
			trigger.index = index;
			$(tpl.evaluate(trigger)).appendTo($table);
			$(`#linktrigger_${index}_drawtype`).val(trigger.drawtype);
		});
	}

	/**
	 * Add link thresholds to link form.
	 *
	 * @param {object} thresholds
	 */
	#addLinkThresholds(thresholds) {
		const tpl = new Template(document.getElementById('threshold-row').innerHTML),
			$table = $('#link-thresholds-container tbody');

		Object.values(thresholds).forEach((threshold, index) => {
			threshold.index = index;
			$(tpl.evaluate(threshold)).appendTo($table);
			$(`#threshold_${index}_drawtype`).val(threshold.drawtype);
		});
	}

	/**
	 * Add link highlights to link form.
	 *
	 * @param {object} highlights
	 */
	#addLinkHighlights(highlights) {
		const tpl = new Template(document.getElementById('highlight-row').innerHTML),
			$table = $('#link-highlights-container tbody');

		Object.values(highlights).forEach((highlight, index) => {
			highlight.index = index;
			$(tpl.evaluate(highlight)).appendTo($table);
			$(`#highlight_${index}_drawtype`).val(highlight.drawtype);
		});
	}

	/**
	 * Add new triggers which were selected in popup to trigger list.
	 *
	 * @param {object} triggers
	 */
	addNewTriggers(triggers) {
		const tpl = new Template(document.getElementById('linkTriggerRow').innerHTML),
			$table = $('#linkTriggerscontainer tbody');

		for (const trigger of triggers) {
			if (this.triggerids[trigger.triggerid] !== undefined) {
				continue;
			}

			const index = getUniqueId();

			// Store triggerid to forbid selecting same trigger twice.
			this.triggerids[trigger.triggerid] = index;

			const link_trigger = {
				index,
				desc_exp: trigger.description,
				triggerid: trigger.triggerid,
				color: this.#getNextColor()
			};

			$(tpl.evaluate(link_trigger)).appendTo($table);
		}
	}

	/**
	 * Add new threshold.
	 */
	#addNewThreshold() {
		const tpl = new Template(document.getElementById('threshold-row').innerHTML),
			$table = $('#link-thresholds-container tbody');

		$(tpl.evaluate({
			index: getUniqueId(),
			color: this.#getNextColor()
		})).appendTo($table);
	}

	/**
	 * Add new highlight.
	 */
	#addNewHighlight() {
		const tpl = new Template(document.getElementById('highlight-row').innerHTML),
			$table = $('#link-highlights-container tbody');

		$(tpl.evaluate({
			index: getUniqueId(),
			color: this.#getNextColor(),
		})).appendTo($table);
	}

	/**
	 * Returns color picker next color.
	 *
	 * @returns {string}
	 */
	#getNextColor() {
		const color_pickers = this.domNode[2].querySelectorAll(`.${ZBX_STYLE_COLOR_PICKER}:not([disabled])`);
		const used_colors = [];

		for (const color_picker of color_pickers) {
			if (color_picker.color !== '') {
				used_colors.push(color_picker.color);
			}
		}

		return colorPalette.getNextColor(used_colors);
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

				let link_indicators = [];

				if (link.indicator_type == Link.INDICATOR_TYPE_TRIGGER) {
					for (const key in link.linktriggers) {
						link_indicators.push(link.linktriggers[key].desc_exp);
					}
				}
				else if (link.indicator_type == Link.INDICATOR_TYPE_ITEM_VALUE && link.itemid && link.itemid != 0) {
					const item = this.sysmap.items[link.itemid];

					link_indicators.push(`${item.prefix}${item.name}`);
				}

				const from_element_name = this.sysmap.selements[link.selementid1].getName(),
					to_element_name = this.sysmap.selements[link.selementid2].getName();

				list.push({from_element_name, to_element_name, linkid: link.linkid, link_indicators});
			});

			// Sort by "From" element, then by "To" element and then by "linkid".
			list.sort((a, b) => a.from_element_name.toLowerCase().localeCompare(b.from_element_name.toLowerCase())
					|| a.to_element_name.toLowerCase().localeCompare(b.to_element_name.toLowerCase())
					|| a.linkid.localeCompare(b.linkid)
			);

			list.forEach((item) => {
				const row = $(row_tpl.evaluate(item)),
					row_urls = $('.element-urls', row);

				item.link_indicators.forEach((indicator, index) => {
					if (index != 0) {
						row_urls.append($('<br>'));
					}

					row_urls.append($('<span>').text(indicator));
				});

				row.appendTo($link_table.find('tbody'));
			});

			$link_table.closest('.element-links').show();
		}
		else {
			$('#mapLinksContainer').hide();
		}
	}

	#handleIndicatorTypeChange() {
		const indicator_type = document.getElementById('indicator-type-field')
			.querySelector('[name=indicator_type]:checked').value;

		if (indicator_type == Link.INDICATOR_TYPE_ITEM_VALUE) {
			this.#onMultiSelectChange(this);
		}
		else {
			this.#toggleItemValueRelatedObjects();
		}

		const item_value_row = document.getElementById('item-value-field');

		item_value_row.style.display = indicator_type == Link.INDICATOR_TYPE_ITEM_VALUE ? '' : 'none';

		for (const input of item_value_row.querySelectorAll('input')) {
			if (indicator_type == Link.INDICATOR_TYPE_ITEM_VALUE) {
				input.removeAttribute('disabled');
			}
			else {
				input.setAttribute('disabled', 'disabled');
			}
		}

		const link_indicators_field = document.getElementById('link-indicators-field');

		link_indicators_field.style.display = indicator_type == Link.INDICATOR_TYPE_TRIGGER ? '' : 'none';

		for (const input of link_indicators_field.querySelectorAll('input')) {
			if (indicator_type == Link.INDICATOR_TYPE_TRIGGER) {
				input.removeAttribute('disabled');
			}
			else {
				input.setAttribute('disabled', 'disabled');
			}
		}
	}

	#onMultiSelectChange() {
		const ms_item_data = $('#itemid').multiSelect('getData');

		this.item_type = null;
		this.#toggleItemValueRelatedObjects();

		if (ms_item_data.length > 0) {
			const map_window = document.getElementById('map-window');

			map_window.classList.add('is-loading', 'is-loading-fadein');

			this.constructor.promiseGetItemType(ms_item_data[0].id)
				.then((type) => {
					this.item_type = type;
					this.#toggleItemValueRelatedObjects(this.item_type);
				})
				.finally(() => map_window.classList.remove('is-loading', 'is-loading-fadein'));
		}
	}

	#toggleItemValueRelatedObjects(type = null) {
		const is_numeric = type == ITEM_VALUE_TYPE_FLOAT || type == ITEM_VALUE_TYPE_UINT64,
			is_text = type == ITEM_VALUE_TYPE_STR || type == ITEM_VALUE_TYPE_LOG || type == ITEM_VALUE_TYPE_TEXT,
			thresholds_field = document.getElementById('link-thresholds-field'),
			highlights_field = document.getElementById('link-highlights-field');

		thresholds_field.style.display = is_numeric ? '' : 'none';

		for (const input of thresholds_field.querySelectorAll('input')) {
			if (is_numeric) {
				input.removeAttribute('disabled');
			}
			else {
				input.setAttribute('disabled', 'disabled');
			}
		}

		highlights_field.style.display = is_text ? '' : 'none';

		for (const input of highlights_field.querySelectorAll('input')) {
			if (is_text) {
				input.removeAttribute('disabled');
			}
			else {
				input.setAttribute('disabled', 'disabled');
			}
		}
	}

	#checkThresholds(link_thresholds) {
		const threshold_values = Object.values(link_thresholds).map((link_threshold) => {
			return {
				threshold: link_threshold.threshold
			};
		});

		return this.constructor.promiseValidateThresholdValues(threshold_values);
	}
}
