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

#include "preproc_proxy.h"

#include "zbxpreproc.h"
#include "zbxtime.h"
#include "zbxcachehistory.h"

int	preproc_prepare_value_proxy(const zbx_variant_t *value, const zbx_pp_value_opt_t *value_opt)
{
	/* allow proxy to send timestamps of empty (throttled etc) values to update nextchecks for queue */
	ZBX_UNUSED(value);
	ZBX_UNUSED(value_opt);

	return SUCCEED;
}

void	preproc_flush_value_proxy(zbx_pp_manager_t *manager, zbx_uint64_t itemid, unsigned char value_type,
	unsigned char flags, zbx_variant_t *value, zbx_timespec_t ts, zbx_pp_value_opt_t *value_opt)
{
	ZBX_UNUSED(manager);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dc_add_history_variant(itemid, value_type, flags, value, ts, value_opt);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
