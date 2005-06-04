<?php
/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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

	define("S_DATE_FORMAT_YMDHMS",				"d M H:i:s");
	define("S_DATE_FORMAT_YMD",				"d M Y");

//	about.php
	define("S_ABOUT_ZABBIX",			"About ZABBIX");
	define("S_INFORMATION_ABOUT_ZABBIX",		"Information about ZABBIX (v1.1alpha11)");
	define("S_HOMEPAGE_OF_ZABBIX",			"Homepage of ZABBIX");
	define("S_HOMEPAGE_OF_ZABBIX_DETAILS",		"This is home page of ZABBIX.");
	define("S_LATEST_ZABBIX_MANUAL",		"Latest ZABBIX Manual");
	define("S_LATEST_ZABBIX_MANUAL_DETAILS",	"Latest version of the Manual.");
	define("S_DOWNLOADS",				"Downloads");
	define("S_DOWNLOADS_DETAILS",			"Latest ZABBIX release can be found here.");
	define("S_FEATURE_REQUESTS",			"Feature requests");
	define("S_FEATURE_REQUESTS_DETAILS",		"If you need additional functionality, go here.");
	define("S_FORUMS",				"Forums");
	define("S_FORUMS_DETAILS",			"ZABBIX-related discussion.");
	define("S_BUG_REPORTS",				"Bug reports");
	define("S_BUG_REPORTS_DETAILS",			"Bug in ZABBIX ? Please, report it.");
	define("S_MAILING_LISTS",			"Mailing lists");
	define("S_MAILING_LISTS_DETAILS",		"ZABBIX-related mailing lists.");

//	actions.php
	define("S_ACTIONS",				"Actions");
	define("S_ACTION_ADDED",			"Action added");
	define("S_CANNOT_ADD_ACTION",			"Cannot add action");
	define("S_ACTION_UPDATED",			"Action updated");
	define("S_CANNOT_UPDATE_ACTION",		"Cannot update action");
	define("S_ACTION_DELETED",			"Action deleted");
	define("S_CANNOT_DELETE_ACTION",		"Cannot delete action");
	define("S_SCOPE",				"Scope");
	define("S_SEND_MESSAGE_TO",			"Send message to");
	define("S_WHEN_TRIGGER",			"When trigger");
	define("S_DELAY",				"Delay");
	define("S_SUBJECT",				"Subject");
	define("S_ON",					"ON");
	define("S_OFF",					"OFF");
	define("S_NO_ACTIONS_DEFINED",			"No actions defined");
	define("S_NEW_ACTION",				"New action");
	define("S_SINGLE_USER",				"Single user");
	define("S_USER_GROUP",				"User group");
	define("S_GROUP",				"Group");
	define("S_USER",				"User");
	define("S_WHEN_TRIGGER_BECOMES",		"When trigger becomes");
	define("S_ON_OR_OFF",				"ON or OFF");
	define("S_DELAY_BETWEEN_MESSAGES_IN_SEC",	"Delay between messages (in sec)");
	define("S_MESSAGE",				"Message");
	define("S_THIS_TRIGGER_ONLY",			"This trigger only");
	define("S_ALL_TRIGGERS_OF_THIS_HOST",		"All triggers of this host");
	define("S_ALL_TRIGGERS",			"All triggers");
	define("S_USE_IF_TRIGGER_SEVERITY",		"Use if trigger's severity equal or more than");
	define("S_NOT_CLASSIFIED",			"Not classified");
	define("S_INFORMATION",				"Information");
	define("S_WARNING",				"Warning");
	define("S_AVERAGE",				"Average");
	define("S_HIGH",				"High");
	define("S_DISASTER",				"Disaster");

//	alarms.php
	define("S_ALARMS",				"Alarms");
	define("S_ALARMS_SMALL",			"Alarms");
	define("S_ALARMS_BIG",				"ALARMS");
	define("S_SHOW_ONLY_LAST_100",			"Show only last 100");
	define("S_SHOW_ALL",				"Show all");
	define("S_TIME",				"Time");
	define("S_STATUS",				"Status");
	define("S_DURATION",				"Duration");
	define("S_SUM",					"Sum");
	define("S_TRUE_BIG",				"TRUE");
	define("S_FALSE_BIG",				"FALSE");
	define("S_DISABLED_BIG",			"DISABLED");
	define("S_UNKNOWN_BIG",				"UNKNOWN");

//	alerts.php
	define("S_ALERT_HISTORY_SMALL",			"Alert history");
	define("S_ALERT_HISTORY_BIG",			"ALERT HISTORY");
	define("S_ALERTS_BIG",				"ALERTS");
	define("S_TYPE",				"Type");
	define("S_RECIPIENTS",				"Recipient(s)");
	define("S_ERROR",				"Error");
	define("S_SENT",				"sent");
	define("S_NOT_SENT",				"not sent");
	define("S_NO_ALERTS",				"No alerts");
	define("S_SHOW_NEXT_100",			"Show next 100");
	define("S_SHOW_PREVIOUS_100",			"Show previous 100");

