<?php declare(strict_types = 0);
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

$subfilter_options = [
	'tagnames' => [],
	'tags' => []
];

$selected_tagnames = [];
$selected_tags = [];


if (count($data['tagnames']) > 0) {

	// Remove non-selected filter options with 0 occurrences.
	$data['tagnames'] = array_filter($data['tagnames'], static function ($element) {
		return ($element['selected'] || $element['count'] != 0);
	});

	$subfilter_used = (bool) array_filter($data['tagnames'], static function ($element) {
		return $element['selected'];
	});

	$subfilter_options_count = count($data['tagnames']);
	$data['tagnames'] = CControllerCharts::getMostSevereSubfilters($data['tagnames']);

	foreach ($data['tagnames'] as $tag_name => $element) {
		if ($element['selected']) {
			$subfilter_options['tagnames'][] = (new CSpan([
				(new CLinkAction($element['name']))
					->addClass('js-subfilter-unset')
					->setAttribute('data-tag', $tag_name),
				' ',
				new CSup($element['count'])
			]))
				->addClass(ZBX_STYLE_SUBFILTER)
				->addClass(ZBX_STYLE_SUBFILTER_ENABLED);

			$selected_tagnames[] = $tag_name;
		}
		else {
			if ($element['count'] == 0) {
				$subfilter_options['tagnames'][] = (new CSpan([
					(new CSpan($element['name']))->addClass(ZBX_STYLE_GREY),
					' ',
					new CSup($element['count'])
				]))->addClass(ZBX_STYLE_SUBFILTER);
			}
			else {
				$subfilter_options['tagnames'][] = (new CSpan([
					(new CLinkAction($element['name']))
						->addClass('js-subfilter-set')
						->setAttribute('data-tag', $tag_name),
					' ',
					new CSup(($subfilter_used ? '+' : '').$element['count'])
				]))->addClass(ZBX_STYLE_SUBFILTER);
			}
		}
	}

	if ($subfilter_options_count > count($subfilter_options['tagnames'])) {
		$subfilter_options['tagnames'][] = new CSpan('...');
	}
}

if (count($data['tags']) > 0) {
	$subfilter_used = (bool) array_filter($data['tags'], static function ($element) {
		return (bool) array_sum(array_column($element, 'selected'));
	});

	$tags_count = count($data['tags']);
	$data['tags'] = CControllerCharts::getMostSevereTagValueSubfilters($data['tags']);

	foreach ($data['tags'] as $tag_name => $tag_values) {

		// Remove non-selected filter options with 0 occurrences.
		$tag_values = array_filter($tag_values, static function ($element) {
			return ($element['selected'] || $element['count'] != 0);
		});

		$tag_values_count = count($tag_values);
		$tag_values = CControllerCharts::getMostSevereSubfilters($tag_values);
		$tag_values_spans = [];

		foreach ($tag_values as $element) {
			if ($element['name'] === '') {
				$element_name = _('None');
				$element_style = 'font-style: italic;';
			}
			else {
				$element_name = $element['name'];
				$element_style = null;
			}

			if ($element['selected']) {
				$tag_values_spans[] = (new CSpan([
					(new CLinkAction($element_name))
						->addClass('js-subfilter-unset')
						->addStyle($element_style)
						->setAttribute('data-tag', $tag_name)
						->setAttribute('data-value', $element['name']),
					' ',
					new CSup($element['count'])
				]))
					->addClass(ZBX_STYLE_SUBFILTER)
					->addClass(ZBX_STYLE_SUBFILTER_ENABLED);

				$selected_tags[$tag_name][] = $element['name'];
			}
			else {
				if ($element['count'] == 0) {
					$tag_values_spans[] = (new CSpan([
						(new CSpan($element_name))
							->addStyle($element_style)
							->addClass(ZBX_STYLE_GREY),
						' ',
						new CSup($element['count'])
					]))->addClass(ZBX_STYLE_SUBFILTER);
				}
				else {
					$tag_values_spans[] = (new CSpan([
						(new CLinkAction($element_name))
							->addClass('js-subfilter-set')
							->addStyle($element_style)
							->setAttribute('data-tag', $tag_name)
							->setAttribute('data-value', $element['name']),
						' ',
						new CSup(($subfilter_used ? '+' : '').$element['count'])
					]))->addClass(ZBX_STYLE_SUBFILTER);
				}
			}
		}

		if ($tag_values) {
			$tag_values_row = ($tag_values_count > count($tag_values))
				? [$tag_values_spans, new CSpan('...')]
				: $tag_values_spans;

			$subfilter_options['tags'][$tag_name] = (new CDiv([
				new CTag('label', true, $tag_name.': '),
				(new CDiv($tag_values_row))->addClass('subfilter-options')
			]))->addClass('subfilter-option-grid');
		}
	}

	if ($tags_count > count($data['tags'])) {
		$subfilter_options['tags'][] = new CSpan('...');
	}
}
else {
	$subfilter_options['tags'] = null;
}

(new CTableInfo())
	->setId('subfilter')
	->addRow([[
		$subfilter_options['tags'] || $subfilter_options['tagnames']
			? new CTag('h4', true, [
				_('Subfilter'), ' ', (new CSpan(_('affects only filtered data')))->addClass(ZBX_STYLE_GREY)
			])
			: null
		]], ZBX_STYLE_HOVER_NOBG
	)
	->addRow(
		$subfilter_options['tagnames']
			? [[
				new CTag('h3', true, _('Tags')),
				$subfilter_options['tagnames'],
				new CVar('subfilter_tagnames', $selected_tagnames)
			]]
			: null
	)
	->addRow(
		$subfilter_options['tags']
			? [[
				new CTag('h3', true, _('Tag values')),
				$subfilter_options['tags'],
				new CVar('subfilter_tags', $selected_tags)
			]]
			: null
	)
	->show();
