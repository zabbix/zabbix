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
	if (!array_key_exists($key, $data) || count($data[$key]) <= 1) {
		$subfilter_options[$key] = null;
		continue;
	}
	else {
		$subfilter_options[$key] = [];
	}

	// Remove non-selected filter fields with 0 occurrences.
	$data[$key] = array_filter($data[$key], function ($field) {
		return ($field['selected'] || $field['count'] != 0);
	});

	$subfilter_used = (bool) array_filter($data[$key], function ($field) {
		return $field['selected'];
	});

	$subfilter_options_count = count($data[$key]);
	$data[$key] = CControllerLatest::getTopPrioritySubfilters($data[$key]);

	foreach ($data[$key] as $value => $element) {
		if ($element['selected']) {
			$subfilter_options[$key][] = (new CSpan([
				(new CLinkAction($element['name']))->onClick(CHtml::encode(
					'view.unsetSubfilter('.json_encode(['subfilter_'.$key.'[]', $value]).')'
				)),
				' ',
				new CSup($element['count'])
			]))
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
				]))->addClass(ZBX_STYLE_SUBFILTER);
			}
		}
	}

	if ($subfilter_options_count > count($subfilter_options[$key])) {
		$subfilter_options[$key][] = new CSpan('...');
	}
}

if (count($data['tags']) > 0) {
	$subfilter_options['tags'] = [];

	$subfilter_used = (bool) array_filter($data['tags'], function ($field) {
		return (bool) array_sum(array_column($field, 'selected'));
	});

	foreach (CControllerLatest::getTopPriorityTagValueSubfilters($data['tags']) as $tags_group) {
		$tag = $tags_group['name'];

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
						->onClick('view.unsetSubfilter('.json_encode([
							'subfilter_tags['.$tag.'][]',
							$element['name']
						]).')'),
					' ',
					new CSup($element['count'])
				]))
					->addClass(ZBX_STYLE_SUBFILTER)
					->addClass(ZBX_STYLE_SUBFILTER_ENABLED);
			}
			else {
				return (new CSpan([
					(new CLinkAction($element_name))
						->addStyle($element_style)
						->onClick('view.setSubfilter('.json_encode([
							'subfilter_tags['.$tag.'][]',
							$element['name']
						]).')'),
					' ',
					new CSup(($subfilter_used ? '+' : '').$element['count'])
				]))->addClass(ZBX_STYLE_SUBFILTER);
			}
		}, $tags_group['values']);

		$tag_values_row = (count($data['tags'][$tag]) > CControllerLatest::SUBFILTERS_VALUES_PER_ROW)
			? [$tag_values, new CSpan('...')]
			: $tag_values;

		$subfilter_options['tags'][$tag] = (new CDiv([
			new CTag('label', true, $tag.': '),
			(new CDiv($tag_values_row))->addClass('subfilter-options')
		]))->addClass('subfilter-option-grid');
	}

	if (count($data['tags']) > CControllerLatest::SUBFILTERS_TAG_VALUE_ROWS) {
		$subfilter_options['tags'][] = new CSpan('...');
	}
}
else {
	$subfilter_options['tags'] = null;
}

(new CTableInfo())
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
