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

	init({rules}) {
		this.overlay = overlays_stack.end();
		this.dialogue = this.overlay.$dialogue[0];
		this.form_element = this.overlay.$dialogue.$body[0].querySelector('form');
		this.form = new CForm(this.form_element, rules);

		this.function = this.form_element.querySelector('#function');
		this.function.addEventListener('change', () => this.#updateFieldVisibility());

		this.#updateFieldVisibility();

		this.form.validateChanges(['column'], true);
	}

	#updateFieldVisibility() {
		const func = parseInt(this.function.value, 10);
		const is_count = func === <?= AGGREGATE_COUNT ?>;
		const is_percentile = func === <?= AGGREGATE_PCTILE ?>;
		const display_none = <?= json_encode(ZBX_STYLE_DISPLAY_NONE) ?>;

		this.form_element.querySelector('#js-column-field').classList.toggle(display_none, is_count);
		this.form_element.querySelector('#js-column-label').classList.toggle(display_none, is_count);
		this.form_element.querySelector('#js-percentile-field').classList.toggle(display_none, !is_percentile);
		this.form_element.querySelector('#js-percentile-label').classList.toggle(display_none, !is_percentile);
	}

	submit() {
		const fields = this.form.getAllValues();

		this.form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.overlay.unsetLoading();

					return;
				}

				const func = parseInt(fields.function, 10);

				overlayDialogueDestroy(this.overlay.dialogueid);
				this.dialogue.dispatchEvent(new CustomEvent('telemetry_aggregated_column.submit', {
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
