<?php
/*
** Zabbix
** Copyright (C) 2000-2012 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


	global $TRANSLATION;

	$TRANSLATION = array(

	'S_YEAR_SHORT'=>			_('y'),
	'S_MONTH_SHORT'=>			_('m'),
	'S_WEEK_SHORT'=>			_('w'),
	'S_DAY_SHORT'=>				_('d'),
	'S_HOUR_SHORT' =>			_('h'),
	'S_MINUTE_SHORT' =>			_('m'),

//	exp_imp.php
	'S_ELEMENT'=>				_('Element'),

//	export.inc.php
	'S_EXPORT_DATE_ATTRIBUTE_DATE_FORMAT'=>	_('d.m.y'),
	'S_EXPORT_TIME_ATTRIBUTE_DATE_FORMAT'=>	_('H.i'),

//	actions.inc.php
	'S_HISTORY_OF_ACTIONS_DATE_FORMAT'=>	_('d M Y H:i:s'),
	'S_EVENT_ACTION_MESSAGES_DATE_FORMAT'=>	_('d M Y H:i:s'),
	'S_EVENT_ACTION_CMDS_DATE_FORMAT'=>	_('Y.M.d H:i:s'),

//	actions.php
	'S_ON'=>					_('On'),
	'S_OFF'=>					_('Off'),
	'S_OR'=>				_('or'),

	'S_TIME'=>				_('Time'),
	'S_TYPE'=>				_('Type'),
	'S_ERROR'=>				_('Error'),

// Lines
	'S_LINE'=>				_('Line'),
	'S_BOLD_LINE'=>				_('Bold line'),
	'S_DOT'=>				_('Dot'),
	'S_DASHED_LINE'=>			_('Dashed line'),

//	config.php
	'S_DEFAULT'=>					_('Default'),
	'S_IMAGE'=>					_('Image'),

//	Latest values
	'S_ALL_S'=>						_('All'),

//	graph.php
	'S_COLOR'=>					_('Colour'),
	'S_LEFT'=>					_('Left'),

	'S_HISTORY_LOG_ITEM_DATE_FORMAT'=>	_('[Y.M.d H:i:s]'),
	'S_HISTORY_LOG_LOCALTIME_DATE_FORMAT'=>	_('Y.M.d H:i:s'),
	'S_HISTORY_LOG_ITEM_PLAINTEXT'=>	_('Y-m-d H:i:s'),
	'S_HISTORY_PLAINTEXT_DATE_FORMAT'=>	_('Y-m-d H:i:s'),
	'S_HISTORY_ITEM_DATE_FORMAT'=>		_('Y.M.d H:i:s'),

	'S_JANUARY'=>				_('January'),
	'S_FEBRUARY'=>				_('February'),
	'S_MARCH'=>				_('March'),
	'S_APRIL'=>				_('April'),
	'S_MAY'=>				_('May'),
	'S_JUNE'=>				_('June'),
	'S_JULY'=>				_('July'),
	'S_AUGUST'=>				_('August'),
	'S_SEPTEMBER'=>				_('September'),
	'S_OCTOBER'=>				_('October'),
	'S_NOVEMBER'=>				_('November'),
	'S_DECEMBER'=>				_('December'),

//	hosts.php
	'S_TRIGGERS'=>					_('Triggers'),
	'S_HOST'=>					_('Host'),
	'S_HOST_GROUP'=>				_('Host group'),
	'S_UPDATE'=>					_('Update'),
	'S_INTERFACES' =>				_('Interfaces'),

//	items.php
	'S_DESCRIPTION'=>					_('Description'),
	'S_HISTORY'=>						_('History'),
	'S_ITEM'=>						_('Item'),

//	events.php
	'S_EVENTS_DISCOVERY_TIME_FORMAT'=>	_('d M Y H:i:s'),
	'S_EVENTS_ACTION_TIME_FORMAT'=>		_('d M Y H:i:s'),

//	sysmap.php
	'S_HIDDEN'=>			_('Hidden'),
	'S_SHOWN'=>				_('Shown'),
	'S_URLS'=>				_('URLs'),
	'S_LABEL'=>				_('Label'),
	'S_NO_IMAGES' => 'You need to have at least one image uploaded to create map element. Images can be uploaded in Administration->General->Images section.',

//	sysmaps.php
	'S_MAP_DELETED'=>			_('Network map deleted'),
	'S_CANNOT_DELETE_MAP'=>			_('Cannot delete network map'),
	'S_CREATE_MAP'=>				_('Create map'),
	'S_DELETE_SELECTED_MAPS_Q'=>		_('Delete selected maps?'),
	'S_MAP_ADDED'=>					_('Network map added'),
	'S_CANNOT_ADD_MAP'=>			_('Cannot add network map'),
	'S_MAP_UPDATED'=>				_('Network map updated'),
	'S_CANNOT_UPDATE_MAP'=>			_('Cannot update network map'),
	'S_TWO_ELEMENTS_SHOULD_BE_SELECTED'=>		_('Two elements should be selected'),
	'S_DELETE_SELECTED_ELEMENTS_Q'=>		_('Delete selected elements?'),
	'S_PLEASE_SELECT_TWO_ELEMENTS'=>		_('Please select two elements'),
	'S_DELETE_LINKS_BETWEEN_SELECTED_ELEMENTS_Q'=>	_('Delete links between selected elements?'),
	'S_NEW_ELEMENT'=>				_('New element'),

	'S_BOTTOM'=>					_('Bottom'),
	'S_TOP'=>						_('Top'),

	'S_CANNOT_FIND_IMAGE'=>			_('Cannot find image'),
	'S_CANNOT_FIND_BACKGROUND_IMAGE'=>	_('Cannot find background image'),
	'S_CANNOT_FIND_TRIGGER'=>		_('Cannot find trigger'),
	'S_CANNOT_FIND_HOST'=>			_('Cannot find host'),
	'S_CANNOT_FIND_HOSTGROUP'=>		_('Cannot find hostgroup'),
	'S_CANNOT_FIND_MAP'=>			_('Cannot find map'),
	'S_CANNOT_FIND_SCREEN'=>		_('Cannot find screen'),
	'S_USED_IN_EXPORTED_MAP_SMALL'=>_('used in exported map'),

	'S_INCORRECT_ELEMENT_MAP_LINK' => _('All links should have "Name" and "URL" specified'),
	'S_EACH_URL_SHOULD_HAVE_UNIQUE' => _('Each URL should have a unique name. Please make sure there is only one URL named'),

//	map.php
	'S_ZABBIX_URL'=>		_('http://www.zabbix.com'),
	'S_MAPS_DATE_FORMAT'=>	_('Y.m.d H:i:s'),
	'S_QUEUE_NODES_DATE_FORMAT'=>		_('d M Y H:i:s'),
	'S_SHOW'=>				_('Show'),

//	chart_bar.php
	'S_CHARTBAR_HOURLY_DATE_FORMAT'=>		_('Y.m.d H:i'),
	'S_CHARTBAR_DAILY_DATE_FORMAT'=>		_('Y.m.d'),
	'S_IT_NOTIFICATIONS'=>			_('Notification report'),
	'S_REPORT4_ANNUALLY_DATE_FORMAT'=>	_('Y'),
	'S_REPORT4_MONTHLY_DATE_FORMAT'=>	_('M Y'),
	'S_REPORT4_DAILY_DATE_FORMAT'=>		_('d M Y'),
	'S_REPORT4_WEEKLY_DATE_FORMAT'=>	_('d M Y H:i'),
	'S_REPORTS_BAR_REPORT_DATE_FORMAT'=>	_('d M Y H:i:s'),

	'S_SIMPLE_GRAPHS'=>				_('Simple graphs'),
	'S_HISTORY_AND_SIMPLE_GRAPHS'=> _('History and simple graphs'),
	'S_WIDTH'=>						_('Width'),
	'S_HEIGHT'=>					_('Height'),
	'S_EDIT'=>						_('Edit'),
	'S_CANNOT_FIND_GRAPH'=>			_('Cannot find graph'),
	'S_CANNOT_FIND_ITEM'=>			_('Cannot find item'),
	'S_USED_IN_EXPORTED_SCREEN_SMALL'=>_('used in exported screen'),

//	screenedit.php
	'S_MAP'=>					_('Map'),
	'S_RIGHT'=>				_('Right'),
	'S_TRIGGER'=>				_('Trigger'),
	'S_DELETE'=>				_('Delete'),
	'S_REMOVE'=>				_('Remove'),
	'S_URL'=>				_('URL'),
	'S_INSERT_MACRO'=>			_('Insert macro'),
	'S_ADD'=>						_('Add'),
	'S_SELECT' => _('Select'),
	'S_NAME'=>					_('Name'),
	'S_HIDE'=>					_('Hide'),
	'S_CLOSE'=>					_('Close'),
	'S_CLEAR' =>				_('Clear'),
	'S_MOVE'=>					_('Move'),
	'S_UNMUTE'=>				_('Unmute'),
	'S_MUTE'=>					_('Mute'),
	'S_SNOOZE'=>				_('Snooze'),
	'S_MESSAGES'=>				_('Messages'),

//	popup_period.php
	'S_POPUP_PERIOD_CAPTION_DATE_FORMAT'=>	_('d M Y H:i:s'),
	'S_DELETE_EXPRESSION_Q'=>	_('Delete expression?'),
	'S_DELETE_KEYWORD_Q'=>		_('Delete keyword?'),

// main.js
	'S_NO_ELEMENTS_SELECTED'=>	_('No elements selected!'),

//	class.calendar.js
	'S_MONDAY_SHORT_BIG'=>		_('M'),
	'S_TUESDAY_SHORT_BIG'=>		_('T'),
	'S_WEDNESDAY_SHORT_BIG'=>	_('W'),
	'S_THURSDAY_SHORT_BIG'=>	_('T'),
	'S_FRIDAY_SHORT_BIG'=>		_('F'),
	'S_SATURDAY_SHORT_BIG'=>	_('S'),
	'S_SUNDAY_SHORT_BIG'=>		_('S'),
	'S_NOW'=>	_('Now'),
	'S_DONE'=>	_('Done'),

//	gtlc.js
	'S_ZOOM'=>			_('Zoom'),
	'S_FIXED_SMALL'=>		_('fixed'),
	'S_DYNAMIC_SMALL'=>		_('dynamic'),
	'S_NOW_SMALL'=>			_('now'),

//	functions.js
	'S_CREATE_LOG_TRIGGER'=>			_('Create trigger'),
	'DO_YOU_REPLACE_CONDITIONAL_EXPRESSION_Q'=>	_('Do you wish to replace the conditional expression?'),
	'S_ADD_SERVICE'=>				_('Add service'),
	'S_EDIT_SERVICE'=>				_('Edit service'),
	'S_DELETE_SERVICE'=>				_('Delete service'),
	'S_DELETE_SELECTED_SERVICES_Q'=>		_('Delete selected services?'),

// class.cookie.js
	'S_MAX_COOKIE_SIZE_REACHED'=>		_('We are sorry, the maximum possible number of elements to remember has been reached.'),
	'S_ICONMAP_IS_NOT_ENABLED' => _('Iconmap is not enabled'),
);
