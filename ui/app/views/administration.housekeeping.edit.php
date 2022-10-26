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
 * @var CView $this
 */

$this->includeJsFile('administration.housekeeping.edit.js.php');

$widget = (new CWidget())
	->setTitle(_('Housekeeping'))
	->setTitleSubmenu(getAdministrationGeneralSubmenu());

$form = (new CForm())
	->setId('housekeeping')
	->setAction((new CUrl('zabbix.php'))
		->setArgument('action', 'housekeeping.update')
		->getUrl()
	)
	->setAttribute('aria-labelledby', ZBX_STYLE_PAGE_TITLE);

$house_keeper_tab = (new CFormList())
	->addRow((new CTag('h4', true, _('Events and alerts')))->addClass('input-section-header'))
	->addRow(
		new CLabel(_('Enable internal housekeeping'), 'hk_events_mode'),
		(new CCheckBox('hk_events_mode'))
			->setChecked($data['hk_events_mode'] == 1)
			->setAttribute('autofocus', 'autofocus')
	)
	->addRow(
		(new CLabel(_('Trigger data storage period'), 'hk_events_trigger'))->setAsteriskMark(),
		(new CTextBox('hk_events_trigger', $data['hk_events_trigger'], false,
			DB::getFieldLength('config', 'hk_events_trigger')
		))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setEnabled($data['hk_events_mode'] == 1)
			->setAriaRequired()
	)
	->addRow(
		(new CLabel(_('Service data storage period'), 'hk_events_service'))->setAsteriskMark(),
		(new CTextBox('hk_events_service', $data['hk_events_service'], false,
			DB::getFieldLength('config', 'hk_events_service')
		))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setEnabled($data['hk_events_mode'] == 1)
			->setAriaRequired()
	)
	->addRow(
		(new CLabel(_('Internal data storage period'), 'hk_events_internal'))->setAsteriskMark(),
		(new CTextBox('hk_events_internal', $data['hk_events_internal'], false,
			DB::getFieldLength('config', 'hk_events_internal')
		))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setEnabled($data['hk_events_mode'] == 1)
			->setAriaRequired()
	)
	->addRow(
		(new CLabel(_('Network discovery data storage period'), 'hk_events_discovery'))
			->setAsteriskMark(),
		(new CTextBox('hk_events_discovery', $data['hk_events_discovery'], false,
			DB::getFieldLength('config', 'hk_events_discovery')
		))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setEnabled($data['hk_events_mode'] == 1)
			->setAriaRequired()
	)
	->addRow(
		(new CLabel(_('Autoregistration data storage period'), 'hk_events_autoreg'))
			->setAsteriskMark(),
		(new CTextBox('hk_events_autoreg', $data['hk_events_autoreg'], false,
			DB::getFieldLength('config', 'hk_events_autoreg')
		))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setEnabled($data['hk_events_mode'] == 1)
			->setAriaRequired()
	)
	->addRow((new CTag('h4', true, _('Services')))->addClass('input-section-header'))
	->addRow(
		new CLabel(_('Enable internal housekeeping'), 'hk_services_mode'),
		(new CCheckBox('hk_services_mode'))->setChecked($data['hk_services_mode'] == 1)
	)
	->addRow(
		(new CLabel(_('Data storage period'), 'hk_services'))
			->setAsteriskMark(),
		(new CTextBox('hk_services', $data['hk_services'], false, DB::getFieldLength('config', 'hk_services')))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setEnabled($data['hk_services_mode'] == 1)
			->setAriaRequired()
	)
	->addRow((new CTag('h4', true, _('User sessions')))->addClass('input-section-header'))
	->addRow(
		new CLabel(_('Enable internal housekeeping'), 'hk_sessions_mode'),
		(new CCheckBox('hk_sessions_mode'))->setChecked($data['hk_sessions_mode'] == 1)
	)
	->addRow(
		(new CLabel(_('Data storage period'), 'hk_sessions'))
			->setAsteriskMark(),
		(new CTextBox('hk_sessions', $data['hk_sessions'], false, DB::getFieldLength('config', 'hk_sessions')))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setEnabled($data['hk_sessions_mode'] == 1)
			->setAriaRequired()
	)
	->addRow((new CTag('h4', true, _('History')))->addClass('input-section-header'))
	->addRow(
		new CLabel(_('Enable internal housekeeping'), 'hk_history_mode'),
		(new CCheckBox('hk_history_mode'))->setChecked($data['hk_history_mode'] == 1)
	)
	->addRow(
		new CLabel(_('Override item history period'), 'hk_history_global'),
		[
			(new CCheckBox('hk_history_global'))->setChecked($data['hk_history_global'] == 1),
			array_key_exists(CHousekeepingHelper::OVERRIDE_NEEDED_HISTORY, $data)
				? new CSpan([
					' ',
					makeWarningIcon(
						_('This setting should be enabled, because history tables contain compressed chunks.')
					)
						->addStyle('display:none;')
						->addClass('js-hk-history-warning')
				])
				: null
		]
	)
	->addRow(
		(new CLabel(_('Data storage period'), 'hk_history'))
			->setAsteriskMark(),
		(new CTextBox('hk_history', $data['hk_history'], false, DB::getFieldLength('config', 'hk_history')))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setEnabled($data['hk_history_global'] == 1)
			->setAriaRequired()
	)
	->addRow((new CTag('h4', true, _('Trends')))->addClass('input-section-header'))
	->addRow(
		new CLabel(_('Enable internal housekeeping'), 'hk_trends_mode'),
		(new CCheckBox('hk_trends_mode'))->setChecked($data['hk_trends_mode'] == 1)
	)
	->addRow(
		new CLabel(_('Override item trend period'), 'hk_trends_global'),
		[
			(new CCheckBox('hk_trends_global'))->setChecked($data['hk_trends_global'] == 1),
			array_key_exists(CHousekeepingHelper::OVERRIDE_NEEDED_TRENDS, $data)
				? new CSpan([
					' ',
					makeWarningIcon(
						_('This setting should be enabled, because trend tables contain compressed chunks.')
					)
						->addStyle('display:none;')
						->addClass('js-hk-trends-warning')
				])
				: null
		]
	)
	->addRow(
		(new CLabel(_('Data storage period'), 'hk_trends'))
			->setAsteriskMark(),
		(new CTextBox('hk_trends', $data['hk_trends'], false, DB::getFieldLength('config', 'hk_trends')))
			->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
			->setEnabled($data['hk_trends_global'] == 1)
			->setAriaRequired()
	);

	if ($data['db_extension'] === ZBX_DB_EXTENSION_TIMESCALEDB) {
		switch ($data['extension_err_code']) {
			case ZBX_EXT_ERR_UNDEFINED:
				$timescaledb_error = _('Unable to retrieve TimescaleDB compression support status.');
				break;

			case ZBX_TIMESCALEDB_POSTGRES_TOO_OLD:
				$timescaledb_error = _('Compression is not supported.').' '.
					_('PostgreSQL database server version is too old.');
				break;

			case ZBX_TIMESCALEDB_VERSION_FAILED_TO_RETRIEVE:
				$timescaledb_error = _('Compression is not supported.').' '.
					_('Unable to retrieve TimescaleDB version.');
				break;

			case ZBX_TIMESCALEDB_VERSION_LOWER_THAN_MINIMUM:
				$timescaledb_error = _('Compression is not supported.').' '.
					_s('Minimum required TimescaleDB version is %1$s.', $data['timescaledb_min_version']);
				break;

			case ZBX_TIMESCALEDB_VERSION_NOT_SUPPORTED:
				$timescaledb_error = _s('Unsupported TimescaleDB version. Should be at least %1$s.',
					$data['timescaledb_min_supported_version']
				);

				if (!$data['compression_availability']) {
					$timescaledb_error = _('Compression is not supported.').' '.$timescaledb_error;
				}
				break;

			case ZBX_TIMESCALEDB_VERSION_HIGHER_THAN_MAXIMUM:
				$timescaledb_error = _s('Unsupported TimescaleDB version. Should not be higher than %1$s.',
					$data['timescaledb_max_version']
				);
				break;

			case ZBX_TIMESCALEDB_LICENSE_NOT_COMMUNITY:
				$timescaledb_error = _('Detected TimescaleDB license does not support compression. Compression is supported in TimescaleDB Community Edition.');
				break;

			case ZBX_EXT_SUCCEED:
			default:
				$timescaledb_error = '';
		}

		$timescaledb_error = $timescaledb_error !== ''
			? (makeErrorIcon($timescaledb_error))->addStyle('margin-left: 5px;')
			: null;

		$compression_status_checkbox = (new CCheckBox('compression_status'))
			->setChecked($data['compression_status'] == 1)
			->setEnabled($data['compression_availability']);

		$house_keeper_tab
			->addRow((new CTag('h4', true, _('History and trends compression')))->addClass('input-section-header'))
			->addRow(
				new CLabel(_('Enable compression'), 'compression_status'),
				[$compression_status_checkbox, $timescaledb_error]
			)
			->addRow(
				(new CLabel(_('Compress records older than'), 'compress_older'))
					->setAsteriskMark(),
				(new CTextBox('compress_older', $data['compress_older'], false,
					DB::getFieldLength('config', 'compress_older')
				))
					->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
					->setEnabled($data['compression_status'] == 1 && $data['compression_availability'])
					->setAriaRequired()
			);
	}

$house_keeper_tab
	->addRow((new CTag('h4', true, _('Audit')))->addClass('input-section-header'))
	->addRow(new CLink(_('Audit settings'), (new CUrl('zabbix.php'))->setArgument('action', 'audit.settings.edit'))
);

$form->addItem(
	(new CTabView())
		->addTab('houseKeeper', _('Housekeeping'), $house_keeper_tab)
		->setFooter(makeFormFooter(
			new CSubmit('update', _('Update')),
			[new CButton('resetDefaults', _('Reset defaults'))]
		))
);

$widget
	->addItem($form)
	->show();
