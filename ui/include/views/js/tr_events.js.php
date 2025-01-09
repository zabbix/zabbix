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
			$.subscribe("acknowledge.create", function(event, response) {
				postMessageOk(response.success.title);
				location.href = location.href;
			});

			this.#setSubmitCallback();
		}

		#setSubmitCallback() {
			window.popupManagerInstance.setSubmitCallback((e) => {
				let new_href = location.href;
				const data = e.detail;

				if ('success' in data) {
					postMessageOk(data.success.title);

					if ('messages' in data.success) {
						postMessageDetails('success', data.success.messages);
					}

					if (data.success.action === 'delete') {
						// If item or trigger is deleted redirect to problems page.
						let list_url = new Curl('zabbix.php');

						list_url.setArgument('action', 'problem.view');
						new_href = list_url.getUrl();
					}
				}

				location.href = new_href;
			});
		}
	};
</script>
