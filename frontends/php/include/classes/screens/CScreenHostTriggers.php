<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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


require_once dirname(__FILE__).'/../../blocks.inc.php';

class CScreenHostTriggers extends CScreenBase {

	/**
	 * Process screen.
	 *
	 * @return CDiv (screen inside container)
	 */
	public function get() {
		$params = [
			'groupids' => null,
			'hostids' => null,
			'maintenance' => null,
			'trigger_name' => '',
			'severity' => null,
			'limit' => $this->screenitem['elements']
		];

		// by default triggers are sorted by date desc, do we need to override this?
		switch ($this->screenitem['sort_triggers']) {
			case SCREEN_SORT_TRIGGERS_DATE_DESC:
				$params['sortfield'] = 'lastchange';
				$params['sortorder'] = ZBX_SORT_DOWN;
				break;
			case SCREEN_SORT_TRIGGERS_SEVERITY_DESC:
				$params['sortfield'] = 'severity';
				$params['sortorder'] = ZBX_SORT_DOWN;
				break;
			case SCREEN_SORT_TRIGGERS_HOST_NAME_ASC:
				// a little black magic here - there is no such field 'hostname' in 'triggers',
				// but API has a special case for sorting by hostname
				$params['sortfield'] = 'hostname';
				$params['sortorder'] = ZBX_SORT_UP;
				break;
		}

		if ($this->screenitem['resourceid'] != 0) {
			$hosts = API::Host()->get([
				'output' => ['name'],
				'hostids' => [$this->screenitem['resourceid']]
			]);

			$header = (new CDiv([
				new CTag('h4', true, _('Host issues')),
				(new CList())->addItem([_('Host'), ':', SPACE, $hosts[0]['name']])
			]))->addClass(ZBX_STYLE_DASHBRD_WIDGET_HEAD);

			$params['hostids'] = $this->screenitem['resourceid'];
		}
		else {
			$groupid = getRequest('tr_groupid', CProfile::get('web.screens.tr_groupid', 0));
			$hostid = getRequest('tr_hostid', CProfile::get('web.screens.tr_hostid', 0));

			CProfile::update('web.screens.tr_groupid', $groupid, PROFILE_TYPE_ID);
			CProfile::update('web.screens.tr_hostid', $hostid, PROFILE_TYPE_ID);

			// get groups
			$groups = API::HostGroup()->get([
				'output' => ['name'],
				'monitored_hosts' => true,
				'preservekeys' => true
			]);
			order_result($groups, 'name');

			foreach ($groups as &$group) {
				$group = $group['name'];
			}
			unset($group);

			// get hsots
			$options = [
				'output' => ['name'],
				'monitored_hosts' => true,
				'preservekeys' => true
			];
			if ($groupid != 0) {
				$options['groupids'] = [$groupid];
			}
			$hosts = API::Host()->get($options);
			order_result($hosts, 'name');

			foreach ($hosts as &$host) {
				$host = $host['name'];
			}
			unset($host);

			$groups = [0 => _('all')] + $groups;
			$hosts = [0 => _('all')] + $hosts;

			if (!array_key_exists($hostid, $hosts)) {
				$hostid = 0;
			}

			if ($groupid != 0) {
				$params['groupids'] = $groupid;
			}
			if ($hostid != 0) {
				$params['hostids'] = $hostid;
			}

			$groups_cb = (new CComboBox('tr_groupid', $groupid, 'submit()', $groups))
				->setEnabled($this->mode != SCREEN_MODE_EDIT);
			$hosts_cb = (new CComboBox('tr_hostid', $hostid, 'submit()', $hosts))
				->setEnabled($this->mode != SCREEN_MODE_EDIT);

			$header = (new CDiv([
				new CTag('h4', true, _('Host issues')),
				(new CForm('get', $this->pageFile))
					->addItem(
						(new CList())
							->addItem([_('Group'), '&nbsp;', $groups_cb])
							->addItem('&nbsp;')
							->addItem([_('Host'), '&nbsp;', $hosts_cb])
					)
			]))->addClass(ZBX_STYLE_DASHBRD_WIDGET_HEAD);
		}

		list($table, $info) = $this->getProblemsListTable($params,
			(new CUrl($this->pageFile))
				->setArgument('screenid', $this->screenid)
				->getUrl()
		);

		$footer = (new CList())
			->addItem($info)
			->addItem(_s('Updated: %s', zbx_date2str(TIME_FORMAT_SECONDS)))
			->addClass(ZBX_STYLE_DASHBRD_WIDGET_FOOT);

		return $this->getOutput(new CUiWidget('hat_trstatus', [$header, $table, $footer]));
	}

