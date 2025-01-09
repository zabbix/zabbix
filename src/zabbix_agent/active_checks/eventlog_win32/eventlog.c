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

#include "process_eventslog.h"
#include "process_eventslog6.h"

#include "../../metrics/metrics.h"
#include "../../logfiles/logfiles.h"

#include "module.h"
#include "zbxalgo.h"
#include "zbxsysinfo.h"
#include "zbxcomms.h"

#include "winmeta.h"

#include <delayimp.h> /* PDelayLoadInfo */

/* winevt.h contents START */
typedef HANDLE EVT_HANDLE, *PEVT_HANDLE;

BOOL WINAPI	EvtClose(EVT_HANDLE Object);
/* winevt.h contents END */

LONG WINAPI	DelayLoadDllExceptionFilter(PEXCEPTION_POINTERS excpointers)
{
	LONG		disposition = EXCEPTION_EXECUTE_HANDLER;
	PDelayLoadInfo	delayloadinfo = (PDelayLoadInfo)(excpointers->ExceptionRecord->ExceptionInformation[0]);

	switch (excpointers->ExceptionRecord->ExceptionCode)
	{
		case VcppException(ERROR_SEVERITY_ERROR, ERROR_MOD_NOT_FOUND):
			zabbix_log(LOG_LEVEL_DEBUG, "function %s was not found in %s",
					delayloadinfo->dlp.szProcName, delayloadinfo->szDll);
			break;
		case VcppException(ERROR_SEVERITY_ERROR, ERROR_PROC_NOT_FOUND):
			if (delayloadinfo->dlp.fImportByName)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "function %s was not found in %s",
						delayloadinfo->dlp.szProcName, delayloadinfo->szDll);
			}
			else
			{
				zabbix_log(LOG_LEVEL_DEBUG, "function ordinal %d was not found in %s",
						delayloadinfo->dlp.dwOrdinal, delayloadinfo->szDll);
			}
			break;
		default:
			disposition = EXCEPTION_CONTINUE_SEARCH;
			break;
	}

	return disposition;
}

