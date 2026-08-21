<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

$tags = (new CMultiSelect([
	'ms_list_of_string_mode' => true,
	'name' => 'window[tags]',
	'data' => array_map(fn (string $name) => ['id' => $name, 'name' => $name], $data['window']['tags']),
	'placeholder' => _('tag names'),
	'maxlength' => DB::getFieldLength('cep_rule_window', 'tags'),
	'add_post_js' => false
]))
	->setId('ceprule-window-groupby-tag')
	->addStyle('width: '.ZBX_TEXTAREA_TAG_WIDTH.'px;');

zbx_add_post_js($tags->getPostJS());

(new CObject())
	->addItem((new CLabel(_('Time window'), 'ceprule-window-type'))
		->addItem(makeHelpIcon([
			_("None - there will be no time window specific processing.").PHP_EOL,
			_("Simple - set up time window based event eviction operations.").PHP_EOL,
			_("Cause and symptom grouping - first event from the window (fixed) will be treated as cause other symptoms.").PHP_EOL,
			_("Tag correlation - map past and current window events using tags.").PHP_EOL,
			_("Event pattern match - execute JavaScript to identify event patterns.")
		]))
	)
	->addItem(new CFormField((new CRadioButtonList('window_type', (int) $data['window_type']))
		->setId('ceprule-window-type')
		->addValue(_('None'), CCepRuleHelper::WINDOW_NONE)
		->addValue(_('Simple'), CCepRuleHelper::WINDOW_SIMPLE)
		->addValue(_('Cause and symptoms grouping'), CCepRuleHelper::WINDOW_CAUSE_SYMPTOM)
		->addValue(_('Tag correlation'), CCepRuleHelper::WINDOW_TAG_MATCH)
		->addValue(_('Event pattern match'), CCepRuleHelper::WINDOW_PATTERN_MATCH)
		->setModern(true)
	))

	->addItem((new CLabel(_('Duration'), 'ceprule-window-duration'))->setAsteriskMark())
	->addItem(new CFormField((new CTextBox('window[duration]', $data['window']['duration']))
			->setId('ceprule-window-duration')
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
	))

	->addItem((new CLabel(_('Capacity'), 'ceprule-window-capacity'))
		->addItem(makeHelpIcon(
			_('Time window may be limited to contain specific event count. When this limit is reached, new events will be evicted due to capacity restriction.')
		))->setAsteriskMark()
	)
	->addItem((new CFormField())
		->addItem((new CRadioButtonList('window[capacity_enabled]', $data['window']['capacity_enabled']  ? '1' : '0'))
			->setId('ceprule-window-capacity-toggle')
			->addValue(_('Unlimited'), '0')
			->addValue(_('Limited'), '1')
			->setModern()
		)
		->addItem(new CObject('&nbsp;'))
		->addItem((new CTextBox('window[capacity]', $data['window']['capacity']))
			->setId('ceprule-window-capacity')
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
		)
	)

	->addItem((new CLabel(_('Group by'), 'ceprule-window-groupby-opt-group'))->setAsteriskMark()->setId('ceprule-groupby-label'))
	->addItem(new CFormField((new CList([
		(new CListItem())
			->addItem((new CCheckBox('window[group_by_host_group]', CCepRuleHelper::GROUP_BY_YES))
				->setUncheckedValue(CCepRuleHelper::GROUP_BY_NO)
				->setChecked($data['window']['group_by_host_group'] == CCepRuleHelper::GROUP_BY_YES)
				->setId('ceprule-window-groupby-opt-group')
			)
			->addItem(new CLabel(_('Host group'), 'ceprule-window-groupby-opt-group')),

		(new CListItem())
			->addItem((new CCheckBox('window[group_by_host]', CCepRuleHelper::GROUP_BY_YES))
				->setUncheckedValue(CCepRuleHelper::GROUP_BY_NO)
				->setChecked($data['window']['group_by_host'] == CCepRuleHelper::GROUP_BY_YES)
				->setId('ceprule-window-groupby-opt-host')
			)
			->addItem(new CLabel(_('Host'), 'ceprule-window-groupby-opt-host')),

		(new CListItem())
			->addItem((new CCheckBox('window[group_by_tags]', CCepRuleHelper::GROUP_BY_YES))
				->setUncheckedValue(CCepRuleHelper::GROUP_BY_NO)
				->setChecked($data['window']['group_by_tags'] == CCepRuleHelper::GROUP_BY_YES)
				->setId('ceprule-window-groupby-opt-tag')
			)
			->addItem(new CLabel(_('Tag'), 'ceprule-window-groupby-opt-tag'))
			->addItem(new CObject('&nbsp;'))
			->addItem($tags)
	]))->setId('ceprule-window-groupby')))

	->addItem((new CLabel(_('Event count tag'), 'ceprule-window-counttag'))->setAsteriskMark())
	->addItem((new CFormField())
		->addItem((new CRadioButtonList('window[event_count_tag_enabled]',
			($data['window']['event_count_tag'] !== '') ? '1' : '0'
		))
			->setId('ceprule-window-counttag-toggle')
			->addValue(_('No'), '0')
			->addValue(_('Yes'), '1')
			->setModern()
		)
		->addItem(new CObject('&nbsp;'))
		->addItem((new CTextAreaFlexible('window[event_count_tag]', $data['window']['event_count_tag']))
			->setMaxlength(DB::getFieldLength('cep_rule_window', 'event_count_tag'))
			->setId('ceprule-window-counttag')
			->setAriaRequired()
		)
	)

	->addItem((new CLabel(_('Script'), 'ceprule-script'))->setAsteriskMark())

	->addItem(new CFormField((new CDiv()) // CMultilineInput fails on iv-validation before DOM is ready, using CDiv.
		->setId('ceprule-script')
		->addClass('multilineinput-control')
		->addStyle('width: '.ZBX_TEXTAREA_BIG_WIDTH.'px')
		->setAttribute('data-name', 'window[script]')
		->setAttribute('data-field-type', 'multiline')
	))

	->show();
