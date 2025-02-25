<?php
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

<script type="text/javascript">
	const view = new class {
		init({userid}) {
			this.userid = userid;

			document.getElementById('user-form').addEventListener('submit', (e) => {
				document.querySelectorAll('#username, #name, #surname, #autologout, #refresh, #url').forEach((elem) => {
					elem.value = elem.value.trim();
				});

				if (!this.#confirmSubmit()) {
					e.preventDefault();
				}
			});

			const roleid_elem = document.getElementById('roleid');
			new MutationObserver(() => {
				if (roleid_elem.querySelectorAll('[name="roleid"]').length > 0) {
					document.getElementById('user-form').submit();
				}
			}).observe(roleid_elem, {childList: true});

			this.#changeVisibilityAutoLoginLogout();
			this.#autologoutHandler();
		}

		#confirmSubmit() {
			const elem_password1 = document.getElementById('password1');
			const elem_password2 = document.getElementById('password2');

			if (elem_password1 && elem_password2) {
				const password1 = elem_password1.value;
				const password2 = elem_password2.value;

				if (this.userid !== null && password1 !== '' && password2 !== '') {
					const warning_msg = <?= json_encode(
						_('In case of successful password change user will be logged out of all active sessions. Continue?')
					) ?>;

					return confirm(warning_msg);
				}
			}

			return true;
		}

		#changeVisibilityAutoLoginLogout() {
			const autologin_cbx = document.querySelector('#autologin');
			const autologout_cbx = document.querySelector('#autologout_visible');

			if (autologin_cbx === null || autologout_cbx === null) {
				return;
			}

			autologin_cbx.addEventListener('click', (e) => {
				if (e.target.checked) {
					autologout_cbx.checked = false;
				}
				this.#autologoutHandler();
			});

			autologout_cbx.addEventListener('click', (e) => {
				if (e.target.checked) {
					autologin_cbx.checked = false;
				}
				this.#autologoutHandler();
			});
		}

		#autologoutHandler() {
			const autologout = document.querySelector('#autologout');

			if (autologout === null) {
				return;
			}

			const autologout_visible = document.querySelector('#autologout_visible');
			const disabled = !autologout_visible.checked;
			const hidden = autologout.parentElement.
				querySelector(`input[type=hidden][name=${autologout.getAttribute('name')}]`);

			if (disabled) {
				autologout.setAttribute('disabled', '')
			}
			else {
				autologout.removeAttribute('disabled')
			}

			if (!hidden) {
				const hidden_input = document.createElement('input');

				hidden_input.type = 'hidden';
				hidden_input.name = autologout.getAttribute('name');
				hidden_input.value = '0';

				autologout.parentElement.insertBefore(hidden_input, autologout);
			}
			else if (!disabled) {
				hidden.remove();
			}
		}
	}
</script>
