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
 * @var CPartial $this
 * @var array    $data
 */

$status = $data['system_info']['status'];

$info_table = (new CTableInfo())
	->setHeader([_('Parameter'), _('Value'), _('Details')])
	->setHeadingColumn(0)
	->addClass(ZBX_STYLE_LIST_TABLE_STICKY_HEADER)
	->addRow([
		_('Zabbix server is running'),
		(new CSpan($status['is_running'] ? _('Yes') : _('No')))
			->addClass($status['is_running'] ? ZBX_STYLE_GREEN : ZBX_STYLE_RED),
		$data['system_info']['server_details']
	])
	->addRow([
		_('Number of hosts (enabled/disabled)'),
		$status['has_status'] ? $status['hosts_count'] : '',
		$status['has_status']
			? [
				(new CSpan($status['hosts_count_monitored']))->addClass(ZBX_STYLE_GREEN), ' / ',
				(new CSpan($status['hosts_count_not_monitored']))->addClass(ZBX_STYLE_RED)
			]
			: ''
	])
	->addRow([
		_('Number of templates'),
		$status['has_status'] ? $status['hosts_count_template'] : '',
		''
	])
	->addRow([
		(new CSpan(_('Number of items (enabled/disabled/not supported)')))
			->setTitle(_('Only items assigned to enabled hosts are counted')),
		$status['has_status'] ? $status['items_count'] : '',
		$status['has_status']
			? [
				(new CSpan($status['items_count_monitored']))->addClass(ZBX_STYLE_GREEN), ' / ',
				(new CSpan($status['items_count_disabled']))->addClass(ZBX_STYLE_RED), ' / ',
				(new CSpan($status['items_count_not_supported']))->addClass(ZBX_STYLE_GREY)
			]
			: ''
	])
	->addRow([
		(new CSpan(_('Number of triggers (enabled/disabled [problem/ok])')))
			->setTitle(_('Only triggers assigned to enabled hosts and depending on enabled items are counted')),
		$status['has_status'] ? $status['triggers_count'] : '',
		$status['has_status']
			? [
				$status['triggers_count_enabled'],
				' / ',
				$status['triggers_count_disabled'],
				' [',
				(new CSpan($status['triggers_count_on']))->addClass(ZBX_STYLE_RED),
				' / ',
				(new CSpan($status['triggers_count_off']))->addClass(ZBX_STYLE_GREEN),
				']'
			]
			: ''
	])
	->addRow([
		_('Number of users (online)'),
		$status['has_status'] ? $status['users_count'] : '',
		$status['has_status'] ? (new CSpan($status['users_online']))->addClass(ZBX_STYLE_GREEN) : ''
	]);

if ($data['user_type'] == USER_TYPE_SUPER_ADMIN) {
	$info_table->addRow([
		_('Required server performance, new values per second'),
		($status['has_status'] && array_key_exists('vps_total', $status)) ? round($status['vps_total'], 2) : '',
		''
	]);

	// Check requirements.
	foreach ($data['system_info']['requirements'] as $requirement) {
		if ($requirement['result'] == CFrontendSetup::CHECK_FATAL) {
			$info_table->addRow(
				(new CRow([
					$requirement['name'],
					$requirement['current'],
					$requirement['error']
				]))->addClass(ZBX_STYLE_RED)
			);
		}
	}

	if ($data['system_info']['encoding_warning'] !== '') {
		$info_table->addRow(
			(new CRow(
				(new CCol($data['system_info']['encoding_warning']))->setAttribute('colspan', 3)
			))->addClass(ZBX_STYLE_RED)
		);
	}
}

// Warn if database history tables have not been upgraded.
if (!$data['system_info']['float_double_precision']) {
	$info_table->addRow([
		_('Database history tables upgraded'),
		(new CSpan(_('No')))->addClass(ZBX_STYLE_RED),
		''
	]);
}

