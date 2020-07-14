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

#if defined(__linux__)
static int	linux_read_uint64(const char *path, zbx_uint64_t *value)
{
	int	ret = SYSINFO_RET_FAIL;
	char	line[MAX_STRING_LEN];
	FILE	*f;

	if (NULL != (f = fopen(path, "r")))
	{
		if (NULL != fgets(line, sizeof(line), f))
		{
			if (1 == sscanf(line, ZBX_FS_UI64 "\n", value))
				ret = SYSINFO_RET_OK;
		}
		zbx_fclose(f);
	}

	return ret;
}
#endif

static int	DBpatch_5010001(void)
{
	const char	rnd_dev[] = "/dev/urandom";
	unsigned char	buff[16];
	char		str[33];
	int		fd, n;
	unsigned int	i;
#if defined(__linux__)
	zbx_uint64_t	value;
#endif
	if (0 == (program_type & ZBX_PROGRAM_TYPE_SERVER))
		return SUCCEED;
#if defined(__linux__)
	if (SYSINFO_RET_OK == linux_read_uint64("/proc/sys/kernel/random/entropy_avail", &value) &&
			sizeof(buff) * 8 > value)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot obtain %d bits of entropy from the pool, current value:"
				ZBX_FS_UI64, (int)sizeof(buff) * 8, value);
		return FAIL;
	}
#endif
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

	if (sizeof(buff) != n)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot read %d bits from %s", (int)sizeof(buff) * 8, rnd_dev);
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