//	charts.php
	define("S_CUSTOM_GRAPHS",			"Custom graphs");
	define("S_GRAPHS_BIG",				"GRAPHS");
	define("S_NO_GRAPHS_TO_DISPLAY",		"No graphs to display");
	define("S_SELECT_GRAPH_TO_DISPLAY",		"Select graph to display");
	define("S_PERIOD",				"Period");
	define("S_1H",					"1h");
	define("S_2H",					"2h");
	define("S_4H",					"4h");
	define("S_8H",					"8h");
	define("S_12H",					"12h");
	define("S_24H",					"24h");
	define("S_WEEK_SMALL",				"week");
	define("S_MONTH_SMALL",				"month");
	define("S_YEAR_SMALL",				"year");
	define("S_KEEP_PERIOD",				"Keep period");
	define("S_ON_C",				"On");
	define("S_OFF_C",				"Off");
	define("S_MOVE",				"Move");
	define("S_SELECT_GRAPH_DOT_DOT_DOT",		"Select graph...");

// Colors
	define("S_BLACK",				"Black");
	define("S_BLUE",				"Blue");
	define("S_CYAN",				"Cyan");
	define("S_DARK_BLUE",				"Dark blue");
	define("S_DARK_GREEN",				"Dark green");
	define("S_DARK_RED",				"Dark red");
	define("S_DARK_YELLOW",				"Dark yellow");
	define("S_GREEN",				"Green");
	define("S_RED",					"Red");
	define("S_WHITE",				"White");
	define("S_YELLOW",				"Yellow");

//	config.php
	define("S_CONFIGURATION_OF_ZABBIX",		"Configuration of ZABBIX");
	define("S_CONFIGURATION_OF_ZABBIX_BIG",		"CONFIGURATION OF ZABBIX");
	define("S_CONFIGURATION_UPDATED",		"Configuration updated");
	define("S_CONFIGURATION_WAS_NOT_UPDATED",	"Configuration was not updated");
	define("S_ADDED_NEW_MEDIA_TYPE",		"Added new media type");
	define("S_NEW_MEDIA_TYPE_WAS_NOT_ADDED",	"New media type was not added");
	define("S_MEDIA_TYPE_UPDATED",			"Media type updated");
	define("S_MEDIA_TYPE_WAS_NOT_UPDATED",		"Media type was not updated");
	define("S_MEDIA_TYPE_DELETED",			"Media type deleted");
	define("S_MEDIA_TYPE_WAS_NOT_DELETED",		"Media type was not deleted");
	define("S_CONFIGURATION",			"Configuration");
	define("S_DO_NOT_KEEP_ACTIONS_OLDER_THAN",	"Do not keep actions older than (in days)");
	define("S_DO_NOT_KEEP_EVENTS_OLDER_THAN",	"Do not keep events older than (in days)");
	define("S_MEDIA_TYPES_BIG",			"MEDIA TYPES");
	define("S_NO_MEDIA_TYPES_DEFINED",		"No media types defined");
	define("S_SMTP_SERVER",				"SMTP server");
	define("S_SMTP_HELO",				"SMTP helo");
	define("S_SMTP_EMAIL",				"SMTP email");
	define("S_SCRIPT_NAME",				"Script name");
	define("S_DELETE_SELECTED_MEDIA",		"Delete selected media?");
	define("S_DELETE_SELECTED_IMAGE",		"Delete selected image?");
	define("S_HOUSEKEEPER",				"Housekeeper");
	define("S_MEDIA_TYPES",				"Media types");
	define("S_ESCALATION_RULES",			"Escalation rules");
	define("S_ESCALATION",				"Escalation");
	define("S_ESCALATION_RULES_BIG",		"ESCALATION RULES");
	define("S_NO_ESCALATION_RULES_DEFINED",		"No escalation rules defined");
	define("S_NO_ESCALATION_DETAILS",		"No escalation details");
	define("S_ESCALATION_DETAILS_BIG",		"ESCALATION DETAILS");
	define("S_ESCALATION_ADDED",			"Escalation added");
	define("S_ESCALATION_WAS_NOT_ADDED",		"Escalation was not added");
	define("S_ESCALATION_UPDATED",			"Escalation updated");
	define("S_ESCALATION_WAS_NOT_UPDATED",		"Escalation was not updated");
	define("S_ESCALATION_DELETED",			"Escalation deleted");
	define("S_ESCALATION_WAS_NOT_DELETED",		"Escalation was not deleted");
	define("S_DEFAULT",				"Default");
	define("S_IS_DEFAULT",				"Is default");
	define("S_LEVEL",				"Level");
	define("S_DELAY_BEFORE_ACTION",			"Delay before action");
	define("S_IMAGES",				"Images");
	define("S_IMAGE",				"Image");
	define("S_IMAGES_BIG",				"IMAGES");
	define("S_NO_IMAGES_DEFINED",			"No images defined");
	define("S_BACKGROUND",				"Background");
	define("S_UPLOAD",				"Upload");
	define("S_IMAGE_ADDED",				"Image added");
	define("S_CANNOT_ADD_IMAGE",			"Cannot add image");
	define("S_IMAGE_DELETED",			"Image deleted");
	define("S_CANNOT_DELETE_IMAGE",			"Cannot delete image");

