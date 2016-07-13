/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
#include "dbcache.h"
#include "zbxself.h"
#include "daemon.h"
#include "log.h"
#include "proxy.h"
#include "snmptrapper.h"
#include "zbxserver.h"

static int	trap_fd = -1;
static int	trap_lastsize;
static ino_t	trap_ino = 0;
static char	*buffer = NULL;
static int	offset = 0;
static int	force = 0;
static int	overflow_warning = 0;

static void	DBget_lastsize()
{
	DB_RESULT	result;
	DB_ROW		row;

	DBbegin();

	result = DBselect("select snmp_lastsize from globalvars");

	if (NULL == (row = DBfetch(result)))
	{
		DBexecute("insert into globalvars (globalvarid,snmp_lastsize) values (1,0)");
		trap_lastsize = 0;
	}
	else
		trap_lastsize = atoi(row[0]);

	DBfree_result(result);

	DBcommit();
}

static void	DBupdate_lastsize()
{
	DBbegin();
	DBexecute("update globalvars set snmp_lastsize=%d", trap_lastsize);
	DBcommit();
}

/******************************************************************************
 *                                                                            *
 * Function: process_trap_for_interface                                       *
 *                                                                            *
 * Purpose: add trap to all matching items for the specified interface        *
 *                                                                            *
 * Return value: SUCCEED - a matching item was found                          *
 *               FAIL - no matching item was found (including fallback items) *
 *                                                                            *
 * Author: Rudolfs Kreicbergs                                                 *
 *                                                                            *
 ******************************************************************************/
