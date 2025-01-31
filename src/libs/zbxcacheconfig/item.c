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

#include "zbxcacheconfig.h"

#include "zbxhistory.h"
#include "zbx_item_constants.h"
#include "zbxcachevalue.h"
#include "zbxtime.h"
#include "zbxvariant.h"

static const char	*item_logtype_string(unsigned char logtype)
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

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve a particular attribute of a log value.                   *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_get_history_log_value(zbx_uint64_t itemid, char **replace_to, int request, int clock, int ns,
		const char *tz)
{
	zbx_dc_item_t		item;
	int			ret = FAIL, errcode = FAIL;
	zbx_timespec_t		ts = {clock, ns};
	zbx_history_record_t	value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dc_config_get_items_by_itemids(&item, &itemid, &errcode, 1);

	if (SUCCEED != errcode || ITEM_VALUE_TYPE_LOG != item.value_type)
		goto out;

	if (SUCCEED != zbx_vc_get_value(itemid, item.value_type, &ts, &value))
		goto out;

	zbx_vc_flush_stats();

	switch (request)
	{
		case ZBX_DC_REQUEST_ITEM_LOG_DATE:
			*replace_to = zbx_strdup(*replace_to, zbx_date2str((time_t)value.value.log->timestamp, tz));
			goto success;
		case ZBX_DC_REQUEST_ITEM_LOG_TIME:
			*replace_to = zbx_strdup(*replace_to, zbx_time2str((time_t)value.value.log->timestamp, tz));
			goto success;
		case ZBX_DC_REQUEST_ITEM_LOG_AGE:
			*replace_to = zbx_strdup(*replace_to, zbx_age2str(time(NULL) - value.value.log->timestamp));
			goto success;
	}

	/* the following attributes are set only for windows eventlog items */
	if (0 != strncmp(item.key_orig, "eventlog[", 9))
		goto clean;

	switch (request)
	{
		case ZBX_DC_REQUEST_ITEM_LOG_SOURCE:
			*replace_to = zbx_strdup(*replace_to, (NULL == value.value.log->source ? "" :
					value.value.log->source));
			break;
		case ZBX_DC_REQUEST_ITEM_LOG_SEVERITY:
			*replace_to = zbx_strdup(*replace_to,
					item_logtype_string((unsigned char)value.value.log->severity));
			break;
		case ZBX_DC_REQUEST_ITEM_LOG_NSEVERITY:
			*replace_to = zbx_dsprintf(*replace_to, "%d", value.value.log->severity);
			break;
		case ZBX_DC_REQUEST_ITEM_LOG_EVENTID:
			*replace_to = zbx_dsprintf(*replace_to, "%d", value.value.log->logeventid);
			break;
	}
success:
	ret = SUCCEED;
clean:
	zbx_history_record_clear(&value, ITEM_VALUE_TYPE_LOG);
out:
	zbx_dc_config_clean_items(&item, &errcode, 1);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
