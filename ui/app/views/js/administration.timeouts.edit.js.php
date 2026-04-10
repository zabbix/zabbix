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

			for (const id of Object.keys(this.#default_timeouts)) {
				const field = document.getElementById(id);

				if (field !== null) {
					field.value = field.value.trim();
				}
			}

			this.#form.submit();
		}

		#resetDefaults(reset_button) {
			overlayDialogue({
				title: <?= json_encode(_('Reset confirmation')) ?>,
				content: document.createElement('span').innerText = <?= json_encode(
					_('Reset all fields to default values?')
				) ?>,
				buttons: [
					{
						title: <?= json_encode(_('Cancel')) ?>,
						cancel: true,
						class: '<?= ZBX_STYLE_BTN_ALT ?>',
						action: () => {}
					},
					{
						title: <?= json_encode(_('Reset defaults')) ?>,
						focused: true,
						action: () => {
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
			}, {
				position: Overlay.prototype.POSITION_CENTER,
				trigger_element: reset_button
			});
		}
	};
</script>
