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

const char	*alert_type_string(unsigned char type);
const char	*alert_status_string(unsigned char type, unsigned char status);
const char	*trigger_state_string(int state);
const char	*item_state_string(int state);
const char	*event_value_string(int source, int object, int value);
const char	*zbx_dobject_status2str(int st);
const char	*trigger_value_string(unsigned char value);
const char	*zbx_type_string(zbx_value_type_t type);

int	expr_db_get_templateid_by_triggerid(zbx_uint64_t triggerid, zbx_uint64_t *templateid);
int	expr_db_get_trigger_template_name(zbx_uint64_t triggerid, const zbx_uint64_t *userid, char **replace_to);
int	expr_db_get_trigger_hostgroup_name(zbx_uint64_t triggerid, const zbx_uint64_t *userid, char **replace_to);
int	expr_db_get_trigger_error(const zbx_db_trigger *trigger, char **replace_to);
void	expr_db_get_escalation_history(zbx_uint64_t actionid, const zbx_db_event *event, const zbx_db_event *r_event,
			char **replace_to, const zbx_uint64_t *recipient_userid, const char *tz);
int	expr_db_get_action_value(const char *macro, zbx_uint64_t actionid, char **replace_to);
int	expr_db_get_event_symptoms(const zbx_db_event *event, char **replace_to);
void	expr_db_get_rootcause(const zbx_db_service *service, char **replace_to);
int	expr_get_item_key(zbx_uint64_t itemid, char **replace_to);

#endif