	/**
	 * Render table with host or host group problems.
	 *
	 * @param array   $filter                  Array of filter options.
	 * @param int     $filter['limit']         Table rows count.
	 * @param array   $filter['groupids']      Host group ids.
	 * @param array   $filter['hostids']       Host ids.
	 * @param string  $filter['sortfield']     Sort field name.
	 * @param string  $filter['sortorder']     Sort order.
	 * @param string  $back_url                URL used by acknowledgment page.
	 */
	protected function getProblemsListTable($filter, $back_url) {
		$config = select_config();

		// If no hostids and groupids defined show recent problems.
		if ($filter['hostids'] === null && $filter['groupids'] === null) {
			$filter['show'] = TRIGGERS_OPTION_RECENT_PROBLEM;
		}

		$filter = $filter + [
			'show' => TRIGGERS_OPTION_IN_PROBLEM,
			'show_timeline' => 0,
			'details' => 1,
			'show_opdata' => 0,
			'sort_field' => '',
			'sort_order' => ZBX_SORT_DOWN
		];

		$data = CScreenProblem::getData($filter, $config, true, true);

		$header = [
			'hostname' => _('Host'),
			'severity' => _('Issue'),
			'lastchange' => _('Last change')
		];

		if (array_key_exists('sortfield', $filter)) {
			$sort_field = $filter['sortfield'];
			$sort_order = ($sort_field !== 'lastchange') ? $filter['sortorder'] : ZBX_SORT_DOWN;

			$header[$sort_field] = [
				$header[$sort_field],
				(new CDiv())->addClass(($sort_order === ZBX_SORT_DOWN) ? ZBX_STYLE_ARROW_DOWN : ZBX_STYLE_ARROW_UP)
			];

			$data = CScreenProblem::sortData($data, $config, $sort_field === 'hostname' ? 'host' : $sort_field,
				$sort_order
			);
		}

		$info = _n('%1$d of %3$d%2$s problem is shown', '%1$d of %3$d%2$s problems are shown',
			min($filter['limit'], count($data['problems'])),
			(count($data['problems']) > $config['search_limit']) ? '+' : '',
			min($config['search_limit'], count($data['problems']))
		);
		$data['problems'] = array_slice($data['problems'], 0, $filter['limit'], true);
		$data = CScreenProblem::makeData($data, $filter, true, true);

		$hostids = [];
		foreach ($data['triggers'] as $trigger) {
			$hostids += $trigger['hosts'] ? array_fill_keys(zbx_objectValues($trigger['hosts'], 'hostid'), '') : [];
		}

		$hosts = API::Host()->get([
			'output' => ['hostid', 'name', 'status'],
			'hostids' => array_keys($hostids),
			'preservekeys' => true
		]);

		$table = (new CTableInfo())->setHeader($header + [_('Age'), _('Info'), _('Ack'), _('Actions')]);

		foreach ($data['problems'] as $problem) {
			$trigger = $data['triggers'][$problem['objectid']];

			// Host name with hint box.
			$host = reset($trigger['hosts']);
			$host = $hosts[$host['hostid']];
			$host_name = (new CLinkAction($host['name']))->setMenuPopup(CMenuPopupHelper::getHost($host['hostid']));

			// Info.
			$info_icons = [];
			if ($problem['r_eventid'] != 0) {
				if ($problem['correlationid'] != 0) {
					$info_icons[] = makeInformationIcon(
						array_key_exists($problem['correlationid'], $data['correlations'])
							? _s('Resolved by correlation rule "%1$s".',
								$data['correlations'][$problem['correlationid']]['name']
							)
							: _('Resolved by correlation rule.')
					);
				}
				elseif ($problem['userid'] != 0) {
					$info_icons[] = makeInformationIcon(
						array_key_exists($problem['userid'], $data['users'])
							? _s('Resolved by user "%1$s".', getUserFullname($data['users'][$problem['userid']]))
							: _('Resolved by inaccessible user.')
					);
				}
			}

			// Clock.
			$clock = new CLink(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $problem['clock']),
				(new CUrl('zabbix.php'))
					->setArgument('action', 'problem.view')
					->setArgument('filter_triggerids[]', $trigger['triggerid'])
					->setArgument('filter_set', '1')
			);

			$table->addRow([
				$host_name,
				(new CCol([
					(new CLinkAction($problem['name']))
						->setHint(make_popup_eventlist(['comments' => $problem['comments']] + $trigger,
							$problem['eventid'], $back_url
						))
				]))->addClass(getSeverityStyle($problem['severity'])),
				$clock,
				zbx_date2age($problem['clock']),
				makeInformationList($info_icons),
				(new CLink($problem['acknowledged'] ? _('Yes') : _('No'),
					(new CUrl('zabbix.php'))
						->setArgument('action', 'acknowledge.edit')
						->setArgument('eventids', [$problem['eventid']])
						->setArgument('backurl', $back_url)
						->getUrl())
					)
					->addClass($problem['acknowledged'] ? ZBX_STYLE_GREEN : ZBX_STYLE_RED)
					->addClass(ZBX_STYLE_LINK_ALT),
				makeEventActionsIcons($problem['eventid'], $data['actions'], $data['mediatypes'], $data['users'],
					$config
				)
			]);
		}

		return [$table, $info];
	}
}
