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

<script>
	const view = {
		init({context, checkbox_hash}) {
			this.context = context;
			this.checkbox_hash = checkbox_hash;

			this.setSubmitCallback();
		},

		setSubmitCallback() {
			window.popupManagerInstance.setSubmitCallback((e) => {
				const data = e.detail;
				let curl = null;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}

					if ('action' in data.success && data.success.action === 'delete') {
						curl = new Curl('host_discovery.php');
						curl.setArgument('context', context);
					}
				}

				uncheckTableRows('host_prototypes_' + this.checkbox_hash, [] ,false);

				if (curl) {
					location.href = curl.getUrl();
				}
				else {
					location.href = location.href;
				}
			});
		}
	};
</script>
