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
	init({serverid, export_file_name, export_data}) {
		this.serverid = serverid;
		this.export_file_name = export_file_name;
		this.export_data = export_data;

		this.#initEvents();
	}

	#initEvents() {
		document.querySelector('.js-copy-button')?.addEventListener('click', (e) => {
			writeTextClipboard(this.serverid);
			e.target.focus();
		});

		document.querySelector('.js-export-system-information').addEventListener('click', (e) => {
			e.preventDefault();

			const data_url = URL.createObjectURL(new Blob([this.export_data], {type: 'application/json'}));
			const a = Object.assign(document.createElement('a'), {download: this.export_file_name, href: data_url});

			a.click();
			requestAnimationFrame(() => URL.revokeObjectURL(data_url));
		});
	}
};
</script>