static int	process_trap_for_interface(zbx_uint64_t interfaceid, char *trap, zbx_timespec_t *ts)
{
	DC_ITEM		*items = NULL;
	char		cmd[MAX_STRING_LEN], params[MAX_STRING_LEN], regex[MAX_STRING_LEN], error[ITEM_ERROR_LEN_MAX];
	size_t		num, i;
	int		ret = FAIL, fb = -1, *lastclocks = NULL, *errcodes = NULL, timestamp;
	zbx_uint64_t	*itemids = NULL;
	unsigned char	*statuses = NULL;
	AGENT_RESULT	*results = NULL;
	ZBX_REGEXP	*regexps = NULL;
	int		regexps_alloc = 0, regexps_num = 0;

	num = DCconfig_get_snmp_items_by_interfaceid(interfaceid, &items);

	itemids = zbx_malloc(itemids, sizeof(zbx_uint64_t) * num);
	statuses = zbx_malloc(statuses, sizeof(unsigned char) * num);
	lastclocks = zbx_malloc(lastclocks, sizeof(int) * num);
	errcodes = zbx_malloc(errcodes, sizeof(int) * num);
	results = zbx_malloc(results, sizeof(AGENT_RESULT) * num);

	for (i = 0; i < num; i++)
	{
		init_result(&results[i]);
		errcodes[i] = FAIL;

		items[i].key = zbx_strdup(items[i].key, items[i].key_orig);
		if (SUCCEED != substitute_key_macros(&items[i].key, NULL, &items[i], NULL,
				MACRO_TYPE_ITEM_KEY, error, sizeof(error)))
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

		if (0 == parse_command(items[i].key, cmd, sizeof(cmd), params, sizeof(params)))
			continue;

		if (0 != strcmp(cmd, "snmptrap"))
			continue;

		if (1 < num_param(params))
			continue;

		if (0 != get_param(params, 1, regex, sizeof(regex)))
			continue;

		if ('@' == *regex)
		{
			DB_RESULT	result;
			DB_ROW		row;
			char		*regex_esc;

			regexps_num = 0;

			regex_esc = DBdyn_escape_string(regex + 1);
			result = DBselect("select e.expression,e.expression_type,e.exp_delimiter,e.case_sensitive"
					" from regexps r,expressions e"
					" where r.regexpid=e.regexpid"
						" and r.name='%s'" DB_NODE,
					regex_esc, DBnode_local("r.regexpid"));
			zbx_free(regex_esc);

			while (NULL != (row = DBfetch(result)))
			{
				add_regexp_ex(&regexps, &regexps_alloc, &regexps_num,
						regex + 1, row[0], atoi(row[1]), row[2][0], atoi(row[3]));
			}
			DBfree_result(result);
		}


		if (SUCCEED != regexp_match_ex(regexps, regexps_num, trap, regex, ZBX_CASE_SENSITIVE))
			continue;

		if (SUCCEED == set_result_type(&results[i], items[i].value_type, items[i].data_type, trap))
			errcodes[i] = SUCCEED;
		else
			errcodes[i] = NOTSUPPORTED;
		ret = SUCCEED;
	}

	clean_regexps_ex(regexps, &regexps_num);
	zbx_free(regexps);

	if (FAIL == ret && -1 != fb)
	{
		if (SUCCEED == set_result_type(&results[fb], items[fb].value_type, items[fb].data_type, trap))
			errcodes[fb] = SUCCEED;
		else
			errcodes[fb] = NOTSUPPORTED;
		ret = SUCCEED;
	}

	for (i = 0; i < num; i++)
	{
		switch (errcodes[i])
		{
			case SUCCEED:
				timestamp = 0;

				if (ITEM_VALUE_TYPE_LOG == items[i].value_type)
					calc_timestamp(trap, &timestamp, items[i].logtimefmt);

				items[i].status = ITEM_STATUS_ACTIVE;
				dc_add_history(items[i].itemid, items[i].value_type, items[i].flags, &results[i],
						ts, items[i].status, NULL, timestamp, NULL, 0, 0, 0, 0);

				itemids[i] = items[i].itemid;
				statuses[i] = items[i].status;
				lastclocks[i] = ts->sec;
				break;
			case NOTSUPPORTED:
				items[i].status = ITEM_STATUS_NOTSUPPORTED;
				dc_add_history(items[i].itemid, items[i].value_type, items[i].flags, NULL,
						ts, items[i].status, results[i].msg, 0, NULL, 0, 0, 0, 0);

				itemids[i] = items[i].itemid;
				statuses[i] = items[i].status;
				lastclocks[i] = ts->sec;
				break;
		}

		zbx_free(items[i].key);
		free_result(&results[i]);
	}

	zbx_free(results);

	DCrequeue_items(itemids, statuses, lastclocks, errcodes, num);

	zbx_free(errcodes);
	zbx_free(lastclocks);
	zbx_free(statuses);
	zbx_free(itemids);

	DCconfig_clean_items(items, NULL, num);
	zbx_free(items);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_trap                                                     *
 *                                                                            *
 * Purpose: process a single trap                                             *
 *                                                                            *
 * Parameters: addr - [IN] address of the target interface(s)                 *
 *             begin - [IN] beginning of the trap message                     *
 *             end - [IN] end of the trap message                             *
 *                                                                            *
 * Author: Rudolfs Kreicbergs                                                 *
 *                                                                            *
 ******************************************************************************/
static void	process_trap(const char *addr, char *begin, char *end)
{
	zbx_timespec_t	ts;
	zbx_uint64_t	*interfaceids = NULL;
	int		count, i, ret = FAIL;
	char		*trap = NULL;

	zbx_timespec(&ts);
	trap = zbx_dsprintf(trap, "%s%s", begin, end);

	count = DCconfig_get_snmp_interfaceids_by_addr(addr, &interfaceids);

	for (i = 0; i < count; i++)
	{
		if (SUCCEED == process_trap_for_interface(interfaceids[i], trap, &ts))
			ret = SUCCEED;
	}

	if (FAIL == ret && 1 == *(unsigned char *)DCconfig_get_config_data(&i, CONFIG_SNMPTRAP_LOGGING))
		zabbix_log(LOG_LEVEL_WARNING, "unmatched trap received from \"%s\": %s", addr, trap);

	zbx_free(interfaceids);
	zbx_free(trap);
}

/******************************************************************************
 *                                                                            *
 * Function: parse_traps                                                      *
 *                                                                            *
 * Purpose: split traps and process them with process_trap()                  *
 *                                                                            *
 * Author: Rudolfs Kreicbergs                                                 *
 *                                                                            *
 ******************************************************************************/
static void	parse_traps(int flag)
{
	char	*c, *line, *begin = NULL, *end = NULL, *addr = NULL, *pzbegin, *pzaddr, *pzdate;

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

		/* process the previos trap */
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
 * Function: read_traps                                                       *
 *                                                                            *
 * Purpose: read the traps and then parse them with parse_traps()             *
 *                                                                            *
 * Author: Rudolfs Kreicbergs                                                 *
 *                                                                            *
 ******************************************************************************/
static int	read_traps()
{
	const char	*__function_name = "read_traps";
	int		nbytes = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() lastsize:%d", __function_name, trap_lastsize);

	if ((off_t)-1 == lseek(trap_fd, (off_t)trap_lastsize, SEEK_SET))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot set position to %d for \"%s\": %s", trap_lastsize,
				CONFIG_SNMPTRAP_FILE, zbx_strerror(errno));
		goto exit;
	}

	if (-1 == (nbytes = read(trap_fd, buffer + offset, MAX_BUFFER_LEN - offset - 1)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot read from SNMP trapper file \"%s\": %s", CONFIG_SNMPTRAP_FILE,
				zbx_strerror(errno));
		goto exit;
	}

	if (0 < nbytes)
	{
		if (ZBX_SNMP_TRAPFILE_MAX_SIZE <= (zbx_uint64_t)trap_lastsize + nbytes)
		{
			nbytes = 0;
			goto exit;
		}

		buffer[nbytes + offset] = '\0';
		trap_lastsize += nbytes;
		DBupdate_lastsize();
		parse_traps(0);
	}
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
	return nbytes;
}

