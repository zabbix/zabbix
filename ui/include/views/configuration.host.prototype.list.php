<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 */

require_once dirname(__FILE__).'/js/configuration.host.prototype.list.js.php';

$html_page = (new CHtmlPage())
	->setTitle(_('Host prototypes'))
	->setDocUrl(CDocHelper::getUrl($data['context'] === 'host'
		? CDocHelper::DATA_COLLECTION_HOST_PROTOTYPE_LIST
		: CDocHelper::DATA_COLLECTION_TEMPLATES_PROTOTYPE_LIST
	))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					new CRedirectButton(_('Create host prototype'),
						(new CUrl('host_prototypes.php'))
							->setArgument('form', 'create')
							->setArgument('parent_discoveryid', $data['parent_discoveryid'])
							->setArgument('context', $data['context'])
					)
				)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->setNavigation(
		getHostNavigation('hosts', $this->data['discovery_rule']['hostid'], $this->data['parent_discoveryid'])
	);

$url = (new CUrl('host_prototypes.php'))
	->setArgument('parent_discoveryid', $data['parent_discoveryid'])
	->setArgument('context', $data['context'])
	->getUrl();

// create form
$itemForm = (new CForm('post', $url))
	->setName('hosts')
	->addVar('parent_discoveryid', $data['parent_discoveryid']);

// create table
$hostTable = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_hosts'))->onClick("checkAll('".$itemForm->getName()."', 'all_hosts', 'group_hostid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $url),
		_('Templates'),
		make_sorting_header(_('Create enabled'), 'status', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Discover'), 'discover', $data['sort'], $data['sortorder'], $url),
		_('Tags')
	]);

$csrf_token = CCsrfTokenHelper::get('host_prototypes.php');

foreach ($this->data['hostPrototypes'] as $host_prototype) {
	$name = [];

	if (array_key_exists($host_prototype['templateid'], $data['parent_host_prototypes'])) {
		$parent_host_prototype = $data['parent_host_prototypes'][$host_prototype['templateid']];

		if ($parent_host_prototype['editable']) {
			$parent_template_name = (new CLink(CHtml::encode($parent_host_prototype['template_name']),
				(new CUrl('host_prototypes.php'))
					->setArgument('parent_discoveryid', $parent_host_prototype['ruleid'])
					->setArgument('context', 'template')
			))->addClass(ZBX_STYLE_LINK_ALT);
		}
		else {
			$parent_template_name = new CSpan(CHtml::encode($parent_host_prototype['template_name']));
		}

		$name[] = [$parent_template_name->addClass(ZBX_STYLE_GREY), NAME_DELIMITER];
	}

	$name[] = new CLink(CHtml::encode($host_prototype['name']),
		(new CUrl('host_prototypes.php'))
			->setArgument('form', 'update')
			->setArgument('parent_discoveryid', $data['discovery_rule']['itemid'])
			->setArgument('hostid', $host_prototype['hostid'])
			->setArgument('context', $data['context'])
	);

	$host_templates = [];

	if ($host_prototype['templates']) {
		order_result($host_prototype['templates'], 'name');

		foreach ($host_prototype['templates'] as $template) {
			if ($host_templates) {
				$host_templates[] = ', ';
			}

			if (array_key_exists($template['templateid'], $data['editable_templates'])) {
				$host_templates[] = (new CLink($template['name'],
					(new CUrl('templates.php'))
						->setArgument('form', 'update')
						->setArgument('templateid', $template['templateid'])
				))
					->addClass(ZBX_STYLE_LINK_ALT)
					->addClass(ZBX_STYLE_GREY);
			}
			else {
				$host_templates[] = (new CSpan($template['name']))->addClass(ZBX_STYLE_GREY);
			}
		}
	}

	$status = (new CLink(
		($host_prototype['status'] == HOST_STATUS_NOT_MONITORED) ? _('No') : _('Yes'),
		(new CUrl('host_prototypes.php'))
			->setArgument('group_hostid[]', $host_prototype['hostid'])
			->setArgument('parent_discoveryid', $data['discovery_rule']['itemid'])
			->setArgument('action', ($host_prototype['status'] == HOST_STATUS_NOT_MONITORED)
				? 'hostprototype.massenable'
				: 'hostprototype.massdisable'
			)
			->setArgument('context', $data['context'])
			->getUrl()
	))
		->addCsrfToken($csrf_token)
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass(itemIndicatorStyle($host_prototype['status']));

	$no_discover = ($host_prototype['discover'] == ZBX_PROTOTYPE_NO_DISCOVER);
	$discover = (new CLink($no_discover ? _('No') : _('Yes'),
			(new CUrl('host_prototypes.php'))
				->setArgument('hostid', $host_prototype['hostid'])
				->setArgument('parent_discoveryid', $data['discovery_rule']['itemid'])
				->setArgument('action', 'hostprototype.updatediscover')
				->setArgument('discover', $no_discover ? ZBX_PROTOTYPE_DISCOVER : ZBX_PROTOTYPE_NO_DISCOVER)
				->setArgument('context', $data['context'])
				->getUrl()
		))
			->addCsrfToken($csrf_token)
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass($no_discover ? ZBX_STYLE_RED : ZBX_STYLE_GREEN);

	$hostTable->addRow([
		new CCheckBox('group_hostid['.$host_prototype['hostid'].']', $host_prototype['hostid']),
		$name,
		$host_templates,
		$status,
		$discover,
		$data['tags'][$host_prototype['hostid']]
	]);
}

// append table to form
$itemForm->addItem([
	$hostTable,
	$data['paging'],
	new CActionButtonList('action', 'group_hostid',
		[
			'hostprototype.massenable' => ['name' => _('Create enabled'),
				'confirm' => _('Create hosts from selected prototypes as enabled?'), 'csrf_token' => $csrf_token
			],
			'hostprototype.massdisable' => ['name' => _('Create disabled'),
				'confirm' => _('Create hosts from selected prototypes as disabled?'), 'csrf_token' => $csrf_token
			],
			'hostprototype.massdelete' => ['name' => _('Delete'),
				'confirm' => _('Delete selected host prototypes?'), 'csrf_token' => $csrf_token
			]
		],
		$data['discovery_rule']['itemid']
	)
]);

$html_page
	->addItem($itemForm)
	->show();
