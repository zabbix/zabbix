/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#ifndef ZABBIX_XML_H
#define ZABBIX_XML_H

#include "config.h"

int	zbx_xml_get_data_dyn(const char *xml, const char *tag, char **data);
void	zbx_xml_free_data_dyn(char **data);
char	*zbx_xml_escape_dyn(const char *data);
void	zbx_xml_escape_xpath(char **data);

#ifdef HAVE_LIBXML2
int	zbx_open_xml(char *data, int options, int maxerrlen, void **xml_doc, void **root_node, char **errmsg);
int	zbx_check_xml_memory(char *mem, int maxerrlen, char **errmsg);
#endif

int	zbx_xmlnode_to_json(void *xml_node, char **jstr);
int	zbx_xml_to_json(char *xml_data, char **jstr, char **errmsg);
int	zbx_json_to_xml(char *json_data, char **xstr, char **errmsg);

#endif  /* ZABBIX_XML_H */
