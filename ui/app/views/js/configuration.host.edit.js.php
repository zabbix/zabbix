<?php declare(strict_types = 1);
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

<script>
	const view = {
		form: null,

		init({form_name, host_interfaces, host_is_discovered}) {
			this.form = document.getElementById(form_name);
			this.form.addEventListener('submit', this.events.submit);

			host_edit.init({form_name, host_interfaces, host_is_discovered});
		},

		submit(button) {
			this.setLoading(button);

			const fields = host_edit.preprocessFormFields(getFormFields(this.form));
			const curl = new Curl(this.form.getAttribute('action'), false);

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body: urlEncodeData(fields)
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						throw {error: response.error};
					}

					postMessageOk(response.success.title);

					if ('messages' in response.success) {
						postMessageDetails('success', response.success.messages);
					}

					const url = new Curl('zabbix.php', false);
					url.setArgument('action', 'host.list');

					location.href = url.getUrl();
				})
				.catch(this.ajaxExceptionHandler)
				.finally(() => {
					this.unsetLoading();
				});
		},

		clone() {
			const url = new Curl('', false);
			url.setArgument('clone', 1);

			const fields = host_edit.preprocessFormFields(getFormFields(this.form));
			delete fields.sid;

			post(url.getUrl(), fields);
		},

		fullClone() {
			const url = new Curl('', false);
			url.setArgument('full_clone', 1);

			const fields = host_edit.preprocessFormFields(getFormFields(this.form));
			delete fields.sid;

			post(url.getUrl(), fields);
		},

		delete(hostid, button) {
			const confirm_text = button.getAttribute('confirm');
			if (!confirm(confirm_text)) {
				return;
			}

			this.setLoading(button);

			const curl = new Curl('zabbix.php');
			curl.setArgument('action', 'host.massdelete');

			fetch(curl.getUrl(), {
				method: 'POST',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body: urlEncodeData({hostids: [hostid]})
			})
				.then((response) => response.json())
				.then((response) => {
					if ('error' in response) {
						throw {error: response.error};
					}

					postMessageOk(response.success.title);

					if ('messages' in response.success) {
						postMessageDetails('success', response.success.messages);
					}

					const url = new Curl('zabbix.php', false);
					url.setArgument('action', 'host.list');

					location.href = url.getUrl();
				})
				.catch(this.ajaxExceptionHandler)
				.finally(() => {
					this.unsetLoading();
				});
		},

		setLoading(active_button) {
			active_button.classList.add('is-loading');

			const footer = this.form.querySelector('.tfoot-buttons');

			for (const button of footer.querySelectorAll('button:not(.js-cancel)')) {
				button.disabled = true;
			}
		},

		unsetLoading() {
			const footer = this.form.querySelector('.tfoot-buttons');

			for (const button of footer.querySelectorAll('button:not(.js-cancel)')) {
				button.classList.remove('is-loading');
				button.disabled = false;
			}
		},

		ajaxExceptionHandler: (exception) => {
			let title;
			let messages = [];

			if (typeof exception === 'object' && 'error' in exception) {
				title = exception.error.title;
				messages = exception.error.messages;
			} else {
				title = <?= json_encode(_('Unexpected server error.')) ?>;
			}

			const message_box = makeMessageBox('bad', messages, title, true, true)[0];

			clearMessages();
			addMessage(message_box);
		},

		events: {
			submit(event) {
				event.preventDefault();
				const submit_button = view.form.querySelector('.tfoot-buttons button[type="submit"]');

				view.submit(submit_button);
			}
		}
	};
</script>
