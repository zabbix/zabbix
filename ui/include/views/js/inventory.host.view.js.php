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

		init() {
			this.setSubmitCallback();
		}

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
						curl = new Curl('hostinventories.php').getUrl();
					}
				}

				location.href = curl === null ? location.href : curl;
			});
		}
	};
</script>
