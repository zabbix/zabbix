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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


#ifndef ZABBIX_ZODBC_H
#define ZABBIX_ZODBC_H

#include <sql.h>
#include <sqlext.h>
#include <sqltypes.h>

typedef char	**ZBX_ODBC_ROW;

typedef struct zbx_odbc_dbh_s
{
	SQLHENV		henv;
	SQLHDBC		hdbc;
	unsigned short	connected;
	SQLHSTMT	hstmt;
	SQLSMALLINT     col_num;
	ZBX_ODBC_ROW	row_data;
	SQLINTEGER	*data_len;
} ZBX_ODBC_DBH;

typedef ZBX_ODBC_DBH*		ZBX_ODBC_RESULT;

int		odbc_DBconnect(ZBX_ODBC_DBH *pdbh, const char *db_name, const char *user, const char *pass);
void		odbc_DBclose(ZBX_ODBC_DBH *pdbh);

ZBX_ODBC_RESULT odbc_DBselect(ZBX_ODBC_DBH *pdbh, const char *query);
ZBX_ODBC_ROW    odbc_DBfetch(ZBX_ODBC_RESULT pdbh);

const char	*get_last_odbc_strerror();

#endif /* ZABBIX_ZODBC_H */
