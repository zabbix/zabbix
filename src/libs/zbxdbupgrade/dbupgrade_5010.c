/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
#include "db.h"
#include "dbupgrade.h"
#include "log.h"

/*
 * 5.2 development database patches
 */

#ifndef HAVE_SQLITE3

extern unsigned char	program_type;

static int	DBpatch_5010000(void)
{
	const ZBX_FIELD	field = {"session_key", "", NULL, NULL, 32, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	return DBadd_field("config", &field);
}

static int	DBpatch_5010001(void)
{
	const char	rnd_dev[] = "/dev/urandom";
	char		buff[16], str[33];
	int		fd, n;
	unsigned int	i;

	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;

	if (-1 == (fd = open(rnd_dev, O_RDONLY)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot open \"%s\": %s", rnd_dev, zbx_strerror(errno));
		return FAIL;
	}

	n = read(fd, buff, sizeof(buff));
	close(fd);

	if (-1 == n)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot read \"%s\": %s", rnd_dev, zbx_strerror(errno));
		return FAIL;
	}

	/* convert hex to text */
	for (i = 0; i < ARRSIZE(buff); i++)
		zbx_snprintf(str + i * 2, sizeof(str) - i * 2, "%02x", buff[i]);

	if (ZBX_DB_OK > DBexecute("update config set session_key='%s' where configid=1", str))
		return FAIL;

	return SUCCEED;
}

#endif

DBPATCH_START(5010)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(5010000, 0, 1)
DBPATCH_ADD(5010001, 0, 1)

DBPATCH_END()
