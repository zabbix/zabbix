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

#include "zbxalgo.h"
#include "log.h"
#include "zbxjson.h"
#include "zbxvariant.h"

#ifdef HAVE_LIBXML2
#	include <libxml/xpath.h>
#endif

#include "../zbxalgo/vectorimpl.h"

#define XML_TEXT_NAME	"text"
#define XML_CDATA_NAME	"cdata"
#define XML_TEXT_TAG	"#text"
#define XML_JSON_TRUE	1
#define XML_JSON_FALSE	0

typedef struct _zbx_xml_node_t zbx_xml_node_t;

ZBX_PTR_VECTOR_DECL(xml_node_ptr, zbx_xml_node_t *)

struct _zbx_xml_node_t
{
	char				*name;
	char				*value;
	zbx_vector_str_t		attributes;
	zbx_vector_xml_node_ptr_t	chnodes;
	int				is_array;
};

ZBX_PTR_VECTOR_IMPL(xml_node_ptr, zbx_xml_node_t *)

/******************************************************************************
 *                                                                            *
 * Purpose: replace <> symbols in string with &lt;&gt; so the resulting       *
 *          string can be written into xml field                              *
 *                                                                            *
 * Parameters: data - [IN] the input string                                   *
 *                                                                            *
 * Return value: an allocated string containing escaped input string          *
 *                                                                            *
 * Comments: The caller must free the returned string after it has been used. *
 *                                                                            *
 ******************************************************************************/
char	*xml_escape_dyn(const char *data)
{
	char		*out, *ptr_out;
	const char	*ptr_in;
	int		size = 0;

	if (NULL == data)
		return zbx_strdup(NULL, "");

	for (ptr_in = data; '\0' != *ptr_in; ptr_in++)
	{
		switch (*ptr_in)
		{
			case '<':
			case '>':
				size += 4;
				break;
			case '&':
				size += 5;
				break;
			case '"':
			case '\'':
				size += 6;
				break;
			default:
				size++;
		}
	}
	size++;

	out = (char *)zbx_malloc(NULL, size);

	for (ptr_out = out, ptr_in = data; '\0' != *ptr_in; ptr_in++)
	{
		switch (*ptr_in)
		{
			case '<':
				*ptr_out++ = '&';
				*ptr_out++ = 'l';
				*ptr_out++ = 't';
				*ptr_out++ = ';';
				break;
			case '>':
				*ptr_out++ = '&';
				*ptr_out++ = 'g';
				*ptr_out++ = 't';
				*ptr_out++ = ';';
				break;
			case '&':
				*ptr_out++ = '&';
				*ptr_out++ = 'a';
				*ptr_out++ = 'm';
				*ptr_out++ = 'p';
				*ptr_out++ = ';';
				break;
			case '"':
				*ptr_out++ = '&';
				*ptr_out++ = 'q';
				*ptr_out++ = 'u';
				*ptr_out++ = 'o';
				*ptr_out++ = 't';
				*ptr_out++ = ';';
				break;
			case '\'':
				*ptr_out++ = '&';
				*ptr_out++ = 'a';
				*ptr_out++ = 'p';
				*ptr_out++ = 'o';
				*ptr_out++ = 's';
				*ptr_out++ = ';';
				break;
			default:
				*ptr_out++ = *ptr_in;
		}

	}
	*ptr_out = '\0';

	return out;
}

/**********************************************************************************
 *                                                                                *
 * Purpose: calculate a string size after symbols escaping                        *
 *                                                                                *
 * Parameters: string - [IN] the string to check                                  *
 *                                                                                *
 * Return value: new size of the string                                           *
 *                                                                                *
 **********************************************************************************/
static size_t	xml_escape_xpath_stringsize(const char *string)
{
	size_t		len = 0;
	const char	*sptr;

	if (NULL == string)
		return 0;

	for (sptr = string; '\0' != *sptr; sptr++)
		len += (('"' == *sptr) ? 2 : 1);

	return len;
}

/**********************************************************************************
 *                                                                                *
 * Purpose: replace " symbol in string with ""                                    *
 *                                                                                *
 * Parameters: string - [IN] the xpath string to escape                           *
 *             p      - [OUT] the result string                                   *
 *                                                                                *
 **********************************************************************************/
