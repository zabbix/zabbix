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
		init({export_file_name, export_payload}) {
			this.export_file_name = export_file_name;
			this.export_payload = export_payload;

			this.#initListeners();
		}

		#initListeners() {
			document.querySelector('.js-copy-button')?.addEventListener('click', (e) => {
				writeTextClipboard(document.querySelector('.js-serverid').textContent);

				e.target.focus();
			});

			document.querySelector('.js-export-system-information').addEventListener('click', (e) => {
				e.preventDefault();

				const a = document.createElement('a');

				a.rel = 'noopener noreferrer';
				a.download = this.export_file_name;
				a.target = '_blank';
				a.href = URL.createObjectURL(new Blob([this.export_payload], {type: 'text/json'}));

				a.click();
			})
		}
	};
</script>