//	Latest values
	define("S_LATEST_VALUES",			"Latest values");
	define("S_NO_PERMISSIONS",			"No permissions !");
	define("S_LATEST_DATA",				"LATEST DATA");
	define("S_ALL_SMALL",				"all");
	define("S_DESCRIPTION_LARGE",			"DESCRIPTION");
	define("S_DESCRIPTION_SMALL",			"Description");
	define("S_GRAPH",				"Graph");
	define("S_TREND",				"Trend");
	define("S_COMPARE",				"Compare");

//	Footer
	define("S_ZABBIX_VER",				"ZABBIX 1.1alpha11");
	define("S_COPYRIGHT_BY",			"Copyright 2001-2005 by ");
	define("S_CONNECTED_AS",			"Connected as");
	define("S_SIA_ZABBIX",				"SIA Zabbix");

//	graph.php
	define("S_CONFIGURATION_OF_GRAPH",		"Configuration of graph");
	define("S_CONFIGURATION_OF_GRAPH_BIG",		"CONFIGURATION OF GRAPH");
	define("S_ITEM_ADDED",				"Item added");
	define("S_ITEM_UPDATED",			"Item updated");
	define("S_SORT_ORDER_UPDATED",			"Sort order updated");
	define("S_CANNOT_UPDATE_SORT_ORDER",		"Cannot update sort order");
	define("S_DISPLAYED_PARAMETERS_BIG",		"DISPLAYED PARAMETERS");
	define("S_SORT_ORDER",				"Sort order");
	define("S_PARAMETER",				"Parameter");
	define("S_COLOR",				"Color");
	define("S_UP",					"Up");
	define("S_DOWN",				"Down");
	define("S_NEW_ITEM_FOR_THE_GRAPH",		"New item for the graph");
	define("S_SORT_ORDER_1_100",			"Sort order (0->100)");

//	graphs.php
	define("S_CONFIGURATION_OF_GRAPHS",		"Configuration of graphs");
	define("S_CONFIGURATION_OF_GRAPHS_BIG",		"CONFIGURATION OF GRAPHS");
	define("S_GRAPH_ADDED",				"Graph added");
	define("S_GRAPH_UPDATED",			"Graph updated");
	define("S_CANNOT_UPDATE_GRAPH",			"Cannot update graph");
	define("S_GRAPH_DELETED",			"Graph deleted");
	define("S_CANNOT_DELETE_GRAPH",			"Cannot delete graph");
	define("S_CANNOT_ADD_GRAPH",			"Cannot add graph");
	define("S_ID",					"Id");
	define("S_NO_GRAPHS_DEFINED",			"No graphs defined");
	define("S_DELETE_GRAPH_Q",			"Delete graph?");
	define("S_YAXIS_TYPE",				"Y axis type");
	define("S_YAXIS_MIN_VALUE",			"Y axis MIN value");
	define("S_YAXIS_MAX_VALUE",			"Y axis MAX value");
	define("S_CALCULATED",				"Calculated");
	define("S_FIXED",				"Fixed");

//	history.php
	define("S_LAST_HOUR_GRAPH",			"Last hour graph");
	define("S_LAST_HOUR_GRAPH_DIFF",		"Last hour graph (diff)");
	define("S_VALUES_OF_LAST_HOUR",			"Values of last hour");
	define("S_VALUES_OF_SPECIFIED_PERIOD",		"Values of specified period");
	define("S_VALUES_IN_PLAIN_TEXT_FORMAT",		"Values in plain text format");
	define("S_TIMESTAMP",				"Timestamp");

//	hosts.php
	define("S_HOSTS",				"Hosts");
	define("S_ITEMS",				"Items");
	define("S_TRIGGERS",				"Triggers");
	define("S_GRAPHS",				"Graphs");
	define("S_HOST_ADDED",				"Host added");
	define("S_CANNOT_ADD_HOST",			"Cannot add host");
	define("S_ITEMS_ADDED",				"Items added");
	define("S_CANNOT_ADD_ITEMS",			"Cannot add items");
	define("S_HOST_UPDATED",			"Host updated");
	define("S_CANNOT_UPDATE_HOST",			"Cannot update host");
	define("S_HOST_STATUS_UPDATED",			"Host status updated");
	define("S_CANNOT_UPDATE_HOST_STATUS",		"Cannot update host status");
	define("S_HOST_DELETED",			"Host deleted");
	define("S_CANNOT_DELETE_HOST",			"Cannot delete host");
	define("S_TEMPLATE_LINKAGE_ADDED",		"Template linkage added");
	define("S_CANNOT_ADD_TEMPLATE_LINKAGE",		"Cannot add template linkage");
	define("S_TEMPLATE_LINKAGE_UPDATED",		"Template linkage updated");
	define("S_CANNOT_UPDATE_TEMPLATE_LINKAGE",	"Cannot update template linkage");
	define("S_TEMPLATE_LINKAGE_DELETED",		"Template linkage deleted");
	define("S_CANNOT_DELETE_TEMPLATE_LINKAGE",	"Cannot delete template linkage");
	define("S_CONFIGURATION_OF_HOSTS_AND_HOST_GROUPS","CONFIGURATION OF HOSTS AND HOST GROUPS");
	define("S_HOST_GROUPS_BIG",			"HOST GROUPS");
	define("S_NO_HOST_GROUPS_DEFINED",		"No host groups defined");
	define("S_NO_LINKAGES_DEFINED",			"No linkages defined");
	define("S_NO_HOSTS_DEFINED",			"No hosts defined");
	define("S_HOSTS_BIG",				"HOSTS");
	define("S_HOST",				"Host");
	define("S_IP",					"IP");
	define("S_PORT",				"Port");
	define("S_MONITORED",				"Monitored");
	define("S_NOT_MONITORED",			"Not monitored");
	define("S_UNREACHABLE",				"Unreachable");
	define("S_TEMPLATE",				"Template");
	define("S_DELETED",				"Deleted");
	define("S_UNKNOWN",				"Unknown");
	define("S_GROUPS",				"Groups");
	define("S_NEW_GROUP",				"New group");
	define("S_USE_IP_ADDRESS",			"Use IP address");
	define("S_IP_ADDRESS",				"IP address");
