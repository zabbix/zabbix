/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#ifndef ZABBIX_XML_H
#define ZABBIX_XML_H

#include "zbxalgo.h"

#ifdef HAVE_LIBXML2
#	include <libxml/tree.h>
#endif

int	zbx_xml_get_data_dyn(const char *xml, const char *tag, char **data);
void	zbx_xml_free_data_dyn(char **data);
char	*zbx_xml_escape_dyn(const char *data);
void	zbx_xml_escape_xpath(char **data);

int	zbx_query_xpath(zbx_variant_t *value, const char *params, char **errmsg);
int	zbx_query_xpath_contents(zbx_variant_t *value, const char *params, int *is_empty, char **errmsg);

#ifdef HAVE_LIBXML2
int	zbx_open_xml(char *data, int options, int maxerrlen, void **xml_doc, void **root_node, char **errmsg);
int	zbx_check_xml_memory(char *mem, int maxerrlen, char **errmsg);
#endif

int	zbx_xmlnode_to_json(void *xml_node, char **jstr);
int	zbx_xml_to_json(char *xml_data, char **jstr, char **errmsg);
int	zbx_json_to_xml(char *json_data, char **xstr, char **errmsg);

int	zbx_xml_xpath_check(const char *xpath, char *error, size_t errlen);

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
int	zbx_xml_read_values(xmlDoc *xdoc, const char *xpath, zbx_vector_str_t *values);
int	zbx_xml_node_read_values(xmlDoc *xdoc, xmlNode *node, const char *xpath, zbx_vector_str_t *values);
int	zbx_xml_try_read_value(const char *data, size_t len, const char *xpath, xmlDoc **xdoc, char **value,
		char **error);
int	zbx_xml_doc_read_num(xmlDoc *xdoc, const char *xpath, int *num);
int	zbx_xml_node_read_num(xmlDoc *xdoc, xmlNode *node, const char *xpath, int *num);
char	*zbx_xml_node_read_value(xmlDoc *xdoc, xmlNode *node, const char *xpath);
char	*zbx_xml_node_read_prop(xmlNode *node, const char *name);
char	*zbx_xml_doc_read_value(xmlDoc *xdoc, const char *xpath);
xmlNode	*zbx_xml_node_get(xmlDoc *xdoc, xmlNode *node, const char *xpath);
xmlNode	*zbx_xml_doc_get(xmlDoc *xdoc, const char *xpath);
int	zbx_xml_node_remove(xmlDoc *xdoc, xmlNode *node, const char *xpath);
#endif /* HAVE_LIBXML2 && HAVE_LIBCURL */

#endif  /* ZABBIX_XML_H */
