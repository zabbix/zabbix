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

		init({action, action_parameters}) {
			ZABBIX.EventHub.subscribe({
				require: {
					context: CPopupManager.EVENT_CONTEXT,
					event: CPopupManagerEvent.EVENT_SUBMIT,
					action: 'host.wizard.edit'
				},
				callback: ({data, event}) => {
					if (data.submit.redirect_latest) {
						const url = new URL('zabbix.php', location.href);

						url.searchParams.set('action', 'latest.view');
						url.searchParams.set('hostids[]', data.submit.hostid);
						url.searchParams.set('filter_set', '1');

						event.setRedirectUrl(url.href);
					}
				}
			});

			ZABBIX.PopupManager.open(action, action_parameters, {supports_standalone: true});
		}
	};
</script>
