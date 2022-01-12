/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "sysinfo.h"
#include "threads.h"
#include "perfstat.h"
#include "zbxjson.h"
#include "zbxalgo.h"
#include "log.h"

int	USER_PERF_COUNTER(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int	ret = SYSINFO_RET_FAIL;
	char	*counter, *error = NULL;
	double	value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (1 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	if (NULL == (counter = get_rparam(request, 0)) || '\0' == *counter)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto out;
	}

	if (SUCCEED != get_perf_counter_value_by_name(counter, &value, &error))
	{
		SET_MSG_RESULT(result, error != NULL ? error :
				zbx_strdup(NULL, "Cannot obtain performance information from collector."));
		goto out;
	}

	SET_DBL_RESULT(result, value);
	ret = SYSINFO_RET_OK;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int perf_counter_ex(const char *function, AGENT_REQUEST *request, AGENT_RESULT *result,
		zbx_perf_counter_lang_t lang)
{
	char	counterpath[PDH_MAX_COUNTER_PATH], *tmp, *error = NULL;
	int	interval, ret = SYSINFO_RET_FAIL;
	double	value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", function);

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto out;
	}

	tmp = get_rparam(request, 0);

	if (NULL == tmp || '\0' == *tmp)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto out;
	}

	strscpy(counterpath, tmp);

	if (NULL == (tmp = get_rparam(request, 1)) || '\0' == *tmp)
	{
		interval = 1;
	}
	else if (FAIL == is_uint31(tmp, &interval))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	if (1 > interval || MAX_COLLECTOR_PERIOD < interval)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Interval out of range."));
		goto out;
	}

	if (FAIL == check_counter_path(counterpath, PERF_COUNTER_LANG_DEFAULT == lang))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid performance counter path."));
		goto out;
	}

	if (SUCCEED != get_perf_counter_value_by_path(counterpath, interval, lang, &value, &error))
	{
		SET_MSG_RESULT(result, error != NULL ? error :
				zbx_strdup(NULL, "Cannot obtain performance information from collector."));
		goto out;
	}

	ret = SYSINFO_RET_OK;
	SET_DBL_RESULT(result, value);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", function, zbx_result_string(ret));

	return ret;
}

int	PERF_COUNTER(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return perf_counter_ex(__func__, request, result, PERF_COUNTER_LANG_DEFAULT);
}

int	PERF_COUNTER_EN(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return perf_counter_ex(__func__, request, result, PERF_COUNTER_LANG_EN);
}

int	perf_instance_discovery_ex(const char *function, AGENT_REQUEST *request, AGENT_RESULT *result,
		zbx_perf_counter_lang_t lang)
{
	char		*tmp;
	wchar_t		*object_name = NULL;
	DWORD		cnt_len = 0, inst_len = 0;
	struct zbx_json	j;
	PDH_STATUS	status;
	int		ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", function);

	if (1 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		goto err;
	}

	tmp = get_rparam(request, 0);

	if (NULL == tmp || '\0' == *tmp)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		goto err;
	}

	if (PERF_COUNTER_LANG_EN == lang)
	{
		if (NULL == (object_name = get_object_name_local(tmp)))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain object's localized name."));
			goto err;
		}
	}
	else
		object_name = zbx_utf8_to_unicode(tmp);

	if (SUCCEED != refresh_object_cache())
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot refresh object cache."));
		goto err;
	}

	if (PDH_CSTATUS_NO_OBJECT == (status = PdhEnumObjectItems(NULL, NULL, object_name, NULL, &cnt_len, NULL,
			&inst_len, PERF_DETAIL_WIZARD, 0)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot find object."));
		goto err;
	}
	else if (PDH_MORE_DATA != status)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain required buffer size."));
		goto err;
	}

	if (0 == inst_len)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Object does not support variable instances."));
		goto err;
	}

	zbx_json_initarray(&j, ZBX_JSON_STAT_BUF_LEN);

	if (2 < inst_len)
	{
		wchar_t			*cnt_list, *inst_list, *instance;
		zbx_vector_str_t	instances, instances_uniq;
		int			i;

		cnt_list = zbx_malloc(NULL, sizeof(wchar_t) * cnt_len);
		inst_list = zbx_malloc(NULL, sizeof(wchar_t) * inst_len);

		if (ERROR_SUCCESS != PdhEnumObjectItems(NULL, NULL, object_name, cnt_list, &cnt_len, inst_list,
				&inst_len, PERF_DETAIL_WIZARD, 0))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot obtain object instances."));
			zbx_json_free(&j);
			zbx_free(cnt_list);
			zbx_free(inst_list);
			goto err;
		}

		zbx_vector_str_create(&instances);

		for (instance = inst_list; L'\0' != *instance; instance += wcslen(instance) + 1)
			zbx_vector_str_append(&instances, zbx_unicode_to_utf8(instance));

		zbx_vector_str_create(&instances_uniq);
		zbx_vector_str_append_array(&instances_uniq, instances.values, instances.values_num);

		zbx_vector_str_sort(&instances_uniq, ZBX_DEFAULT_STR_COMPARE_FUNC);
		zbx_vector_str_uniq(&instances_uniq, ZBX_DEFAULT_STR_COMPARE_FUNC);

		for (i = 0; i < instances_uniq.values_num; i++)
		{
			zbx_json_addobject(&j, NULL);
			zbx_json_addstring(&j, "{#INSTANCE}", instances_uniq.values[i], ZBX_JSON_TYPE_STRING);
			zbx_json_close(&j);
		}

		zbx_vector_str_clear_ext(&instances, zbx_str_free);
		zbx_vector_str_destroy(&instances);
		zbx_vector_str_destroy(&instances_uniq);

		zbx_free(cnt_list);
		zbx_free(inst_list);
	}

	zbx_json_close(&j);
	SET_STR_RESULT(result, zbx_strdup(NULL, j.buffer));
	zbx_json_free(&j);
	ret = SYSINFO_RET_OK;
err:
	zbx_free(object_name);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", function);

	return ret;
}

int	PERF_INSTANCE_DISCOVERY(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return perf_instance_discovery_ex(__func__, request, result, PERF_COUNTER_LANG_DEFAULT);
}

int	PERF_INSTANCE_DISCOVERY_EN(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return perf_instance_discovery_ex(__func__, request, result, PERF_COUNTER_LANG_EN);
}
