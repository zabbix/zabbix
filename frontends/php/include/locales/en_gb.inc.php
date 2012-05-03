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

	'S_YEAR_SHORT'=>			_x('y', 'year short'),
	'S_MONTH_SHORT'=>			_x('m', 'month short'),
	'S_WEEK_SHORT'=>			_x('w', 'week short'),
	'S_DAY_SHORT'=>				_x('d', 'day short'),
	'S_HOUR_SHORT' =>			_x('h', 'hour short'),
	'S_MINUTE_SHORT' =>			_x('m', 'minute short'),

//	exp_imp.php
	'S_ON'=>					_('On'),
	'S_OFF'=>					_('Off'),
	'S_TIME'=>				_('Time'),
	'S_DOT'=>				_('Dot'),
	'S_DEFAULT'=>					_('Default'),
	'S_IMAGE'=>					_('Image'),
	'S_ALL_S'=>						_('All'),

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

	'S_HOST'=>					_('Host'),
	'S_HOST_GROUP'=>				_('Host group'),
	'S_HISTORY'=>						_('History'),

//	sysmap.php
	'S_HIDDEN'=>			_('Hidden'),
	'S_SHOWN'=>				_('Shown'),
	'S_NO_IMAGES' => 'You need to have at least one image uploaded to create map element. Images can be uploaded in Administration->General->Images section.',

//	sysmaps.php
	'S_TWO_ELEMENTS_SHOULD_BE_SELECTED'=>		_('Two elements should be selected'),
	'S_DELETE_SELECTED_ELEMENTS_Q'=>		_('Delete selected elements?'),
	'S_PLEASE_SELECT_TWO_ELEMENTS'=>		_('Please select two elements'),
	'S_DELETE_LINKS_BETWEEN_SELECTED_ELEMENTS_Q'=>	_('Delete links between selected elements?'),
	'S_NEW_ELEMENT'=>				_('New element'),
	'S_INCORRECT_ELEMENT_MAP_LINK' => _('All links should have "Name" and "URL" specified'),
	'S_EACH_URL_SHOULD_HAVE_UNIQUE' => _('Each URL should have a unique name. Please make sure there is only one URL named'),

//	map.php
	'S_ZABBIX_URL'=>		_('http://www.zabbix.com'),

	'S_SIMPLE_GRAPHS'=>				_('Simple graphs'),
	'S_HISTORY_AND_SIMPLE_GRAPHS'=> _('History and simple graphs'),
	'S_USED_IN_EXPORTED_SCREEN_SMALL'=>_('used in exported screen'),

//	screenedit.php
	'S_MAP'=>					_('Map'),
	'S_TRIGGER'=>				_('Trigger'),
	'S_DELETE'=>				_('Delete'),
	'S_INSERT_MACRO'=>			_('Insert macro'),
	'S_CLOSE'=>					_('Close'),
	'S_CLEAR' =>				_('Clear'),
	'S_MOVE'=>					_('Move'),
	'S_UNMUTE'=>				_('Unmute'),
	'S_MUTE'=>					_('Mute'),
	'S_SNOOZE'=>				_('Snooze'),
	'S_MESSAGES'=>				_('Messages'),
	'S_DELETE_EXPRESSION_Q'=>	_('Delete expression?'),
	'S_DELETE_KEYWORD_Q'=>		_('Delete keyword?'),
	'S_NO_ELEMENTS_SELECTED'=>	_('No elements selected!'),

//	class.calendar.js
	'S_MONDAY_SHORT_BIG'=>		_x('M', 'Monday short'),
	'S_TUESDAY_SHORT_BIG'=>		_x('T', 'Tuesday short'),
	'S_WEDNESDAY_SHORT_BIG'=>	_x('W', 'Wednesday short'),
	'S_THURSDAY_SHORT_BIG'=>	_x('T', 'Thursday short'),
	'S_FRIDAY_SHORT_BIG'=>		_x('F', 'Friday short'),
	'S_SATURDAY_SHORT_BIG'=>	_x('S', 'Saturday short'),
	'S_SUNDAY_SHORT_BIG'=>		_x('S', 'Sunday short'),
	'S_NOW'=>	_('Now'),
	'S_DONE'=>	_('Done'),

	'S_ZOOM'=>			_('Zoom'),
	'S_FIXED_SMALL'=>		_('fixed'),
	'S_DYNAMIC_SMALL'=>		_('dynamic'),
	'S_NOW_SMALL'=>			_('now'),
	'S_CREATE_LOG_TRIGGER'=>			_('Create trigger'),
	'DO_YOU_REPLACE_CONDITIONAL_EXPRESSION_Q'=>	_('Do you wish to replace the conditional expression?'),
	'S_MAX_COOKIE_SIZE_REACHED'=>		_('We are sorry, the maximum possible number of elements to remember has been reached.'),
	'S_ICONMAP_IS_NOT_ENABLED' => _('Iconmap is not enabled'),
);