static void xml_escape_xpath_string(char *p, const char *string)
{
	const char	*sptr = string;

	while ('\0' != *sptr)
	{
		if ('"' == *sptr)
			*p++ = '"';

		*p++ = *sptr++;
	}
}

/**********************************************************************************
 *                                                                                *
 * Purpose: escaping of symbols for using in xpath expression                     *
 *                                                                                *
 * Parameters: data - [IN/OUT] the string to update                               *
 *                                                                                *
 **********************************************************************************/
void xml_escape_xpath(char **data)
{
	size_t	size;
	char	*buffer;

	if (0 == (size = xml_escape_xpath_stringsize(*data)))
		return;

	buffer = zbx_malloc(NULL, size + 1);
	buffer[size] = '\0';
	xml_escape_xpath_string(buffer, *data);
	zbx_free(*data);
	*data = buffer;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute xpath query                                               *
 *                                                                            *
 * Parameters: value  - [IN/OUT] the value to process                         *
 *             params - [IN] the operation parameters                         *
 *             errmsg - [OUT] error message                                   *
 *                                                                            *
 * Return value: SUCCEED - the value was processed successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_query_xpath(zbx_variant_t *value, const char *params, char **errmsg)
{
#ifndef HAVE_LIBXML2
	ZBX_UNUSED(value);
	ZBX_UNUSED(params);
	*errmsg = zbx_dsprintf(*errmsg, "Zabbix was compiled without libxml2 support");
	return FAIL;
#else
	int		i, ret = FAIL;
	char		buffer[32], *ptr;
	xmlDoc		*doc = NULL;
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	xmlErrorPtr	pErr;
	xmlBufferPtr	xmlBufferLocal;

	if (NULL == (doc = xmlReadMemory(value->data.str, strlen(value->data.str), "noname.xml", NULL, 0)))
	{
		if (NULL != (pErr = xmlGetLastError()))
			*errmsg = zbx_dsprintf(*errmsg, "cannot parse xml value: %s", pErr->message);
		else
			*errmsg = zbx_strdup(*errmsg, "cannot parse xml value");
		return FAIL;
	}

	xpathCtx = xmlXPathNewContext(doc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *)params, xpathCtx)))
	{
		if (NULL != (pErr = xmlGetLastError()))
			*errmsg = zbx_dsprintf(*errmsg, "cannot parse xpath: %s", pErr->message);
		else
			*errmsg = zbx_strdup(*errmsg, "cannot parse xpath");
		goto out;
	}

	switch (xpathObj->type)
	{
		case XPATH_NODESET:
			if (NULL == (xmlBufferLocal = xmlBufferCreate()))
				break;

			if (0 == xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
			{
				nodeset = xpathObj->nodesetval;

				for (i = 0; i < nodeset->nodeNr; i++)
					xmlNodeDump(xmlBufferLocal, doc, nodeset->nodeTab[i], 0, 0);
			}
			zbx_variant_clear(value);
			zbx_variant_set_str(value, zbx_strdup(NULL, (const char *)xmlBufferLocal->content));

			xmlBufferFree(xmlBufferLocal);
			ret = SUCCEED;
			break;
		case XPATH_STRING:
			zbx_variant_clear(value);
			zbx_variant_set_str(value, zbx_strdup(NULL, (const char *)xpathObj->stringval));
			ret = SUCCEED;
			break;
		case XPATH_BOOLEAN:
			zbx_variant_clear(value);
			zbx_variant_set_str(value, zbx_dsprintf(NULL, "%d", xpathObj->boolval));
			ret = SUCCEED;
			break;
		case XPATH_NUMBER:
			zbx_variant_clear(value);
			zbx_snprintf(buffer, sizeof(buffer), ZBX_FS_DBL, xpathObj->floatval);

			/* check for nan/inf values - isnan(), isinf() is not supported by c89/90 */
			/* so simply check the result starts with digit (accounting for -inf) */
			if ('-' == *(ptr = buffer))
				ptr++;
			if (0 != isdigit(*ptr))
			{
				del_zeros(buffer);
				zbx_variant_set_str(value, zbx_strdup(NULL, buffer));
				ret = SUCCEED;
			}
			else
				*errmsg = zbx_strdup(*errmsg, "Invalid numeric value");
			break;
		default:
			*errmsg = zbx_dsprintf(*errmsg, "Unknown XPath object type %d", (int)xpathObj->type);
			break;
	}
out:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);
	xmlFreeDoc(doc);

	return ret;
