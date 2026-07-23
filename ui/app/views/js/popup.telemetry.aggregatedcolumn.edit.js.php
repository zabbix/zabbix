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
		this.overlay.setLoading();
		this.#clearMessages();

		const fields = this.form.getAllValues();

		this.form.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.overlay.unsetLoading();

					return;
				}

				const curl = new Curl('zabbix.php');

				curl.setArgument('action', 'popup.telemetry.aggregatedcolumn.check');

				fetch(curl.getUrl(), {
					method: 'POST',
					headers: {'Content-Type': 'application/json'},
					body: JSON.stringify(fields)
				})
					.then((response) => response.json())
					.then((response) => {
						if ('error' in response) {
							throw {error: response.error};
						}

						if ('form_errors' in response) {
							this.form.setErrors(response.form_errors, true, true);
							this.form.renderErrors();

							return;
						}

						overlayDialogueDestroy(this.overlay.dialogueid);
						this.dialogue.dispatchEvent(
							new CustomEvent('telemetry_aggregated_column.submit', {detail: response})
						);
					})
					.catch((exception) => this.#ajaxExceptionHandler(exception))
					.finally(() => this.overlay.unsetLoading());
			});
	}

	#clearMessages() {
		for (const element of this.form_element.parentNode.children) {
			if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
				element.parentNode.removeChild(element);
			}
		}
	}

	#ajaxExceptionHandler(exception) {
		let title, messages;

		if (typeof exception === 'object' && 'error' in exception) {
			title = exception.error.title;
			messages = exception.error.messages;
		}
		else {
			messages = [<?= json_encode(_('Unexpected server error.')) ?>];
		}

		this.form_element.parentNode.insertBefore(makeMessageBox('bad', messages, title)[0], this.form_element);
	}
}
