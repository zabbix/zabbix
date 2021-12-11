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


require_once __DIR__.'/../../include/forms.inc.php';

/**
 * Configuration for SLA.
 */
class CControllerServiceSlaEdit extends CController {

	/**
	 * Edited SLA.
	 *
	 * @var ?array
	 */
	protected $record;

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'id'					=> 'db sla.slaid',
			'name'					=> 'db sla.name',
			'slo'					=> 'db sla.slo',
			'effective_date'		=> 'db sla.effective_date',
			'timezone'				=> 'db sla.timezone',
			'period'				=> 'in '.implode(',', array_keys(CSlaHelper::periods())),
			'status'				=> 'db sla.status|in '.implode(',', [
				CSlaHelper::SLA_STATUS_DISABLED,
				CSlaHelper::SLA_STATUS_ENABLED
			]),
			'schedule_mode'			=> 'in '.implode(',', [
				CSlaHelper::SCHEDULE_MODE_NONSTOP,
				CSlaHelper::SCHEDULE_MODE_CUSTOM
			]),
			'description'			=> 'db sla.description',
			'service_tags'			=> 'array',
			'schedule'				=> 'array',
			'excluded_downtimes'	=> 'array',
			'clone'					=> 'db sla.slaid'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(
				new CControllerResponseData([
					'main_block' => json_encode(['errors' => getMessages()->toString()])
				])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		if (!$this->checkAccess(CRoleHelper::UI_SERVICES_SLA)) {
			return false;
		}

		if ($this->hasInput('id') || $this->hasInput('clone')) {
			$source_id = $this->hasInput('clone') ? $this->getInput('clone') : $this->getInput('id');

			$this->record = API::SLA()->get([
				'output' => array_merge(CSlaHelper::OUTPUT_FIELDS, ['slaid']),
				'slaids' => $source_id,
				'selectTags' => ['tag', 'value'],
				'selectSchedule' => ['period_from', 'period_to'],
				'selectExcludedDowntimes' => ['name', 'period_from', 'period_to'],
				'editable' => true,
				'limit' => 1
			]);

			if (!$this->record) {
				return false;
			}

			$this->record = $this->record[0];
		}

		return true;
	}

	protected function doAction(): void {
		if ($this->record === null) {
			$timezone = CWebUser::$data['timezone'];

			if ($timezone === TIMEZONE_DEFAULT) {
				$timezone = CSettingsHelper::getGlobal(CSettingsHelper::DEFAULT_TIMEZONE);
			}

			if ($timezone === 'system') {
				$timezone = ini_get('date.timezone');
			}

			$this-> record = [
				'slaid' => null,
				'timezone' => $timezone,
				'effective_date' => ''
			];
		}

		if ($this->hasInput('clone')) {
			$this->record = ['slaid' => null];
		}

		$this->record = $this->record + DB::getDefaults('sla');
		$this->getInputs($this->record, array_keys($this->record));
		$this->record += [
			'schedule' => [],
			'service_tags' => [],
			'excluded_downtimes' => []
		];

		$schedule_mode = CSlaHelper::SCHEDULE_MODE_NONSTOP;
		$this->record['schedule'] = CSlaHelper::convertScheduleToWeekdayPeriods($this->record['schedule']);

		foreach (range(0, 6) as $weekday) {
			if (array_key_exists($weekday, $this->record['schedule'])) {
				CArrayHelper::sort($this->record['schedule'][$weekday], ['period_from']);
				$schedule_mode = CSlaHelper::SCHEDULE_MODE_CUSTOM;
			}
			else {
				$this->record['schedule'][$weekday] = [[
					'period_from' => strtotime(getDayOfWeekCaption($weekday).', 08:00'),
					'period_to' => strtotime(getDayOfWeekCaption($weekday).', 17:00'),
					'disabled' => true
				]];
			}
		}

		foreach ($this->record['schedule'] as $weekday => $periods) {
			$disabled = false;

			foreach ($periods as &$period) {
				if (array_key_exists('disabled', $period)) {
					$disabled = true;
				}

				$period = zbx_date2str(TIME_FORMAT, $period['period_from']).
					'-'.zbx_date2str(TIME_FORMAT, $period['period_to']);

			}
			unset($period);

			$this->record['schedule'][$weekday] = [
				'periods' => implode(', ', $periods),
				'disabled' => $disabled
			];
		}

		$data = [
			'form_action' => (new CUrl('zabbix.php'))->setArgument('action', 'services.sla.update')->toString(),
			'schedule_mode' => $schedule_mode,
			'timezones' => (new CDateTimeZoneHelper())->getAllDateTimeZones(),
			'form' => $this->record,
			'user' => ['debug_mode' => $this->getDebugMode()]
		];

		foreach ($data['form']['service_tags'] as &$tag) {
			if (!array_key_exists('value', $tag)) {
				$tag['value'] = '';
			}
		}
		unset($tag);

		foreach ($data['form']['excluded_downtimes'] as &$downtime) {
			$duration = $downtime['period_to'] - $downtime['period_from'];

			$downtime = array_merge($downtime, [
				'start_time' => zbx_date2str(DATE_TIME_FORMAT, $downtime['period_from']),
				'duration' => convertUnitsS($duration, false),
				'duration_days' => floor($duration / SEC_PER_DAY),
				'duration_hours' => floor(($duration % SEC_PER_DAY) / SEC_PER_HOUR),
				'duration_minutes' => floor(($duration % SEC_PER_HOUR) / SEC_PER_MIN),
			]);
		}
		unset($downtime);

		if (!array_key_exists('slaid', $this->record) || $this->record['slaid'] === null) {
			$buttons = [
				[
					'title' => _('Add'),
					'class' => 'js-add',
					'keepOpen' => true,
					'isSubmit' => true,
					'action' => 'sla_edit.submit();'
				]
			];
		}
		else {
			$buttons = [
				[
					'title' => _('Update'),
					'class' => 'js-update',
					'keepOpen' => true,
					'isSubmit' => true,
					'action' => 'sla_edit.submit();'
				],
				[
					'title' => _('Clone'),
					'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-clone']),
					'keepOpen' => true,
					'action' => 'sla_edit.clone('.json_encode(_('New SLA')).');'
				],
				[
					'title' => _('Delete'),
					'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-delete']),
					'keepOpen' => true,
					'action' => 'sla_edit.delete('.json_encode($data['form']['slaid']).');'
				],
				[
					'title' => _('Cancel'),
					'class' => implode(' ', [ZBX_STYLE_BTN_ALT, 'js-cancel']),
					'keepOpen' => true,
					'isSubmit' => true,
					'action' => 'sla_edit.close();'
				]
			];
		}

		$data['buttons'] = $buttons;

		CArrayHelper::sort($data['form']['service_tags'], ['tag', 'value']);
		CArrayHelper::sort($data['form']['excluded_downtimes'], ['period_from', 'period_to']);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of SLA'));

		$this->setResponse($response);
	}
}
