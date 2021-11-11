/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
 * Excludes "used" multiselect enteries from its auto-suggest, Select-button popup.
 */
class CMultiselectEntryExcluder {
	ms = null;
	$ms = null;
	input_selector = [];

	/**
	 * Set up listeners for multiselect instance that updates suggested items.
	 *
	 * @param {string} id      DOM id of the multiselct element.
	 * @param {array}  inputs  DOM names of input(s) that contain values linked, to add, to clear - to be excluded.
	 */
	constructor(id, inputs) {
		this.ms = document.getElementById(id);
		this.$ms = $(this.ms);

		this.updateDisabledTemplates = function() {
			const templateids = this.getEntryIds();
			const ms_params = this.$ms.data('multiSelect');

			const link = new Curl(ms_params.options.url, false);
			link.setArgument('disabledids', templateids);

			ms_params.options.url = link.getUrl();
			ms_params.options.popup.parameters.disableids = templateids;

			this.$ms.data('multiSelect', ms_params);
		}

		this.ms.addEventListener('multiselect.item.removed', (e) => {
			this.updateDisabledTemplates();
		});

		this.ms.addEventListener('multiselect.item.added', (e) => {
			this.updateDisabledTemplates();
		});

		this.input_selector = inputs.map((name) => {
			return '[name^="'+name+'["]';
		}).join(', ');
	}

	/**
	 * Helper to get all multiselect entry (e.g. linked, added, to clear templates) IDs as an array.
	 *
	 * @return {array}  Templateids.
	 */
	getEntryIds() {
		const templateids = [];

		this.ms.closest('form')
			.querySelectorAll(this.input_selector)
				.forEach((input) => {
					templateids.push(input.value);
				});

		return templateids;
	}
}
