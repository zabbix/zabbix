<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
 * @var CView $this
 */
?>

window.update_problem_popup = new class {
	init() {
		this.problem_suppressible = !document.getElementById('suppress_problem').disabled;
		this.problem_unsuppressible = !document.getElementById('unsuppress_problem').disabled;

		document
			.getElementById('suppress_problem')
			.addEventListener('change', () => {
				this._update();
			});

		document
			.getElementById('suppress_time_option')
			.addEventListener('change', () => {
				this._update();
			});

		document
			.getElementById('unsuppress_problem')
			.addEventListener('change', () => {
				this._update();
			});

		document
			.getElementById('close_problem')
			.addEventListener('change', () => {
				this._update();
			});
	}

	_update() {
		const suppress_checked = document.getElementById('suppress_problem').checked;
		const unsuppress_checked = document.getElementById('unsuppress_problem').checked;
		const close_problem_checked = document.getElementById('close_problem').checked;

		this._update_suppress_problem_state(close_problem_checked || unsuppress_checked);
		this._update_unsuppress_problem_state(close_problem_checked || suppress_checked);

		this._update_suppress_time_options();
	}

	_update_suppress_problem_state(state) {
		if (this.problem_suppressible) {
			document.getElementById('suppress_problem').disabled = state;
			if (state) {
				document.getElementById('suppress_problem').checked = false;
			}
		}
	}

	_update_unsuppress_problem_state(state) {
		if (this.problem_unsuppressible) {
			document.getElementById('unsuppress_problem').disabled = state;
			if (state) {
				document.getElementById('unsuppress_problem').checked = false;
			}
		}
	}

	_update_suppress_time_options() {

		for (const element of document.querySelectorAll('#suppress_time_option input[type="radio"]')) {
			element.disabled = !document.getElementById('suppress_problem').checked;

			document.getElementById('suppress_until_problem').disabled = element.disabled;
			document.getElementById('suppress_until_problem_calendar').disabled = element.disabled;
		}

		const time_option_checked = document.querySelector('#suppress_time_option input:checked').value;
		if (time_option_checked == <?= ZBX_PROBLEM_SUPPRESS_TIME_INDEFINITE ?>) {
			document.getElementById('suppress_until_problem').disabled = true;
			document.getElementById('suppress_until_problem_calendar').disabled = true;
		}
	}

	/**
	 * @param {Overlay} overlay
	 */
	submitAcknowledge(overlay) {
		var $form = overlay.$dialogue.find('form'),
			url = new Curl('zabbix.php', false),
			form_data;

		$form.trimValues(['#message', '#suppress_until_problem']);
		form_data = jQuery('#message, input:visible, input[type=hidden]', $form).serialize();
		url.setArgument('action', 'popup.acknowledge.create');

		overlay.xhr = sendAjaxData(url.getUrl(), {
			data: form_data,
			dataType: 'json',
			method: 'POST',
			beforeSend: function() {
				overlay.setLoading();
			},
			complete: function() {
				overlay.unsetLoading();
			}
		})
		.done(function(response) {
			overlay.$dialogue.find('.msg-bad').remove();

			if ('error' in response) {
				const message_box = makeMessageBox('bad', response.error.messages,
					response.error.title
				);

				message_box.insertBefore($form);
			}
			else {
				overlayDialogueDestroy(overlay.dialogueid);
				$.publish('acknowledge.create', [response, overlay]);
			}
		});
	}
};
