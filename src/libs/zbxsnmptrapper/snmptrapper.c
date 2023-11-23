/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "zbxsnmptrapper.h"

#include "zbxtimekeeper.h"
#include "zbxthreads.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxstr.h"
#include "zbxexpression.h"
#include "zbxself.h"
#include "zbxnix.h"
#include "zbxlog.h"
#include "zbxregexp.h"
#include "zbxnum.h"
#include "zbxtime.h"
#include "zbxsysinfo.h"
#include "zbx_item_constants.h"
#include "zbxpreproc.h"

static int	trap_fd = -1;
static off_t	trap_lastsize;
static ino_t	trap_ino = 0;
static char	*buffer = NULL;
static int	offset = 0;
static int	force = 0;

static void	DBget_lastsize(void)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	zbx_db_begin();

	result = zbx_db_select("select snmp_lastsize from globalvars");

	if (NULL == (row = zbx_db_fetch(result)))
	{
		zbx_db_execute("insert into globalvars (globalvarid,snmp_lastsize) values (1,0)");
		trap_lastsize = 0;
	}
	else
		ZBX_STR2UINT64(trap_lastsize, row[0]);

	zbx_db_free_result(result);

	zbx_db_commit();
}

static void	db_update_lastsize(void)
{
	zbx_db_begin();
	zbx_db_execute("update globalvars set snmp_lastsize=%lld", (long long int)trap_lastsize);
	zbx_db_commit();
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds trap to all matching items for specified interface           *
 *                                                                            *
 * Return value: SUCCEED - matching item was found                            *
 *               FAIL - no matching item was found (including fallback items) *
 *                                                                            *
 ******************************************************************************/
static int	process_trap_for_interface(zbx_uint64_t interfaceid, char *trap, zbx_timespec_t *ts)
{
	zbx_dc_item_t		*items = NULL;
	const char		*regex;
	char			error[ZBX_ITEM_ERROR_LEN_MAX];
	int			ret = FAIL, fb = -1, value_type, regexp_ret;
	AGENT_REQUEST		request;
	zbx_vector_expression_t	regexps;
	zbx_dc_um_handle_t	*um_handle;

	zbx_vector_expression_create(&regexps);

	um_handle = zbx_dc_open_user_macros();

	size_t			num = zbx_dc_config_get_snmp_items_by_interfaceid(interfaceid, &items);
	zbx_uint64_t		*itemids = (zbx_uint64_t *)zbx_malloc(NULL, sizeof(zbx_uint64_t) * num);
	int			*lastclocks = (int *)zbx_malloc(NULL, sizeof(int) * num),
				*errcodes = (int *)zbx_malloc(NULL, sizeof(int) * num);
	AGENT_RESULT		*results = (AGENT_RESULT *)zbx_malloc(NULL, sizeof(AGENT_RESULT) * num);

	for (size_t i = 0; i < num; i++)
	{
		zbx_init_agent_result(&results[i]);
		errcodes[i] = FAIL;

		items[i].key = zbx_strdup(items[i].key, items[i].key_orig);
		if (SUCCEED != zbx_substitute_key_macros(&items[i].key, NULL, &items[i], NULL, NULL,
				ZBX_MACRO_TYPE_ITEM_KEY, error, sizeof(error)))
		{
			SET_MSG_RESULT(&results[i], zbx_strdup(NULL, error));
			errcodes[i] = NOTSUPPORTED;
			continue;
		}

		if (0 == strcmp(items[i].key, "snmptrap.fallback"))
		{
			fb = i;
			continue;
		}

		zbx_init_agent_request(&request);

		if (SUCCEED != zbx_parse_item_key(items[i].key, &request))
			goto next;

		if (0 != strcmp(get_rkey(&request), "snmptrap"))
			goto next;

		if (1 < get_rparams_num(&request))
			goto next;

		if (NULL != (regex = get_rparam(&request, 0)))
		{
			if ('@' == *regex)
			{
				zbx_dc_get_expressions_by_name(&regexps, regex + 1);

				if (0 == regexps.values_num)
				{
					SET_MSG_RESULT(&results[i], zbx_dsprintf(NULL,
							"Global regular expression \"%s\" does not exist.", regex + 1));
					errcodes[i] = NOTSUPPORTED;
					goto next;
				}
			}

			if (ZBX_REGEXP_NO_MATCH == (regexp_ret = zbx_regexp_match_ex(&regexps, trap, regex,
					ZBX_CASE_SENSITIVE)))
			{
				goto next;
			}
			else if (FAIL == regexp_ret)
			{
				SET_MSG_RESULT(&results[i], zbx_dsprintf(NULL,
						"Invalid regular expression \"%s\".", regex));
				errcodes[i] = NOTSUPPORTED;
				goto next;
			}
		}

		value_type = (ITEM_VALUE_TYPE_LOG == items[i].value_type ? ITEM_VALUE_TYPE_LOG : ITEM_VALUE_TYPE_TEXT);
		zbx_set_agent_result_type(&results[i], value_type, trap);
		errcodes[i] = SUCCEED;
		ret = SUCCEED;
next:
		zbx_free_agent_request(&request);
	}

	if (FAIL == ret && -1 != fb)
	{
		value_type = (ITEM_VALUE_TYPE_LOG == items[fb].value_type ? ITEM_VALUE_TYPE_LOG : ITEM_VALUE_TYPE_TEXT);
		zbx_set_agent_result_type(&results[fb], value_type, trap);
		errcodes[fb] = SUCCEED;
		ret = SUCCEED;
	}

	for (size_t i = 0; i < num; i++)
	{
		switch (errcodes[i])
		{
			case SUCCEED:
				if (ITEM_VALUE_TYPE_LOG == items[i].value_type)
				{
					zbx_calc_timestamp(results[i].log->value, &results[i].log->timestamp,
							items[i].logtimefmt);
				}

				items[i].state = ITEM_STATE_NORMAL;
				zbx_preprocess_item_value(items[i].itemid, items[i].host.hostid, items[i].value_type,
						items[i].flags, &results[i], ts, items[i].state, NULL);

				itemids[i] = items[i].itemid;
				lastclocks[i] = ts->sec;
				break;
			case NOTSUPPORTED:
				items[i].state = ITEM_STATE_NOTSUPPORTED;
				zbx_preprocess_item_value(items[i].itemid, items[i].host.hostid, items[i].value_type,
						items[i].flags, NULL, ts, items[i].state, results[i].msg);

				itemids[i] = items[i].itemid;
				lastclocks[i] = ts->sec;
				break;
		}

		zbx_free(items[i].key);
		zbx_free_agent_result(&results[i]);
	}

	zbx_free(results);

	zbx_dc_requeue_items(itemids, lastclocks, errcodes, num);

	zbx_free(errcodes);
	zbx_free(lastclocks);
	zbx_free(itemids);

	zbx_dc_config_clean_items(items, NULL, num);
	zbx_free(items);

	zbx_dc_close_user_macros(um_handle);

	zbx_regexp_clean_expressions(&regexps);
	zbx_vector_expression_destroy(&regexps);

	zbx_preprocessor_flush();

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes single trap                                             *
 *                                                                            *
 * Parameters: addr  - [IN] address of target interface(s)                    *
 *             begin - [IN] beginning of trap message                         *
 *             end   - [IN] end of trap message                               *
 *                                                                            *
 ******************************************************************************/
static void	process_trap(const char *addr, char *begin, char *end)
{
	zbx_timespec_t	ts;
	zbx_uint64_t	*interfaceids = NULL;
	int		ret = FAIL;
	char		*trap = NULL;

	zbx_timespec(&ts);

	trap = zbx_dsprintf(trap, "%s%s", begin, end);

	int	count = zbx_dc_config_get_snmp_interfaceids_by_addr(addr, &interfaceids);

	for (int i = 0; i < count; i++)
	{
		if (SUCCEED == process_trap_for_interface(interfaceids[i], trap, &ts))
			ret = SUCCEED;
	}

	if (FAIL == ret)
	{
		zbx_config_t	cfg;

		zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_SNMPTRAP_LOGGING);

		if (ZBX_SNMPTRAP_LOGGING_ENABLED == cfg.snmptrap_logging)
			zabbix_log(LOG_LEVEL_WARNING, "unmatched trap received from \"%s\": %s", addr, trap);

		zbx_config_clean(&cfg);
	}

	zbx_free(interfaceids);
	zbx_free(trap);
}

/******************************************************************************
 *                                                                            *
 * Purpose: splits traps and processes them with process_trap()               *
 *                                                                            *
 ******************************************************************************/
static void	parse_traps(int flag)
{
	char	*c, *line, *begin = NULL, *end = NULL, *addr = NULL, *pzbegin, *pzaddr = NULL, *pzdate = NULL;

	c = line = buffer;

	while ('\0' != *c)
	{
		if ('\n' == *c)
		{
			line = ++c;
			continue;
		}

		if (0 != strncmp(c, "ZBXTRAP", 7))
		{
			c++;
			continue;
		}

		pzbegin = c;

		c += 7;	/* c now points to the delimiter between "ZBXTRAP" and address */

		while ('\0' != *c && NULL != strchr(ZBX_WHITESPACE, *c))
			c++;

		/* c now points to the address */

		/* process the previous trap */
		if (NULL != begin)
		{
			*(line - 1) = '\0';
			*pzdate = '\0';
			*pzaddr = '\0';

			process_trap(addr, begin, end);
			end = NULL;
		}

		/* parse the current trap */
		begin = line;
		addr = c;
		pzdate = pzbegin;

		while ('\0' != *c && NULL == strchr(ZBX_WHITESPACE, *c))
			c++;

		pzaddr = c;

		end = c + 1;	/* the rest of the trap */
	}

	if (0 == flag)
	{
		if (NULL == begin)
			offset = c - buffer;
		else
			offset = c - begin;

		if (offset == MAX_BUFFER_LEN - 1)
		{
			if (NULL != end)
			{
				zabbix_log(LOG_LEVEL_WARNING, "SNMP trapper buffer is full,"
						" trap data might be truncated");
				parse_traps(1);
			}
			else
				zabbix_log(LOG_LEVEL_WARNING, "failed to find trap in SNMP trapper file");

			offset = 0;
			*buffer = '\0';
		}
		else
		{
			if (NULL != begin && begin != buffer)
				memmove(buffer, begin, offset + 1);
		}
	}
	else
	{
		if (NULL != end)
		{
			*(line - 1) = '\0';
			*pzdate = '\0';
			*pzaddr = '\0';

			process_trap(addr, begin, end);
			offset = 0;
			*buffer = '\0';
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "invalid trap data found \"%s\"", buffer);
			offset = 0;
			*buffer = '\0';
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: Delays SNMP trapper file related issue log entries for 60 seconds *
 *          unless this is the first time this issue has occurred.            *
 *                                                                            *
 * Parameters: error     - [IN] string containing log entry text              *
 *             log_level - [IN]                                               *
 *                                                                            *
 ******************************************************************************/
static void	delay_trap_logs(char *error, int log_level)
{
	static int		lastlogtime = 0;
	static zbx_hash_t	last_error_hash = 0;
	int			now = (int)time(NULL);
	zbx_hash_t		error_hash = zbx_default_string_hash_func(error);

	if (ZBX_LOG_ENTRY_INTERVAL_DELAY <= now - lastlogtime || last_error_hash != error_hash)
	{
		zabbix_log(log_level, "%s", error);
		lastlogtime = now;
		last_error_hash = error_hash;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: reads traps and then parses them with parse_traps()               *
 *                                                                            *
 ******************************************************************************/
static int	read_traps(const char *config_snmptrap_file)
{
	int	nbytes = 0;
	char	*error = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() lastsize: %lld", __func__, (long long int)trap_lastsize);

	if (-1 == lseek(trap_fd, trap_lastsize, SEEK_SET))
	{
		error = zbx_dsprintf(error, "cannot set position to %lld for \"%s\": %s", (long long int)trap_lastsize,
				config_snmptrap_file, zbx_strerror(errno));
		delay_trap_logs(error, LOG_LEVEL_WARNING);
		goto out;
	}

	if (-1 == (nbytes = read(trap_fd, buffer + offset, MAX_BUFFER_LEN - offset - 1)))
	{
		error = zbx_dsprintf(error, "cannot read from SNMP trapper file \"%s\": %s",
				config_snmptrap_file, zbx_strerror(errno));
		delay_trap_logs(error, LOG_LEVEL_WARNING);
		goto out;
	}

	if (0 < nbytes)
	{
		buffer[nbytes + offset] = '\0';
		trap_lastsize += nbytes;
		db_update_lastsize();
		parse_traps(0);
	}
out:
	zbx_free(error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return nbytes;
}

/******************************************************************************
 *                                                                            *
 * Purpose: closes trap file and resets lastsize                              *
 *                                                                            *
 * Comments: !!! do not reset lastsize elsewhere !!!                          *
 *                                                                            *
 ******************************************************************************/
static void	close_trap_file(void)
{
	if (-1 != trap_fd)
		close(trap_fd);

	trap_fd = -1;
	trap_lastsize = 0;
	db_update_lastsize();
}

/******************************************************************************
 *                                                                            *
 * Purpose: opens trap file and gets it's node number                         *
 *                                                                            *
 * Return value: file descriptor of opened file or -1 otherwise               *
 *                                                                            *
 ******************************************************************************/
static int	open_trap_file(const char *config_snmptrap_file)
{
	zbx_stat_t	file_buf;
	char		*error = NULL;

	if (-1 == (trap_fd = open(config_snmptrap_file, O_RDONLY)))
	{
		if (ENOENT != errno)	/* file exists but cannot be opened */
		{
			error = zbx_dsprintf(error, "cannot open SNMP trapper file \"%s\": %s",
					config_snmptrap_file, zbx_strerror(errno));
			delay_trap_logs(error, LOG_LEVEL_CRIT);
		}
		goto out;
	}

	if (0 != zbx_fstat(trap_fd, &file_buf))
	{
		error = zbx_dsprintf(error, "cannot stat SNMP trapper file \"%s\": %s", config_snmptrap_file,
				zbx_strerror(errno));
		delay_trap_logs(error, LOG_LEVEL_CRIT);
		close(trap_fd);
		trap_fd = -1;
		goto out;
	}

	trap_ino = file_buf.st_ino;	/* a new file was opened */
out:
	zbx_free(error);

	return trap_fd;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Opens the latest trap file. If the current file has been rotated, *
 *          processes that and then opens the latest file.                    *
 *                                                                            *
 * Return value: SUCCEED - there are new traps to be parsed                   *
 *               FAIL - there are no new traps or trap file does not exist    *
 *                                                                            *
 ******************************************************************************/
static int	get_latest_data(const char *config_snmptrap_file)
{
	zbx_stat_t	file_buf;

	if (-1 != trap_fd)	/* a trap file is already open */
	{
		if (0 != zbx_stat(config_snmptrap_file, &file_buf))
		{
			/* file might have been renamed or deleted, process the current file */

			if (ENOENT != errno)
			{
				zabbix_log(LOG_LEVEL_CRIT, "cannot stat SNMP trapper file \"%s\": %s",
						config_snmptrap_file, zbx_strerror(errno));
			}

			while (0 < read_traps(config_snmptrap_file))
				;

			if (0 != offset)
				parse_traps(1);

			close_trap_file();
		}
		else if (file_buf.st_ino != trap_ino || file_buf.st_size < trap_lastsize)
		{
			/* file has been rotated, process the current file */

			while (0 < read_traps(config_snmptrap_file))
				;

			if (0 != offset)
				parse_traps(1);

			close_trap_file();
		}
		/* in case when file access permission is changed and read permission is denied */
		else if (0 != access(config_snmptrap_file, R_OK))
		{
			if (EACCES == errno)
				close_trap_file();
		}
		else if (file_buf.st_size == trap_lastsize)
		{
			if (1 == force)
			{
				parse_traps(1);
				force = 0;
			}
			else if (0 != offset && 0 == force)
			{
				force = 1;
			}

			return FAIL;	/* no new traps */
		}
	}

	force = 0;

	if (-1 == trap_fd && -1 == open_trap_file(config_snmptrap_file))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: SNMP trap reader's entry point                                    *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(zbx_snmptrapper_thread, args)
{
	double			sec;
	const zbx_thread_info_t	*info = &((zbx_thread_args_t *)args)->info;
	int			server_num = ((zbx_thread_args_t *)args)->info.server_num,
				process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char		process_type = ((zbx_thread_args_t *)args)->info.process_type;

	zbx_thread_snmptrapper_args	*snmptrapper_args_in = (zbx_thread_snmptrapper_args *)
			(((zbx_thread_args_t *)args)->args);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() trapfile:'%s'", __func__, snmptrapper_args_in->config_snmptrap_file);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	DBget_lastsize();

	buffer = (char *)zbx_malloc(buffer, MAX_BUFFER_LEN);
	*buffer = '\0';

	while (ZBX_IS_RUNNING())
	{
		sec = zbx_time();
		zbx_update_env(get_process_type_string(process_type), sec);

		zbx_setproctitle("%s [processing data]", get_process_type_string(process_type));

		while (ZBX_IS_RUNNING() && FAIL == zbx_vps_monitor_capped())
		{
			if (SUCCEED != get_latest_data(snmptrapper_args_in->config_snmptrap_file))
				break;

			read_traps(snmptrapper_args_in->config_snmptrap_file);
		}

		sec = zbx_time() - sec;

		zbx_setproctitle("%s [processed data in " ZBX_FS_DBL " sec, idle 1 sec%s]",
				get_process_type_string(process_type), sec, zbx_vps_monitor_status());

		zbx_sleep_loop(info, 1);
	}

	zbx_free(buffer);

	if (-1 != trap_fd)
		close(trap_fd);

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
