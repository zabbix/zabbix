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

$window = $data['window'];
$id = $data['id'];
// TODO: replace ids
$id_duration = "$id-duration";
$id_capacity = "$id-capacity";
$id_capacity_toggle = "$id-capacity-toggle";
$id_groupby_tag_input = "$id-groupby-tag";
$id_groupby_tag = "$id-groupby-opt-tag";
$id_groupby_host = "$id-groupby-opt-host";
$id_groupby_group = "$id-groupby-opt-group";

echo (new CObject())
	->addItem(new CLabel(_('Time window'), 'ceprule-window'))
	->addItem(new CFormField((new CRadioButtonList('window_type', (int) $data['window_type']))
		->setId('ceprule-window')
		->addValue(_('None'), ZBX_CEP_WINDOW_NONE)
		->addValue(_('Simple'), ZBX_CEP_WINDOW_SIMPLE)
		->addValue(_('Cause and symptoms grouping'), ZBX_CEP_WINDOW_CAUSE_SYMPTOM)
		->addValue(_('Tag correlation'), ZBX_CEP_WINDOW_TAG_MATCH)
		->addValue(_('Event pattern match'), ZBX_CEP_WINDOW_PATTERN_MATCH)
		->setModern(true)
	))

	->addItem(new CLabel(_('Duration'), 'ceprule-time-window'))
	->addItem(new CFormField(new CTextBox('window[duration]', $window['duration'])
			->setId($id_duration)
			->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
	))

	->addItem((new CLabel(_('Capacity'), $id_capacity))
		->addItem(makeHelpIcon(
			_('Time window may be limited to contain specific event count. When this limit is reached, new events will be evicted due to capacity restriction.')
		))->setAsteriskMark()
	)
	->addItem(new CFormField(
		(new CRadioButtonList('window[capacity_enabled]', $window['capacity'] !== 0 ? '1' : '0'))
			->setId($id_capacity_toggle)
			->addValue('Unlimited', '0')
			->addValue('Limited', '1')
			->setModern()
	))
		->addItem(new CObject('&nbsp;'))
		->addItem((new CTextBox('window[capacity]', $window['capacity']))
			->setId($id_capacity)
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
	)

	->addItem(new CLabel(_('Group by')))
	->addItem(new CFormField((new CList([
		(new CListItem())
			->addItem((new CCheckBox('window[group_by_host_group]', ZBX_CEP_GROUP_BY_YES))
				->setUncheckedValue(ZBX_CEP_GROUP_BY_NO)
				->setChecked($window['group_by_host_group'] == ZBX_CEP_GROUP_BY_YES)
				->setId($id_groupby_group)
			)
			->addItem(new CLabel(_('Host group'), $id_groupby_group)),

		(new CListItem())
			->addItem((new CCheckBox('window[group_by_host]', ZBX_CEP_GROUP_BY_YES))
				->setUncheckedValue(ZBX_CEP_GROUP_BY_NO)
				->setChecked($window['group_by_host'] == ZBX_CEP_GROUP_BY_YES)
				->setId($id_groupby_host)
			)
			->addItem(new CLabel(_('Host'), $id_groupby_host)),

		(new CListItem())
			->addItem((new CCheckBox('window[group_by_tag]', ZBX_CEP_GROUP_BY_YES))
				->setUncheckedValue(ZBX_CEP_GROUP_BY_NO)
				->setChecked($window['group_by_tag'] == ZBX_CEP_GROUP_BY_YES)
				->setId($id_groupby_tag)
			)
			->addItem(new CLabel(_('Tag'), $id_groupby_tag))
			->addItem(new CObject('&nbsp;'))
			->addItem(new CObject('&nbsp;'))
			->addItem((new CTextBox('window[group_by_tag]', $window['group_by_tag']))->setId($id_groupby_tag_input)),
	]))))

	->addItem((new CLabel(_('Event count tag'), 'ceprule-window-counttag'))->setAsteriskMark())
	->addItem(new CFormField()
		->addItem((new CRadioButtonList('window[event_count_tag_enabled]', $window['event_count_tag'] !== '' ? '1' : '0'))
			->setId('ceprule-window-counttag-toggle')
			->addValue('No', '0')
			->addValue('Yes', '1')
			->setModern()
		)
		->addItem(new CObject('&nbsp;'))
		->addItem((new CTextBox('window[event_count_tag]', $window['event_count_tag']))
			->setId('ceprule-window-counttag')
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setAriaRequired()
			->setAttribute('autofocus', 'autofocus')
		)
	)

	->addItem((new CLabel(_('Script'), 'ceprule-script'))->setAsteriskMark())

	->addItem(new CFormField((new CDiv()) // CMultilineInput fails on iv-validation before DOM is ready, using CDiv.
		->setId('ceprule-script')
		->addClass('multilineinput-control')
		->setAttribute('data-name', 'window[script]')
		->setAttribute('data-field-type', 'multiline')
	))

	->addItem(new CPartial('ceprule.window.conditions', [
		'filter' => $window['filter']
	]));
