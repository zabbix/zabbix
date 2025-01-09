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


/**
 * @var CView $this
 * @var array $data
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
	])
	->setPageNavigation($data['paging']);

$csrf_token = CCsrfTokenHelper::get('host_prototypes.php');

foreach ($this->data['hostPrototypes'] as $hostPrototype) {
	// name
	$name = [];
	$name[] = makeHostPrototypeTemplatePrefix($hostPrototype['hostid'], $data['parent_templates'],
		$data['allowed_ui_conf_templates']
	);
	$name[] = new CLink($hostPrototype['name'],
		(new CUrl('host_prototypes.php'))
			->setArgument('form', 'update')
			->setArgument('parent_discoveryid', $data['discovery_rule']['itemid'])
			->setArgument('hostid', $hostPrototype['hostid'])
			->setArgument('context', $data['context'])
	);

	// template list
	if (empty($hostPrototype['templates'])) {
		$hostTemplates = '';
	}
	else {
		$hostTemplates = [];
		order_result($hostPrototype['templates'], 'name');

		foreach ($hostPrototype['templates'] as $template) {
			$caption = [];

			if ($data['allowed_ui_conf_templates']
					&& array_key_exists($template['templateid'], $data['writable_templates'])) {
				$template_url = (new CUrl('zabbix.php'))
					->setArgument('action', 'popup')
					->setArgument('popup', 'template.edit')
					->setArgument('templateid', $template['templateid'])
					->getUrl();

				$caption[] = (new CLink($template['name'], $template_url))
					->addClass(ZBX_STYLE_LINK_ALT)
					->addClass(ZBX_STYLE_GREY)
					->setAttribute('data-templateid', $template['templateid'])
					->setAttribute('data-action', 'template.edit');
			}
			else {
				$caption[] = (new CSpan($template['name']))->addClass(ZBX_STYLE_GREY);
			}

			$linkedTemplates = $this->data['linkedTemplates'][$template['templateid']]['parentTemplates'];
			if ($linkedTemplates) {
				order_result($linkedTemplates, 'name');

				$caption[] = ' (';
				foreach ($linkedTemplates as $tpl) {
					$tpl_url = (new CUrl('zabbix.php'))
						->setArgument('action', 'popup')
						->setArgument('popup', 'template.edit')
						->setArgument('templateid', $tpl['templateid'])
						->getUrl();

					if (array_key_exists($tpl['templateid'], $data['writable_templates'])) {
						$caption[] = (new CLink($tpl['name'], $tpl_url))
							->setAttribute('data-templateid', $tpl['templateid'])
							->setAttribute('data-action', 'template.edit')
							->addClass(ZBX_STYLE_LINK_ALT)
							->addClass(ZBX_STYLE_GREY);
					}
					else {
						$caption[] = (new CSpan($tpl['name']))->addClass(ZBX_STYLE_GREY);
					}

					$caption[] = ', ';
				}
				array_pop($caption);

				$caption[] = ')';
			}

			$hostTemplates[] = $caption;
			$hostTemplates[] = ', ';
		}

		if ($hostTemplates) {
			array_pop($hostTemplates);
		}
	}

	// status
	$status = (new CLink(
		($hostPrototype['status'] == HOST_STATUS_NOT_MONITORED) ? _('No') : _('Yes'),
		(new CUrl('host_prototypes.php'))
			->setArgument('group_hostid[]', $hostPrototype['hostid'])
			->setArgument('parent_discoveryid', $data['discovery_rule']['itemid'])
			->setArgument('action', ($hostPrototype['status'] == HOST_STATUS_NOT_MONITORED)
				? 'hostprototype.massenable'
				: 'hostprototype.massdisable'
			)
			->setArgument('context', $data['context'])
			->setArgument('backurl', $url)
			->getUrl()
	))
		->addCsrfToken($csrf_token)
		->addClass(ZBX_STYLE_LINK_ACTION)
		->addClass(itemIndicatorStyle($hostPrototype['status']));

	$nodiscover = ($hostPrototype['discover'] == ZBX_PROTOTYPE_NO_DISCOVER);
	$discover = (new CLink($nodiscover ? _('No') : _('Yes'),
			(new CUrl('host_prototypes.php'))
				->setArgument('hostid', $hostPrototype['hostid'])
				->setArgument('parent_discoveryid', $data['discovery_rule']['itemid'])
				->setArgument('action', 'hostprototype.updatediscover')
				->setArgument('discover', $nodiscover ? ZBX_PROTOTYPE_DISCOVER : ZBX_PROTOTYPE_NO_DISCOVER)
				->setArgument('context', $data['context'])
				->setArgument('backurl', $url)
				->getUrl()
		))
			->addCsrfToken($csrf_token)
			->addClass(ZBX_STYLE_LINK_ACTION)
			->addClass($nodiscover ? ZBX_STYLE_RED : ZBX_STYLE_GREEN);

	$hostTable->addRow([
		new CCheckBox('group_hostid['.$hostPrototype['hostid'].']', $hostPrototype['hostid']),
		$name,
		$hostTemplates,
		$status,
		$discover,
		$data['tags'][$hostPrototype['hostid']]
	]);
}

// append table to form
$itemForm->addItem([
	$hostTable,
	new CActionButtonList('action', 'group_hostid',
		[
			'hostprototype.massenable' => [
				'name' => _('Create enabled'),
				'confirm_singular' => _('Create hosts from selected prototype as enabled?'),
				'confirm_plural' => _('Create hosts from selected prototypes as enabled?'),
				'csrf_token' => $csrf_token
			],
			'hostprototype.massdisable' => [
				'name' => _('Create disabled'),
				'confirm_singular' => _('Create hosts from selected prototype as disabled?'),
				'confirm_plural' => _('Create hosts from selected prototypes as disabled?'),
				'csrf_token' => $csrf_token
			],
			'hostprototype.massdelete' => [
				'name' => _('Delete'),
				'confirm_singular' => _('Delete selected host prototype?'),
				'confirm_plural' => _('Delete selected host prototypes?'),
				'csrf_token' => $csrf_token
			]
		],
		$data['discovery_rule']['itemid']
	)
]);

$html_page
	->addItem($itemForm)
	->show();

(new CScriptTag('
	view.init('.json_encode([
		'context' => $data['context'],
		'checkbox_hash' => $data['discovery_rule']['itemid']
	]).');
'))
	->setOnDocumentReady()
	->show();
