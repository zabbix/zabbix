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
 * @var CView $this
 */
?>

window.scheduled_report_subscription_edit = new class {
	init({rules}) {
		this.form_element = document.getElementById('subscription-form');
		this.form = new CForm(this.form_element, rules);
		this.overlay = overlays_stack.getById('scheduledreport.subscription.edit');
		this.#initActions();
	}

	#initActions() {
		this.overlay.$dialogue.$footer[0].querySelector('.js-submit').addEventListener('click', () => this.#submit());
	}

	#submit() {
		const recipient = $('#recipientid').multiSelect('getData');

		if (recipient.length) {
			document.getElementById('recipient_name').value = recipient[0]['name'];
		}

		const fields = this.form.getAllValues();

		fields.recipientid = fields.recipientid === undefined ? null : fields.recipientid;

		this.overlay.setLoading();
		this.form
			.validateSubmit(fields)
			.then((result) => {
				if (!result) {
					this.overlay.unsetLoading();
					return;
				}

				this.#post(fields, this.overlay);
			});
	}

	#post(data) {
		const url = new Curl(this.form_element.getAttribute('action'));
		url.setArgument('action', 'popup.scheduledreport.subscription.check');

		fetch(url.getUrl(), {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('form_errors' in response) {
					this.form.setErrors(response.form_errors, true, true);
					this.form.renderErrors();
					return;
				}
				else if ('error' in response) {
					throw {error: response.error};
				}

				new ReportSubscription(response, response.edit ? this.overlay.element.closest('tr') : null);

				overlayDialogueDestroy(this.overlay.dialogueid);
			})
			.catch((exception) => {
				this.overlay.$dialogue.find('.<?= ZBX_STYLE_MSG_BAD ?>').remove();

				let title, messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [t('Unexpected server error.')];
				}

				const message_box = makeMessageBox('bad', messages, title);

				message_box.insertBefore(this.form_element);
			})
			.finally(() => this.overlay.unsetLoading());
	}
};
