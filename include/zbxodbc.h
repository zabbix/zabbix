/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#ifndef ZABBIX_ZBXODBC_H
#define ZABBIX_ZBXODBC_H

#include <sql.h>
#include <sqlext.h>
#include <sqltypes.h>

#define ZBX_ODBC_ROW	char **
#define ZBX_ODBC_RESULT	ZBX_ODBC_DBH *

typedef struct
{
	SQLHENV		henv;
	SQLHDBC		hdbc;
	unsigned short	connected;
	SQLHSTMT	hstmt;
	SQLSMALLINT     col_num;
	ZBX_ODBC_ROW	row_data;
}
ZBX_ODBC_DBH;

int		odbc_DBconnect(ZBX_ODBC_DBH *pdbh, char *db_name, char *user, char *pass, int login_timeout);
void		odbc_DBclose(ZBX_ODBC_DBH *pdbh);

ZBX_ODBC_RESULT odbc_DBselect(ZBX_ODBC_DBH *pdbh, char *query);
ZBX_ODBC_ROW    odbc_DBfetch(ZBX_ODBC_RESULT pdbh);

const char	*get_last_odbc_strerror(void);

#endif
