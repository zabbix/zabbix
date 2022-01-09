<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
<script type="text/javascript">
	const OPTION_ALL = '-1';

	class resourceInputManage {
		constructor() {
			this.action_elem = document.getElementById('action-select');
			this.action_options = this.action_elem.getOptions();
			this.resource_elem = document.getElementById('resourcetype-select');

			this
				.resource_elem
				.addEventListener('change', this.updateHandler.bind(this));

			this.update(this.resource_elem.value);
		}

		updateHandler(event) {
			// Reset select to first element.
			this.action_elem.selectedIndex = 0;

			this.update(event.currentTarget.value);
		}

		update(resourceid) {
			// Enabling all action options when selected resource option "All".
			if (resourceid == OPTION_ALL) {
				this.enableAllOptions();
			}
			else {
				this.disableOptionsByActions(this.getActionsByResource(resourceid));
			}
		}

		enableAllOptions() {
			[...this.action_options].map((elem) => {
				elem.disabled = false;
			});
		}

		disableOptionsByActions(actions) {
			[...this.action_options].map((elem) => {
				elem.disabled = !actions.includes(elem.value);
			});
		}

		getActionsByResource(resource) {
			// [Action => [Resources]]
			const resources = <?php echo json_encode([
				CAudit::ACTION_ADD => [
					CAudit::RESOURCE_ACTION, CAudit::RESOURCE_AUTH_TOKEN, CAudit::RESOURCE_AUTOREGISTRATION,
					CAudit::RESOURCE_CORRELATION, CAudit::RESOURCE_DASHBOARD, CAudit::RESOURCE_DISCOVERY_RULE,
					CAudit::RESOURCE_GRAPH, CAudit::RESOURCE_GRAPH_PROTOTYPE, CAudit::RESOURCE_HA_NODE,
					CAudit::RESOURCE_HOST, CAudit::RESOURCE_HOST_GROUP, CAudit::RESOURCE_HOST_PROTOTYPE,
					CAudit::RESOURCE_ICON_MAP, CAudit::RESOURCE_IMAGE, CAudit::RESOURCE_ITEM,
					CAudit::RESOURCE_ITEM_PROTOTYPE, CAudit::RESOURCE_IT_SERVICE, CAudit::RESOURCE_MACRO,
					CAudit::RESOURCE_MAINTENANCE, CAudit::RESOURCE_MAP, CAudit::RESOURCE_MEDIA_TYPE,
					CAudit::RESOURCE_MODULE, CAudit::RESOURCE_PROXY, CAudit::RESOURCE_REGEXP, CAudit::RESOURCE_SCENARIO,
					CAudit::RESOURCE_SCHEDULED_REPORT, CAudit::RESOURCE_SCRIPT, CAudit::RESOURCE_SLA,
					CAudit::RESOURCE_TEMPLATE, CAudit::RESOURCE_TEMPLATE_DASHBOARD, CAudit::RESOURCE_TRIGGER,
					CAudit::RESOURCE_TRIGGER_PROTOTYPE, CAudit::RESOURCE_USER, CAudit::RESOURCE_USER_GROUP,
					CAudit::RESOURCE_USER_ROLE, CAudit::RESOURCE_VALUE_MAP
				],
				CAudit::ACTION_UPDATE => [
					CAudit::RESOURCE_ACTION, CAudit::RESOURCE_AUTHENTICATION, CAudit::RESOURCE_AUTH_TOKEN,
					CAudit::RESOURCE_AUTOREGISTRATION, CAudit::RESOURCE_CORRELATION, CAudit::RESOURCE_DASHBOARD,
					CAudit::RESOURCE_DISCOVERY_RULE, CAudit::RESOURCE_GRAPH, CAudit::RESOURCE_GRAPH_PROTOTYPE,
					CAudit::RESOURCE_HA_NODE, CAudit::RESOURCE_HOST, CAudit::RESOURCE_HOST_GROUP,
					CAudit::RESOURCE_HOST_PROTOTYPE, CAudit::RESOURCE_HOUSEKEEPING, CAudit::RESOURCE_ICON_MAP,
					CAudit::RESOURCE_IMAGE, CAudit::RESOURCE_ITEM, CAudit::RESOURCE_ITEM_PROTOTYPE,
					CAudit::RESOURCE_IT_SERVICE, CAudit::RESOURCE_MACRO, CAudit::RESOURCE_MAINTENANCE,
					CAudit::RESOURCE_MAP, CAudit::RESOURCE_MEDIA_TYPE, CAudit::RESOURCE_MODULE, CAudit::RESOURCE_PROXY,
					CAudit::RESOURCE_REGEXP, CAudit::RESOURCE_SCENARIO, CAudit::RESOURCE_SCHEDULED_REPORT,
					CAudit::RESOURCE_SCRIPT, CAudit::RESOURCE_SETTINGS, CAudit::RESOURCE_SLA, CAudit::RESOURCE_TEMPLATE,
					CAudit::RESOURCE_TEMPLATE_DASHBOARD, CAudit::RESOURCE_TRIGGER,
					CAudit::RESOURCE_TRIGGER_PROTOTYPE, CAudit::RESOURCE_USER, CAudit::RESOURCE_USER_GROUP,
					CAudit::RESOURCE_USER_ROLE, CAudit::RESOURCE_VALUE_MAP
				],
				CAudit::ACTION_DELETE => [
					CAudit::RESOURCE_ACTION, CAudit::RESOURCE_AUTH_TOKEN, CAudit::RESOURCE_AUTOREGISTRATION,
					CAudit::RESOURCE_CORRELATION, CAudit::RESOURCE_DASHBOARD, CAudit::RESOURCE_DISCOVERY_RULE,
					CAudit::RESOURCE_GRAPH, CAudit::RESOURCE_GRAPH_PROTOTYPE, CAudit::RESOURCE_HA_NODE,
					CAudit::RESOURCE_HOST, CAudit::RESOURCE_HOST_GROUP, CAudit::RESOURCE_HOST_PROTOTYPE,
					CAudit::RESOURCE_ICON_MAP, CAudit::RESOURCE_IMAGE, CAudit::RESOURCE_ITEM,
					CAudit::RESOURCE_ITEM_PROTOTYPE, CAudit::RESOURCE_IT_SERVICE, CAudit::RESOURCE_MACRO,
					CAudit::RESOURCE_MAINTENANCE, CAudit::RESOURCE_MAP, CAudit::RESOURCE_MEDIA_TYPE,
					CAudit::RESOURCE_MODULE, CAudit::RESOURCE_PROXY, CAudit::RESOURCE_REGEXP, CAudit::RESOURCE_SCENARIO,
					CAudit::RESOURCE_SCHEDULED_REPORT, CAudit::RESOURCE_SCRIPT, CAudit::RESOURCE_SLA,
					CAudit::RESOURCE_TEMPLATE, CAudit::RESOURCE_TEMPLATE_DASHBOARD, CAudit::RESOURCE_TRIGGER,
					CAudit::RESOURCE_TRIGGER_PROTOTYPE, CAudit::RESOURCE_USER, CAudit::RESOURCE_USER_GROUP,
					CAudit::RESOURCE_USER_ROLE, CAudit::RESOURCE_VALUE_MAP
				],
				CAudit::ACTION_LOGOUT => [CAudit::RESOURCE_USER],
				CAudit::ACTION_EXECUTE => [CAudit::RESOURCE_SCRIPT],
				CAudit::ACTION_LOGIN_SUCCESS => [CAudit::RESOURCE_USER],
				CAudit::ACTION_LOGIN_FAILED => [CAudit::RESOURCE_USER],
				CAudit::ACTION_HISTORY_CLEAR => [CAudit::RESOURCE_ITEM]
			]); ?>

			// Add action "All" to every resource.
			const actions = [OPTION_ALL];

			for (let i in resources) {
				if (resources.hasOwnProperty(i) && resources[i].includes(parseInt(resource))) {
					actions.push(i);
				}
			}

			return actions;
		}
	}

	function openAuditDetails(details) {
		const wrapper = document.createElement('div');
		wrapper
			.classList
			.add('audit-details-popup-wrapper');

		const textarea = document.createElement('textarea');
		textarea.readOnly = true;
		textarea.innerHTML = details;
		textarea
			.classList
			.add('audit-details-popup-textarea', 'active-readonly');

		wrapper.appendChild(textarea)

		overlayDialogue({
			title: <?= json_encode(_('Details')) ?>,
			content: wrapper,
			class: 'modal-popup modal-popup-generic',
			buttons: [
				{
					title: <?= json_encode(_('Ok')) ?>,
					cancel: true,
					action: () => true
				}
			]
		});
	}

	// Initialize class when DOM ready.
	document.addEventListener('DOMContentLoaded', () => {
		new resourceInputManage();
	}, false);
</script>
