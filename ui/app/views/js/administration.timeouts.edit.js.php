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

<script>
	const view = new class {

		/** @type {HTMLFormElement} */
		#form;

		/** @type {Object} */
		#default_timeouts = {};

		init({default_timeouts}) {
			this.#form = document.getElementById('timeouts-form');
			this.#default_timeouts = default_timeouts;

			this.#form.addEventListener('submit', (e) => this.#submit(e));

			document.getElementById('reset-defaults').addEventListener('click', (e) => this.#resetDefaults(e.target));
		}

		#submit(event) {
			event.preventDefault();

			const fields_to_trim = ['timeout_zabbix_agent', 'timeout_simple_check', 'timeout_snmp_agent',
				'timeout_external_check', 'timeout_db_monitor', 'timeout_http_agent', 'timeout_ssh_agent',
				'timeout_telnet_agent', 'timeout_script', 'timeout_browser', 'socket_timeout', 'connect_timeout',
				'media_type_test_timeout', 'script_timeout', 'item_test_timeout', 'report_test_timeout'
			];

			for (const id of fields_to_trim) {
				const field = document.getElementById(id);

				field.value = field.value.trim();
			}

			this.#form.submit();
		}

		#resetDefaults(reset_button) {
			overlayDialogue({
				'title': <?= json_encode(_('Reset confirmation')) ?>,
				'class': 'position-middle',
				'content': document.createElement('span').innerText = <?= json_encode(
					_('Reset all fields to default values?')
				) ?>,
				'buttons': [
					{
						'title': <?= json_encode(_('Cancel')) ?>,
						'cancel': true,
						'class': '<?= ZBX_STYLE_BTN_ALT ?>',
						'action': () => {}
					},
					{
						'title': <?= json_encode(_('Reset defaults')) ?>,
						'focused': true,
						'action': () => {
							for (const element of document.querySelectorAll('.wrapper > output[role="contentinfo"]')) {
								if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
									element.parentNode.removeChild(element);
								}
							}

							for (const [timeout, default_value] of Object.entries(this.#default_timeouts)) {
								const element = document.getElementById(timeout);

								if (element !== null) {
									element.value = default_value;
								}
							}
						}
					}
				]
			}, reset_button);
		}
	};
</script>
