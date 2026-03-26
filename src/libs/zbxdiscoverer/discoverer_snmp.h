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

#ifndef ZABBIX_DISCOVERER_SNMP_H_
#define ZABBIX_DISCOVERER_SNMP_H_

#include "discoverer_int.h"

int	discovery_jobs_check_snmp(zbx_uint64_t druleid, zbx_discoverer_task_t *task, int concurrency_max, int *stop,
		zbx_discoverer_manager_t *dmanager, int worker_id, char **error);

#endif /* ZABBIX_DISCOVERER_SNMP_H_ */
