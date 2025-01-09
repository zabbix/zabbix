<?php declare(strict_types = 0);
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
 * @var CView $this
 */
?>

window.correlation_condition_popup = new class {

	init() {
		this.overlay = overlays_stack.getById('correlation-condition-form');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');

		this.form.querySelector('#condition-type')
			.onchange = () => reloadPopup(this.form, 'correlation.condition.edit');

		const $event_ms = $('#groupids_');

		$event_ms.on('change', () => {
			$event_ms.multiSelect('setDisabledEntries',
				[...this.form.querySelectorAll('[name^="groupids[]"]')].map((input) => input.value)
			);
		});
	}

	submit() {
		const fields = getFormFields(this.form);

		switch (parseInt(fields.conditiontype)) {
			case <?= ZBX_CORR_CONDITION_OLD_EVENT_TAG ?>:
			case <?= ZBX_CORR_CONDITION_NEW_EVENT_TAG ?>:
				fields.tag = fields.tag.trim();
				break;

			case <?= ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE ?>:
			case <?= ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE ?>:
				fields.tag = fields.tag.trim();
				fields.value = fields.value.trim();
				break;

			case <?= ZBX_CORR_CONDITION_EVENT_TAG_PAIR ?>:
				fields.oldtag = fields.oldtag.trim();
				fields.newtag = fields.newtag.trim();
				break;
		}

		const curl = new Curl('zabbix.php');

		curl.setArgument('action', 'correlation.condition.check');

		this.#post(curl.getUrl(), fields);
	}

	/**
	 * Sends a POST request to the specified URL with the provided data.
	 *
	 * @param {string} url   The URL to send the POST request to.
	 * @param {object} data  The data to send with the POST request.
	 */
	#post(url, data) {
		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}
				overlayDialogueDestroy(this.overlay.dialogueid);

				document.dispatchEvent(new CustomEvent('condition.dialogue.submit', {detail: response}));
				this.dialogue.dispatchEvent(new CustomEvent('condition.dialogue.submit', {detail: response}));
			})
			.catch((exception) => {
				for (const element of this.form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title,
					messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title)[0];

				this.form.parentNode.insertBefore(message_box, this.form);
			})
			.finally(() => this.overlay.unsetLoading());
	}
}