int	process_eventlog_check(zbx_vector_addr_ptr_t *addrs, zbx_vector_ptr_t *agent2_result,
		zbx_vector_expression_t *regexps, zbx_active_metric_t *metric, zbx_process_value_func_t process_value_cb,
		zbx_uint64_t *lastlogsize_sent, const zbx_config_tls_t *config_tls, int config_timeout,
		const char *config_source_ip, const char *config_hostname, int config_buffer_send,
		int config_buffer_size, int config_eventlog_max_lines_per_second, char **error)
{
	int 			ret = FAIL, rate, max_rate = MAX_VALUE_LINES;
	AGENT_REQUEST		request;
	const char		*filename, *pattern, *maxlines_persec, *skip,*key_severity, *key_source,
				*key_logeventid;
	OSVERSIONINFO		versionInfo;
	zbx_vector_prov_meta_t	prov_meta;

	zbx_init_agent_request(&request);
	zbx_vector_prov_meta_create(&prov_meta);

	if (SUCCEED != zbx_parse_item_key(metric->key, &request))
	{
		*error = zbx_strdup(*error, "Invalid item key format.");
		goto out;
	}

	if (0 == get_rparams_num(&request))
	{
		*error = zbx_strdup(*error, "Invalid number of parameters.");
		goto out;
	}

	if (7 < get_rparams_num(&request))
	{
		*error = zbx_strdup(*error, "Too many parameters.");
		goto out;
	}

	if (NULL == (filename = get_rparam(&request, 0)) || '\0' == *filename)
	{
		*error = zbx_strdup(*error, "Invalid first parameter.");
		goto out;
	}

	if (NULL == (pattern = get_rparam(&request, 1)))
	{
		pattern = "";
	}
	else if ('@' == *pattern && SUCCEED != zbx_global_regexp_exists(pattern + 1, regexps))
	{
		*error = zbx_dsprintf(*error, "Global regular expression \"%s\" does not exist.", pattern + 1);
		goto out;
	}

	if (NULL == (key_severity = get_rparam(&request, 2)))
	{
		key_severity = "";
	}
	else if ('@' == *key_severity && SUCCEED != zbx_global_regexp_exists(key_severity + 1, regexps))
	{
		*error = zbx_dsprintf(*error, "Global regular expression \"%s\" does not exist.", key_severity + 1);
		goto out;
	}

	if (NULL == (key_source = get_rparam(&request, 3)))
	{
		key_source = "";
	}
	else if ('@' == *key_source && SUCCEED != zbx_global_regexp_exists(key_source + 1, regexps))
	{
		*error = zbx_dsprintf(*error, "Global regular expression \"%s\" does not exist.", key_source + 1);
		goto out;
	}

	if (NULL == (key_logeventid = get_rparam(&request, 4)))
	{
		key_logeventid = "";
	}
	else if ('@' == *key_logeventid && SUCCEED != zbx_global_regexp_exists(key_logeventid + 1, regexps))
	{
		*error = zbx_dsprintf(*error, "Global regular expression \"%s\" does not exist.", key_logeventid + 1);
		goto out;
	}

	max_rate *= 0 != (ZBX_METRIC_FLAG_LOG_COUNT & metric->flags) ? MAX_VALUE_LINES_MULTIPLIER : 1;

	if (NULL == (maxlines_persec = get_rparam(&request, 5)) || '\0' == *maxlines_persec)
	{
		rate = config_eventlog_max_lines_per_second;

		if (0 != (ZBX_METRIC_FLAG_LOG_COUNT & metric->flags))
			rate *= MAX_VALUE_LINES_MULTIPLIER;
	}
	else if (MIN_VALUE_LINES > (rate = atoi(maxlines_persec)) || max_rate < rate)
	{
		*error = zbx_strdup(*error, "Invalid sixth parameter.");
		goto out;
	}

	if (NULL == (skip = get_rparam(&request, 6)) || '\0' == *skip || 0 == strcmp(skip, "all"))
	{
		metric->skip_old_data = 0;
	}
	else if (0 != strcmp(skip, "skip"))
	{
		*error = zbx_strdup(*error, "Invalid seventh parameter.");
		goto out;
	}

	versionInfo.dwOSVersionInfoSize = sizeof(OSVERSIONINFO);
	GetVersionEx(&versionInfo);

	if (versionInfo.dwMajorVersion >= 6)	/* Windows Vista, 7 or Server 2008 */
	{
		__try
		{

			zbx_uint64_t	lastlogsize = metric->lastlogsize;
			EVT_HANDLE	eventlog6_render_context = NULL;
			EVT_HANDLE	eventlog6_query = NULL;
			zbx_uint64_t	eventlog6_firstid = 0;
			zbx_uint64_t	eventlog6_lastid = 0;

			if (SUCCEED != initialize_eventlog6(filename, &lastlogsize, &eventlog6_firstid,
					&eventlog6_lastid, &eventlog6_render_context, &eventlog6_query, error))
			{
				finalize_eventlog6(&eventlog6_render_context, &eventlog6_query);
				goto out;
			}

			ret = process_eventslog6(addrs, agent2_result, filename, &eventlog6_render_context,
					&eventlog6_query, lastlogsize, eventlog6_firstid, eventlog6_lastid, regexps,
					pattern, key_severity, key_source, key_logeventid, rate, process_value_cb,
					config_tls, config_timeout, config_source_ip, config_hostname,
					config_buffer_send, config_buffer_size, metric, lastlogsize_sent, &prov_meta,
					error);

			for (int i = 0; i < prov_meta.values_num; i++)
			{
				if (NULL != prov_meta.values[i].handle)
					EvtClose(prov_meta.values[i].handle);

				zbx_free(prov_meta.values[i].name);
			}

			finalize_eventlog6(&eventlog6_render_context, &eventlog6_query);
		}
		__except (DelayLoadDllExceptionFilter(GetExceptionInformation()))
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed to process eventlog");
		}
	}
	else if (versionInfo.dwMajorVersion < 6)	/* Windows versions before Vista */
	{
		ret = process_eventslog(addrs, agent2_result, filename, regexps, pattern, key_severity, key_source,
				key_logeventid, rate, process_value_cb, config_tls, config_timeout, config_source_ip,
				config_hostname, config_buffer_send, config_buffer_size, metric, lastlogsize_sent,
				error);
	}
out:
	zbx_vector_prov_meta_destroy(&prov_meta);
	zbx_free_agent_request(&request);

	return ret;
}
