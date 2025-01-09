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
#include "zbxcrypto.h"
#include "zbxhash.h"

static int	trap_fd = -1;
static off_t	trap_lastsize;
static ino_t	trap_ino = 0;
static char	*buffer = NULL;
static int	offset = 0;
static int	force = 0;

static void	db_update_lastsize(void)
{
	zbx_db_begin();
	zbx_db_execute("update globalvars set value=" ZBX_FS_I64 " where name='snmp_lastsize'",
			(zbx_int64_t)trap_lastsize);
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
 * Purpose: Delays SNMP trapper file related issue log entries for 60 seconds *
 *          unless this is the first time this issue has occurred.            *
 *                                                                            *
 * Parameters: error     - [IN] string containing log entry text              *
 *             log_level - [IN]                                               *
 *                                                                            *
 ******************************************************************************/
static void	delay_trap_logs(const char *error, int log_level)
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

#define	ZBX_SHA512_BINARY_LENGTH	64
#define	ZBX_SHA512_HEX_LENGTH		(128 + 1)

static void	get_trap_hash(const char *trap, char *hash)
{
	char	*ptr;

	/* pdu info cannot be used to calculate hash as it is not same for trap received on other node */
	/* first OID should always be sysUpTimeInstance */
	if (NULL != (ptr = strstr(trap, "\nVARBINDS:\n")) || NULL != (ptr = strstr(trap, "sysUpTimeInstance")) ||
			NULL != (ptr = strstr(trap, ".1.3.6.1.2.1.1.3.0")) ||
			NULL != (ptr = strstr(trap, " iso.3.6.1.2.1.1.3.0")))
	{
		zbx_sha512_hash(ptr, hash);

		return;
	}

	zbx_sha512_hash(trap, hash);
}

static void	db_update_snmp_id(const char *date, const char *trap)
{
	time_t	timestamp;
	char	hash_bin[ZBX_SHA512_BINARY_LENGTH], hash_hex[ZBX_SHA512_HEX_LENGTH], *sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;

	if (FAIL == zbx_iso8601_utc(date, &timestamp))
	{
		timestamp = 0;
		delay_trap_logs("Cannot find valid ISO 8601 timestamp in SNMP trapper log", LOG_LEVEL_WARNING);
	}

	get_trap_hash(trap, hash_bin);

	zbx_bin2hex((unsigned char *)hash_bin, ZBX_SHA512_BINARY_LENGTH, hash_hex, ZBX_SHA512_HEX_LENGTH);

	zbx_db_begin();

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"update globalvars set value=%d where name='snmp_timestamp';\n", (int)timestamp);
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"update globalvars set value='%s' where name='snmp_id';\n", hash_hex);
	zbx_db_execute("%s", sql);
	zbx_free(sql);

	zbx_db_commit();
}

