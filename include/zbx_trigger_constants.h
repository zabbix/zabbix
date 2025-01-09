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

#ifndef ZABBIX_ZBX_TRIGGER_CONSTANTS_H
#define ZABBIX_ZBX_TRIGGER_CONSTANTS_H

#define ZBX_DC_TRIGGER_PROBLEM_EXPRESSION	0x1	/* this flag shows that trigger value recalculation is  */
							/* initiated by a time-based function or a new value of */
							/* an item in problem expression */

#define ZBX_TRIGGER_GET_ITEMIDS		0x0001

#define ZBX_TRIGGER_GET_DEFAULT		(~(unsigned int)ZBX_TRIGGER_GET_ITEMIDS)
#define ZBX_TRIGGER_GET_ALL		(~(unsigned int)0)

#define ZBX_TRIGGER_DEPENDENCY_LEVELS_MAX	32

#define ZBX_TRIGGER_DEPENDENCY_FAIL		1
#define ZBX_TRIGGER_DEPENDENCY_UNRESOLVED	2

/* trigger statuses */
#define TRIGGER_STATUS_ENABLED		0
#define TRIGGER_STATUS_DISABLED		1

/* trigger types */
#define TRIGGER_TYPE_NORMAL		0
#define TRIGGER_TYPE_MULTIPLE_TRUE	1

/* trigger values */
#define TRIGGER_VALUE_OK		0
#define TRIGGER_VALUE_PROBLEM		1
#define TRIGGER_VALUE_UNKNOWN		2	/* only in server code, never in DB */
#define TRIGGER_VALUE_NONE		3	/* only in server code, never in DB */

/* trigger states */
#define TRIGGER_STATE_NORMAL		0
#define TRIGGER_STATE_UNKNOWN		1

/* trigger severity */
#define TRIGGER_SEVERITY_NOT_CLASSIFIED	0
#define TRIGGER_SEVERITY_INFORMATION	1
#define TRIGGER_SEVERITY_WARNING	2
#define TRIGGER_SEVERITY_AVERAGE	3
#define TRIGGER_SEVERITY_HIGH		4
#define TRIGGER_SEVERITY_DISASTER	5
#define TRIGGER_SEVERITY_COUNT		6	/* number of trigger severities */

/* trigger recovery mode */
#define TRIGGER_RECOVERY_MODE_EXPRESSION		0
#define TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION	1
#define TRIGGER_RECOVERY_MODE_NONE			2

/* trigger correlation modes */
#define ZBX_TRIGGER_CORRELATION_NONE	0
#define ZBX_TRIGGER_CORRELATION_TAG	1

#endif /*ZABBIX_ZBX_TRIGGER_CONSTANTS_H*/