if (array_key_exists('history_pk', $data['system_info']) && !$data['system_info']['history_pk']) {
	$info_table->addRow([
		_('Database history tables use primary key'),
		(new CSpan(_('No')))->addClass(ZBX_STYLE_RED),
		''
	]);
}

// Check DB version.
if ($data['user_type'] == USER_TYPE_SUPER_ADMIN) {
	foreach ($data['system_info']['dbversion_status'] as $dbversion) {
		switch ($dbversion['flag']) {
			case DB_VERSION_LOWER_THAN_MINIMUM:
				$error = _s('Error! Unable to start Zabbix server.').' '.
					_s('Minimum required %1$s database version is %2$s.', $dbversion['database'],
						$dbversion['min_version']
					);
				break;

			case DB_VERSION_HIGHER_THAN_MAXIMUM:
				$error = _s('Error! Unable to start Zabbix server.').' '.
					_s('Maximum required %1$s database version is %2$s.', $dbversion['database'],
						$dbversion['max_version']
					);
				break;

			case DB_VERSION_FAILED_TO_RETRIEVE:
				$error = _('Warning! Unable to retrieve database version.');
				$dbversion['current_version'] = '';
				break;

			case DB_VERSION_NOT_SUPPORTED_ERROR:
				$error = _s('Error! Unable to start Zabbix server.').' '.
					_s('Unsupported %1$s database server version. Must be at least %2$s.', $dbversion['database'],
						$dbversion['min_supported_version']
					);
				break;

			case DB_VERSION_NOT_SUPPORTED_WARNING:
				$error = _s('Warning! Unsupported %1$s database server version. Should be at least %2$s.',
					$dbversion['database'], $dbversion['min_supported_version']
				);
				break;

			case DB_VERSION_HIGHER_THAN_MAXIMUM_ERROR:
				$error = _s('Error! Unable to start Zabbix server.').' '.
					_s('Unsupported %1$s database server version. Must not be higher than %2$s.',
						$dbversion['database'], $dbversion['max_version']
					);
				break;

			case DB_VERSION_HIGHER_THAN_MAXIMUM_WARNING:
				$error = _s('Warning! Unsupported %1$s database server version. Should not be higher than %2$s.',
					$dbversion['database'], $dbversion['max_version']
				);
				break;

			case DB_VERSION_SUPPORTED:
			default:
				continue 2;
		}

		$info_table->addRow(
			(new CRow([$dbversion['database'], $dbversion['current_version'], $error]))->addClass(ZBX_STYLE_RED)
		);
	}

	if (array_key_exists(CHousekeepingHelper::OVERRIDE_NEEDED_HISTORY, $data['system_info'])) {
		$info_table->addRow((new CRow([
			_('Housekeeping'),
			_('Override item history period'),
			(new CCol([
				_('This setting should be enabled, because history tables contain compressed chunks.'),
				' ',
				new CLink(_('Configuration').'&hellip;',
					(new CUrl('zabbix.php'))->setArgument('action', 'housekeeping.edit')
				)
			]))->addClass(ZBX_STYLE_RED)
		])));
	}

	if (array_key_exists(CHousekeepingHelper::OVERRIDE_NEEDED_TRENDS, $data['system_info'])) {
		$info_table->addRow((new CRow([
			_('Housekeeping'),
			_('Override item trend period'),
			(new CCol([
				_('This setting should be enabled, because trend tables contain compressed chunks.'),
				' ',
				new CLink(_('Configuration').'&hellip;',
					(new CUrl('zabbix.php'))->setArgument('action', 'housekeeping.edit')
				)
			]))->addClass(ZBX_STYLE_RED)
		])));
	}

	if ($data['system_info']['ha_cluster_enabled']) {
		$info_table->addRow([
			_('High availability cluster'),
			(new CSpan(_('Enabled')))->addClass(ZBX_STYLE_GREEN),
			_s('Fail-over delay: %1$s', $data['system_info']['failover_delay'])
		]);
	}
	else {
		$info_table->addRow([
			_('High availability cluster'),
			_('Disabled'),
			''
		]);
	}
}

$info_table->show();
