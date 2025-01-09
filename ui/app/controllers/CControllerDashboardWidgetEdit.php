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


use Zabbix\Core\{
	CModule,
	CWidget
};

use \Zabbix\Widgets\CWidgetField;

class CControllerDashboardWidgetEdit extends CController {

	private ?CWidget $widget;

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'type' =>						'string|required',
			'fields' =>						'array',
			'templateid' =>					'db dashboard.templateid',
			'name' =>						'string',
			'view_mode' =>					'in '.implode(',', [ZBX_WIDGET_VIEW_MODE_NORMAL, ZBX_WIDGET_VIEW_MODE_HIDDEN_HEADER]),
			'unique_id' =>					'string',
			'dashboard_page_unique_id' =>	'string'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			/** @var CWidget $widget */
			$widget = APP::ModuleManager()->getModule($this->getInput('type'));

			if ($widget !== null && $widget->getType() === CModule::TYPE_WIDGET) {
				$this->widget = $widget;
			}
			else {
				error(_('Inaccessible widget type.'));

				$ret = false;
			}
		}

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData([
					'main_block' => json_encode([
						'header' => $this->hasInput('unique_id') ? _('Edit widget') : _('Add widget'),
						'error' => [
							'messages' => array_column(get_and_clear_messages(), 'message')
						]
					], JSON_THROW_ON_ERROR)
				]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->hasInput('templateid')
			? ($this->getUserType() >= USER_TYPE_ZABBIX_ADMIN)
			: ($this->getUserType() >= USER_TYPE_ZABBIX_USER);
	}

	protected function doAction(): void {
		$known_types = [];
		$deprecated_types = [];

		/** @var CWidget $widget */
		foreach (APP::ModuleManager()->getWidgets() as $widget) {
			if (!$widget->isDeprecated()) {
				$known_types[$widget->getId()] = $widget->getDefaultName();
			}
			else {
				$deprecated_types[$widget->getId()] = $widget->getDefaultName();
			}
		}

		natcasesort($known_types);
		natcasesort($deprecated_types);

		$values = $this->getInput('fields', $this->widget->getInitialFieldsValues());
		$templateid = $this->hasInput('templateid') ? $this->getInput('templateid') : null;

		$form = $this->widget->getForm($values, $templateid);
		$form->validate();

		$captions = $this->getValuesCaptions($form->fieldsToApi());
		$form_fields = $form->getFields();

		/** @var CWidgetField $field */
		foreach ($form_fields as $field) {
			$field->setValuesCaptions($captions);
		}

		$url = $this->widget->getUrl();

		if ($url !== '' && parse_url($url, PHP_URL_HOST) === null) {
			$url = CDocHelper::getUrl($url);
		}

		$this->setResponse(new CControllerResponseData([
			'name' => $this->getInput('name', ''),
			'type' => $this->getInput('type'),
			'known_types' => $known_types,
			'deprecated_types' => $deprecated_types,
			'templateid' => $templateid,
			'fields' => $form_fields,
			'view_mode' => $this->getInput('view_mode', ZBX_WIDGET_VIEW_MODE_NORMAL),
			'unique_id' => $this->hasInput('unique_id') ? $this->getInput('unique_id') : null,
			'dashboard_page_unique_id' => $this->hasInput('dashboard_page_unique_id')
				? $this->getInput('dashboard_page_unique_id')
				: null,
			'url' => $url,
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		]));
	}

	/**
	 * Prepares mapped list of names for all required resources.
	 */
	private function getValuesCaptions(array $api_fields): array {
		$captions = $ids = [
			ZBX_WIDGET_FIELD_TYPE_GROUP => [],
			ZBX_WIDGET_FIELD_TYPE_HOST => [],
			ZBX_WIDGET_FIELD_TYPE_ITEM => [],
			ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE => [],
			ZBX_WIDGET_FIELD_TYPE_GRAPH => [],
			ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE => [],
			ZBX_WIDGET_FIELD_TYPE_MAP => [],
			ZBX_WIDGET_FIELD_TYPE_SERVICE => [],
			ZBX_WIDGET_FIELD_TYPE_SLA => [],
			ZBX_WIDGET_FIELD_TYPE_USER => [],
			ZBX_WIDGET_FIELD_TYPE_ACTION => [],
			ZBX_WIDGET_FIELD_TYPE_MEDIA_TYPE => []
		];

		foreach ($api_fields as $api_field) {
			if (array_key_exists($api_field['type'], $ids)) {
				$ids[$api_field['type']][$api_field['value']] = true;
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_GROUP]) {
			$db_groups = API::HostGroup()->get([
				'output' => ['name'],
				'groupids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_GROUP]),
				'preservekeys' => true
			]);

			foreach ($db_groups as $groupid => $group) {
				$captions[ZBX_WIDGET_FIELD_TYPE_GROUP][$groupid] = [
					'id' => $groupid,
					'name' => $group['name']
				];
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_HOST]) {
			$db_hosts = API::Host()->get([
				'output' => ['name'],
				'hostids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_HOST]),
				'preservekeys' => true
			]);

			foreach ($db_hosts as $hostid => $host) {
				$captions[ZBX_WIDGET_FIELD_TYPE_HOST][$hostid] = [
					'id' => $hostid,
					'name' => $host['name']
				];
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_ITEM]) {
			$name_field = $this->hasInput('templateid') ? 'name' : 'name_resolved';

			$db_items = API::Item()->get([
				'output' => [$name_field],
				'selectHosts' => ['name'],
				'itemids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_ITEM]),
				'webitems' => true,
				'preservekeys' => true
			]);

			foreach ($db_items as $itemid => $item) {
				$captions[ZBX_WIDGET_FIELD_TYPE_ITEM][$itemid] = [
					'id' => $itemid,
					'name' => $item[$name_field],
					'prefix' => $item['hosts'][0]['name'].NAME_DELIMITER
				];
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE]) {
			$db_item_prototypes = API::ItemPrototype()->get([
				'output' => ['name'],
				'selectHosts' => ['name'],
				'itemids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE]),
				'preservekeys' => true
			]);

			foreach ($db_item_prototypes as $itemid => $item) {
				$captions[ZBX_WIDGET_FIELD_TYPE_ITEM_PROTOTYPE][$itemid] = [
					'id' => $itemid,
					'name' => $item['name'],
					'prefix' => $item['hosts'][0]['name'].NAME_DELIMITER
				];
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH]) {
			$db_graphs = API::Graph()->get([
				'output' => ['graphid', 'name'],
				'selectHosts' => ['name'],
				'graphids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH]),
				'preservekeys' => true
			]);

			foreach ($db_graphs as $graphid => $graph) {
				$captions[ZBX_WIDGET_FIELD_TYPE_GRAPH][$graphid] = [
					'id' => $graphid,
					'name' => $graph['name'],
					'prefix' => $graph['hosts'][0]['name'].NAME_DELIMITER
				];
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE]) {
			$db_graph_prototypes = API::GraphPrototype()->get([
				'output' => ['graphid', 'name'],
				'selectHosts' => ['hostid', 'name'],
				'selectDiscoveryRule' => ['hostid'],
				'graphids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE]),
				'preservekeys' => true
			]);

			foreach ($db_graph_prototypes as $graphid => $graph) {
				$host_names = array_column($graph['hosts'], 'name', 'hostid');

				$captions[ZBX_WIDGET_FIELD_TYPE_GRAPH_PROTOTYPE][$graphid] = [
					'id' => $graphid,
					'name' => $graph['name'],
					'prefix' => $host_names[$graph['discoveryRule']['hostid']].NAME_DELIMITER
				];
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_MAP]) {
			$db_sysmaps = API::Map()->get([
				'output' => ['sysmapid', 'name'],
				'sysmapids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_MAP]),
				'preservekeys' => true
			]);

			foreach ($db_sysmaps as $sysmapid => $sysmap) {
				$captions[ZBX_WIDGET_FIELD_TYPE_MAP][$sysmapid] = [
					'id' => $sysmapid,
					'name' => $sysmap['name']
				];
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_SERVICE]) {
			$db_services = API::Service()->get([
				'output' => ['serviceid', 'name'],
				'serviceids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_SERVICE]),
				'preservekeys' => true
			]);

			foreach ($db_services as $serviceid => $service) {
				$captions[ZBX_WIDGET_FIELD_TYPE_SERVICE][$serviceid] = [
					'id' => $serviceid,
					'name' => $service['name']
				];
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_SLA]) {
			$db_slas = API::Sla()->get([
				'output' => ['slaid', 'name'],
				'slaids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_SLA]),
				'preservekeys' => true
			]);

			foreach ($db_slas as $slaid => $sla) {
				$captions[ZBX_WIDGET_FIELD_TYPE_SLA][$slaid] = [
					'id' => $slaid,
					'name' => $sla['name']
				];
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_USER]) {
			$db_users = API::User()->get([
				'output' => ['userid', 'username', 'name', 'surname'],
				'userids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_USER]),
				'preservekeys' => true
			]);

			foreach ($db_users as $userid => $user) {
				$captions[ZBX_WIDGET_FIELD_TYPE_USER][$userid] = [
					'id' => $userid,
					'name' => getUserFullname($user)
				];
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_ACTION]) {
			$db_actions = API::Action()->get([
				'output' => ['actionid', 'name'],
				'actionids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_ACTION]),
				'preservekeys' => true
			]);

			foreach ($db_actions as $actionid => $action) {
				$captions[ZBX_WIDGET_FIELD_TYPE_ACTION][$actionid] = [
					'id' => $actionid,
					'name' => $action['name']
				];
			}
		}

		if ($ids[ZBX_WIDGET_FIELD_TYPE_MEDIA_TYPE]) {
			$db_media_types = API::MediaType()->get([
				'output' => ['mediatypeid', 'name'],
				'mediatypeids' => array_keys($ids[ZBX_WIDGET_FIELD_TYPE_MEDIA_TYPE]),
				'preservekeys' => true
			]);

			foreach ($db_media_types as $mediatypeid => $media_type) {
				$captions[ZBX_WIDGET_FIELD_TYPE_MEDIA_TYPE][$mediatypeid] = [
					'id' => $mediatypeid,
					'name' => $media_type['name']
				];
			}
		}

		return $captions;
	}
}
