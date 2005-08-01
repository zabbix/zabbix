#include "common.h"
#include "log.h"

#include <string.h>

/* Get DATA from <tag>DATA</tag> */
int xml_get_data(char *xml,char *tag, char *data, int maxlen)
{
	int ret = SUCCEED;
	char *start, *end;
	char tag_open[MAX_STRING_LEN];
	char tag_close[MAX_STRING_LEN];
	int len;

        zabbix_log( LOG_LEVEL_WARNING, "In xml_get_data([%s],[%s])", xml, tag);

	snprintf(tag_open,MAX_STRING_LEN-1,"<%s>",tag);
	snprintf(tag_close,MAX_STRING_LEN-1,"</%s>",tag);

	if(NULL==(start=strstr(xml,tag_open)))
	{
		ret = FAIL;
	}

	if(NULL==(end=strstr(xml,tag_close)))
	{
		ret = FAIL;
	}

	if(ret == SUCCEED)
	{
		if(end<start)	ret = FAIL;
	}

	if(ret == SUCCEED)
	{
		len=end-(start+strlen(tag_open));

		if(len>maxlen)	len=maxlen;

		strncpy(data, start+strlen(tag_open),len);
	}

        zabbix_log( LOG_LEVEL_WARNING, "In xml_get_data. Data [%s]", data);

	return ret;
}
