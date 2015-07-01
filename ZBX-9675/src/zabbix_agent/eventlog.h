/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

#ifndef ZABBIX_EVENTLOG_H
#define ZABBIX_EVENTLOG_H

#ifndef _WINDOWS
#	error "This module is only available for Windows OS"
#endif

int	process_eventlog(const char *source, zbx_uint64_t *lastlogsize, unsigned long *out_timestamp,
		char **out_source, unsigned short *out_severity, char **out_message, unsigned long *out_eventid,
		unsigned char skip_old_data);

#endif /* ZABBIX_EVENTLOG_H */
