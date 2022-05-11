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
 * @var CPartial $this
 */

$tag_filter_table = (new CTable())
	->setId('tag-filter-table')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Host group'), _('Tags'), _('Action')]);

$previous_name = '';
foreach ($data['tag_filters'] as $key => $tag_filter) {
	if ($previous_name === $tag_filter['name']) {
		$tag_filter['name'] = '';
	}
	else {
		$previous_name = $tag_filter['name'];
	}

	if ($tag_filter['tag'] !== '' && $tag_filter['value'] !== '') {
		$tag_value = $tag_filter['tag'].NAME_DELIMITER.$tag_filter['value'];
	}
	elseif ($tag_filter['tag'] !== '') {
		$tag_value = $tag_filter['tag'];
	}
	else {
		$tag_value = italic(_('All tags'));
	}

	$action = [
		(new CSimpleButton(_('Remove')))->addClass(ZBX_STYLE_BTN_LINK)
			->onClick('javascript: usergroups.removeTagFilterRow($(this));'),
		(new CVar('tag_filters['.$key.'][groupid]', $tag_filter['groupid']))->removeId(),
		(new CVar('tag_filters['.$key.'][tag]', $tag_filter['tag']))->removeId(),
		(new CVar('tag_filters['.$key.'][value]', $tag_filter['value']))->removeId(),
		(new CVar('tag_filter', json_encode([
			'groupid' => $tag_filter['groupid'],
			'tag' => $tag_filter['tag'],
			'value' => $tag_filter['value']
		])))->removeId()->setEnabled(false)
	];

	$tag_filter_table->addRow([$tag_filter['name'], $tag_value, $action]);
}

$tag_filter_table->show();
