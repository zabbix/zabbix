/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "zbxdbhigh.h"

#include "zbxalgo.h"

typedef struct
{
	char	*sendto;
	char	*params;
	char	*error;
	int	status;
}
zbx_push_alert_t;

ZBX_PTR_VECTOR_DECL(push_alert, zbx_push_alert_t *)

void	zbx_push_alert_free(zbx_push_alert_t *alert);
void	get_build_push_params(const zbx_db_event *event, const zbx_db_event *r_event,
		zbx_uint64_t actionid, zbx_uint64_t userid, const char *sendto, const char *subject,
		const char *message, const zbx_db_acknowledge *ack, const zbx_service_alarm_t *service_alarm,
		const zbx_db_service *service, zbx_vector_push_alert_t *alerts, const char *tz);