//	define("S_USE_THE_HOST_AS_A_TEMPLATE",		"Use the host as a template");
	define("S_USE_TEMPLATES_OF_THIS_HOST",		"Use templates of this host");
	define("S_DELETE_SELECTED_HOST_Q",		"Delete selected host?");
	define("S_GROUP_NAME",				"Group name");
	define("S_HOST_GROUP",				"Host group");
	define("S_HOST_GROUPS",				"Host groups");
	define("S_UPDATE",				"Update");
	define("S_AVAILABILITY",			"Availability");
	define("S_AVAILABLE",				"Available");
	define("S_NOT_AVAILABLE",			"Not available");

//	items.php
	define("S_CONFIGURATION_OF_ITEMS",		"Configuration of items");
	define("S_CONFIGURATION_OF_ITEMS_BIG",		"CONFIGURATION OF ITEMS");
	define("S_CANNOT_UPDATE_ITEM",			"Cannot update item");
	define("S_STATUS_UPDATED",			"Status updated");
	define("S_CANNOT_UPDATE_STATUS",		"Cannot update status");
	define("S_CANNOT_ADD_ITEM",			"Cannot add item");
	define("S_ITEM_DELETED",			"Item deleted");
	define("S_CANNOT_DELETE_ITEM",			"Cannot delete item");
	define("S_ITEMS_DELETED",			"Items deleted");
	define("S_CANNOT_DELETE_ITEMS",			"Cannot delete items");
	define("S_ITEMS_ACTIVATED",			"Items activated");
	define("S_CANNOT_ACTIVATE_ITEMS",		"Cannot activate items");
	define("S_ITEMS_DISABLED",			"Items disabled");
	define("S_KEY",					"Key");
	define("S_DESCRIPTION",				"Description");
	define("S_UPDATE_INTERVAL",			"Update interval");
	define("S_HISTORY",				"History");
	define("S_TRENDS",				"Trends");
	define("S_SHORT_NAME",				"Short name");
	define("S_ZABBIX_AGENT",			"ZABBIX agent");
	define("S_ZABBIX_AGENT_ACTIVE",			"ZABBIX agent (active)");
	define("S_SNMPV1_AGENT",			"SNMPv1 agent");
	define("S_ZABBIX_TRAPPER",			"ZABBIX trapper");
	define("S_SIMPLE_CHECK",			"Simple check");
	define("S_SNMPV2_AGENT",			"SNMPv2 agent");
	define("S_SNMPV3_AGENT",			"SNMPv3 agent");
	define("S_ZABBIX_INTERNAL",			"ZABBIX internal");
	define("S_ZABBIX_UNKNOWN",			"Unknown");
	define("S_ACTIVE",				"Active");
	define("S_NOT_ACTIVE",				"Not active");
	define("S_NOT_SUPPORTED",			"Not supported");
	define("S_ACTIVATE_SELECTED_ITEMS_Q",		"Activate selected items?");
	define("S_DISABLE_SELECTED_ITEMS_Q",		"Disable selected items?");
	define("S_DELETE_SELECTED_ITEMS_Q",		"Delete selected items?");
	define("S_EMAIL",				"Email");
	define("S_SCRIPT",				"Script");
	define("S_UNITS",				"Units");
	define("S_MULTIPLIER",				"Multiplier");
	define("S_UPDATE_INTERVAL_IN_SEC",		"Update interval (in sec)");
	define("S_KEEP_HISTORY_IN_DAYS",		"Keep history (in days)");
	define("S_KEEP_TRENDS_IN_DAYS",			"Keep trends (in days)");
	define("S_TYPE_OF_INFORMATION",			"Type of information");
	define("S_STORE_VALUE",				"Store value");
	define("S_NUMERIC",				"Numeric");
	define("S_CHARACTER",				"Character");
	define("S_LOG",					"Log");
	define("S_AS_IS",				"As is");
	define("S_DELTA_SPEED_PER_SECOND",		"Delta (speed per second)");
	define("S_DELTA_SIMPLE_CHANGE",			"Delta (simple change)");
	define("S_ITEM",				"Item");
	define("S_SNMP_COMMUNITY",			"SNMP community");
	define("S_SNMP_OID",				"SNMP OID");
	define("S_SNMP_PORT",				"SNMP port");
	define("S_ALLOWED_HOSTS",			"Allowed hosts");
	define("S_SNMPV3_SECURITY_NAME",		"SNMPv3 security name");
	define("S_SNMPV3_SECURITY_LEVEL",		"SNMPv3 security level");
	define("S_SNMPV3_AUTH_PASSPHRASE",		"SNMPv3 auth passphrase");
	define("S_SNMPV3_PRIV_PASSPHRASE",		"SNMPv3 priv passphrase");
	define("S_CUSTOM_MULTIPLIER",			"Custom multiplier");
	define("S_DO_NOT_USE",				"Do not use");
	define("S_USE_MULTIPLIER",			"Use multiplier");
	define("S_SELECT_HOST_DOT_DOT_DOT",		"Select host...");

