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
 * @var CPartial $this
 * @var array    $data
 */

$subfilters_expanded = array_key_exists('subfilters_expanded', $data) ? $data['subfilters_expanded'] : [];
$subfilters = $data['subfilters'];

$subfilter_options = [];

foreach (['hostids', 'tagnames', 'state', 'data'] as $key) {
	if (($key === 'hostids' || $key === 'tagnames' || $key === 'state') && count($subfilters[$key]) == 0) {
		$subfilter_options[$key] = null;

		continue;
	}

	$subfilter_options[$key] = [];

	// Remove non-selected filter fields with 0 occurrences (for hosts and tag names).
	if ($key === 'hostids' || $key === 'tagnames' || $key === 'state') {
		$subfilters[$key] = array_filter($subfilters[$key], function ($field) {
			return $field['selected'] || $field['count'] > 0;
		});
	}

	if ($key === 'state'
			&& (!$subfilters[$key] || (count($subfilters[$key]) == 1 && !current($subfilters[$key])['selected']))) {
		$subfilter_options[$key] = null;

		continue;
	}

	$subfilter_used = (bool) array_filter($subfilters[$key], function ($field) {
		return $field['selected'];
	});

	$subfilter_options_count = count($subfilters[$key]);
	$subfilters[$key] = CControllerLatest::getTopPrioritySubfilters($subfilters[$key]);

	foreach ($subfilters[$key] as $value => $element) {
		if ($element['selected']) {
			$subfilter_options[$key][] = (new CSpan([
				(new CLinkAction($element['name']))
					->setAttribute('data-key', $key)
					->setAttribute('data-value', $value)
					->onClick('view.unsetSubfilter([`subfilter_${this.dataset.key}[]`, this.dataset.value]);'),
				' ',
				new CSup($element['count'])
			]))
				->addClass(ZBX_STYLE_SUBFILTER)
				->addClass(ZBX_STYLE_SUBFILTER_ENABLED);
		}
		else {
			if ($element['count'] > 0 || $key === 'data') {
				// Data subfilter counters are only known when the filter is in use.
				$count_text = $key !== 'data' || $subfilter_used ? $element['count'] : '';

				$subfilter_options[$key][] = (new CSpan([
					(new CLinkAction($element['name']))
						->setAttribute('data-key', $key)
						->setAttribute('data-value', $value)
						->onClick('view.setSubfilter([`subfilter_${this.dataset.key}[]`, this.dataset.value]);'),
					' ',
					new CSup(($subfilter_used ? '+' : '').$count_text)
				]))->addClass(ZBX_STYLE_SUBFILTER);
			}
			else {
				$subfilter_options[$key][] = (new CSpan([
					(new CSpan($element['name']))->addClass(ZBX_STYLE_GREY),
					' ',
					new CSup($element['count'])
				]))->addClass(ZBX_STYLE_SUBFILTER);
			}
		}
	}

	if ($subfilter_options_count > count($subfilter_options[$key])) {
		$subfilter_options[$key][] = new CSpan('...');
	}
}

if (count($subfilters['tags']) > 0) {
	$subfilter_options['tags'] = [];

	$subfilter_used = (bool) array_filter($subfilters['tags'], function ($field) {
		return (bool) array_sum(array_column($field, 'selected'));
	});
	$tags_expanded = array_key_exists('tags', $subfilters_expanded);

	$index = 0;
	foreach (CControllerLatest::getTopPriorityTagValueSubfilters($subfilters['tags']) as $tags_group) {
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
						->setAttribute('data-key', $tag)
						->setAttribute('data-value', $element['name'])
						->onClick(
							'view.unsetSubfilter([`subfilter_tags[${encodeURIComponent(this.dataset.key)}][]`,'.
								'this.dataset.value]'.
							');'
						),
					' ',
					new CSup($element['count'])
				]))
					->addClass(ZBX_STYLE_SUBFILTER)
					->addClass(ZBX_STYLE_SUBFILTER_ENABLED);
			}

			return (new CSpan([
				(new CLinkAction($element_name))
					->addStyle($element_style)
					->setAttribute('data-key', $tag)
					->setAttribute('data-value', $element['name'])
					->onClick(
						'view.setSubfilter([`subfilter_tags[${encodeURIComponent(this.dataset.key)}][]`,'.
							'this.dataset.value]'.
						');'
					),
				' ',
				new CSup(($subfilter_used ? '+' : '').$element['count'])
			]))->addClass(ZBX_STYLE_SUBFILTER);
		}, $tags_group['values']);

		$tag_values = $tags_group['trimmed'] ? [$tag_values, new CSpan('...')] : $tag_values;

		$subfilter_options['tags'][$tag] = (new CDiv([
			new CTag('label', true, $tag.': '),
			(new CExpandableSubfilter('tagnames', $tag_values, array_key_exists('tagnames', $subfilters_expanded)))
				->addClass(CExpandableSubfilter::ZBX_STYLE_EXPANDABLE_TEN_LINES)
				->addClass('subfilter-options')
		]))->addClass('subfilter-option-grid');

		if (!$tags_expanded && ++$index > CControllerLatest::SUBFILTERS_TAG_VALUE_ROWS) {
			$subfilter_options['tags'][$tag]->addClass('display-none');
		}
	}

	if (!$tags_expanded && count($subfilter_options['tags']) > CControllerLatest::SUBFILTERS_TAG_VALUE_ROWS) {
		$subfilter_options['tags'][] = (new CButton('expand_tag_values'))
			->setAttribute('data-name', 'tags')
			->addClass(ZBX_STYLE_BTN_ICON)
			->addClass(ZBX_ICON_MORE);
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
	]], ZBX_STYLE_HOVER_NOBG)
	->addRow(
		$subfilter_options['hostids'] !== null
			? [[
				new CTag('h3', true, _('Hosts')),
				(new CExpandableSubfilter('hostids', $subfilter_options['hostids'],
					array_key_exists('hostids', $subfilters_expanded)
				))->addClass(CExpandableSubfilter::ZBX_STYLE_EXPANDABLE_TEN_LINES)
			]]
			: null
	)
	->addRow(
		$subfilter_options['tagnames'] !== null
			? [[
				new CTag('h3', true, _('Tags')),
				(new CExpandableSubfilter('tagnames', $subfilter_options['tagnames'],
					array_key_exists('tagnames', $subfilters_expanded)
				))->addClass(CExpandableSubfilter::ZBX_STYLE_EXPANDABLE_TEN_LINES)
			]]
			: null
	)
	->addRow(
		$subfilter_options['tags'] !== null
			? [[
				new CTag('h3', true, _('Tag values')),
				$subfilter_options['tags']
			]]
			: null
	)
	->addRow(
		$subfilter_options['state'] !== null
			? [[
				new CTag('h3', true, _('State')),
				$subfilter_options['state']
			]]
			: null
	)
	->addRow(
		$subfilter_options['data'] !== null
			? [[
				new CTag('h3', true, _('Data')),
				$subfilter_options['data']
			]]
			: null
	)
	->addClass('tabfilter-subfilter')
	->setId('latest-data-subfilter')
	->show();
