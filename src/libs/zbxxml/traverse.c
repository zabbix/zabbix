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

#include "zbxxml.h"

#ifdef HAVE_LIBXML2
static int	xml_traverse_elements(xmlNode *node, zbx_xml_resolv_func_t resolver, va_list args)
{
	int	ret = SUCCEED;
	xmlChar	*value;
	xmlAttr	*attr;
	char	*value_tmp;
	va_list	pargs;

	for (;NULL != node && SUCCEED == ret; node = node->next)
	{
		switch (node->type)
		{
			case XML_TEXT_NODE:
			case XML_CDATA_SECTION_NODE:
				if (NULL == (value = xmlNodeGetContent(node)))
					break;

				value_tmp = zbx_strdup(NULL, (const char *)value);

				va_copy(pargs, args); /* copy current argument position */

				ret = resolver(&value_tmp, NULL, 0, pargs);

				va_end(pargs);

				xmlNodeSetContent(node, NULL);
				xmlNodeAddContent(node, (xmlChar *)value_tmp);

				zbx_free(value_tmp);
				xmlFree(value);
				break;
			case XML_ELEMENT_NODE:
				for (attr = node->properties; NULL != attr && SUCCEED == ret; attr = attr->next)
				{
					if (NULL == attr->name || NULL == (value = xmlGetProp(node, attr->name)))
						continue;

					value_tmp = zbx_strdup(NULL, (const char *)value);

					va_copy(pargs, args); /* copy current argument position */

					ret = resolver(&value_tmp, NULL, 0, pargs);

					va_end(pargs);

					xmlSetProp(node, attr->name, (xmlChar *)value_tmp);

					zbx_free(value_tmp);
					xmlFree(value);
				}
				break;
			default:
				break;
		}

		if (SUCCEED != ret)
			break;

		ret = xml_traverse_elements(node->children, resolver, args);
	}

	return ret;
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: Traverse XML text nodes, attributes of noode or in CDATA section, *
 *          substitute them validates XML.                                    *
 *                                                                            *
 * Parameters: data      - [IN/OUT] pointer to buffer that contains xml       *
 *             error     - [OUT] reason for xml parsing failure               *
 *             maxerrlen - [IN] size of error buffer                          *
 *             resolver  - [IN] function callback to perform substitution on  *
 *             ...       - [IN] variadic arguments passed to resolver         *
 *                              callback                                      *
 *                                                                            *
 * Return value: SUCCEED or FAIL if XML validation has failed.                *
 *                                                                            *
 ******************************************************************************/
int	zbx_xml_traverse(char **data, char *error, int maxerrlen, zbx_xml_resolv_func_t resolver, ...)
{
	int	ret = FAIL;
	va_list	args;

	va_start(args, resolver);

#ifndef HAVE_LIBXML2
	ZBX_UNUSED(data);

	zbx_snprintf(error, maxerrlen, "Support for XML was not compiled in");
#else
	xmlDoc		*doc;
	xmlNode		*root_element;
	xmlChar		*mem;
	int		size;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == zbx_open_xml(*data, 0, maxerrlen, (void **)&doc, (void **)&root_element, &error))
	{
		if (NULL == doc)
			goto exit;

		if (NULL == root_element)
			goto clean;
	}

	ret = xml_traverse_elements(root_element, resolver, args);
	xmlDocDumpMemory(doc, &mem, &size);

	if (FAIL == zbx_check_xml_memory((char *)mem, maxerrlen, &error))
		goto clean;

	zbx_free(*data);
	*data = zbx_malloc(NULL, (size_t)(size + 1));
	memcpy(*data, (const char *)mem, (size_t)(size + 1));
	xmlFree(mem);
	ret = SUCCEED;
clean:
	xmlFreeDoc(doc);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));
#endif
	va_end(args);

	return ret;
}
