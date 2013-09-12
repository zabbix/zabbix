/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
		zabbix_log(LOG_LEVEL_WARNING, "unmatched trap received from [%s]: %s", addr, trap);

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
static void	parse_traps(char *buffer)
{
	char	*c, *line, *begin = NULL, *end = NULL, *addr = NULL;

	c = line = buffer;

	for (; '\0' != *c; c++)
	{
		if ('\n' == *c)
			line = c + 1;

		if (0 != strncmp(c, "ZBXTRAP", 7))
			continue;

		*c = '\0';
		c += 7;	/* c now points to the delimiter between "ZBXTRAP" and address */

		while ('\0' != *c && NULL != strchr(ZBX_WHITESPACE, *c))
			c++;

		/* c now points to the address */

		/* process the previos trap */
		if (NULL != begin)
		{
			*(line - 1) = '\0';
			process_trap(addr, begin, end);
			end = NULL;
		}

		/* parse the current trap */
		begin = line;
		addr = c;

		while ('\0' != *c && NULL == strchr(ZBX_WHITESPACE, *c))
			c++;

		if ('\0' == c)
		{
			zabbix_log(LOG_LEVEL_WARNING, "invalid trap found [%s...]", begin);
			begin = NULL;
			c = addr;
			continue;
		}

		*c++ = '\0';
		end = c;	/* the rest of the trap */
	}

	/* process the last trap */
	if (NULL != end)
		process_trap(addr, begin, end);
	else if (NULL == addr)	/* no trap was found */
		zabbix_log(LOG_LEVEL_WARNING, "invalid trap found [%s]", buffer);
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
static void	read_traps()
{
	const char	*__function_name = "read_traps";
	int		nbytes;
	char		buffer[MAX_BUFFER_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() lastsize:%d", __function_name, trap_lastsize);

	*buffer = '\0';

	if ((off_t)-1 == lseek(trap_fd, (off_t)trap_lastsize, SEEK_SET))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot set position to [%d] for [%s]: %s",
				trap_lastsize, CONFIG_SNMPTRAP_FILE, zbx_strerror(errno));
		goto exit;
	}

	if (-1 == (nbytes = read(trap_fd, buffer, sizeof(buffer) - 1)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot read from [%s]: %s",
				CONFIG_SNMPTRAP_FILE, zbx_strerror(errno));
		goto exit;
	}

	if (0 < nbytes)
	{
		buffer[nbytes] = '\0';
		zbx_rtrim(buffer + MAX(nbytes - 3, 0), " \r\n");

		trap_lastsize += nbytes;
		DBupdate_lastsize();

		parse_traps(buffer);
	}
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
	struct stat	file_buf;

	if (-1 == (trap_fd = open(CONFIG_SNMPTRAP_FILE, O_RDONLY)))
	{
		if (ENOENT != errno)	/* file exists but cannot be opened */
			zabbix_log(LOG_LEVEL_CRIT, "cannot open [%s]: %s", CONFIG_SNMPTRAP_FILE, zbx_strerror(errno));
	}
	else if (FAIL == stat(CONFIG_SNMPTRAP_FILE, &file_buf))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot stat [%s]: %s", CONFIG_SNMPTRAP_FILE, zbx_strerror(errno));
		close_trap_file();
	}
	else
		trap_ino = file_buf.st_ino;	/* a new file was opened */

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
	struct stat	file_buf;

	if (-1 != trap_fd)	/* a trap file is already open */
	{
		if (0 != stat(CONFIG_SNMPTRAP_FILE, &file_buf))
		{
			/* file might have been renamed or deleted, process the current file */

			if (ENOENT != errno)
			{
				zabbix_log(LOG_LEVEL_CRIT, "cannot stat [%s]: %s",
						CONFIG_SNMPTRAP_FILE, zbx_strerror(errno));
			}
			read_traps();
			close_trap_file();
		}
		else if (file_buf.st_ino != trap_ino || file_buf.st_size < trap_lastsize)
		{
			/* file has been rotated, process the current file */

			read_traps();
			close_trap_file();
		}
		else if (file_buf.st_size == trap_lastsize)
		{
			return FAIL;	/* no new traps */
		}
	}

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

	for (;;)
	{
		zbx_setproctitle("%s [processing data]", get_process_type_string(process_type));

		while (SUCCEED == get_latest_data())
			read_traps();

		zbx_sleep_loop(1);
	}

	if (-1 != trap_fd)
		close(trap_fd);
}
