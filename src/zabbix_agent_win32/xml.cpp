#include "zabbixw32.h"

#include <string.h>

/* Get DATA from <tag>DATA</tag> */
int xml_get_data(char *xml,char *tag, char *data, int maxlen)
{
	int ret = SUCCEED;
	char *start, *end;
	char tag_open[MAX_STRING_LEN];
	char tag_close[MAX_STRING_LEN];
	int len;

INIT_CHECK_MEMORY(main);

	sprintf(tag_open,"<%s>",tag);
	sprintf(tag_close,"</%s>",tag);

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
		if(end<start)
		{
			ret = FAIL;
		}
	}

	if(ret == SUCCEED)
	{
		len = (int)(end - (start + (int)strlen(tag_open)));

		if(len>maxlen)	len=maxlen;

		strncpy(data, start+strlen(tag_open),len);
	}

CHECK_MEMORY(main,"xml_get_data","end");

	return ret;
}
