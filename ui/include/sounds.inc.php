<?php
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


function getSounds() {
	$file_list = [];
	$dir = scandir('./audio');

	foreach ($dir as $file) {
		$pos = strrpos($file, '.');
		if ($pos === false || mb_strtolower(substr($file, $pos + 1)) !== 'mp3') {
			continue;
		}

		$filename = substr($file, 0, $pos);
		$file_list[$filename] = $file;
	}

	return $file_list;
}

function getMessageSettings() {
	$defSeverities = [
		TRIGGER_SEVERITY_NOT_CLASSIFIED => 1,
		TRIGGER_SEVERITY_INFORMATION => 1,
		TRIGGER_SEVERITY_WARNING => 1,
		TRIGGER_SEVERITY_AVERAGE => 1,
		TRIGGER_SEVERITY_HIGH => 1,
		TRIGGER_SEVERITY_DISASTER => 1
	];

	$messages = [
		'enabled' => 0,
		'timeout' => 60,
		'last.clock' => 0,
		'triggers.recovery' => 1,
		'triggers.severities' => null,
		'sounds.mute' => 0,
		'sounds.repeat' => 1,
		'sounds.recovery' => 'alarm_ok.mp3',
		'sounds.'.TRIGGER_SEVERITY_NOT_CLASSIFIED => 'no_sound.mp3',
		'sounds.'.TRIGGER_SEVERITY_INFORMATION => 'alarm_information.mp3',
		'sounds.'.TRIGGER_SEVERITY_WARNING => 'alarm_warning.mp3',
		'sounds.'.TRIGGER_SEVERITY_AVERAGE => 'alarm_average.mp3',
		'sounds.'.TRIGGER_SEVERITY_HIGH => 'alarm_high.mp3',
		'sounds.'.TRIGGER_SEVERITY_DISASTER => 'alarm_disaster.mp3',
		'show_suppressed' => 0,
		'snoozed.eventid' => 0
	];

	$dbProfiles = DBselect(
		'SELECT p.idx,p.source,p.value_id,p.value_str,type'.
		' FROM profiles p'.
		' WHERE p.userid='.CWebUser::$data['userid'].
			' AND '.dbConditionString('p.idx', ['web.messages'])
	);

	while ($profile = DBfetch($dbProfiles)) {
		$messages[$profile['source']] = $profile['type'] == PROFILE_TYPE_ID
			? $profile['value_id']
			: $profile['value_str'];
	}

	if ($messages['triggers.severities'] === null) {
		$messages['triggers.severities'] = $defSeverities;
	}
	else {
		$messages['triggers.severities'] = unserialize($messages['triggers.severities']);
	}

	return $messages;
}

function updateMessageSettings($messages) {
	if (!isset($messages['enabled'])) {
		$messages['enabled'] = 0;
	}
	if (isset($messages['triggers.severities'])) {
		$messages['triggers.severities'] = serialize(array_filter($messages['triggers.severities'], function($v) {
			return $v == 1;
		}));
	}

	$dbProfiles = DBselect(
		'SELECT p.profileid,p.idx,p.source,p.value_id,p.value_str,type'.
		' FROM profiles p'.
		' WHERE p.userid='.CWebUser::$data['userid'].
			' AND '.dbConditionString('p.idx', ['web.messages'])
	);

	while ($profile = DBfetch($dbProfiles)) {
		$profile['value'] = $profile['type'] == PROFILE_TYPE_ID ? $profile['value_id'] : $profile['value_str'];
		$dbMessages[$profile['source']] = $profile;
	}

	$inserts = [];
	$updates = [];

	foreach ($messages as $key => $value) {
		if ($key === 'timeout' && !validateTimeUnit($messages['timeout'], 30, SEC_PER_DAY, false, $error)) {
			error(_s('Incorrect value for field "%1$s": %2$s.', 'timeout', $error));

			return false;
		}

		$values = [
			'userid' => CWebUser::$data['userid'],
			'idx' => 'web.messages',
			'source' => $key,
			'type' => $key === 'snoozed.eventid' ? PROFILE_TYPE_ID : PROFILE_TYPE_STR
		];

		if ($values['type'] == PROFILE_TYPE_ID) {
			$values['value_id'] = $value;
		}
		else {
			$values['value_str'] = $value;
		}

		if (!isset($dbMessages[$key])) {
			$inserts[] = $values;
		}
		elseif ($dbMessages[$key]['value'] != $value) {
			$updates[] = [
				'values' => $values,
				'where' => ['profileid' => $dbMessages[$key]['profileid']]
			];
		}
	}

	try {
		DB::insert('profiles', $inserts);
		DB::update('profiles', $updates);
	}
	catch (APIException $e) {
		error($e->getMessage());

		return false;
	}

	return true;
}
