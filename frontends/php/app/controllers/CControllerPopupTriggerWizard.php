<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


require_once dirname(__FILE__).'/../../include/config.inc.php';
require_once dirname(__FILE__).'/../../include/hosts.inc.php';
require_once dirname(__FILE__).'/../../include/triggers.inc.php';
require_once dirname(__FILE__).'/../../include/items.inc.php';

class CControllerPopupTriggerWizard extends CController {

	protected function checkInput() {
		$fields = [
			'description' =>	'string',
			'itemid' =>			'db items.itemid',
			'triggerid' =>		'db triggers.triggerid',
			'type' =>			'in 0,1',
			'expressions' =>	'array',
			'expr_type' =>		'in 0,1',
			'comments' =>		'string',
			'url' =>			'string',
			'status' =>			'in 1',
			'priority' =>		'ge '.TRIGGER_SEVERITY_NOT_CLASSIFIED.'|le '.TRIGGER_SEVERITY_COUNT,
			'keys' => 			'array',
			'save' =>			'in 1'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions() {
		if ($this->hasInput('itemid')) {
			$items = API::Item()->get([
				'output' => [],
				'itemids' => $this->getInput('itemid'),
				'editable' => true
			]);

			if (!$items) {
				return false;
			}
		}

		if ($this->hasInput('triggerid')) {
			$triggers = API::Trigger()->get([
				'output' => [],
				'triggerids' => $this->getInput('triggerid'),
				'editable' => true
			]);

			if (!$triggers) {
				return false;
			}
		}

		return true;
	}

	protected function doAction() {
		$page_options = [
			'description' => $this->getInput('description', ''),
			'itemid' => $this->getInput('itemid', 0),
			'type' => $this->getInput('type', 0),
			'priority' => $this->getInput('priority', 0),
			'comments' => $this->getInput('comments', ''),
			'url' => $this->getInput('url', ''),
			'status' => ($this->hasInput('status') || !$this->hasInput('save'))
				? TRIGGER_STATUS_ENABLED
				: TRIGGER_STATUS_DISABLED,
			'item_name' => ''
		];

		$exprs = $this->getInput('expressions', []);
		$constructor = new CTextTriggerConstructor(new CTriggerExpression());

		if ($this->hasInput('triggerid')) {
			$page_options['triggerid'] = $this->getInput('triggerid');
		}

		// Save trigger.
		if ($this->hasInput('save')) {
			$item = API::Item()->get([
				'output' => ['key_'],
				'selectHosts' => ['host'],
				'itemids' => $page_options['itemid'],
				'limit' => 1
			]);

			$item = reset($item);
			$host = reset($item['hosts']);
			$trigger_valid = true;

			// Trigger validation.
			if ($page_options['description'] === '') {
				error(_s('Incorrect value for field "%1$s": cannot be empty.', _('Name')));
				$trigger_valid = false;
			}

			if (!$item) {
				error('No permissions to referred object or it does not exist!');
				$trigger_valid = false;
			}

			if ($exprs && ($expression = $constructor->getExpressionFromParts($host['host'], $item['key_'], $exprs))) {
				if (check_right_on_trigger_by_expression(PERM_READ_WRITE, $expression)) {
					if (array_key_exists('triggerid', $page_options)) {
						$triggerid = $page_options['triggerid'];
						$description = $page_options['description'];

						$db_triggers = API::Trigger()->get([
							'output' => ['description', 'expression', 'templateid'],
							'triggerids' => [$triggerid]
						]);

						if ($db_triggers[0]['templateid'] != 0) {
							$db_triggers = CMacrosResolverHelper::resolveTriggerExpressions($db_triggers);

							$description = $db_triggers[0]['description'];
							$expression = $db_triggers[0]['expression'];
						}

						$trigger = [
							'triggerid' => $triggerid,
							'expression' => $expression,
							'description' => $description,
							'type' => TRIGGER_MULT_EVENT_ENABLED,
							'priority' => $page_options['priority'],
							'status' => $page_options['status'],
							'comments' => $page_options['comments'],
							'url' => $page_options['url']
						];
					}
					else {
						$trigger = [
							'expression' => $expression,
							'description' => $page_options['description'],
							'type' => TRIGGER_MULT_EVENT_ENABLED,
							'priority' => $page_options['priority'],
							'status' => $page_options['status'],
							'comments' => $page_options['comments'],
							'url' => $page_options['url']
						];
					}

					// Save if no errors found.
					if (array_key_exists('triggerid', $page_options) && $trigger_valid) {
						$result = API::Trigger()->update($trigger);
						$audit_action = AUDIT_ACTION_UPDATE;

						if (!$result['triggerids']) {
							error(_('Cannot update trigger'));
						}
					}
					elseif ($trigger_valid) {
						$result = API::Trigger()->create($trigger);
						if ($result['triggerids']) {
							$db_triggers = API::Trigger()->get([
								'triggerids' => $result['triggerids'],
								'output' => ['triggerid']
							]);

							$triggerid = $db_triggers[0]['triggerid'];
						}

						$audit_action = AUDIT_ACTION_ADD;
						if (!$result['triggerids']) {
							error(_('Cannot add trigger'));
						}
					}
					else {
						$result['triggerids'] = false;
					}

					if ($result['triggerids']) {
						DBstart();

						add_audit($audit_action, AUDIT_RESOURCE_TRIGGER,
							_('Trigger').' ['.$triggerid.'] ['.$trigger['description'].']'
						);

						DBend(true);
					}
				}
				else {
					error('No permissions to referred object or it does not exist!');
				}
			}
			else {
				error(_s('Field "%1$s" is mandatory.', 'expressions'));
			}

			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => CJs::encodeJson($output)]))->disableView()
			);
		}
		else {
			// Select requested trigger.
			if (array_key_exists('triggerid', $page_options)) {
				$result = DBselect(
					'SELECT t.expression,t.description,t.priority,t.comments,t.url,t.status,t.type'.
					' FROM triggers t'.
					' WHERE t.triggerid='.zbx_dbstr($page_options['triggerid']).
						' AND EXISTS ('.
							'SELECT NULL'.
							' FROM functions f,items i'.
							' WHERE t.triggerid=f.triggerid'.
								' AND f.itemid=i.itemid '.
								' AND i.value_type IN ('.
									ITEM_VALUE_TYPE_LOG.','.ITEM_VALUE_TYPE_TEXT.','.ITEM_VALUE_TYPE_STR.
								')'.
						')'
				);

				if ($row = DBfetch($result)) {
					$expression = CMacrosResolverHelper::resolveTriggerExpression($row['expression']);
					$page_options['description'] = $row['description'];
					$page_options['type'] = $row['type'];
					$page_options['priority'] = $row['priority'];
					$page_options['comments'] = $row['comments'];
					$page_options['url'] = $row['url'];
					$page_options['status'] = $row['status'];
				}

				// Break expression into parts.
				$exprs = $constructor->getPartsFromExpression($expression);
			}

			// Resolve item name.
			if ($page_options['itemid']) {
				$items = API::Item()->get([
					'output' => ['itemid', 'hostid', 'key_', 'name'],
					'selectHosts' => ['name'],
					'itemids' => $page_options['itemid']
				]);

				if ($items) {
					$items = CMacrosResolverHelper::resolveItemNames($items);
					$page_options['item_name'] = $items[0]['hosts'][0]['name'].NAME_DELIMITER.$items[0]['name_expanded'];
				}
			}

			// Output popup form.
			$this->setResponse(new CControllerResponseData([
				'title' => _('Trigger'),
				'options' => $page_options,
				'keys' => $this->getInput('keys', []),
				'expressions' => $exprs,
				'user' => [
					'debug_mode' => $this->getDebugMode()
				]
			]));
		}
	}
}
