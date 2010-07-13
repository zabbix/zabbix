<?php
/*
** Zabbix
** Copyright (C) 2000-2010 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/
?>
<?php

function getSounds(){
	$fileList = array();

	$dir = scandir('./audio');
	foreach($dir as $fnum => $file){
		if(!preg_match('/^([\w\d_]+)\.(wav|ogg)$/i', $file)) continue;

		list($filename, $type) = explode('.', $file);
		$fileList[$filename] = $file;
	}
	
return $fileList;
}

function getMessageSettings(){
	$defSeverities = array(
		TRIGGER_SEVERITY_NOT_CLASSIFIED => 1,
		TRIGGER_SEVERITY_INFORMATION => 1,
		TRIGGER_SEVERITY_WARNING => 1,
		TRIGGER_SEVERITY_AVERAGE => 1,
		TRIGGER_SEVERITY_HIGH => 1,
		TRIGGER_SEVERITY_DISASTER => 1,
	);

	$messages['enabled'] = CProfile::get('web.messages.enabled', 1);
	$messages['timeout'] = CProfile::get('web.messages.timeout', 90);

	$messages['triggers'] = array();

	$severities = CProfile::get('web.messages.triggers.severities');
	$messages['triggers']['severities'] = is_null($severities)?$defSeverities:unserialize($severities);

	$messages['triggers']['ok'] = CProfile::get('web.messages.triggers.ok', 1);

	$messages['sounds'] = array();
	$messages['sounds'][TRIGGER_SEVERITY_NOT_CLASSIFIED] = CProfile::get('web.sounds.severity.0', 'alarm_notclassified.wav');
	$messages['sounds'][TRIGGER_SEVERITY_INFORMATION] = CProfile::get('web.sounds.severity.1',	'alarm_information.wav');
	$messages['sounds'][TRIGGER_SEVERITY_WARNING] = CProfile::get('web.sounds.severity.2',	'alarm_warning.wav');
	$messages['sounds'][TRIGGER_SEVERITY_AVERAGE] = CProfile::get('web.sounds.severity.3',	'alarm_average.wav');
	$messages['sounds'][TRIGGER_SEVERITY_HIGH] = CProfile::get('web.sounds.severity.4', 'alarm_high.wav');
	$messages['sounds'][TRIGGER_SEVERITY_DISASTER] = CProfile::get('web.sounds.severity.5', 'alarm_disaster.wav');

	$messages['sounds']['ok'] = CProfile::get('web.sounds.ok', 'trigger_off.wav');

	$messages['sounds']['loop'] = CProfile::get('web.sounds.loop', 1);

return $messages;
}

function updateMessageSettings($messages){
	CProfile::update('web.messages.enabled', isset($messages['enabled']), PROFILE_TYPE_INT);
	CProfile::update('web.messages.timeout', $messages['timeout'], PROFILE_TYPE_INT);
	CProfile::update('web.messages.triggers.severities', serialize($messages['triggers']['severities']), PROFILE_TYPE_STR);
	CProfile::update('web.messages.triggers.ok', isset($messages['triggers']['ok']), PROFILE_TYPE_INT);

	CProfile::update('web.sounds.severity.0', $messages['sounds'][TRIGGER_SEVERITY_NOT_CLASSIFIED], PROFILE_TYPE_STR);
	CProfile::update('web.sounds.severity.1', $messages['sounds'][TRIGGER_SEVERITY_INFORMATION], PROFILE_TYPE_STR);
	CProfile::update('web.sounds.severity.2', $messages['sounds'][TRIGGER_SEVERITY_WARNING], PROFILE_TYPE_STR);
	CProfile::update('web.sounds.severity.3', $messages['sounds'][TRIGGER_SEVERITY_AVERAGE], PROFILE_TYPE_STR);
	CProfile::update('web.sounds.severity.4', $messages['sounds'][TRIGGER_SEVERITY_HIGH], PROFILE_TYPE_STR);
	CProfile::update('web.sounds.severity.5', $messages['sounds'][TRIGGER_SEVERITY_DISASTER], PROFILE_TYPE_STR);

	CProfile::update('web.sounds.ok', $messages['sounds']['ok'], PROFILE_TYPE_STR);

	CProfile::update('web.sounds.loop', $messages['sounds']['loop'], PROFILE_TYPE_INT);

return $messages;
}
?>