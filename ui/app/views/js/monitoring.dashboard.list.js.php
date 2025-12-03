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
		#csrf_token;

		init({csrf_token}) {
			this.#csrf_token = csrf_token;
			this.#initEvents();
		}

		#initEvents () {
			document.getElementById('dashboard_import').addEventListener('click', () => this.#import());
		}

		#import() {
			return PopUp('popup.import',
				{
					rules_preset: 'dashboard',
					[CSRF_TOKEN_NAME]: this.#csrf_token
				},
				{
					dialogueid: 'popup_import',
					dialogue_class: 'modal-popup-generic'
				}
			)
		}
	};
</script>
