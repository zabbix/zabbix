<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

$subfilter_options = [];

foreach (['hostids', 'tagnames', 'data'] as $key) {
	if (count($data[$key]) <= 1) {
		$subfilter_options[$key] = null;
		continue;
	}

	$subfilter_used = (bool) array_filter($data[$key], function ($elmnt) {
		return $elmnt['selected'];
	});

	foreach ($data[$key] as $value => $element) {
		if ($element['selected']) {
			$subfilter_options[$key][] = (new CSpan([
				(new CLinkAction($element['name']))->onClick(CHtml::encode(
					'view.unsetSubfilter('.json_encode(['subfilter_'.$key.'[]', $value]).')'
				)),
				' ',
				new CSup($element['count'])
			]))
				->addClass(ZBX_STYLE_NOWRAP)
				->addClass(ZBX_STYLE_SUBFILTER)
				->addClass(ZBX_STYLE_SUBFILTER_ENABLED);
		}
		else {
			if ($element['count'] == 0) {
				$subfilter_options[$key][] = (new CSpan([
					(new CSpan($element['name']))->addClass(ZBX_STYLE_GREY),
					' ',
					new CSup($element['count'])
				]))->addClass(ZBX_STYLE_SUBFILTER);
			}
			else {
				$subfilter_options[$key][] = (new CSpan([
					(new CLinkAction($element['name']))->onClick(CHtml::encode(
						'view.setSubfilter('.json_encode(['subfilter_'.$key.'[]', $value]).')'
					)),
					' ',
					new CSup(($subfilter_used ? '+' : '').$element['count'])
				]))
					->addClass(ZBX_STYLE_NOWRAP)
					->addClass(ZBX_STYLE_SUBFILTER);
			}
		}
	}
}

if (count($data['tags']) > 0) {
	$subfilter_options['tags'] = [];

	$subfilter_used = (bool) array_filter($data['tags'], function ($elmnt) {
		return (bool) array_sum(array_column($elmnt, 'selected'));
	});

	foreach ($data['tags'] as $tag => $tag_values) {
		$tag_values = array_map(function ($element) use ($tag, $subfilter_used) {
			if ($element['name'] === '') {
				$element_name = _('None');
				$element_style = 'font-style: italic;';
			}
			else {
				$element_name = $element['name'];
				$element_style = null;
			}

			if ($element['selected']) {
				return (new CSpan([
					(new CLinkAction($element_name))
						->addStyle($element_style)
						->onClick(CHtml::encode(
							'view.unsetSubfilter('.json_encode(['subfilter_tags['.$tag.'][]', $element['name']]).')'
						)),
					' ',
					new CSup($element['count'])
				]))
					->addClass(ZBX_STYLE_NOWRAP)
					->addClass(ZBX_STYLE_SUBFILTER)
					->addClass(ZBX_STYLE_SUBFILTER_ENABLED);
			}
			else {
				if ($element['count'] == 0) {
					return (new CSpan([
						(new CSpan($element_name))
							->addStyle($element_style)
							->addClass(ZBX_STYLE_GREY),
						' ',
						new CSup($element['count'])
					]))->addClass(ZBX_STYLE_SUBFILTER);
				}
				else {
					return (new CSpan([
						(new CLinkAction($element_name))
							->addStyle($element_style)
							->onClick(CHtml::encode(
								'view.setSubfilter('.json_encode(['subfilter_tags['.$tag.'][]', $element['name']]).')'
							)),
						' ',
						new CSup(($subfilter_used ? '+' : '').$element['count'])
					]))
						->addClass(ZBX_STYLE_NOWRAP)
						->addClass(ZBX_STYLE_SUBFILTER);
				}
			}
		}, $tag_values);

		$subfilter_options['tags'][$tag] = (new CDiv([
			new CTag('label', true, $tag.': '),
			(new CDiv($tag_values))->addClass('subfilter-options')
		]))->addClass('subfilter-option-grid');
	}
}
else {
	$subfilter_options['tags'] = null;
}

$subfilter = (new CTableInfo())
	->addRow([[
		new CTag('h4', true, [
			_('Subfilter'), ' ', (new CSpan(_('affects only filtered data')))->addClass(ZBX_STYLE_GREY)
		])
	]])
	->addRow(
		$subfilter_options['hostids']
			? [[
				new CTag('h3', true, _('Hosts')),
				$subfilter_options['hostids']
			]]
			: null
	)
	->addRow(
		$subfilter_options['tagnames']
			? [[
				new CTag('h3', true, _('Tags')),
				$subfilter_options['tagnames']
			]]
			: null
	)
	->addRow(
		$subfilter_options['tags']
			? [[
				new CTag('h3', true, _('Tag values')),
				$subfilter_options['tags']
			]]
			: null
	)
	->addRow(
		$subfilter_options['data']
			? [[
				new CTag('h3', true, _('Data')),
				$subfilter_options['data']
			]]
			: null
	)
	->addClass('tabfilter-subfilter')
	->setId('latest-data-subfilter')
	->show();