//	latestalarms.php
	define("S_LATEST_EVENTS",			"Latest events");
	define("S_HISTORY_OF_EVENTS_BIG",		"HISTORY OF EVENTS");

//	latest.php
	define("S_LAST_CHECK",				"Last check");
	define("S_LAST_CHECK_BIG",			"LAST CHECK");
	define("S_LAST_VALUE",				"Last value");

//	sysmap.php
	define("S_LABEL",				"Label");
	define("S_X",					"X");
	define("S_Y",					"Y");
	define("S_ICON",				"Icon");
	define("S_HOST_1",				"Host 1");
	define("S_HOST_2",				"Host 2");
	define("S_LINK_STATUS_INDICATOR",		"Link status indicator");

//	map.php
	define("S_OK_BIG",				"OK");
	define("S_PROBLEMS_SMALL",			"problems");
	define("S_ZABBIX_URL",				"http://www.zabbix.com");

//	maps.php
	define("S_NETWORK_MAPS",			"Network maps");
	define("S_NETWORK_MAPS_BIG",			"NETWORK MAPS");
	define("S_NO_MAPS_TO_DISPLAY",			"No maps to display");
	define("S_SELECT_MAP_TO_DISPLAY",		"Select map to display");
	define("S_SELECT_MAP_DOT_DOT_DOT",		"Select map...");
	define("S_BACKGROUND_IMAGE",			"Background image");
	define("S_ICON_LABEL_TYPE",			"Icon label type");
	define("S_HOST_LABEL",				"Host label");
	define("S_HOST_NAME",				"Host name");
	define("S_STATUS_ONLY",				"Status only");
	define("S_NOTHING",				"Nothing");

//	media.php
	define("S_MEDIA",				"Media");
	define("S_MEDIA_BIG",				"MEDIA");
	define("S_MEDIA_ACTIVATED",			"Media activated");
	define("S_CANNOT_ACTIVATE_MEDIA",		"Cannot activate media");
	define("S_MEDIA_DISABLED",			"Media disabled");
	define("S_CANNOT_DISABLE_MEDIA",		"Cannot disable media");
	define("S_MEDIA_ADDED",				"Media added");
	define("S_CANNOT_ADD_MEDIA",			"Cannot add media");
	define("S_MEDIA_UPDATED",			"Media updated");
	define("S_CANNOT_UPDATE_MEDIA",			"Cannot update media");
	define("S_MEDIA_DELETED",			"Media deleted");
	define("S_CANNOT_DELETE_MEDIA",			"Cannot delete media");
	define("S_SEND_TO",				"Send to");
	define("S_WHEN_ACTIVE",				"When active");
	define("S_NO_MEDIA_DEFINED",			"No media defined");
	define("S_NEW_MEDIA",				"New media");
	define("S_USE_IF_SEVERITY",			"Use if severity");
	define("S_DELETE_SELECTED_MEDIA_Q",		"Delete selected media?");

//	Menu
	define("S_MENU_LATEST_VALUES",			"LATEST VALUES");
	define("S_MENU_TRIGGERS",			"TRIGGERS");
	define("S_MENU_QUEUE",				"QUEUE");
	define("S_MENU_ALARMS",				"ALARMS");
	define("S_MENU_ALERTS",				"ALERTS");
	define("S_MENU_NETWORK_MAPS",			"NETWORK MAPS");
	define("S_MENU_GRAPHS",				"GRAPHS");
	define("S_MENU_SCREENS",			"SCREENS");
	define("S_MENU_IT_SERVICES",			"IT SERVICES");
	define("S_MENU_HOME",				"HOME");
	define("S_MENU_ABOUT",				"ABOUT");
	define("S_MENU_STATUS_OF_ZABBIX",		"STATUS OF ZABBIX");
	define("S_MENU_AVAILABILITY_REPORT",		"AVAILABILITY REPORT");
	define("S_MENU_CONFIG",				"CONFIG");
	define("S_MENU_USERS",				"USERS");
	define("S_MENU_HOSTS",				"HOSTS");
	define("S_MENU_ITEMS",				"ITEMS");
	define("S_MENU_AUDIT",				"AUDIT");

