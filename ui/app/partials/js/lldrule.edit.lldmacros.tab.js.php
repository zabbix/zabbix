<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
 * @var CPartial $this
 */
?>

var LldRuleEditLldMacrosTab = class {
	#container;

	constructor({container, lld_macro_paths}) {
		this.#container = container;

		if (lld_macro_paths.length == 0) {
			lld_macro_paths.push({});
		}

		$(this.#container.querySelector('.js-lld-macro-paths'))
			.dynamicRows({
				template: this.#container.querySelector('.js-lld-macro-paths-template'),
				rows: lld_macro_paths,
				allow_empty: true
			})
			.on('click', 'button.element-table-add', () => {
				this.#initMacroFields();
			})

		this.#initMacroFields();
	}

	#initMacroFields() {
		this.#container.querySelectorAll('.js-macro:not(.initialized-field)').forEach(textarea => {
			const $textarea = $(textarea);

			$textarea.on('change keydown', (e) => {
				if (e.type === 'change' || e.which === 13) {
					$(textarea).val($(textarea).val().toUpperCase());
				}
			});
		});
	}
};
