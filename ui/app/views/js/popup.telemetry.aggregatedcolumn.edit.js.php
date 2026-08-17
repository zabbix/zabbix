<?php declare(strict_types = 0);
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
?>


window.telemetry_aggregated_column_popup = new class {

	#overlay;
	#dialogue;
	#form_element;
	#form;

	init({rules}) {
		this.#overlay = overlays_stack.end();
		this.#dialogue = this.#overlay.$dialogue[0];
		this.#form_element = this.#overlay.$dialogue.$body[0].querySelector('form');
		this.#form = new CForm(this.#form_element, rules);

		this.#form.findFieldByName('function').getField()
			.addEventListener('change', () => this.#updateFieldVisibility());

		this.#updateFieldVisibility();

		this.#form.validateChanges(['column'], true);
	}

	#updateFieldVisibility() {
		const func = this.#form.findFieldByName('function').getValue();
		const is_count = func === '<?= AGGREGATE_COUNT ?>';
		const is_percentile = func === '<?= AGGREGATE_PCTILE ?>';

		this.#form_element.querySelector('#js-column-field').style.display = is_count ? 'none' : '';
		this.#form_element.querySelector('#js-column-label').style.display = is_count ? 'none' : '';
		this.#form_element.querySelector('#js-percentile-field').style.display = is_percentile ? '' : 'none';
		this.#form_element.querySelector('#js-percentile-label').style.display = is_percentile ? '' : 'none';
	}

	submit() {
		const fields = this.#form.getAllValues();

		this.#form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.#overlay.unsetLoading();

					return;
				}

				const func = parseInt(fields.function, 10);

				overlayDialogueDestroy(this.#overlay.dialogueid);
				this.#dialogue.dispatchEvent(new CustomEvent('telemetry_aggregated_column.submit', {
					detail: {
						row_index: fields.row_index,
						column: func === <?= AGGREGATE_COUNT ?> ? '' : fields.column,
						function: func,
						percentile: func === <?= AGGREGATE_PCTILE ?> ? fields.percentile : '',
						alias: fields.alias
					}
				}));
			});
	}
}
