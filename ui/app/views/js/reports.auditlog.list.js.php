<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
					AUDIT_ACTION_LOGIN => [AUDIT_RESOURCE_USER],
					AUDIT_ACTION_LOGOUT => [AUDIT_RESOURCE_USER],
					AUDIT_ACTION_ADD => [
						AUDIT_RESOURCE_USER, AUDIT_RESOURCE_MEDIA_TYPE, AUDIT_RESOURCE_HOST,
						AUDIT_RESOURCE_HOST_PROTOTYPE, AUDIT_RESOURCE_ACTION, AUDIT_RESOURCE_GRAPH,
						AUDIT_RESOURCE_GRAPH_PROTOTYPE, AUDIT_RESOURCE_GRAPH_ELEMENT, AUDIT_RESOURCE_USER_GROUP,
						AUDIT_RESOURCE_APPLICATION, AUDIT_RESOURCE_TRIGGER, AUDIT_RESOURCE_TRIGGER_PROTOTYPE,
						AUDIT_RESOURCE_HOST_GROUP, AUDIT_RESOURCE_ITEM, AUDIT_RESOURCE_ITEM_PROTOTYPE,
						AUDIT_RESOURCE_VALUE_MAP, AUDIT_RESOURCE_IT_SERVICE, AUDIT_RESOURCE_MAP, AUDIT_RESOURCE_SCREEN,
						AUDIT_RESOURCE_SCENARIO, AUDIT_RESOURCE_DISCOVERY_RULE, AUDIT_RESOURCE_SLIDESHOW,
						AUDIT_RESOURCE_PROXY, AUDIT_RESOURCE_REGEXP, AUDIT_RESOURCE_MAINTENANCE, AUDIT_RESOURCE_SCRIPT,
						AUDIT_RESOURCE_MACRO, AUDIT_RESOURCE_TEMPLATE, AUDIT_RESOURCE_ICON_MAP,
						AUDIT_RESOURCE_DASHBOARD, AUDIT_RESOURCE_AUTOREGISTRATION, AUDIT_RESOURCE_MODULE,
						AUDIT_RESOURCE_TEMPLATE_DASHBOARD
					],
					AUDIT_ACTION_UPDATE => [
						AUDIT_RESOURCE_USER, AUDIT_RESOURCE_ZABBIX_CONFIG,
						AUDIT_RESOURCE_MEDIA_TYPE, AUDIT_RESOURCE_HOST, AUDIT_RESOURCE_HOST_PROTOTYPE,
						AUDIT_RESOURCE_ACTION, AUDIT_RESOURCE_GRAPH, AUDIT_RESOURCE_GRAPH_PROTOTYPE,
						AUDIT_RESOURCE_GRAPH_ELEMENT, AUDIT_RESOURCE_USER_GROUP, AUDIT_RESOURCE_APPLICATION,
						AUDIT_RESOURCE_TRIGGER, AUDIT_RESOURCE_TRIGGER_PROTOTYPE, AUDIT_RESOURCE_HOST_GROUP,
						AUDIT_RESOURCE_ITEM, AUDIT_RESOURCE_ITEM_PROTOTYPE, AUDIT_RESOURCE_IMAGE,
						AUDIT_RESOURCE_VALUE_MAP, AUDIT_RESOURCE_IT_SERVICE, AUDIT_RESOURCE_MAP, AUDIT_RESOURCE_SCREEN,
						AUDIT_RESOURCE_SCENARIO, AUDIT_RESOURCE_DISCOVERY_RULE, AUDIT_RESOURCE_SLIDESHOW,
						AUDIT_RESOURCE_PROXY, AUDIT_RESOURCE_REGEXP, AUDIT_RESOURCE_MAINTENANCE, AUDIT_RESOURCE_SCRIPT,
						AUDIT_RESOURCE_MACRO, AUDIT_RESOURCE_TEMPLATE, AUDIT_RESOURCE_ICON_MAP,
						AUDIT_RESOURCE_DASHBOARD, AUDIT_RESOURCE_AUTOREGISTRATION, AUDIT_RESOURCE_MODULE,
						AUDIT_RESOURCE_SETTINGS, AUDIT_RESOURCE_HOUSEKEEPING, AUDIT_RESOURCE_AUTHENTICATION,
						AUDIT_RESOURCE_TEMPLATE_DASHBOARD
					],
					AUDIT_ACTION_DISABLE => [AUDIT_RESOURCE_HOST, AUDIT_RESOURCE_DISCOVERY_RULE],
					AUDIT_ACTION_ENABLE => [AUDIT_RESOURCE_HOST, AUDIT_RESOURCE_DISCOVERY_RULE],
					AUDIT_ACTION_DELETE => [
						AUDIT_RESOURCE_USER, AUDIT_RESOURCE_MEDIA_TYPE, AUDIT_RESOURCE_HOST,
						AUDIT_RESOURCE_HOST_PROTOTYPE, AUDIT_RESOURCE_ACTION, AUDIT_RESOURCE_GRAPH,
						AUDIT_RESOURCE_GRAPH_PROTOTYPE, AUDIT_RESOURCE_GRAPH_ELEMENT, AUDIT_RESOURCE_USER_GROUP,
						AUDIT_RESOURCE_APPLICATION, AUDIT_RESOURCE_TRIGGER, AUDIT_RESOURCE_TRIGGER_PROTOTYPE,
						AUDIT_RESOURCE_HOST_GROUP, AUDIT_RESOURCE_ITEM, AUDIT_RESOURCE_ITEM_PROTOTYPE,
						AUDIT_RESOURCE_VALUE_MAP, AUDIT_RESOURCE_IT_SERVICE, AUDIT_RESOURCE_MAP, AUDIT_RESOURCE_SCREEN,
						AUDIT_RESOURCE_SCENARIO, AUDIT_RESOURCE_DISCOVERY_RULE, AUDIT_RESOURCE_SLIDESHOW,
						AUDIT_RESOURCE_PROXY, AUDIT_RESOURCE_REGEXP, AUDIT_RESOURCE_MAINTENANCE, AUDIT_RESOURCE_SCRIPT,
						AUDIT_RESOURCE_MACRO, AUDIT_RESOURCE_TEMPLATE, AUDIT_RESOURCE_ICON_MAP,
						AUDIT_RESOURCE_CORRELATION, AUDIT_RESOURCE_DASHBOARD, AUDIT_RESOURCE_AUTOREGISTRATION,
						AUDIT_RESOURCE_MODULE, AUDIT_RESOURCE_TEMPLATE_DASHBOARD
					],
					AUDIT_ACTION_EXECUTE => [AUDIT_RESOURCE_SCRIPT]
				]); ?>
			// Add action "All" to every resource.
			const arr = [OPTION_ALL];

			for (let i in resources) {
				if (resources.hasOwnProperty(i) && resources[i].includes(parseInt(resource))) {
					arr.push(i);
				}
			}

			return arr;
		}
	}

	// Initialize class when DOM ready.
	document.addEventListener('DOMContentLoaded', () => {
		new resourceInputManage();
	}, false);
</script>
