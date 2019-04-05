/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

#ifndef __zbxprometheus_h__
#define __zbxprometheus_h__

int	zbx_prometheus_pattern(const char *data, const char *filter_data, const char *output,
						char **value, char **err);
int	zbx_prometheus_to_json(const char *data, const char *filter_data, char **value, char **err);

int	zbx_prometheus_validate_filter(const char *pattern, char **error);
int	zbx_prometheus_validate_label(const char *label);

#endif /* __zbxprometheus_h__ */
