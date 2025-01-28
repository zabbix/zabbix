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

#include "zbxdbwrap.h"

#include "zbxhistory.h"
#include "zbxcachevalue.h"
#include "zbxexpression.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxtime.h"

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve item value by trigger expression and number of function. *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_item_value(const zbx_db_trigger *trigger, char **value, int N_functionid, int clock, int ns, int raw)
{
	zbx_uint64_t	itemid;
	zbx_timespec_t	ts = {clock, ns};
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED == (ret = zbx_db_trigger_get_itemid(trigger, N_functionid, &itemid)))
		ret = zbx_db_item_get_value(itemid, value, raw, &ts);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve item value by item id.                                   *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_item_get_value(zbx_uint64_t itemid, char **lastvalue, int raw, zbx_timespec_t *ts)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = zbx_db_select(
			"select value_type,valuemapid,units"
			" from items"
			" where itemid=" ZBX_FS_UI64,
			itemid);

	if (NULL != (row = zbx_db_fetch(result)))
	{
		unsigned char		value_type;
		zbx_uint64_t		valuemapid;
		zbx_history_record_t	vc_value;

		value_type = (unsigned char)atoi(row[0]);
		ZBX_DBROW2UINT64(valuemapid, row[1]);

		if (SUCCEED == zbx_vc_get_value(itemid, value_type, ts, &vc_value))
		{
			char	tmp[MAX_BUFFER_LEN];

			zbx_vc_flush_stats();
			zbx_history_value_print(tmp, sizeof(tmp), &vc_value.value, value_type);
			zbx_history_record_clear(&vc_value, value_type);

			if (0 == raw)
				zbx_format_value(tmp, sizeof(tmp), valuemapid, row[2], value_type);

			*lastvalue = zbx_strdup(*lastvalue, tmp);

			ret = SUCCEED;
		}
	}
	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve item lastvalue by trigger expression                     *
 *          and number of function.                                           *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_item_lastvalue(const zbx_db_trigger *trigger, char **lastvalue, int N_functionid, int raw)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = zbx_db_item_value(trigger, lastvalue, N_functionid, (int)time(NULL), 999999999, raw);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
