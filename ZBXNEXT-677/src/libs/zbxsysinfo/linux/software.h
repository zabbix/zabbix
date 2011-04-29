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

#ifndef ZABBIX_SOFTWARE_H
#define ZABBIX_SOFTWARE_H

#define SW_OS_FULL		"/proc/version"
#define SW_OS_SHORT		"/proc/version_signature"
#define SW_OS_NAME		"/etc/issue.net"

typedef struct
{
	char	*name;
	char	*test_cmd;	/* if this shell command has strout output, package manager is present */
	char	*list_cmd;	/* this command lists the installed packages */
	int	(*parser)();	/* for non-standard list (package per line), add a parser function */
}
ZBX_PACKAGE_MANAGER;

int	dpkg_parser(char *line, char *package);

ZBX_PACKAGE_MANAGER	package_managers[] =
/*	NAME		TEST_CMD					LIST_CMD			PARSER_FUNC */
{
	{"dpkg",	"dpkg --version",				"dpkg --get-selections",	dpkg_parser},
	{"pkgtools",	"[ -d /var/log/packages ] && echo 'true'",	"ls /var/log/packages",		NULL},
	{"rpm",		"rpm --version",				"rpm -qa",			NULL},
	{"pacman",	"pacman --version",				"pacman -Q",			NULL},
	{0}
};

#endif	/* ZABBIX_SOFTWARE_H */
