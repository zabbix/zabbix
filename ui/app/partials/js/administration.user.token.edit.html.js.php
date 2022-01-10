<?php declare(strict_types=1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
	'use strict';

	window.user_token_edit = {
		form_name: null,
		form: null,
		expires_at_row: null,
		expires_at_label: null,
		expires_at: null,
		expires_state: null,

		/**
		 * User token form setup.
		 */
		init({form_name}) {
			this.form_name = form_name;
			this.form = document.getElementById(form_name);
			this.expires_at_row = document.getElementById('expires-at-row');
			this.expires_at_label = this.expires_at_row.previousSibling;
			this.expires_at = document.getElementById('expires_at');
			this.expires_state = document.getElementById('expires_state');
			this.showHide();
		},

		trimFields(fields) {
			const fields_to_trim = ['name', 'description'];
			for (const field of fields_to_trim) {
				if (field in fields) {
					fields[field] = fields[field].trim();
				}
			}
			return fields;
		},

		showHide() {
			if (this.expires_state.checked == false) {
				let expires_state_hidden = document.createElement('input');
				expires_state_hidden.setAttribute('type', 'hidden');
				expires_state_hidden.setAttribute('name', 'expires_state');
				expires_state_hidden.setAttribute('value', '0');
				expires_state_hidden.setAttribute('id', 'expires_state_hidden');
				this.expires_state.append(expires_state_hidden);

				this.expires_at_row.style.display = 'none';
				this.expires_at_label.style.display = 'none';
				this.expires_at.disabled = true;
			}
			else {
				this.expires_at_row.style.display = "";
				this.expires_at_label.style.display = "";
				this.expires_at.disabled = false;
				let expires_state_hidden = document.getElementById('expires_state_hidden');
				if (expires_state_hidden) {
					expires_state_hidden.parentNode.removeChild(expires_state_hidden);
				}
			}
		}
	}
</script>
