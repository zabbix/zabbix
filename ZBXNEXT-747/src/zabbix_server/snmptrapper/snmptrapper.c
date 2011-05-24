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
static off_t	trap_lastsize = 0;
static ino_t	trap_ino = 0;

/******************************************************************************
 *                                                                            *
 * Function: process_traps                                                    *
 *                                                                            *
 * Purpose: process the traps from tmp_trap_file                              *
 *                                                                            *
 * Author: Rudolfs Kreicbergs                                                 *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void process_traps()
{
	const char	*__function_name = "process_traps";
	int		nbytes;
	char		buffer[MAX_BUFFER_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(), lastsize [%lu]", __function_name, trap_lastsize);

	*buffer = 0;

	if (FAIL == (nbytes = read(trap_fd, buffer, sizeof(buffer))))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s(): cannot read from [%s]: %s",
				__function_name, CONFIG_SNMPTRAP_FILE, zbx_strerror(errno));
		goto exit;
	}

	trap_lastsize += (off_t)nbytes;

	buffer[nbytes] = '\0';
	zbx_rtrim(buffer + MAX(nbytes - 3, 0), " \r\n");

	if ('\0' != *buffer)
		zabbix_log(LOG_LEVEL_ERR, "trap: %s", buffer);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
		close(trap_fd);
		trap_fd = -1;
	}
	else
	{
		trap_ino = file_buf.st_ino;
		trap_lastsize = 0;
	}

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
			process_traps();
			close(trap_fd);
			trap_fd = -1;
		}
		else if (file_buf.st_ino != trap_ino || file_buf.st_size < trap_lastsize)
		{
			process_traps();
			close(trap_fd);
			trap_fd = -1;
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

	while (ZBX_IS_RUNNING())
	{
		update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);
		zbx_setproctitle("%s [processing data]", get_process_type_string(process_type));

		while (SUCCEED == get_latest_data())	/* there are unprocessed traps */
			process_traps();

		update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);
		zbx_setproctitle("snmptrapper [sleeping for 1 second]");
		zbx_sleep(1);
	}

	if (-1 != trap_fd)
		close(trap_fd);
}