/******************************************************************************
 *                                                                            *
 * Function: close_trap_file                                                  *
 *                                                                            *
 * Purpose: close trap file and reset lastsize                                *
 *                                                                            *
 * Author: Rudolfs Kreicbergs                                                 *
 *                                                                            *
 * Comments: !!! do not reset lastsize elsewhere !!!                          *
 *                                                                            *
 ******************************************************************************/
static void	close_trap_file()
{
	if (-1 != trap_fd)
		close(trap_fd);

	trap_fd = -1;
	trap_lastsize = 0;
	DBupdate_lastsize();
}

/******************************************************************************
 *                                                                            *
 * Function: open_trap_file                                                   *
 *                                                                            *
 * Purpose: open the trap file and get it's node number                       *
 *                                                                            *
 * Return value: file descriptor of the opened file or -1 otherwise           *
 *                                                                            *
 * Author: Rudolfs Kreicbergs                                                 *
 *                                                                            *
 ******************************************************************************/
static int	open_trap_file()
{
	zbx_stat_t	file_buf;

	if (0 != zbx_stat(CONFIG_SNMPTRAP_FILE, &file_buf))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot stat SNMP trapper file \"%s\": %s", CONFIG_SNMPTRAP_FILE,
				zbx_strerror(errno));
		goto out;
	}

	if (ZBX_SNMP_TRAPFILE_MAX_SIZE <= (zbx_uint64_t)file_buf.st_size)
	{
		if (0 == overflow_warning)
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot process SNMP trapper file \"%s\":"
					" file size exceeds the maximum supported size of 2 GB",
					CONFIG_SNMPTRAP_FILE);
			overflow_warning = 1;
		}
		goto out;
	}

	overflow_warning = 0;

	if (-1 == (trap_fd = open(CONFIG_SNMPTRAP_FILE, O_RDONLY)))
	{
		if (ENOENT != errno)	/* file exists but cannot be opened */
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot open SNMP trapper file \"%s\": %s", CONFIG_SNMPTRAP_FILE,
					zbx_strerror(errno));
		}
		goto out;
	}

	trap_ino = file_buf.st_ino;	/* a new file was opened */
out:
	return trap_fd;
}

/******************************************************************************
 *                                                                            *
 * Function: get_latest_data                                                  *
 *                                                                            *
 * Purpose: Open the latest trap file. If the current file has been rotated,  *
 *          process that and then open the latest file.                       *
 *                                                                            *
 * Return value: SUCCEED - there are new traps to be parsed                   *
 *               FAIL - there are no new traps or trap file does not exist    *
 *                                                                            *
 * Author: Rudolfs Kreicbergs                                                 *
 *                                                                            *
 ******************************************************************************/
static int	get_latest_data()
{
	zbx_stat_t	file_buf;

	if (-1 != trap_fd)	/* a trap file is already open */
	{
		if (0 != zbx_stat(CONFIG_SNMPTRAP_FILE, &file_buf))
		{
			/* file might have been renamed or deleted, process the current file */

			if (ENOENT != errno)
			{
				zabbix_log(LOG_LEVEL_CRIT, "cannot stat SNMP trapper file \"%s\": %s",
						CONFIG_SNMPTRAP_FILE, zbx_strerror(errno));
			}

			while (0 < read_traps())
				;

			if (0 != offset)
				parse_traps(1);

			close_trap_file();
		}
		else if (ZBX_SNMP_TRAPFILE_MAX_SIZE <= (zbx_uint64_t)file_buf.st_size)
		{
			close_trap_file();
		}
		else if (file_buf.st_ino != trap_ino || file_buf.st_size < trap_lastsize)
		{
			/* file has been rotated, process the current file */

			while (0 < read_traps())
				;

			if (0 != offset)
				parse_traps(1);

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

	if (-1 == trap_fd && -1 == open_trap_file())
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: main_snmptrapper_loop                                            *
 *                                                                            *
 * Purpose: SNMP trap reader's entry point                                    *
 *                                                                            *
 * Author: Rudolfs Kreicbergs                                                 *
 *                                                                            *
 ******************************************************************************/
void	main_snmptrapper_loop()
{
	const char	*__function_name = "main_snmptrapper_loop";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() trapfile:'%s'", __function_name, CONFIG_SNMPTRAP_FILE);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	DBget_lastsize();

	buffer = zbx_malloc(buffer, MAX_BUFFER_LEN);
	*buffer = '\0';

	for (;;)
	{
		zbx_setproctitle("%s [processing data]", get_process_type_string(process_type));

		while (SUCCEED == get_latest_data())
			read_traps();

		zbx_sleep_loop(1);
	}

	zbx_free(buffer);

	if (-1 != trap_fd)
		close(trap_fd);
}
