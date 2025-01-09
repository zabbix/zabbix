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

#include "preproc_server.h"

#include "../lld/lld_protocol.h"

#include "zbxpreproc.h"
#include "zbxtime.h"
#include "zbxcachehistory.h"
#include "zbxvariant.h"
#include "zbxalgo.h"
#include "zbxpreprocbase.h"

int	preproc_prepare_value_server(const zbx_variant_t *value, const zbx_pp_value_opt_t *value_opt)
{
	if (ZBX_VARIANT_NONE == value->type && 0 == (value_opt->flags & ZBX_PP_VALUE_OPT_META))
		return FAIL;

	return SUCCEED;
}

void	preproc_flush_value_server(zbx_pp_manager_t *manager, zbx_uint64_t itemid, unsigned char value_type,
	unsigned char flags, zbx_variant_t *value, zbx_timespec_t ts, zbx_pp_value_opt_t *value_opt)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == (flags & ZBX_FLAG_DISCOVERY_RULE))
	{
		zbx_dc_add_history_variant(itemid, value_type, flags, value, ts, value_opt);
	}
	else
	{
		zbx_pp_item_t	*item;

		if (NULL != (item = (zbx_pp_item_t *)zbx_hashset_search(zbx_pp_manager_items(manager), &itemid)))
		{
			const char	*value_lld = NULL, *error_lld = NULL;
			unsigned char	meta = 0;
			zbx_uint64_t	lastlogsize = 0;
			int		mtime = 0;

			if (ZBX_VARIANT_ERR == value->type)
			{
				error_lld = value->data.err;
			}
			else
			{
				if (SUCCEED == zbx_variant_convert(value, ZBX_VARIANT_STR))
					value_lld = value->data.str;
			}

			if (0 != (value_opt->flags & ZBX_PP_VALUE_OPT_META))
			{
				meta = 1;
				lastlogsize = value_opt->lastlogsize;
				mtime = value_opt->mtime;
			}

			if (NULL != value_lld || NULL != error_lld || 0 != meta)
			{
				zbx_lld_queue_value(itemid, item->preproc->hostid, value_lld, &ts, meta, lastlogsize,
						mtime, error_lld);
			}
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
