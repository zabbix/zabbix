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

#ifndef ZABBIX_BASE64_H
#define ZABBIX_BASE64_H

void	str_base64_encode(const char *p_str, char *p_b64str, int in_size);
void	str_base64_encode_dyn(const char *p_str, char **p_b64str, int in_size);
void	str_base64_decode(const char *p_b64str, char *p_str, int maxsize, int *p_out_size);

#endif /* ZABBIX_BASE64_H */
