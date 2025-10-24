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
 * @var array $data
 */

$this->includeJsFile('host.prototype.list.js.php');

$form = (new CForm())->setName('host_prototype');

$url = (new CUrl('zabbix.php'))
	->setArgument('action', $data['action'])
	->setArgument('parent_discoveryid', $data['parent_discoveryid'])
	->setArgument('context', $data['context'])
	->getUrl();

$table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_hosts'))->onClick("checkAll('".$form->getName()."', 'all_hosts', 'group_hostid');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		make_sorting_header(_('Name'), 'name', $data['sort'], $data['sortorder'], $url),
		_('Templates'),
		make_sorting_header(_('Create enabled'), 'status', $data['sort'], $data['sortorder'], $url),
		make_sorting_header(_('Discover'), 'discover', $data['sort'], $data['sortorder'], $url),
		_('Tags')
	])
	->setPageNavigation($data['paging']);

foreach ($data['host_prototypes'] as $host_prototype) {
	$name = [makeHostPrototypeTemplatePrefix($host_prototype['hostid'], $data['parent_templates'],
		$data['allowed_ui_conf_templates']
	)];

	if ($host_prototype['flags'] & ZBX_FLAG_DISCOVERY_CREATED) {
		$name[] = (new CLink($data['source_link_data']['name'],
			(new CUrl('zabbix.php'))
				->setArgument('action', 'popup')
				->setArgument('popup', 'host.prototype.edit')
				->setArgument('parent_discoveryid', $data['source_link_data']['parent_itemid'])
				->setArgument('hostid', $host_prototype['discoveryData']['parent_hostid'])
				->setArgument('context', 'host')
				->getUrl()
		))
			->addClass(ZBX_STYLE_LINK_ALT)
			->addClass(ZBX_STYLE_ORANGE);

		$name[] = NAME_DELIMITER;
	}

	$name[] = new CLink($host_prototype['name'],
		(new CUrl('zabbix.php'))
			->setArgument('action', 'popup')
			->setArgument('popup', 'host.prototype.edit')
			->setArgument('parent_discoveryid', $data['discovery_rule']['itemid'])
			->setArgument('hostid', $host_prototype['hostid'])
			->setArgument('context', $data['context'])
	);

	if (!$host_prototype['templates']) {
		$host_templates = '';
	}
	else {
		$host_templates = [];

		CArrayHelper::sort($host_prototype['templates'], ['name']);

		foreach ($host_prototype['templates'] as $template) {
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
					->addClass(ZBX_STYLE_GREY);
			}
			else {
				$caption[] = (new CSpan($template['name']))->addClass(ZBX_STYLE_GREY);
			}

			$linked_templates = $data['linked_templates'][$template['templateid']]['parentTemplates'];

			if ($linked_templates) {
				CArrayHelper::sort($linked_templates, ['name']);

				$caption[] = ' (';
				foreach ($linked_templates as $linked_template) {
					$linked_template_url = (new CUrl('zabbix.php'))
						->setArgument('action', 'popup')
						->setArgument('popup', 'template.edit')
						->setArgument('templateid', $linked_template['templateid'])
						->getUrl();

					if (array_key_exists($linked_template['templateid'], $data['writable_templates'])) {
						$caption[] = (new CLink($linked_template['name'], $linked_template_url))
							->addClass(ZBX_STYLE_LINK_ALT)
							->addClass(ZBX_STYLE_GREY);
					}
					else {
						$caption[] = (new CSpan($linked_template['name']))->addClass(ZBX_STYLE_GREY);
					}

					$caption[] = ', ';
				}
				array_pop($caption);

				$caption[] = ')';
			}

			$host_templates[] = $caption;
			$host_templates[] = ', ';
		}

		if ($host_templates) {
			array_pop($host_templates);
		}
	}

	$status_disabled = $host_prototype['status'] == HOST_STATUS_NOT_MONITORED;
	$status_toggle = $data['is_parent_discovered']
		? (new CSpan($status_disabled ? _('No') : _('Yes')))
		: (new CLink($status_disabled ? _('No') : _('Yes')))
			->setAttribute('data-hostid', $host_prototype['hostid'])
			->setAttribute('data-status', $status_disabled ? HOST_STATUS_MONITORED : HOST_STATUS_NOT_MONITORED)
			->addClass($status_disabled ? 'js-enable' : 'js-disable')
			->addClass(ZBX_STYLE_LINK_ACTION);

	$no_discover = $host_prototype['discover'] == ZBX_PROTOTYPE_NO_DISCOVER;
	$discover_toggle = $data['is_parent_discovered']
		? (new CSpan($no_discover ? _('No') : _('Yes')))
		: (new CLink($no_discover ? _('No') : _('Yes')))
			->setAttribute('data-hostid', $host_prototype['hostid'])
			->setAttribute('data-discover', $no_discover ? ZBX_PROTOTYPE_DISCOVER : ZBX_PROTOTYPE_NO_DISCOVER)
			->addClass($no_discover ? 'js-enable' : 'js-disable')
			->addClass(ZBX_STYLE_LINK_ACTION);

	$table->addRow([
		new CCheckBox('group_hostid['.$host_prototype['hostid'].']', $host_prototype['hostid']),
		$name,
		$host_templates,
		$status_toggle->addClass(itemIndicatorStyle($host_prototype['status'])),
		$discover_toggle->addClass($no_discover ? ZBX_STYLE_RED : ZBX_STYLE_GREEN),
		(new CDiv($data['tags'][$host_prototype['hostid']]))->addClass(ZBX_STYLE_TAGS_WRAPPER)
	]);
}

