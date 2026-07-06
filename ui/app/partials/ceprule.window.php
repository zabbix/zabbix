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

echo (new CObject())
	->addItem(new CLabel(_('Time window'), 'ceprule-window-type'))
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
			->setAttribute('autofocus', 'autofocus')
	))

	->addItem((new CLabel(_('Capacity'), 'ceprule-window-capacity'))
		->addItem(makeHelpIcon(
			_('Time window may be limited to contain specific event count. When this limit is reached, new events will be evicted due to capacity restriction.')
		))->setAsteriskMark()
	)
	->addItem((new CFormField())
		->addItem((new CRadioButtonList('window[capacity_enabled]', $data['window']['capacity'] === '' ? '0' : '1'))
			->setId('ceprule-window-capacity-toggle')
			->addValue(_('Unlimited'), '0')
			->addValue(_('Limited'), '1')
			->setModern()
		)
		->addItem(new CObject('&nbsp;'))
		->addItem((new CTextBox('window[capacity]', $data['window']['capacity']))
			->setId('ceprule-window-capacity')
			->addStyle('line-height: 16px;')
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
		)
	)

	->addItem(new CLabel(_('Group by')))
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
			->addItem((new CCheckBox('window[group_by_tag]', CCepRuleHelper::GROUP_BY_YES))
				->setUncheckedValue(CCepRuleHelper::GROUP_BY_NO)
				->setChecked($data['window']['group_by_tag'] == CCepRuleHelper::GROUP_BY_YES)
				->setId('ceprule-window-groupby-opt-tag')
			)
			->addItem(new CLabel(_('Tag'), 'ceprule-window-groupby-opt-tag'))
			->addItem(new CObject('&nbsp;'))

			->addItem(
				(new CPatternSelect([
					'name' => 'window[tags][]',
					'data' => $data['window']['tags'],
					'multiple' => true,
					'popup' => false,
					'add_new' => true,
					'new_item_name' => 'window[tags][]',
					'selectedLimit' => 0,
					'placeholder' => _('tag names'),
					'add_post_js' => false
				]))
					->setId('ceprule-window-groupby-tag')
					->setAttribute('data-field-name', 'window[tags]')
			)

	]))->setId('ceprule-window-groupby')))

	->addItem((new CLabel(_('Event count tag'), 'ceprule-window-counttag'))->setAsteriskMark())
	->addItem((new CFormField())
		->addItem((new CRadioButtonList(
			name: 'window[event_count_tag_enabled]',
			value: ($data['window']['event_count_tag'] !== '') ? '1' : '0'
		))
			->setId('ceprule-window-counttag-toggle')
			->addValue('No', '0')
			->addValue('Yes', '1')
			->setModern()
		)
		->addItem(new CObject('&nbsp;'))
		->addItem((new CTextBox('window[event_count_tag]', $data['window']['event_count_tag']))
			->addStyle('line-height: 16px;')
			->setId('ceprule-window-counttag')
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
		)
	)

	->addItem((new CLabel(_('Script'), 'ceprule-script'))->setAsteriskMark())

	->addItem(new CFormField((new CDiv()) // CMultilineInput fails on iv-validation before DOM is ready, using CDiv.
		->setId('ceprule-script')
		->addClass('multilineinput-control')
		->addStyle('width: '.ZBX_TEXTAREA_BIG_WIDTH.'px')
		->setAttribute('data-name', 'window[script]')
		->setAttribute('data-field-type', 'multiline')
	));
