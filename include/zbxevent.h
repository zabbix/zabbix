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

#ifndef ZABBIX_ZBXEVENT_H
#define ZABBIX_ZBXEVENT_H

#include "zbxcacheconfig.h"

/* acknowledgment actions (flags) */
#define ZBX_PROBLEM_UPDATE_CLOSE		0x0001
#define ZBX_PROBLEM_UPDATE_ACKNOWLEDGE		0x0002
#define ZBX_PROBLEM_UPDATE_MESSAGE		0x0004
#define ZBX_PROBLEM_UPDATE_SEVERITY		0x0008
#define ZBX_PROBLEM_UPDATE_UNACKNOWLEDGE	0x0010
#define ZBX_PROBLEM_UPDATE_SUPPRESS		0x0020
#define ZBX_PROBLEM_UPDATE_UNSUPPRESS		0x0040
#define ZBX_PROBLEM_UPDATE_RANK_TO_CAUSE	0x0080
#define ZBX_PROBLEM_UPDATE_RANK_TO_SYMPTOM	0x0100

#define ZBX_PROBLEM_UPDATE_ACTION_COUNT	9

typedef struct
{
	char	*host;
	char	*severity;
	char	*tags;
	char	*name;
	int	clock;
	int	nseverity;
}
zbx_eventdata_t;

ZBX_VECTOR_DECL(eventdata, zbx_eventdata_t)

void	zbx_eventdata_free(zbx_eventdata_t *eventdata);
int	zbx_eventdata_compare(const zbx_eventdata_t *d1, const zbx_eventdata_t *d2);
int	zbx_eventdata_to_str(const zbx_vector_eventdata_t *eventdata, char **replace_to);

void	zbx_event_get_macro_value(const char *macro, const zbx_db_event *event, char **replace_to,
			const zbx_uint64_t *recipient_userid, const zbx_db_event *r_event, const char *tz);

void	zbx_event_get_tag(const char *text, const zbx_db_event *event, char **replace_to);

void	zbx_event_get_str_tags(const zbx_db_event *event, char **replace_to);
void	zbx_event_get_json_tags(const zbx_db_event *event, char **replace_to);
void	zbx_event_get_json_actions(const zbx_db_acknowledge *ack, char **replace_to);

int	zbx_event_db_get_host(const zbx_db_event *event, zbx_dc_host_t *host, char *error, size_t max_error_len);

int	zbx_event_db_get_dhost(const zbx_db_event *event, char **replace_to, const char *fieldname);
int	zbx_event_db_get_dchecks(const zbx_db_event *event, char **replace_to, const char *fieldname);
int	zbx_event_db_get_dservice(const zbx_db_event *event, char **replace_to, const char *fieldname);
int	zbx_event_db_get_drule(const zbx_db_event *event, char **replace_to, const char *fieldname);

int	zbx_event_db_count_from_trigger(zbx_uint64_t triggerid, char **replace_to, int problem_only, int acknowledged);

int	zbx_event_db_get_autoreg(const zbx_db_event *event, char **replace_to, const char *fieldname);
void	zbx_event_db_get_history(const zbx_db_event *event, char **replace_to,
		const zbx_uint64_t *recipient_userid, const char *tz);

int	zbx_problem_get_actions(const zbx_db_acknowledge *ack, int actions, const char *tz, char **out);

#endif