#endif
}

#ifdef HAVE_LIBXML2
/******************************************************************************
 *                                                                            *
 * Purpose: compare two xml nodes by name                                     *
 *                                                                            *
 * Comments: This function is used to sort xml nodes by name                  *
 *                                                                            *
 ******************************************************************************/
static int	compare_xml_nodes_by_name(const void *d1, const void *d2)
{
	zbx_xml_node_t	*p1 = *(zbx_xml_node_t **)d1;
	zbx_xml_node_t	*p2 = *(zbx_xml_node_t **)d2;

	return strcmp(p1->name, p2->name);
}

static void	zbx_xml_node_free(zbx_xml_node_t *node)
{
	zbx_vector_xml_node_ptr_clear_ext(&node->chnodes, zbx_xml_node_free);
	zbx_vector_xml_node_ptr_destroy(&node->chnodes);
	zbx_vector_str_clear_ext(&node->attributes, zbx_str_free);
	zbx_vector_str_destroy(&node->attributes);
	zbx_free(node->name);
	zbx_free(node->value);
	zbx_free(node);
}

/******************************************************************************
 *                                                                            *
 * Purpose: to collect content of XML document nodes into vector              *
 *                                                                            *
 * Parameters: xml_node  - [IN] parent XML node structure                     *
 *             nodes     - [OUT] vector of child XML nodes                    *
 *                                                                            *
 ******************************************************************************/
static void	xml_to_vector(xmlNode *xml_node, zbx_vector_xml_node_ptr_t *nodes)
{
	int				index;
	xmlChar				*value;
	xmlAttr				*attr;
	zbx_vector_xml_node_ptr_t	nodes_local;

	zbx_vector_xml_node_ptr_create(&nodes_local);

	for (; NULL != xml_node; xml_node = xml_node->next)
	{
		zbx_xml_node_t	*node;

		node = (zbx_xml_node_t *)zbx_malloc(NULL, sizeof(zbx_xml_node_t));

		if (NULL != xml_node->name)
			node->name = zbx_strdup(NULL, (const char *)xml_node->name);
		else
			node->name = NULL;

		node->value = NULL;
		node->is_array = XML_JSON_FALSE;

		zbx_vector_xml_node_ptr_create(&node->chnodes);
		zbx_vector_str_create(&node->attributes);

		switch (xml_node->type)
		{
			case XML_TEXT_NODE:
				if (NULL == (value = xmlNodeGetContent(xml_node)))
					break;

				node->value = zbx_strdup(NULL, (const char *)value);
				xmlFree(value);
				break;
			case XML_CDATA_SECTION_NODE:
				if (NULL == (value = xmlNodeGetContent(xml_node)))
					break;
				node->value = zbx_strdup(NULL, (const char *)value);
				node->name = zbx_strdup(node->name, XML_CDATA_NAME);
				xmlFree(value);
				break;
			case XML_ELEMENT_NODE:
				for (attr = xml_node->properties; NULL != attr; attr = attr->next)
				{
					char	*attr_name = NULL;
					size_t	attr_name_alloc = 0, attr_name_offset = 0;

					if (NULL == attr->name)
						continue;

					zbx_snprintf_alloc(&attr_name, &attr_name_alloc, &attr_name_offset, "@%s",
							attr->name);
					zbx_vector_str_append(&node->attributes, attr_name);
					if (NULL != (value = xmlGetProp(xml_node, attr->name)))
					{
						zbx_vector_str_append(&node->attributes, zbx_strdup(NULL,
								(const char *)value));
						xmlFree(value);
					}
					else
						zbx_vector_str_append(&node->attributes, (char *)NULL);
				}
				break;
			default:
				zabbix_log(LOG_LEVEL_DEBUG, "Unsupported XML node type %d, ignored",
						(int)xml_node->type);
				zbx_xml_node_free(node);
				node = NULL;
				break;
		}

		if (NULL != node)
		{
			xml_to_vector(xml_node->children, &node->chnodes);
			zbx_vector_xml_node_ptr_append(&nodes_local, node);
		}
	}

	zbx_vector_xml_node_ptr_reserve(nodes, (size_t)nodes_local.values_num);

	while (0 < nodes_local.values_num)
	{
		zbx_xml_node_t	*first_node, *next_node;

		first_node = nodes_local.values[0];
		zbx_vector_xml_node_ptr_remove(&nodes_local, 0);
		zbx_vector_xml_node_ptr_append(nodes, first_node);

		while (FAIL != (index = zbx_vector_xml_node_ptr_search(&nodes_local, first_node,
				compare_xml_nodes_by_name)))
		{
			first_node->is_array = XML_JSON_TRUE;
			next_node = nodes_local.values[index];
			next_node->is_array = XML_JSON_TRUE;
			zbx_vector_xml_node_ptr_remove(&nodes_local, index);
			zbx_vector_xml_node_ptr_append(nodes, next_node);
		}
	}

	zbx_vector_xml_node_ptr_clear_ext(&nodes_local, zbx_xml_node_free);
	zbx_vector_xml_node_ptr_destroy(&nodes_local);
}

