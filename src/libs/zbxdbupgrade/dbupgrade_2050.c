/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
#include "zbxdbupgrade.h"
#include "dbupgrade.h"
#include "sysinfo.h"

/*
 * 3.0 maintenance database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_2050000(void)
{
	const ZBX_FIELD	field = {"agent", "Zabbix", NULL, NULL, 255, ZBX_TYPE_CHAR, ZBX_NOTNULL, 0};

	return DBset_default("httptest", &field);
}

static int	DBpatch_2050001(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*oid = NULL;
	size_t		oid_alloc = 0;
	int		ret = FAIL, rc;

	/* flags - ZBX_FLAG_DISCOVERY_RULE                               */
	/* type  - ITEM_TYPE_SNMPv1, ITEM_TYPE_SNMPv2c, ITEM_TYPE_SNMPv3 */
	if (NULL == (result = DBselect("select itemid,snmp_oid from items where flags=1 and type in (1,4,6)")))
		return FAIL;

	while (NULL != (row = DBfetch(result)))
	{
		char	*param, *oid_esc;
		size_t	oid_offset = 0;

		param = zbx_strdup(NULL, row[1]);
		quote_key_param(&param, 0);

		zbx_snprintf_alloc(&oid, &oid_alloc, &oid_offset, "discovery[{#SNMPVALUE},%s]", param);

		oid_esc = DBdyn_escape_string(oid);

		rc = DBexecute("update items set snmp_oid='%s' where itemid=%s", oid_esc, row[0]);

		zbx_free(oid_esc);
		zbx_free(param);

		if (ZBX_DB_OK > rc)
			goto out;
	}

	ret = SUCCEED;
out:
	DBfree_result(result);
	zbx_free(oid);

	return ret;
}

#endif

DBPATCH_START(2050)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(2050000, 0, 1)
DBPATCH_ADD(2050001, 0, 1)

DBPATCH_END()