$buttons = [
	'host.prototype.massenable' => [
		'content' => (new CSimpleButton(_('Create enabled')))
			->setAttribute('data-status', HOST_STATUS_MONITORED)
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass('js-no-chkbxrange')
			->addClass('js-massenable')
	],
	'host.prototype.massdisable' => [
		'content' => (new CSimpleButton(_('Create disabled')))
			->setAttribute('data-status', HOST_STATUS_NOT_MONITORED)
			->addClass(ZBX_STYLE_BTN_ALT)
			->addClass('js-no-chkbxrange')
			->addClass('js-massdisable')
	]
];

if ($data['is_parent_discovered']) {
	foreach ($buttons as &$button) {
		$button['content']
			->setEnabled(false)
			->setAttribute('data-disabled', $data['is_parent_discovered']);
	}
	unset($button);
}

$buttons['host.prototype.massdelete'] = [
	'content' => (new CSimpleButton(_('Delete')))
		->addClass(ZBX_STYLE_BTN_ALT)
		->addClass('js-no-chkbxrange')
		->addClass('js-massdelete')
];

$form->addItem([
	$table,
	new CActionButtonList('action', 'group_hostid', $buttons, 'host_prototypes_'.$data['parent_discoveryid'])
]);

(new CHtmlPage())
	->setTitle(_('Host prototypes'))
	->setDocUrl(CDocHelper::getUrl($data['context'] === 'host'
		? CDocHelper::DATA_COLLECTION_HOST_PROTOTYPE_LIST
		: CDocHelper::DATA_COLLECTION_TEMPLATES_PROTOTYPE_LIST
	))
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					(new CSimpleButton(_('Create host prototype')))
						->setId('js-create')
						->setEnabled(!$data['is_parent_discovered'])
				)
		))->setAttribute('aria-label', _('Content controls'))
	)
	->setNavigation(getHostNavigation('hosts', $data['discovery_rule']['hostid'], $data['parent_discoveryid']))
	->addItem($form)
	->show();

(new CScriptTag(
	'view.init('.json_encode([
		'context' => $data['context'],
		'parent_discoveryid' => $data['parent_discoveryid']
	]).');'
))
	->setOnDocumentReady()
	->show();