/******************************************************************************
 *                                                                            *
 * Purpose: to check if node is leaf node with text content                   *
 *                                                                            *
 * Parameters: node       - [IN] node structure                               *
 *                                                                            *
 * Return value: SUCCEED - node has text content                              *
 *               FAIL    - node has no content                                *
 *                                                                            *
 ******************************************************************************/
static int	is_data(zbx_xml_node_t *node)
{
	if (0 == node->chnodes.values_num &&
			(0 == strcmp(XML_TEXT_NAME, node->name) || 0 == strcmp(XML_CDATA_NAME, node->name)))
	{
		return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: to write content of vector into JSON document                     *
 *                                                                            *
 * Parameters: nodes   - [IN] vector of nodes                                 *
 *             json    - [IN/OUT] JSON structure                              *
 *             text    - [OUT] text content for given node                    *
 *                                                                            *
 ******************************************************************************/
static void	vector_to_json(zbx_vector_xml_node_ptr_t *nodes, struct zbx_json *json, char **text)
{
	int		i, j, is_object, arr_cnt = 0;
	char		*tag, *out_text, *arr_name = NULL;
	zbx_xml_node_t	*node;

	*text = NULL;

	for (i = 0; i < nodes->values_num; i++)
	{
		node = nodes->values[i];

		if ((XML_JSON_FALSE == node->is_array && 0 != arr_cnt) || (XML_JSON_TRUE == node->is_array &&
				NULL != arr_name && 0 != strcmp(arr_name, node->name)))
		{
			if (FAIL == zbx_json_close(json))
				THIS_SHOULD_NEVER_HAPPEN;
			arr_name = NULL;
			arr_cnt = 0;
		}

		if (XML_JSON_TRUE == node->is_array)
		{
			if (0 == arr_cnt)
			{
				zbx_json_addarray(json, node->name);
				arr_name = node->name;
			}
			arr_cnt++;
		}

		is_object = XML_JSON_FALSE;

		if (0 != node->chnodes.values_num)
		{
			zbx_xml_node_t	*chnode;

			/* if first child node is not data node that is enough to recognize current node as object */
			chnode = node->chnodes.values[0];

			if (FAIL == is_data(chnode))
				is_object = XML_JSON_TRUE;
		}

		if (0 != node->attributes.values_num)
			is_object = XML_JSON_TRUE;

		if (XML_JSON_TRUE == is_object)
			zbx_json_addobject(json, 0 != arr_cnt ? NULL : node->name);

		for (j = 0; j < node->attributes.values_num; j += 2)
		{
			zbx_json_addstring(json, node->attributes.values[j], node->attributes.values[j + 1],
					ZBX_JSON_TYPE_STRING);
		}

		vector_to_json(&node->chnodes, json, &out_text);

		*text = node->value;

		if (NULL != out_text || (XML_JSON_FALSE == is_object && FAIL == is_data(node)))
		{
			if (0 != node->attributes.values_num)
				tag = XML_TEXT_TAG;
			else if (0 != arr_cnt)
				tag = NULL;
			else
				tag = node->name;
			zbx_json_addstring(json, tag, out_text, ZBX_JSON_TYPE_STRING);
		}

		if (XML_JSON_TRUE == is_object && FAIL == zbx_json_close(json))
			THIS_SHOULD_NEVER_HAPPEN;
	}

	if (0 != arr_cnt && FAIL == zbx_json_close(json))
		THIS_SHOULD_NEVER_HAPPEN;
}
#endif /* HAVE_LIBXML2 */

#ifdef HAVE_LIBXML2
/******************************************************************************
 *                                                                            *
 * Purpose: to create xmlDoc and it's root node for input data                *
 *                                                                            *
 * Parameters: data      - [IN] input data                                    *
 *             options   - [IN] XML options                                   *
 *             maxerrlen - [IN] the size of error buffer, -1 to ignore        *
 *             xml_doc   - [OUT] pointer to xmlDoc structure                  *
 *             root_node - [OUT] pointer to xmlNode structure                 *
 *             errmsg    - [OUTY] error message                               *
 *                                                                            *
 * Return value: SUCCEED - xmlDoc and root node structure created             *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_open_xml(char *data, int options, int maxerrlen, void **xml_doc, void **root_node, char **errmsg)
{
	xmlErrorPtr	pErr;

	if (NULL == (*xml_doc = xmlReadMemory(data, strlen(data), "noname.xml", NULL, options)))
	{
		if (NULL != (pErr = xmlGetLastError()))
		{
			const char	*pmessage;

			if (NULL != strstr(pErr->message, "use XML_PARSE_HUGE option"))
				pmessage = "Excessive depth in XML document";
			else
				pmessage = pErr->message;

			if (0 > maxerrlen)
				*errmsg = zbx_dsprintf(*errmsg, "cannot parse xml value: %s", pmessage);
			else
				zbx_snprintf(*errmsg, (size_t)maxerrlen, "Cannot parse XML value: %s", pmessage);
		}
		else
		{
			if (0 > maxerrlen)
				*errmsg = zbx_strdup(*errmsg, "cannot parse xml value");
			else
				zbx_snprintf(*errmsg, (size_t)maxerrlen, "Cannot parse XML value");
		}

		return FAIL;
	}

	if (NULL == (*root_node = xmlDocGetRootElement((xmlDoc *)*xml_doc)))
	{
		if (0 > maxerrlen)
			*errmsg = zbx_dsprintf(*errmsg, "Cannot parse XML root");
		else
			zbx_snprintf(*errmsg, (size_t)maxerrlen, "Cannot parse XML root");

		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: to check xml memory to be valid                                   *
 *                                                                            *
 * Parameters: mem       - [IN] pointer to memory                             *
 *             maxerrlen - [IN] the size of error buffer, -1 to ignore        *
 *             errmsg    - [OUTY] error message                               *
 *                                                                            *
 * Return value: SUCCEED - xml memory is not NULL                             *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_check_xml_memory(char *mem, int maxerrlen, char **errmsg)
{
	xmlErrorPtr	pErr;

	if (NULL == mem)
	{
		if (NULL != (pErr = xmlGetLastError()))
			if (0 > maxerrlen)
				*errmsg = zbx_dsprintf(*errmsg, "cannot parse xml value: %s", pErr->message);
			else
				zbx_snprintf(*errmsg, (size_t)maxerrlen, "Cannot save XML: %s", pErr->message);
		else
			if (0 > maxerrlen)
				*errmsg = zbx_strdup(*errmsg, "cannot parse xml value");
			else
				zbx_snprintf(*errmsg, (size_t)maxerrlen, "Cannot save XML");

		return FAIL;
	}

	return SUCCEED;
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: convert XML format value to JSON format                           *
 *                                                                            *
 * Parameters: xml_data - [IN] the XML data to process                        *
 *             jstr     - [OUT] the JSON output                               *
 *             errmsg   - [OUT] error message                                 *
 *                                                                            *
 * Return value: SUCCEED - the value was processed successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_xml_to_json(char *xml_data, char **jstr, char **errmsg)
{
#ifndef HAVE_LIBXML2
	ZBX_UNUSED(xml_data);
	ZBX_UNUSED(jstr);
	*errmsg = zbx_dsprintf(*errmsg, "Zabbix was compiled without libxml2 support");
	return FAIL;
#else
	xmlDoc				*doc = NULL;
	xmlNode				*node;
	int				ret = FAIL;

	if (FAIL == zbx_open_xml(xml_data, XML_PARSE_NOBLANKS, -1, (void **)&doc, (void **)&node, errmsg))
	{
		if (NULL == doc)
			goto exit;

		if (NULL == node)
			goto clean;
	}

	ret = zbx_xmlnode_to_json((void *)node, jstr);
clean:
	xmlFreeDoc(doc);
exit:
	return ret;
#endif /* HAVE_LIBXML2 */
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert XML format value to JSON format                           *
 *                                                                            *
 * Parameters: xml_node - [IN] the XML data to process                        *
 *             jstr     - [OUT] the JSON output                               *
 *                                                                            *
 * Return value: SUCCEED - the value was processed successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_xmlnode_to_json(void *xml_node, char **jstr)
{
#ifndef HAVE_LIBXML2
	ZBX_UNUSED(xml_node);
	ZBX_UNUSED(jstr);
	return FAIL;
#else
	struct zbx_json			json;
	zbx_vector_xml_node_ptr_t	nodes;
	char				*out;
	xmlNode				*node = (xmlNode*)xml_node;

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	zbx_vector_xml_node_ptr_create(&nodes);

	xml_to_vector(node, &nodes);
	vector_to_json(&nodes, &json, &out);
	*jstr = zbx_strdup(*jstr, json.buffer);

	zbx_vector_xml_node_ptr_clear_ext(&nodes, zbx_xml_node_free);
	zbx_vector_xml_node_ptr_destroy(&nodes);
	zbx_json_free(&json);
	return SUCCEED;
#endif /* HAVE_LIBXML2 */
}

#ifdef HAVE_LIBXML2
/******************************************************************************
 *                                                                            *
 * Purpose: to write content of JSON document into XML node                   *
 *                                                                            *
 * Parameters: jp             - [IN] JSON parse structure                     *
 *             arr_name       - [IN] name of parent array                     *
 *             deep           - [IN] node depth level                         *
 *             doc            - [IN/OUT] xml document structure               *
 *             parent_node    - [IN/OUT] parent XML node                      *
 *             attr           - [OUT] node attribute name                     *
 *             attr_val       - [OUT] node attribute value                    *
 *             text           - [OUT] node content                            *
 *                                                                            *
 ******************************************************************************/
static void	json_to_xmlnode(struct zbx_json_parse *jp, char *arr_name, int deep, xmlDoc *doc, xmlNode *parent_node,
		char **attr, char **attr_val, char **text)
{
	const char		*json_string_ptr = NULL, *json_string_ptr_old = NULL;
	char			*array_loc, *pname, name[MAX_STRING_LEN], value[MAX_STRING_LEN], *attr_loc = NULL,
			*attr_val_loc = NULL, *text_loc = NULL, *pvalue = NULL;
	int			set_attr, set_text, idx = 0;
	zbx_json_type_t		type;
	xmlNode			*node;
	struct zbx_json_parse	jp_data;

	do
	{
		set_attr = 0;
		set_text = 0;
		array_loc = NULL;
		pname = NULL;

		if (NULL != (json_string_ptr = zbx_json_pair_next(jp, json_string_ptr, name, sizeof(name))))
		{
			pname = name;

			if (NULL == zbx_json_decodevalue(json_string_ptr, value, sizeof(value), &type))
				type = zbx_json_valuetype(json_string_ptr);
			else
				pvalue = xml_escape_dyn(value);
			if ('@' == name[0])
				set_attr = 1;
			else if (0 == strcmp(name, XML_TEXT_TAG))
				set_text = 1;
		}
		else
		{
			json_string_ptr = json_string_ptr_old;
			if (NULL != (json_string_ptr = zbx_json_next_value(jp, json_string_ptr, value, sizeof(value),
					&type)))
			{
				pvalue = xml_escape_dyn(value);
			}
			else
			{
				json_string_ptr = json_string_ptr_old;
				if (NULL != (json_string_ptr = zbx_json_next(jp, json_string_ptr)))
					type = zbx_json_valuetype(json_string_ptr);
			}
		}
		json_string_ptr_old = json_string_ptr;

		if (0 != set_attr)
		{
			*attr = zbx_strdup(*attr, &name[1]);
			if (NULL != pvalue)
				*attr_val = zbx_strdup(*attr_val, pvalue);
		}
		else if (0 != set_text && NULL != pvalue)
		{
			*text = zbx_strdup(*text, pvalue);
		}
		else if (NULL != json_string_ptr)
		{
			pname = (NULL == arr_name) ? pname : arr_name;
			node = NULL;

			if (0 == deep && 0 < idx)
				break;

			if (ZBX_JSON_TYPE_ARRAY == type)
			{
				array_loc = name;
				node = parent_node;
			}
			else if (ZBX_JSON_TYPE_OBJECT == type || ZBX_JSON_TYPE_UNKNOWN == type)
			{
				node = xmlNewDocNode(doc, NULL, (xmlChar *)pname, NULL);
			}
			else
				node = xmlNewDocNode(doc, NULL, (xmlChar *)pname, (xmlChar *)pvalue);

			if (0 == deep)
			{
				if (NULL != node)
					xmlDocSetRootElement(doc, node);
				else
					break;
			}
			else
			{
				if (NULL != node && node != parent_node)
					node = xmlAddChild(parent_node, node);
			}

			if (SUCCEED == zbx_json_brackets_open(json_string_ptr, &jp_data))
			{
				json_to_xmlnode(&jp_data, array_loc, deep + 1, doc, node, &attr_loc, &attr_val_loc,
						&text_loc);
			}
		}

		if (NULL != attr_loc)
			xmlNewProp(node, (xmlChar *)attr_loc, (xmlChar *)attr_val_loc);
		if (NULL != text_loc)
			xmlNodeSetContent(node, (xmlChar *)text_loc);

		zbx_free(attr_loc);
		zbx_free(attr_val_loc);
		zbx_free(text_loc);
		zbx_free(pvalue);
		idx++;
	}
	while (NULL != json_string_ptr);

	zbx_free(pvalue);
}
#endif /* HAVE_LIBXML2 */

/******************************************************************************
 *                                                                            *
 * Purpose: convert JSON format value to XML format                           *
 *                                                                            *
 * Parameters: json_data   - [IN] the JSON data to process                    *
 *             xstr        - [OUT] the XML output                             *
 *             errmsg      - [OUT] error message                              *
 *                                                                            *
 * Return value: SUCCEED - the value was processed successfully               *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_json_to_xml(char *json_data, char **xstr, char **errmsg)
{
#ifndef HAVE_LIBXML2
	ZBX_UNUSED(json_data);
	ZBX_UNUSED(xstr);
	*errmsg = zbx_dsprintf(*errmsg, "Zabbix was compiled without libxml2 support");
	return FAIL;
#else
	char			*attr = NULL, *attr_val = NULL, *text = NULL;
	int			size, ret = FAIL;
	struct zbx_json_parse	jp;
	xmlDoc			*doc = NULL;
	xmlErrorPtr		pErr;
	xmlChar			*xmem;

	if (NULL == (doc = xmlNewDoc(BAD_CAST XML_DEFAULT_VERSION)))
	{
		if (NULL != (pErr = xmlGetLastError()))
			*errmsg = zbx_dsprintf(*errmsg, "cannot parse xml value: %s", pErr->message);
		else
			*errmsg = zbx_strdup(*errmsg, "cannot parse xml value");
		goto exit;
	}

	if (SUCCEED != zbx_json_open(json_data, &jp))
	{
		*errmsg = zbx_strdup(*errmsg, zbx_json_strerror());
		goto clean;
	}

	json_to_xmlnode(&jp, NULL, 0, doc, NULL, &attr, &attr_val, &text);

	xmlDocDumpMemory(doc, &xmem, &size);

	zbx_free(text);
	zbx_free(attr_val);
	zbx_free(attr);

	if (FAIL == zbx_check_xml_memory((char *)xmem, -1, errmsg))
		goto clean;

	*xstr = zbx_malloc(*xstr, (size_t)size + 1);
	memcpy(*xstr, (const char *)xmem, (size_t)size + 1);
	xmlFree(xmem);
	ret = SUCCEED;
clean:
	xmlFreeDoc(doc);
exit:
	return ret;
#endif /* HAVE_LIBXML2 */
}
