<?php declare(strict_types = 0);
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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CPartial $this
 * @var array    $data
 */

$tag_filter_table = (new CTable())
	->setId('tag-filter-table')
	->setAttribute('style', 'width: 100%;')
	->setHeader([_('Host groups'), _('Tags'), _('Action')]);

foreach ($data['tag_filters'] as $key => $tag_filter) {
	$action = [
		(new CButtonLink(_('Edit')))->addClass('js-edit-tag-filter'),
		(new CButtonLink(_('Remove')))->addClass('js-remove-tag-filter'),
		(new CVar('tag_filters['.$key.'][groupid]', $tag_filter['groupid']))->removeId(),
		(new CVar('tag_filters['.$key.'][tags]', $tag_filter['tags']))->removeId()
	];

	$first_index = key($tag_filter['tags']);

	if ($tag_filter['tags'][$first_index]['tag'] === '' && $tag_filter['tags'][$first_index]['value'] === '') {
		$badges = italic(_('All tags'));
	}
	else {
		$badges = $data['tag_filters_badges'][$tag_filter['groupid']];
	}

	$tag_filter_table->addRow([
		(new CCol($tag_filter['name']))
			->addClass(ZBX_STYLE_WORDWRAP)
			->addStyle('max-width: '.ZBX_TEXTAREA_BIG_WIDTH.'px;'),
		$badges,
		(new CCol($action))->addClass(ZBX_STYLE_NOWRAP)
	]);
}

$tag_filter_table->addItem(
	(new CTag('tfoot', true))
		->addItem(new CCol((new CButtonLink(_('Add')))->addClass('js-add-tag-filter')))
);

$tag_filter_table->show();
