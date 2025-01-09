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
?>


<script type="text/javascript">
	const view = new class {

		init() {
			this.filter_actions_checkboxes = document.querySelectorAll('#filter-actions input[type="checkbox"]');

			this.filter_resource_select = document.getElementById('resourcetype-select');
			this.filter_resource_select.addEventListener('change', () => this._update());

			this._update();

			for (const details_link of document.forms['auditForm'].querySelectorAll('[data-details]')) {
				details_link.addEventListener('click', (e) => {
					this._openAuditDetails(JSON.parse(e.target.dataset.details))
				});
			}
		}

		_update() {
			const enabled_actions = this.filter_resource_select.value !== '-1'
				? this._getActionsByResource(this.filter_resource_select.value)
				: null;

			for (const checkbox of this.filter_actions_checkboxes) {
				checkbox.disabled = enabled_actions !== null && !enabled_actions.includes(checkbox.value);

				if (checkbox.disabled) {
					checkbox.checked = false;
				}
			}
		}

		_getActionsByResource(resource) {
			// [Action => [Resources]]
			const resources = <?php echo json_encode([
				CAudit::ACTION_ADD => [
					CAudit::RESOURCE_ACTION, CAudit::RESOURCE_AUTH_TOKEN, CAudit::RESOURCE_CONNECTOR,
					CAudit::RESOURCE_CORRELATION, CAudit::RESOURCE_DASHBOARD, CAudit::RESOURCE_DISCOVERY_RULE,
					CAudit::RESOURCE_GRAPH, CAudit::RESOURCE_GRAPH_PROTOTYPE, CAudit::RESOURCE_HA_NODE,
					CAudit::RESOURCE_HOST, CAudit::RESOURCE_HOST_GROUP, CAudit::RESOURCE_HOST_PROTOTYPE,
					CAudit::RESOURCE_ICON_MAP, CAudit::RESOURCE_IMAGE, CAudit::RESOURCE_IT_SERVICE,
					CAudit::RESOURCE_ITEM, CAudit::RESOURCE_ITEM_PROTOTYPE, CAudit::RESOURCE_LLD_RULE,
					CAudit::RESOURCE_MACRO, CAudit::RESOURCE_MAINTENANCE, CAudit::RESOURCE_MAP,
					CAudit::RESOURCE_MEDIA_TYPE, CAudit::RESOURCE_MODULE, CAudit::RESOURCE_PROXY,
					CAudit::RESOURCE_PROXY_GROUP, CAudit::RESOURCE_REGEXP, CAudit::RESOURCE_SCENARIO,
					CAudit::RESOURCE_SCHEDULED_REPORT, CAudit::RESOURCE_SCRIPT, CAudit::RESOURCE_SLA,
					CAudit::RESOURCE_TEMPLATE, CAudit::RESOURCE_TEMPLATE_DASHBOARD, CAudit::RESOURCE_TEMPLATE_GROUP,
					CAudit::RESOURCE_TRIGGER, CAudit::RESOURCE_TRIGGER_PROTOTYPE, CAudit::RESOURCE_USER,
					CAudit::RESOURCE_USERDIRECTORY, CAudit::RESOURCE_USER_GROUP, CAudit::RESOURCE_USER_ROLE,
					CAudit::RESOURCE_VALUE_MAP
				],
				CAudit::ACTION_UPDATE => [
					CAudit::RESOURCE_ACTION, CAudit::RESOURCE_AUTHENTICATION, CAudit::RESOURCE_AUTH_TOKEN,
					CAudit::RESOURCE_AUTOREGISTRATION, CAudit::RESOURCE_CONNECTOR, CAudit::RESOURCE_CORRELATION,
					CAudit::RESOURCE_DASHBOARD, CAudit::RESOURCE_DISCOVERY_RULE, CAudit::RESOURCE_GRAPH,
					CAudit::RESOURCE_GRAPH_PROTOTYPE, CAudit::RESOURCE_HA_NODE, CAudit::RESOURCE_HOST,
					CAudit::RESOURCE_HOST_GROUP, CAudit::RESOURCE_HOST_PROTOTYPE, CAudit::RESOURCE_HOUSEKEEPING,
					CAudit::RESOURCE_ICON_MAP, CAudit::RESOURCE_IMAGE, CAudit::RESOURCE_IT_SERVICE,
					CAudit::RESOURCE_ITEM, CAudit::RESOURCE_ITEM_PROTOTYPE, CAudit::RESOURCE_LLD_RULE,
					CAudit::RESOURCE_MACRO, CAudit::RESOURCE_MAINTENANCE, CAudit::RESOURCE_MAP,
					CAudit::RESOURCE_MEDIA_TYPE, CAudit::RESOURCE_MODULE, CAudit::RESOURCE_PROXY,
					CAudit::RESOURCE_PROXY_GROUP, CAudit::RESOURCE_REGEXP, CAudit::RESOURCE_SCENARIO,
					CAudit::RESOURCE_SCHEDULED_REPORT, CAudit::RESOURCE_SCRIPT, CAudit::RESOURCE_SETTINGS,
					CAudit::RESOURCE_SLA, CAudit::RESOURCE_TEMPLATE, CAudit::RESOURCE_TEMPLATE_DASHBOARD,
					CAudit::RESOURCE_TEMPLATE_GROUP, CAudit::RESOURCE_TRIGGER, CAudit::RESOURCE_TRIGGER_PROTOTYPE,
					CAudit::RESOURCE_USER, CAudit::RESOURCE_USERDIRECTORY, CAudit::RESOURCE_USER_GROUP,
					CAudit::RESOURCE_USER_ROLE, CAudit::RESOURCE_VALUE_MAP
				],
				CAudit::ACTION_DELETE => [
					CAudit::RESOURCE_ACTION, CAudit::RESOURCE_AUTH_TOKEN, CAudit::RESOURCE_CONNECTOR,
					CAudit::RESOURCE_CORRELATION, CAudit::RESOURCE_DASHBOARD, CAudit::RESOURCE_DISCOVERY_RULE,
					CAudit::RESOURCE_GRAPH, CAudit::RESOURCE_GRAPH_PROTOTYPE, CAudit::RESOURCE_HA_NODE,
					CAudit::RESOURCE_HOST, CAudit::RESOURCE_HOST_GROUP, CAudit::RESOURCE_HOST_PROTOTYPE,
					CAudit::RESOURCE_ICON_MAP, CAudit::RESOURCE_IMAGE, CAudit::RESOURCE_IT_SERVICE,
					CAudit::RESOURCE_ITEM, CAudit::RESOURCE_ITEM_PROTOTYPE, CAudit::RESOURCE_LLD_RULE,
					CAudit::RESOURCE_MACRO, CAudit::RESOURCE_MAINTENANCE, CAudit::RESOURCE_MAP,
					CAudit::RESOURCE_MEDIA_TYPE, CAudit::RESOURCE_MODULE, CAudit::RESOURCE_PROXY,
					CAudit::RESOURCE_PROXY_GROUP, CAudit::RESOURCE_REGEXP, CAudit::RESOURCE_SCENARIO,
					CAudit::RESOURCE_SCHEDULED_REPORT, CAudit::RESOURCE_SCRIPT, CAudit::RESOURCE_SLA,
					CAudit::RESOURCE_TEMPLATE, CAudit::RESOURCE_TEMPLATE_DASHBOARD, CAudit::RESOURCE_TEMPLATE_GROUP,
					CAudit::RESOURCE_TRIGGER, CAudit::RESOURCE_TRIGGER_PROTOTYPE, CAudit::RESOURCE_USER,
					CAudit::RESOURCE_USERDIRECTORY, CAudit::RESOURCE_USER_GROUP, CAudit::RESOURCE_USER_ROLE,
					CAudit::RESOURCE_VALUE_MAP
				],
				CAudit::ACTION_LOGOUT => [CAudit::RESOURCE_USER],
				CAudit::ACTION_EXECUTE => [CAudit::RESOURCE_SCRIPT],
				CAudit::ACTION_LOGIN_SUCCESS => [CAudit::RESOURCE_USER],
				CAudit::ACTION_LOGIN_FAILED => [CAudit::RESOURCE_USER],
				CAudit::ACTION_HISTORY_CLEAR => [CAudit::RESOURCE_ITEM],
				CAudit::ACTION_CONFIG_REFRESH => [CAudit::RESOURCE_PROXY],
				CAudit::ACTION_PUSH => [CAudit::RESOURCE_HISTORY]
			]); ?>

			const actions = [];

			for (let action in resources) {
				if (resources.hasOwnProperty(action) && resources[action].includes(parseInt(resource))) {
					actions.push(action);
				}
			}

			return actions;
		}

		_openAuditDetails(details) {
			const wrapper = document.createElement('div');
			wrapper.classList.add('audit-details-popup-wrapper');

			const textarea = document.createElement('textarea');
			textarea.readOnly = true;
			textarea.innerHTML = details;
			textarea.classList.add('audit-details-popup-textarea', 'active-readonly');

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
	};
</script>