static void	compare_trap(const char *date, const char *trap, int snmp_timestamp, const char *snmp_id_bin, int *skip)
{
	time_t	timestamp = 0;

	if (0 != snmp_timestamp && FAIL == zbx_iso8601_utc(date, &timestamp))
	{
		/* skip records with old or invalid timestamp until correct one is found */
		delay_trap_logs("SNMP trapper log contains non ISO 8601 or invalid timestamp", LOG_LEVEL_WARNING);
	}

	if (NULL == snmp_id_bin)
	{
		if (timestamp >= snmp_timestamp)
		{
			*skip = 0;
			zabbix_log(LOG_LEVEL_WARNING, "SNMP traps processing resumed from last timestamp");
		}

		return;
	}

	if (0 != timestamp)
	{
		if (timestamp > snmp_timestamp + SEC_PER_MIN)
		{
			zabbix_log(LOG_LEVEL_TRACE, "skipping records past timestamp");
			return;
		}

		if (timestamp < snmp_timestamp - SEC_PER_MIN)
		{
			zabbix_log(LOG_LEVEL_TRACE, "skipping records before timestamp");
			return;
		}
	}

	char	hash_bin[ZBX_SHA512_BINARY_LENGTH];

	get_trap_hash(trap, hash_bin);

	if (0 == memcmp(hash_bin, snmp_id_bin, ZBX_SHA512_BINARY_LENGTH))
	{
		*skip = 0;

		if (0 != timestamp)
		{
			zabbix_log(LOG_LEVEL_WARNING, "SNMP traps processing resumed from last record and"
					" timestamp");
		}
		else
			zabbix_log(LOG_LEVEL_WARNING, "SNMP traps processing resumed from last record");
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: splits traps and processes them with process_trap()               *
 *                                                                            *
 ******************************************************************************/
static void	parse_traps(int flag, int snmp_timestamp, const char *snmp_id_bin, int *skip,
		const char *config_node_name)
{
	char	*c, *line, *begin = NULL, *end = NULL, *addr = NULL, *pzbegin = NULL, *pzaddr = NULL, *pzdate = NULL,
		*last_date = NULL, *last_trap = NULL;

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
			if (line > buffer + 1)
				*(line - 1) = '\0';

			if (NULL != pzdate)
				*pzdate = '\0';

			if (NULL != pzaddr)
				*pzaddr = '\0';

			if (NULL != skip && 1 == *skip)
			{
				compare_trap(begin, end, snmp_timestamp, snmp_id_bin, skip);
			}
			else
			{
				if (NULL != config_node_name)
				{
					last_date = begin;
					last_trap = end;
				}

				process_trap(addr, begin, end);
			}
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

	if (NULL != last_trap)
		db_update_snmp_id(last_date, last_trap);

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
				parse_traps(1, snmp_timestamp, snmp_id_bin, skip, config_node_name);
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
			if (line > buffer + 1)
				*(line - 1) = '\0';

			if (NULL != pzdate)
				*pzdate = '\0';

			if (NULL != pzaddr)
				*pzaddr = '\0';

			if (NULL != skip && 1 == *skip)
			{
				compare_trap(begin, end, snmp_timestamp, snmp_id_bin, skip);
			}
			else
			{
				process_trap(addr, begin, end);

				if (NULL != config_node_name)
					db_update_snmp_id(begin, end);
			}

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
 * Purpose: reads traps and then parses them with parse_traps()               *
 *                                                                            *
 ******************************************************************************/
static int	read_traps(const char *config_snmptrap_file, int snmp_timestamp, const char *snmp_id, int *skip,
		const char *config_node_name)
{
	int	nbytes = 0;
	char	*error = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() lastsize: " ZBX_FS_I64, __func__, (zbx_int64_t)trap_lastsize);

	if (-1 == lseek(trap_fd, trap_lastsize, SEEK_SET))
	{
		error = zbx_dsprintf(error, "cannot set position to " ZBX_FS_I64 " for \"%s\": %s",
				(zbx_int64_t)trap_lastsize, config_snmptrap_file, zbx_strerror(errno));
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

		if (NULL == skip || 0 == *skip)
			db_update_lastsize();

		parse_traps(0, snmp_timestamp, snmp_id, skip, config_node_name);
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

static void	read_all_traps(const char *config_snmptrap_file, int snmp_timestamp, const char *snmp_id_bin,
		int *skip, const char *config_node_name)
{
	while (0 < read_traps(config_snmptrap_file, snmp_timestamp, snmp_id_bin, skip, config_node_name))
		;

	if (0 != offset)
		parse_traps(1, snmp_timestamp, snmp_id_bin, skip, config_node_name);
}

static void	DBget_lastsize(const char *config_node_name, const char *config_snmptrap_file)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		snmp_timestamp = 0;
	char		*snmp_id = NULL, *snmp_node = NULL;

	zbx_db_begin();

	result = zbx_db_select("select value from globalvars where name='snmp_lastsize'");
	if (NULL == (row = zbx_db_fetch(result)))
	{
		zbx_db_execute("insert into globalvars (name,value) values ('snmp_lastsize','0')");
		trap_lastsize = 0;
	}
	else
		ZBX_STR2UINT64(trap_lastsize, row[0]);
	zbx_db_free_result(result);

	if (NULL == config_node_name)
	{
		zbx_db_execute("delete from globalvars where name in ('snmp_node','snmp_timestamp','snmp_id')");
	}
	else
	{
		result = zbx_db_select("select value from globalvars where name='snmp_node'");
		if (NULL != (row = zbx_db_fetch(result)))
			snmp_node = zbx_strdup(NULL, row[0]);
		zbx_db_free_result(result);

		result = zbx_db_select("select value from globalvars where name='snmp_timestamp'");
		if (NULL == (row = zbx_db_fetch(result)))
		{
			zbx_db_execute("insert into globalvars (name,value) values ('snmp_timestamp','0')");
		}
		else
			snmp_timestamp = atoi(row[0]);
		zbx_db_free_result(result);

		result = zbx_db_select("select value from globalvars where name='snmp_id'");
		if (NULL == (row = zbx_db_fetch(result)))
		{
			zbx_db_execute("insert into globalvars (name,value) values ('snmp_id','')");
		}
		else
			snmp_id = zbx_strdup(NULL, row[0]);
		zbx_db_free_result(result);
	}

	zbx_db_commit();

	if (NULL != config_node_name)
	{
		if (NULL != snmp_id && '\0' != *snmp_id && NULL != snmp_node &&
				0 != strcmp(config_node_name, snmp_node))
		{
			int	skip = 1;

			if (-1 != open_trap_file(config_snmptrap_file))
			{
				char	snmp_id_bin[ZBX_SHA512_BINARY_LENGTH];
				int	ret;

				if (ZBX_SHA512_BINARY_LENGTH != (ret = zbx_hex2bin((const unsigned char *)snmp_id,
						(unsigned char *)snmp_id_bin, ZBX_SHA512_BINARY_LENGTH)))
				{
					zabbix_log(LOG_LEVEL_DEBUG, "invalid SNMP ID length:%d", ret);
				}
				else
				{
					trap_lastsize = 0;

					read_all_traps(config_snmptrap_file, snmp_timestamp, snmp_id_bin, &skip,
							config_node_name);
				}

				if (0 != snmp_timestamp)
				{
					if (1 == skip)
					{
						trap_lastsize = 0;

						read_all_traps(config_snmptrap_file, snmp_timestamp, NULL, &skip,
								config_node_name);
					}

					if (1 == skip && 0 != trap_lastsize)
					{
						zabbix_log(LOG_LEVEL_WARNING, "cannot resume SNMP traps processing from"
								" last position: timestamp and record not found");
					}
				}
				else if (1 == skip && 0 != trap_lastsize)
				{
					zabbix_log(LOG_LEVEL_WARNING, "cannot resume SNMP traps processing from"
							" last position: record is not found and timestamp is"
							" not available");
				}

				db_update_lastsize();
			}
		}

		if (0 != zbx_strcmp_null(snmp_node, config_node_name))
		{
			zbx_db_begin();

			if (NULL == snmp_node)
			{
				zbx_db_insert_t	db_insert;

				zbx_db_insert_prepare(&db_insert, "globalvars", "name", "value", (char *)NULL);
				zbx_db_insert_add_values(&db_insert, "snmp_node", config_node_name);
				zbx_db_insert_execute(&db_insert);
				zbx_db_insert_clean(&db_insert);
			}
			else
			{
				char	*config_node_name_esc;

				config_node_name_esc = zbx_db_dyn_escape_string(config_node_name);
				zbx_db_execute("update globalvars set value='%s' where name='snmp_node'",
						config_node_name_esc);
				zbx_free(config_node_name_esc);
			}

			zbx_db_commit();
		}
	}

	zbx_free(snmp_node);
	zbx_free(snmp_id);
}
#undef	ZBX_SHA512_BINARY_LENGTH
#undef	ZBX_SHA512_HEX_LENGTH

/******************************************************************************
 *                                                                            *
 * Purpose: Opens the latest trap file. If the current file has been rotated, *
 *          processes that and then opens the latest file.                    *
 *                                                                            *
 * Return value: SUCCEED - there are new traps to be parsed                   *
 *               FAIL - there are no new traps or trap file does not exist    *
 *                                                                            *
 ******************************************************************************/
static int	get_latest_data(const char *config_snmptrap_file, const char *config_node_name)
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

			read_all_traps(config_snmptrap_file, 0, NULL, NULL, config_node_name);

			close_trap_file();
		}
		else if (file_buf.st_ino != trap_ino || file_buf.st_size < trap_lastsize)
		{
			/* file has been rotated, process the current file */
			read_all_traps(config_snmptrap_file, 0, NULL, NULL, config_node_name);

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
				parse_traps(1, 0, NULL, NULL, config_node_name);
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

	if (NULL != snmptrapper_args_in->config_ha_node_name && '\0' == *snmptrapper_args_in->config_ha_node_name)
		snmptrapper_args_in->config_ha_node_name = NULL;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() trapfile:'%s'", __func__, snmptrapper_args_in->config_snmptrap_file);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);
	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	buffer = (char *)zbx_malloc(buffer, MAX_BUFFER_LEN);
	*buffer = '\0';

	DBget_lastsize(snmptrapper_args_in->config_ha_node_name, snmptrapper_args_in->config_snmptrap_file);

	while (ZBX_IS_RUNNING())
	{
		sec = zbx_time();
		zbx_update_env(get_process_type_string(process_type), sec);

		zbx_setproctitle("%s [processing data]", get_process_type_string(process_type));

		while (ZBX_IS_RUNNING() && FAIL == zbx_vps_monitor_capped())
		{
			if (SUCCEED != get_latest_data(snmptrapper_args_in->config_snmptrap_file,
					snmptrapper_args_in->config_ha_node_name))
			{
				break;
			}

			read_traps(snmptrapper_args_in->config_snmptrap_file, 0, NULL, NULL,
					snmptrapper_args_in->config_ha_node_name);
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
