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

#include "dbupgrade_component.h"

#include "zbxcommon.h"
#include "zbxdbhigh.h"
#include "zbxdbwrap.h"

void	dbupgrade_copy_template_host_prototypes(zbx_uint64_t hostid, int flags, zbx_vector_uint64_t *templateids,
		zbx_db_insert_t *db_insert)
{
	zabbix_log(LOG_LEVEL_WARNING, "COPY: " ZBX_FS_UI64, hostid);
	for (int i = 0; i < templateids->values_num; i++)
		zabbix_log(LOG_LEVEL_WARNING, "  " ZBX_FS_UI64, templateids->values[i]);

#if defined(ZABBIX_SERVER)
	int	audit_context;

	if (0 != (flags & 4))	/* 4 - ZBX_FLAG_DISCOVERY_CREATED */
		audit_context = 4;	/* 4 - ZBX_AUDIT_LLD_CONTEXT */
	else
		audit_context = ZBX_TEMPLATE_LINK_MANUAL;

	zbx_db_copy_template_host_prototypes(hostid, templateids, audit_context, db_insert);
#else
	ZBX_UNUSED(hostid);
	ZBX_UNUSED(flags);
	ZBX_UNUSED(templateids);
	ZBX_UNUSED(db_insert);
#endif
}