//	overview.php
	define("S_SELECT_GROUP_DOT_DOT_DOT",		"Select group ...");
	define("S_OVERVIEW",				"Overview");
	define("S_OVERVIEW_BIG",			"OVERVIEW");
	define("S_EXCL",				"!");
	define("S_DATA",				"Data");

//	queue.php
	define("S_QUEUE_BIG",				"QUEUE");
	define("S_QUEUE_OF_ITEMS_TO_BE_UPDATED_BIG",	"QUEUE OF ITEMS TO BE UPDATED");
	define("S_NEXT_CHECK",				"Next check");
	define("S_THE_QUEUE_IS_EMPTY",			"The queue is empty");
	define("S_TOTAL",				"Total");
	define("S_COUNT",				"Count");
	define("S_5_SECONDS",				"5 seconds");
	define("S_10_SECONDS",				"10 seconds");
	define("S_30_SECONDS",				"30 seconds");
	define("S_1_MINUTE",				"1 minute");
	define("S_5_MINUTES",				"5 minutes");
	define("S_MORE_THAN_5_MINUTES",			"More than 5 minutes");

//	report1.php
	define("S_STATUS_OF_ZABBIX",			"Status of ZABBIX");
	define("S_STATUS_OF_ZABBIX_BIG",		"STATUS OF ZABBIX");
	define("S_VALUE",				"Value");
	define("S_ZABBIX_SERVER_IS_RUNNING",		"ZABBIX server is running");
	define("S_NUMBER_OF_VALUES_STORED",		"Number of values stored");
	define("S_NUMBER_OF_TRENDS_STORED",		"Number of trends stored");
	define("S_NUMBER_OF_ALARMS",			"Number of alarms");
	define("S_NUMBER_OF_ALERTS",			"Number of alerts");
	define("S_NUMBER_OF_TRIGGERS_ENABLED_DISABLED",	"Number of triggers (enabled/disabled)");
	define("S_NUMBER_OF_ITEMS_ACTIVE_TRAPPER",	"Number of items (active/trapper/not active/not supported)");
	define("S_NUMBER_OF_USERS",			"Number of users");
	define("S_NUMBER_OF_HOSTS_MONITORED",		"Number of hosts (monitored/not monitored/templates)");
	define("S_YES",					"Yes");
	define("S_NO",					"No");

//	report2.php
	define("S_AVAILABILITY_REPORT",			"Availability report");
	define("S_AVAILABILITY_REPORT_BIG",		"AVAILABILITY REPORT");
	define("S_SHOW",				"Show");
	define("S_TRUE",				"True");
	define("S_FALSE",				"False");

//	report3.php
	define("S_IT_SERVICES_AVAILABILITY_REPORT_BIG",	"IT SERVICES AVAILABILITY REPORT");
	define("S_FROM",				"From");
	define("S_TILL",				"Till");
	define("S_OK",					"Ok");
	define("S_PROBLEMS",				"Problems");
	define("S_PERCENTAGE",				"Percentage");
	define("S_SLA",					"SLA");
	define("S_DAY",					"Day");
	define("S_MONTH",				"Month");
	define("S_YEAR",				"Year");
	define("S_DAILY",				"Daily");
	define("S_WEEKLY",				"Weekly");
	define("S_MONTHLY",				"Monthly");
	define("S_YEARLY",				"Yearly");

//	screenconf.php
	define("S_SCREENS",				"Screens");
	define("S_SCREEN",				"Screen");
	define("S_CONFIGURATION_OF_SCREENS_BIG",	"CONFIGURATION OF SCREENS");
	define("S_SCREEN_ADDED",			"Screen added");
	define("S_CANNOT_ADD_SCREEN",			"Cannot add screen");
	define("S_SCREEN_UPDATED",			"Screen updated");
	define("S_CANNOT_UPDATE_SCREEN",		"Cannot update screen");
	define("S_SCREEN_DELETED",			"Screen deleted");
	define("S_CANNOT_DELETE_SCREEN",		"Cannot deleted screen");
	define("S_COLUMNS",				"Columns");
	define("S_ROWS",				"Rows");
	define("S_NO_SCREENS_DEFINED",			"No screens defined");
	define("S_DELETE_SCREEN_Q",			"Delete screen?");
	define("S_CONFIGURATION_OF_SCREEN_BIG",		"CONFIGURATION OF SCREEN");
	define("S_SCREEN_CELL_CONFIGURATION",		"Screen cell configuration");
	define("S_RESOURCE",				"Resource");
	define("S_SIMPLE_GRAPH",			"Simple graph");
	define("S_GRAPH_NAME",				"Graph name");
	define("S_WIDTH",				"Width");
	define("S_HEIGHT",				"Height");
	define("S_EMPTY",				"Empty");

//	screenedit.php
	define("S_MAP",					"Map");
	define("S_PLAIN_TEXT",				"Plain text");
	define("S_COLUMN_SPAN",				"Column span");
	define("S_ROW_SPAN",				"Row span");

