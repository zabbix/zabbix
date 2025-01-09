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

#ifndef ZABBIX_DATAFUNC_H
#define ZABBIX_DATAFUNC_H

#include "evalfunc.h"

#include "zbxdbhigh.h"
#include "zbxtime.h"

/* The following definitions are used to identify the request field */
/* for various value getters grouped by their scope:                */

/* expr_db_get_item_value() */
#define ZBX_REQUEST_HOST_DESCRIPTION		104
#define ZBX_REQUEST_ITEM_ID			105
#define ZBX_REQUEST_ITEM_NAME			106
#define ZBX_REQUEST_ITEM_NAME_ORIG		107
#define ZBX_REQUEST_ITEM_KEY			108
#define ZBX_REQUEST_ITEM_KEY_ORIG		109
#define ZBX_REQUEST_ITEM_DESCRIPTION		110
#define ZBX_REQUEST_ITEM_DESCRIPTION_ORIG	111
#define ZBX_REQUEST_PROXY_NAME			112
#define ZBX_REQUEST_PROXY_DESCRIPTION		113
#define ZBX_REQUEST_ITEM_VALUETYPE		114
#define	ZBX_REQUEST_ITEM_ERROR			115

/* expr_db_get_history_log_value() */
#define ZBX_REQUEST_ITEM_LOG_DATE		201
#define ZBX_REQUEST_ITEM_LOG_TIME		202
#define ZBX_REQUEST_ITEM_LOG_AGE		203
#define ZBX_REQUEST_ITEM_LOG_SOURCE		204
#define ZBX_REQUEST_ITEM_LOG_SEVERITY		205
#define ZBX_REQUEST_ITEM_LOG_NSEVERITY		206
#define ZBX_REQUEST_ITEM_LOG_EVENTID		207
#define ZBX_REQUEST_ITEM_LOG_TIMESTAMP		208

const char	*item_logtype_string(unsigned char logtype);
const char	*alert_type_string(unsigned char type);
const char	*alert_status_string(unsigned char type, unsigned char status);
const char	*trigger_state_string(int state);
const char	*item_state_string(int state);
const char	*event_value_string(int source, int object, int value);
const char	*zbx_dobject_status2str(int st);
const char	*trigger_value_string(unsigned char value);
const char	*zbx_type_string(zbx_value_type_t type);

int	expr_db_get_proxy_value(zbx_uint64_t proxyid, char **replace_to, const char *field_name);
int	expr_db_get_templateid_by_triggerid(zbx_uint64_t triggerid, zbx_uint64_t *templateid);
int	expr_db_get_trigger_template_name(zbx_uint64_t triggerid, const zbx_uint64_t *userid, char **replace_to);
int	expr_db_get_trigger_hostgroup_name(zbx_uint64_t triggerid, const zbx_uint64_t *userid, char **replace_to);
int	expr_db_get_item_value(zbx_uint64_t itemid, char **replace_to, int request);
int	expr_db_get_trigger_value(const zbx_db_trigger *trigger, char **replace_to, int N_functionid, int request);
int	expr_db_get_trigger_error(const zbx_db_trigger *trigger, char **replace_to);
int	expr_db_get_history_log_value(zbx_uint64_t itemid, char **replace_to, int request, int clock, int ns,
		const char *tz);
int	expr_db_item_get_value(zbx_uint64_t itemid, char **lastvalue, int raw, zbx_timespec_t *ts);
int	expr_db_item_value(const zbx_db_trigger *trigger, char **value, int N_functionid, int clock, int ns, int raw);
int	expr_db_item_lastvalue(const zbx_db_trigger *trigger, char **lastvalue, int N_functionid, int raw);
void	expr_db_get_escalation_history(zbx_uint64_t actionid, const zbx_db_event *event, const zbx_db_event *r_event,
			char **replace_to, const zbx_uint64_t *recipient_userid, const char *tz);
int	expr_db_get_action_value(const char *macro, zbx_uint64_t actionid, char **replace_to);
int	expr_get_history_log_value(const char *m, const zbx_db_trigger *trigger, char **replace_to, int N_functionid,
		int clock, int ns, const char *tz);
int	expr_db_get_event_symptoms(const zbx_db_event *event, char **replace_to);
void	expr_db_get_rootcause(const zbx_db_service *service, char **replace_to);

#endif
