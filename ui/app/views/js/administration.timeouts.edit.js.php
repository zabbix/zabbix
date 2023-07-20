<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
	const view = new class {

		/** @type {HTMLFormElement} */
		#form;

		/** @type {Object} */
		#default_timeouts = {};

		init({default_timeouts}) {
			this.#form = document.getElementById('timeouts');
			this.#default_timeouts = default_timeouts;

			document.getElementById('reset-defaults').addEventListener('click', (e) => this.#resetDefaults(e.target));
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