//	screens.php
	define("S_CUSTOM_SCREENS",			"Custom screens");
	define("S_SCREENS_BIG",				"SCREENS");
	define("S_NO_SCREENS_TO_DISPLAY",		"No screens to display");
	define("S_SELECT_SCREEN_TO_DISPLAY",		"Select screen to display");
	define("S_SELECT_SCREEN_DOT_DOT_DOT",		"Select screen ...");

//	services.php
	define("S_IT_SERVICES",				"IT services");
	define("S_SERVICE_UPDATED",			"Service updated");
	define("S_CANNOT_UPDATE_SERVICE",		"Cannot update service");
	define("S_SERVICE_ADDED",			"Service added");
	define("S_CANNOT_ADD_SERVICE",			"Cannot add service");
	define("S_LINK_ADDED",				"Link added");
	define("S_CANNOT_ADD_LINK",			"Cannot add link");
	define("S_SERVICE_DELETED",			"Service deleted");
	define("S_CANNOT_DELETE_SERVICE",		"Cannot delete service");
	define("S_LINK_DELETED",			"Link deleted");
	define("S_CANNOT_DELETE_LINK",			"Cannot delete link");
	define("S_STATUS_CALCULATION",			"Status calculation");
	define("S_STATUS_CALCULATION_ALGORITHM",	"Status calculation algorithm");
	define("S_NONE",				"None");
	define("S_MAX_OF_CHILDS",			"MAX of childs");
	define("S_MIN_OF_CHILDS",			"MIN of childs");
	define("S_SERVICE_1",				"Service 1");
	define("S_SERVICE_2",				"Service 2");
	define("S_SOFT_HARD_LINK",			"Soft/hard link");
	define("S_SOFT",				"Soft");
	define("S_HARD",				"Hard");
	define("S_DO_NOT_CALCULATE",			"Do not calculate");
	define("S_MAX_BIG",				"MAX");
	define("S_MIN_BIG",				"MIN");
	define("S_SHOW_SLA",				"Show SLA");
	define("S_ACCEPTABLE_SLA_IN_PERCENT",		"Acceptabe SLA (in %)");
	define("S_LINK_TO_TRIGGER_Q",			"Link to trigger?");
	define("S_SORT_ORDER_0_999",			"Sort order (0->999)");
	define("S_DELETE_SERVICE_Q",			"S_DELETE_SERVICE_Q");
	define("S_LINK_TO",				"Link to");
	define("S_SOFT_LINK_Q",				"Soft link?");
	define("S_ADD_SERVER_DETAILS",			"Add server details");
	define("S_TRIGGER",				"Trigger");
	define("S_SERVER",				"Server");
	define("S_DELETE",				"Delete");

//	srv_status.php
	define("S_IT_SERVICES_BIG",			"IT SERVICES");
	define("S_SERVICE",				"Service");
	define("S_REASON",				"Reason");
	define("S_SLA_LAST_7_DAYS",			"SLA (last 7 days)");
	define("S_PLANNED_CURRENT_SLA",			"Planned/current SLA");
	define("S_TRIGGER_BIG",				"TRIGGER");

//	triggers.php
	define("S_CONFIGURATION_OF_TRIGGERS",		"Configuration of triggers");
	define("S_CONFIGURATION_OF_TRIGGERS_BIG",	"CONFIGURATION OF TRIGGERS");
	define("S_DEPENDENCY_ADDED",			"Dependency added");
	define("S_CANNOT_ADD_DEPENDENCY",		"Cannot add dependency");
	define("S_TRIGGERS_UPDATED",			"Triggers updated");
	define("S_CANNOT_UPDATE_TRIGGERS",		"Cannot update triggers");
	define("S_TRIGGERS_DISABLED",			"Triggers disabled");
	define("S_CANNOT_DISABLE_TRIGGERS",		"Cannot disable triggers");
	define("S_TRIGGERS_DELETED",			"Triggers deleted");
	define("S_CANNOT_DELETE_TRIGGERS",		"Cannot delete triggers");
	define("S_TRIGGER_DELETED",			"Trigger deleted");
	define("S_CANNOT_DELETE_TRIGGER",		"Cannot delete trigger");
	define("S_INVALID_TRIGGER_EXPRESSION",		"Invalid trigger expression");
	define("S_TRIGGER_ADDED",			"Trigger added");
	define("S_CANNOT_ADD_TRIGGER",			"Cannot add trigger");
	define("S_SEVERITY",				"Severity");
	define("S_EXPRESSION",				"Expression");
	define("S_DISABLED",				"Disabled");
	define("S_ENABLED",				"Enabled");
	define("S_ENABLE_SELECTED_TRIGGERS_Q",		"Enable selected triggers?");
	define("S_DISABLE_SELECTED_TRIGGERS_Q",		"Disable selected triggers?");
	define("S_CHANGE",				"Change");
	define("S_TRIGGER_UPDATED",			"Trigger updated");
	define("S_CANNOT_UPDATE_TRIGGER",		"Cannot update trigger");
	define("S_DEPENDS_ON",				"Depends on");

