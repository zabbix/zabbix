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

#include "datafunc.h"
#include "evalfunc.h"

#include "zbx_item_constants.h"
#include "zbx_trigger_constants.h"
#include "zbx_discoverer_constants.h"
#include "zbxdbhigh.h"
#include "zbxexpression.h"

const char	*item_logtype_string(unsigned char logtype)
{
	switch (logtype)
	{
		case ITEM_LOGTYPE_INFORMATION:
			return "Information";
		case ITEM_LOGTYPE_WARNING:
			return "Warning";
		case ITEM_LOGTYPE_ERROR:
			return "Error";
		case ITEM_LOGTYPE_FAILURE_AUDIT:
			return "Failure Audit";
		case ITEM_LOGTYPE_SUCCESS_AUDIT:
			return "Success Audit";
		case ITEM_LOGTYPE_CRITICAL:
			return "Critical";
		case ITEM_LOGTYPE_VERBOSE:
			return "Verbose";
		default:
			return "unknown";
	}
}

const char	*alert_type_string(unsigned char type)
{
	switch (type)
	{
		case ALERT_TYPE_MESSAGE:
			return "message";
		default:
			return "script";
	}
}

const char	*alert_status_string(unsigned char type, unsigned char status)
{
	switch (status)
	{
		case ALERT_STATUS_SENT:
			return (ALERT_TYPE_MESSAGE == type ? "sent" : "executed");
		case ALERT_STATUS_NOT_SENT:
			return "in progress";
		default:
			return "failed";
	}
}

const char	*trigger_state_string(int state)
{
	switch (state)
	{
		case TRIGGER_STATE_NORMAL:
			return "Normal";
		case TRIGGER_STATE_UNKNOWN:
			return "Unknown";
		default:
			return "unknown";
	}
}

const char	*item_state_string(int state)
{
	switch (state)
	{
		case ITEM_STATE_NORMAL:
			return "Normal";
		case ITEM_STATE_NOTSUPPORTED:
			return "Not supported";
		default:
			return "unknown";
	}
}

const char	*event_value_string(int source, int object, int value)
{
	if (EVENT_SOURCE_TRIGGERS == source || EVENT_SOURCE_SERVICE == source)
	{
		switch (value)
		{
			case EVENT_STATUS_PROBLEM:
				return "PROBLEM";
			case EVENT_STATUS_RESOLVED:
				return "RESOLVED";
			default:
				return "unknown";
		}
	}

	if (EVENT_SOURCE_INTERNAL == source)
	{
		switch (object)
		{
			case EVENT_OBJECT_TRIGGER:
				return trigger_state_string(value);
			case EVENT_OBJECT_ITEM:
			case EVENT_OBJECT_LLDRULE:
				return item_state_string(value);
		}
	}

	return "unknown";
}

const char	*zbx_dobject_status2str(int st)
{
	switch (st)
	{
		case DOBJECT_STATUS_UP:
			return "UP";
		case DOBJECT_STATUS_DOWN:
			return "DOWN";
		case DOBJECT_STATUS_DISCOVER:
			return "DISCOVERED";
		case DOBJECT_STATUS_LOST:
			return "LOST";
		default:
			return "UNKNOWN";
	}
}

const char	*zbx_dservice_type_string(zbx_dservice_type_t service)
{
	switch (service)
	{
		case SVC_SSH:
			return "SSH";
		case SVC_LDAP:
			return "LDAP";
		case SVC_SMTP:
			return "SMTP";
		case SVC_FTP:
			return "FTP";
		case SVC_HTTP:
			return "HTTP";
		case SVC_POP:
			return "POP";
		case SVC_NNTP:
			return "NNTP";
		case SVC_IMAP:
			return "IMAP";
		case SVC_TCP:
			return "TCP";
		case SVC_AGENT:
			return "Zabbix agent";
		case SVC_SNMPv1:
			return "SNMPv1 agent";
		case SVC_SNMPv2c:
			return "SNMPv2c agent";
		case SVC_SNMPv3:
			return "SNMPv3 agent";
		case SVC_ICMPPING:
			return "ICMP ping";
		case SVC_HTTPS:
			return "HTTPS";
		case SVC_TELNET:
			return "Telnet";
		default:
			return "unknown";
	}
}

const char	*trigger_value_string(unsigned char value)
{
	switch (value)
	{
		case TRIGGER_VALUE_PROBLEM:
			return "PROBLEM";
		case TRIGGER_VALUE_OK:
			return "OK";
		default:
			return "unknown";
	}
}

const char	*zbx_type_string(zbx_value_type_t type)
{
	switch (type)
	{
		case ZBX_VALUE_NONE:
			return "none";
		case ZBX_VALUE_SECONDS:
			return "sec";
		case ZBX_VALUE_NVALUES:
			return "num";
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return "unknown";
	}
}
