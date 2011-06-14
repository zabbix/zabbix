/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "dbcache.h"
#include "zbxself.h"
#include "daemon.h"
#include "log.h"

#include "snmptrapper.h"

static int	trap_fd = -1;
static int	trap_lastsize;
static ino_t	trap_ino = 0;

static void	DBget_lastsize()
{
	DB_RESULT	result;
	DB_ROW		row;
	result = DBselect("select snmp_lastsize from globalvars");

	if (NULL != (row = DBfetch(result)))
		trap_lastsize = atoi(row[0]);
	else
		trap_lastsize = 0;

	DBfree_result(result);
}

static void	DBupdate_lastsize()
{
	DBexecute("update globalvars set snmp_lastsize=%d", trap_lastsize);
}

/******************************************************************************
 *                                                                            *
 * Function: process_trap                                                     *
 *                                                                            *
 * Purpose: process a single trap                                             *
 *                                                                            *
 * Author: Rudolfs Kreicbergs                                                 *
 *                                                                            *
 ******************************************************************************/
static void process_trap(char *ip, char *trap)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_timespec_t	ts;
	char		*res = NULL, *key, regex[MAX_STRING_LEN], params[MAX_STRING_LEN];

	zbx_timespec(&ts);

	result = DBselect("select i.key_, i.itemid"
			" from items i, interface n"
			" where n.hostid=i.hostid and n.ip='%s' and i.type=%d",
			ip, ITEM_TYPE_SNMPTRAP);

	while (NULL != (row = DBfetch(result)))
	{
		key = row[0];

		if (2 != parse_command(key, NULL, 0, params, MAX_STRING_LEN))
			continue;

		if (0 != get_param(params, 1, regex, sizeof(regex)))
			continue;

		if (NULL != (res = zbx_regexp_match(trap, regex, NULL)))
		{
			DC_ITEM		item;
			AGENT_RESULT	value;
			zbx_uint64_t	itemid;

			SET_STR_RESULT(&value, strdup(trap));

			ZBX_STR2UINT64(itemid, row[1]);

			if (SUCCEED != DCconfig_get_item_by_itemid(&item, itemid))
			{
				zabbix_log(LOG_LEVEL_ERR, "failed to get item [%lu]", itemid);
				return;
			}

			dc_add_history(item.itemid, item.value_type, item.flags, &value, &ts, 0, NULL, 0, 0, 0, 0);

			zabbix_log(LOG_LEVEL_ERR, "trap: %s", trap);
			break;

		}
	}
	DBfree_result(result);

	if (NULL == res)
		zabbix_log(LOG_LEVEL_ERR, "trap for unknown host [%s]: %s", ip, trap);
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
static void parse_traps(char *buffer)
{
	char	*c, *line, *begin = NULL, *end = NULL, *ip, trap[MAX_STRING_LEN];

	c = buffer;
	line = buffer;

	for (; '\0' != *c; c++)
	{
		if ('\n' == *c)
			line = c + 1;

		if (0 != strncmp(c, "ZBXTRAP ", 8))
			continue;

		*c = '\0';
		c += 8;	/* c now points to the IP address */

		/* process the previos trap */
		if (NULL != begin)
		{
			*(line - 1) = '\0';
			zbx_snprintf(trap, sizeof(trap), "%s%s", begin, end);
			process_trap(ip, trap);
		}

		/* parse the current trap */
		begin = line;

		ip = c;

		if (NULL == (c = strchr(c, ' ')))
		{
			zabbix_log(LOG_LEVEL_ERR, "invalid trap format");
			return;
		}

		*c++ = '\0';
		end = c;	/* the rest of the trap */
	}

	/* process the last trap */
	if (NULL != end)
	{
		zbx_snprintf(trap, sizeof(trap), "%s%s", begin, end);
		process_trap(ip, trap);
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
static void read_traps()
{
	const char	*__function_name = "process_traps";
	int		nbytes;
	char		buffer[MAX_BUFFER_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(), lastsize [%lu]", __function_name, trap_lastsize);

	*buffer = 0;


	if ((off_t)-1 == lseek(trap_fd, (off_t)trap_lastsize, SEEK_SET))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s(): cannot set position to [%li]: %s",
				__function_name, trap_lastsize, zbx_strerror(errno));
		goto exit;
	}

	if (FAIL == (nbytes = read(trap_fd, buffer, sizeof(buffer))))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s(): cannot read from [%s]: %s",
				__function_name, CONFIG_SNMPTRAP_FILE, zbx_strerror(errno));
		goto exit;
	}

	buffer[nbytes] = '\0';
	zbx_rtrim(buffer + MAX(nbytes - 3, 0), " \r\n");

	trap_lastsize += (off_t)nbytes;
	DBupdate_lastsize();

	parse_traps(buffer);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	close_trap_file()
{
	if (-1 != trap_fd)
		close(trap_fd);
	trap_fd = -1;
	trap_lastsize = 0;
	DBupdate_lastsize();
}

static int	open_trap_file()
{
	const char	*__function_name = "open_trap_file";
	struct stat	file_buf;

	if(-1 == (trap_fd = open(CONFIG_SNMPTRAP_FILE, O_RDONLY)))
	{
		if (errno != ENOENT)	/* file exists but cannot be opened */
		{
			zabbix_log(LOG_LEVEL_CRIT, "%s(): cannot open [%s]: %s",
					__function_name, CONFIG_SNMPTRAP_FILE, zbx_strerror(errno));
		}
	}
	else if (FAIL == stat(CONFIG_SNMPTRAP_FILE, &file_buf))
	{
		zabbix_log(LOG_LEVEL_CRIT, "%s(): cannot stat [%s]: %s",
				__function_name, CONFIG_SNMPTRAP_FILE, zbx_strerror(errno));
		close_trap_file();
	}
	else
		trap_ino = file_buf.st_ino;	/* a new file was opened */

	return trap_fd;
}

static int	get_latest_data()
{
	const char	*__function_name = "get_latest_data";
	struct stat	file_buf;

	if (-1 != trap_fd)	/* a trap file is open */
	{
		if (FAIL == stat(CONFIG_SNMPTRAP_FILE, &file_buf))	/* file might have been renamed or deleted */
		{
			if  (errno != ENOENT)
			{
				zabbix_log(LOG_LEVEL_CRIT, "%s(): cannot stat [%s]: %s",
						__function_name, CONFIG_SNMPTRAP_FILE, zbx_strerror(errno));
			}
			read_traps();
			close_trap_file();
		}
		else if (file_buf.st_ino != trap_ino || file_buf.st_size < trap_lastsize)	/* file rotation */
		{
			read_traps();
			close_trap_file();
		}
		else if (file_buf.st_size == trap_lastsize)
			return FAIL;	/* no new traps */
	}

	if (-1 == trap_fd && -1 == open_trap_file())
		return FAIL;

	return SUCCEED;
}

void	main_snmptrapper_loop(int server_num)
{
	const char	*__function_name = "main_snmptrapper_loop";

	zabbix_log(LOG_LEVEL_ERR, "In %s(), trapfile [%s]", __function_name, CONFIG_SNMPTRAP_FILE);

	set_child_signal_handler();

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	DBget_lastsize();

	while (ZBX_IS_RUNNING())
	{
		update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);
		zbx_setproctitle("%s [processing data]", get_process_type_string(process_type));

		while (SUCCEED == get_latest_data())	/* there are new traps */
			read_traps();

		update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);
		zbx_setproctitle("snmptrapper [sleeping for 1 second]");
		zbx_sleep(1);
	}

	if (-1 != trap_fd)
		close(trap_fd);
}