//	tr_comments.php
	define("S_TRIGGER_COMMENTS",			"Trigger comments");
	define("S_TRIGGER_COMMENTS_BIG",		"TRIGGER COMMENTS");
	define("S_COMMENT_UPDATED",			"Comment updated");
	define("S_CANNOT_UPDATE_COMMENT",		"Cannot update comment");
	define("S_ADD",					"Add");

//	tr_status.php
	define("S_STATUS_OF_TRIGGERS",			"Status of triggers");
	define("S_STATUS_OF_TRIGGERS_BIG",		"STATUS OF TRIGGERS");
	define("S_SHOW_ONLY_TRUE",			"Show only true");
	define("S_HIDE_ACTIONS",			"Hide actions");
	define("S_SHOW_ACTIONS",			"Show actions");
	define("S_SHOW_ALL_TRIGGERS",			"Show all triggers");
	define("S_HIDE_DETAILS",			"Hide details");
	define("S_SHOW_DETAILS",			"Show details");
	define("S_SELECT",				"Select");
	define("S_HIDE_SELECT",				"Hide select");
	define("S_TRIGGERS_BIG",			"TRIGGERS");
	define("S_DESCRIPTION_BIG",			"DESCRIPTION");
	define("S_SEVERITY_BIG",			"SEVERITY");
	define("S_LAST_CHANGE_BIG",			"LAST CHANGE");
	define("S_LAST_CHANGE",				"Last change");
	define("S_COMMENTS",				"Comments");

//	users.php
	define("S_USERS",				"Users");
	define("S_USER_ADDED",				"User added");
	define("S_CANNOT_ADD_USER",			"Cannot add user");
	define("S_CANNOT_ADD_USER_BOTH_PASSWORDS_MUST",	"Cannot add user. Both passwords must be equal.");
	define("S_USER_DELETED",			"User deleted");
	define("S_CANNOT_DELETE_USER",			"Cannot delete user");
	define("S_PERMISSION_DELETED",			"Permission deleted");
	define("S_CANNOT_DELETE_PERMISSION",		"Cannot delete permission");
	define("S_PERMISSION_ADDED",			"Permission added");
	define("S_CANNOT_ADD_PERMISSION",		"Cannot add permission");
	define("S_USER_UPDATED",			"User updated");
	define("S_CANNOT_UPDATE_USER",			"Cannot update user");
	define("S_CANNOT_UPDATE_USER_BOTH_PASSWORDS",	"Cannot update user. Both passwords must be equal.");
	define("S_GROUP_ADDED",				"Group added");
	define("S_CANNOT_ADD_GROUP",			"Cannot add group");
	define("S_GROUP_UPDATED",			"Group updated");
	define("S_CANNOT_UPDATE_GROUP",			"Cannot update group");
	define("S_GROUP_DELETED",			"Group deleted");
	define("S_CANNOT_DELETE_GROUP",			"Cannot delete group");
	define("S_CONFIGURATION_OF_USERS_AND_USER_GROUPS","CONFIGURATION OF USERS AND USER GROUPS");
	define("S_USER_GROUPS_BIG",			"USER GROUPS");
	define("S_USERS_BIG",				"USERS");
	define("S_USER_GROUPS",				"User groups");
	define("S_MEMBERS",				"Members");
	define("S_TEMPLATES",				"Templates");
	define("S_HOSTS_TEMPLATES_LINKAGE",		"Hosts/templates linkage");
	define("S_CONFIGURATION_OF_TEMPLATES_LINKAGE",	"CONFIGURATION OF TEMPLATES LINKAGE");
	define("S_LINKED_TEMPLATES_BIG",		"LINKED TEMPLATES");
	define("S_NO_USER_GROUPS_DEFINED",		"No user groups defined");
	define("S_ALIAS",				"Alias");
	define("S_NAME",				"Name");
	define("S_SURNAME",				"Surname");
	define("S_IS_ONLINE_Q",				"Is online?");
	define("S_NO_USERS_DEFINED",			"No users defined");
	define("S_PERMISSION",				"Permission");
	define("S_RIGHT",				"Right");
	define("S_RESOURCE_NAME",			"Resource name");
	define("S_READ_ONLY",				"Read only");
	define("S_READ_WRITE",				"Read-write");
	define("S_HIDE",				"Hide");
	define("S_PASSWORD",				"Password");
	define("S_PASSWORD_ONCE_AGAIN",			"Password (once again)");
	define("S_URL_AFTER_LOGIN",			"URL (after login)");

//	audit.php
	define("S_AUDIT_LOG",				"Audit log");
	define("S_AUDIT_LOG_BIG",			"AUDIT LOG");
	define("S_ACTION",				"Action");
	define("S_DETAILS",				"Details");
	define("S_UNKNOWN_ACTION",			"Unknown action");
	define("S_ADDED",				"Added");
	define("S_UPDATED",				"Updated");
	define("S_LOGGED_IN",				"Logged in");
	define("S_LOGGED_OUT",				"Logged out");
	define("S_MEDIA_TYPE",				"Media type");
	define("S_GRAPH_ELEMENT",			"Graph element");
?>
