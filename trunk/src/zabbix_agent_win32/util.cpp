/* 
** ZabbixW32 - Win32 agent for Zabbix
** Copyright (C) 2002 Victor Kirhenshtein
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
**
** $module: util.cpp
**
**/

#include "zabbixw32.h"


//
// Display help
//

static void Help(void)
{
   printf("Usage: zabbixw32 [options] [command]\n\n"
          "Where possible commands are:\n"
          "   check-config    : Check configuration file and exit\n"
          "   standalone      : Run in standalone mode\n"
          "   start           : Start Zabbix Win32 Agent service\n"
          "   stop            : Stop Zabbix Win32 Agent service\n"
          "   install         : Install Zabbix Win32 Agent as service\n"
          "   remove          : Remove previously installed Zabbix Win32 Agent service\n"
          "   install-events  : Install Zabbix Win32 Agent as event source for Event Log\n"
          "                     This is done automatically when service is being installed\n"
          "   remove-events   : Remove Zabbix Win32 Agent event source\n"
          "                     This is done automatically when service is being removed\n"
          "   help            : Display help information\n"
          "   version         : Display version information\n"
          "   test <metric>   : Test specified metric and exit\n\n"
          "And possible options are:\n"
          "   --config <file> : Specify alternate configuration file\n"
          "                     (default is %s)\n\n", confFile);
}


//
// Parse command line
// Return value:
//    TRUE   if program execution should be continued
//    FALSE  otherwise
//

int ParseCommandLine(int argc,char *argv[])
{
   int i;
   int ret = TRUE;
   char path[MAX_PATH];

INIT_CHECK_MEMORY(main);

   for(i=1;i<argc;i++)
   {
      if (!strcmp(argv[i],"help"))    // Display help and exit
      {
         Help();
		 exit(0);
		 goto lbl_end;
      }
      else if (!strcmp(argv[i],"version"))    // Display version and exit
      {
         printf("Zabbix Win32 Agent Version " AGENT_VERSION " Build of " __DATE__ "\n");
		 exit(0);
		 goto lbl_end;
      }
      else if (!strcmp(argv[i],"--config"))  // Config file
      {
         i++;
         strcpy(confFile,argv[i]);     // Next word should contain name of the config file
         ret = TRUE;
      }
      else if (!strcmp(argv[i],"standalone"))  // Run in standalone mode
      {
         dwFlags|=AF_STANDALONE;
         ret = TRUE;
      }
      else if ((!strcmp(argv[i],"install"))||
               (!strcmp(argv[i],"install-events")))
      {
         _fullpath(path,argv[0],MAX_PATH);

         if (!strcmp(argv[i],"install"))
            ret = ZabbixCreateService(path);
         else
            ret = ZabbixInstallEventSource(path);
		 exit(ret);
		 goto lbl_end;
      }
      else if (!strcmp(argv[i],"remove"))
      {
         ret = ZabbixRemoveService();
		 exit(ret);
		 goto lbl_end;
      }
      else if (!strcmp(argv[i],"remove-events"))
      {
         ret = ZabbixRemoveEventSource();
		 exit(ret);
		 goto lbl_end;
      }
      else if (!strcmp(argv[i],"start"))
      {
         ret = ZabbixStartService();
		 exit(ret);
		 goto lbl_end;
      }
      else if (!strcmp(argv[i],"stop"))
      {
         ret = ZabbixStopService();
		 exit(ret);
		 goto lbl_end;
      }
      else if (!strcmp(argv[i],"test"))
      {
			i++;
			test_cmd = argv[i];
			dwFlags|=AF_STANDALONE;
	        ret = TRUE;
      }
      else if (!strcmp(argv[i],"check-config"))
      {
         dwFlags|=AF_STANDALONE;
         printf("Checking configuration file:\n\n");
         ret = ReadConfig();
		 exit(ret);
		 goto lbl_end;
      }
      else
      {
         printf("ERROR: Invalid command line argument\n\n");
         Help();
		 exit(1);
		 goto lbl_end;
      }
   }
lbl_end:
CHECK_MEMORY(main,"GetSystemErrorText","end");

   return ret;
}


//
// Get system error string by call to FormatMessage
//

char *GetSystemErrorText(DWORD error)
{
   char *msgBuf;
   static char staticBuffer[1024];

INIT_CHECK_MEMORY(main);

   if (FormatMessage(FORMAT_MESSAGE_ALLOCATE_BUFFER | 
                     FORMAT_MESSAGE_FROM_SYSTEM | 
                     FORMAT_MESSAGE_IGNORE_INSERTS,
                     NULL,error,
                     MAKELANGID(LANG_NEUTRAL,SUBLANG_DEFAULT), // Default language
                     (LPSTR)&msgBuf,0,NULL)>0)
   {
      msgBuf[strcspn(msgBuf,"\r\n")]=0;
      strncpy(staticBuffer,msgBuf,1023);
      LocalFree(msgBuf);
   }
   else
   {
      sprintf(staticBuffer,"3. MSG 0x%08X - Unable to find message text [0x%X]", error , GetLastError());
   }

CHECK_MEMORY(main,"GetSystemErrorText","end");

   return staticBuffer;
}


//
// Extract word from line. Extracted word will be placed in buffer.
// Returns pointer to the next word or to the null character if end
// of line reached.
//

char *ExtractWord(char *line,char *buffer)
{
   char *ptr,*bptr;

INIT_CHECK_MEMORY(main);

   for(ptr=line;(*ptr==' ')||(*ptr=='\t');ptr++);  // Skip initial spaces
   // Copy word to buffer
   for(bptr=buffer;(*ptr!=' ')&&(*ptr!='\t')&&(*ptr!=0);ptr++,bptr++)
      *bptr=*ptr;
   *bptr=0;

   CHECK_MEMORY(main,"MatchString","end");
   return ptr;
}


//
// Match string against pattern with * and ? metasymbols
//

BOOL MatchString(char *pattern,char *string)
{
   char *SPtr,*MPtr,*BPtr;

   SPtr=string;
   MPtr=pattern;

INIT_CHECK_MEMORY(main);

   while(*MPtr!=0)
   {
      switch(*MPtr)
      {
         case '?':
            if (*SPtr!=0)
            {
               SPtr++;
               MPtr++;
            }
            else
               return FALSE;
            break;
         case '*':
            while(*MPtr=='*')
               MPtr++;
            if (*MPtr==0)
	            return TRUE;
            if (*MPtr=='?')      // Handle "*?" case
            {
               if (*SPtr!=0)
                  SPtr++;
               else
                  return FALSE;
               break;
            }
            BPtr=MPtr;           // Text block begins here
            while((*MPtr!=0)&&(*MPtr!='?')&&(*MPtr!='*'))
               MPtr++;     // Find the end of text block
            for(;;)
            {
               while((*SPtr!=0)&&(*SPtr!=*BPtr))
                  SPtr++;
               if (strlen(SPtr)<(size_t)(MPtr-BPtr))
                  return FALSE;  // Length of remained text less than remaining pattern
               if (!memcmp(BPtr,SPtr,MPtr-BPtr))
                  break;
               SPtr++;
            }
            SPtr+=(MPtr-BPtr);   // Increment SPtr because we alredy match current fragment
            break;
         default:
            if (*MPtr==*SPtr)
            {
               SPtr++;
               MPtr++;
            }
            else
               return FALSE;
            break;
      }
   }

CHECK_MEMORY(main,"MatchString","end");
   return *SPtr==0 ? TRUE : FALSE;
}


//
// Strip whitespaces and tabs off the string
//

void StrStrip(char *str)
{
   int i;

INIT_CHECK_MEMORY(main);

   for(i=0;(str[i]!=0)&&((str[i]==' ')||(str[i]=='\t'));i++);
   if (i>0)
      memmove(str,&str[i],strlen(&str[i])+1);
   for(i=strlen(str)-1;(i>=0)&&((str[i]==' ')||(str[i]=='\t'));i--);
   str[i+1]=0;

CHECK_MEMORY(main,"StrStrip","end");
}


//
// Calculate MD5 hash for array of bytes
//

void CalculateMD5Hash(const unsigned char *data,int nbytes,unsigned char *hash)
{
	md5_state_t state;

INIT_CHECK_MEMORY(main);

	md5_init(&state);
	md5_append(&state,(const md5_byte_t *)data,nbytes);
	md5_finish(&state,(md5_byte_t *)hash);

CHECK_MEMORY(main,"CalculateMD5Hash","end");
}


//
// Table for CRC calculation
//

static DWORD crctab[256]=
{
	0x0,
	0x04c11db7, 0x09823b6e, 0x0d4326d9, 0x130476dc, 0x17c56b6b,
	0x1a864db2, 0x1e475005, 0x2608edb8, 0x22c9f00f, 0x2f8ad6d6,
	0x2b4bcb61, 0x350c9b64, 0x31cd86d3, 0x3c8ea00a, 0x384fbdbd,
	0x4c11db70, 0x48d0c6c7, 0x4593e01e, 0x4152fda9, 0x5f15adac,
	0x5bd4b01b, 0x569796c2, 0x52568b75, 0x6a1936c8, 0x6ed82b7f,
	0x639b0da6, 0x675a1011, 0x791d4014, 0x7ddc5da3, 0x709f7b7a,
	0x745e66cd, 0x9823b6e0, 0x9ce2ab57, 0x91a18d8e, 0x95609039,
	0x8b27c03c, 0x8fe6dd8b, 0x82a5fb52, 0x8664e6e5, 0xbe2b5b58,
	0xbaea46ef, 0xb7a96036, 0xb3687d81, 0xad2f2d84, 0xa9ee3033,
	0xa4ad16ea, 0xa06c0b5d, 0xd4326d90, 0xd0f37027, 0xddb056fe,
	0xd9714b49, 0xc7361b4c, 0xc3f706fb, 0xceb42022, 0xca753d95,
	0xf23a8028, 0xf6fb9d9f, 0xfbb8bb46, 0xff79a6f1, 0xe13ef6f4,
	0xe5ffeb43, 0xe8bccd9a, 0xec7dd02d, 0x34867077, 0x30476dc0,
	0x3d044b19, 0x39c556ae, 0x278206ab, 0x23431b1c, 0x2e003dc5,
	0x2ac12072, 0x128e9dcf, 0x164f8078, 0x1b0ca6a1, 0x1fcdbb16,
	0x018aeb13, 0x054bf6a4, 0x0808d07d, 0x0cc9cdca, 0x7897ab07,
	0x7c56b6b0, 0x71159069, 0x75d48dde, 0x6b93dddb, 0x6f52c06c,
	0x6211e6b5, 0x66d0fb02, 0x5e9f46bf, 0x5a5e5b08, 0x571d7dd1,
	0x53dc6066, 0x4d9b3063, 0x495a2dd4, 0x44190b0d, 0x40d816ba,
	0xaca5c697, 0xa864db20, 0xa527fdf9, 0xa1e6e04e, 0xbfa1b04b,
	0xbb60adfc, 0xb6238b25, 0xb2e29692, 0x8aad2b2f, 0x8e6c3698,
	0x832f1041, 0x87ee0df6, 0x99a95df3, 0x9d684044, 0x902b669d,
	0x94ea7b2a, 0xe0b41de7, 0xe4750050, 0xe9362689, 0xedf73b3e,
	0xf3b06b3b, 0xf771768c, 0xfa325055, 0xfef34de2, 0xc6bcf05f,
	0xc27dede8, 0xcf3ecb31, 0xcbffd686, 0xd5b88683, 0xd1799b34,
	0xdc3abded, 0xd8fba05a, 0x690ce0ee, 0x6dcdfd59, 0x608edb80,
	0x644fc637, 0x7a089632, 0x7ec98b85, 0x738aad5c, 0x774bb0eb,
	0x4f040d56, 0x4bc510e1, 0x46863638, 0x42472b8f, 0x5c007b8a,
	0x58c1663d, 0x558240e4, 0x51435d53, 0x251d3b9e, 0x21dc2629,
	0x2c9f00f0, 0x285e1d47, 0x36194d42, 0x32d850f5, 0x3f9b762c,
	0x3b5a6b9b, 0x0315d626, 0x07d4cb91, 0x0a97ed48, 0x0e56f0ff,
	0x1011a0fa, 0x14d0bd4d, 0x19939b94, 0x1d528623, 0xf12f560e,
	0xf5ee4bb9, 0xf8ad6d60, 0xfc6c70d7, 0xe22b20d2, 0xe6ea3d65,
	0xeba91bbc, 0xef68060b, 0xd727bbb6, 0xd3e6a601, 0xdea580d8,
	0xda649d6f, 0xc423cd6a, 0xc0e2d0dd, 0xcda1f604, 0xc960ebb3,
	0xbd3e8d7e, 0xb9ff90c9, 0xb4bcb610, 0xb07daba7, 0xae3afba2,
	0xaafbe615, 0xa7b8c0cc, 0xa379dd7b, 0x9b3660c6, 0x9ff77d71,
	0x92b45ba8, 0x9675461f, 0x8832161a, 0x8cf30bad, 0x81b02d74,
	0x857130c3, 0x5d8a9099, 0x594b8d2e, 0x5408abf7, 0x50c9b640,
	0x4e8ee645, 0x4a4ffbf2, 0x470cdd2b, 0x43cdc09c, 0x7b827d21,
	0x7f436096, 0x7200464f, 0x76c15bf8, 0x68860bfd, 0x6c47164a,
	0x61043093, 0x65c52d24, 0x119b4be9, 0x155a565e, 0x18197087,
	0x1cd86d30, 0x029f3d35, 0x065e2082, 0x0b1d065b, 0x0fdc1bec,
	0x3793a651, 0x3352bbe6, 0x3e119d3f, 0x3ad08088, 0x2497d08d,
	0x2056cd3a, 0x2d15ebe3, 0x29d4f654, 0xc5a92679, 0xc1683bce,
	0xcc2b1d17, 0xc8ea00a0, 0xd6ad50a5, 0xd26c4d12, 0xdf2f6bcb,
	0xdbee767c, 0xe3a1cbc1, 0xe760d676, 0xea23f0af, 0xeee2ed18,
	0xf0a5bd1d, 0xf464a0aa, 0xf9278673, 0xfde69bc4, 0x89b8fd09,
	0x8d79e0be, 0x803ac667, 0x84fbdbd0, 0x9abc8bd5, 0x9e7d9662,
	0x933eb0bb, 0x97ffad0c, 0xafb010b1, 0xab710d06, 0xa6322bdf,
	0xa2f33668, 0xbcb4666d, 0xb8757bda, 0xb5365d03, 0xb1f740b4
};


//
// Calculate CRC32 for buffer of specified length
//

DWORD CalculateCRC32(const unsigned char *data,DWORD nbytes)
{
	register DWORD crc,len;
   register const unsigned char *ptr;

INIT_CHECK_MEMORY(main);

	crc=0;
	for(len=nbytes,ptr=data;len>0;ptr++,len--)
      crc=(crc << 8)^crctab[(crc >> 24)^(*ptr)];

	// Include the length of the data block
	for (len=nbytes;len!=0;len>>=8)
      crc=(crc << 8)^crctab[(crc >> 24)^(len & 0xFF)];

CHECK_MEMORY(main,"GetPdhErrorText","end2");

	return ~crc;
}


//
// Get error text for PDH functions
//

char *GetPdhErrorText(DWORD error)
{
   char *msgBuf;
   static char staticBuffer[1024];

INIT_CHECK_MEMORY(main);

   if (FormatMessage(FORMAT_MESSAGE_ALLOCATE_BUFFER | 
                     FORMAT_MESSAGE_FROM_HMODULE | 
                     FORMAT_MESSAGE_IGNORE_INSERTS,
                     GetModuleHandle("PDH.DLL"),error,
                     MAKELANGID(LANG_NEUTRAL,SUBLANG_DEFAULT), // Default language
                     (LPSTR)&msgBuf,0,NULL)>0)
   {
      msgBuf[strcspn(msgBuf,"\r\n")]=0;
      strncpy(staticBuffer,msgBuf,1023);
      LocalFree(msgBuf);
CHECK_MEMORY(main,"GetPdhErrorText","end1");
      return staticBuffer;
   }
   else
   {
CHECK_MEMORY(main,"GetPdhErrorText","end2");
      return GetSystemErrorText(error);
   }
}


//
// Get performance counter name by index
//

char *GetCounterName(DWORD index)
{
	PERFCOUNTER	*counterName;
	DWORD	dwSize;
	char hostname[MAX_COMPUTERNAME_LENGTH+3];

	counterName=perfCounterList;
	while(counterName!=NULL)
	{
		if (counterName->pdhIndex == index)
		   break;
		counterName = counterName->next;
	}
	if (counterName == NULL)
	{
		counterName = (PERFCOUNTER *)malloc(sizeof(PERFCOUNTER));
		memset(counterName, 0, sizeof(PERFCOUNTER));
		counterName->pdhIndex = index;
		counterName->next = perfCounterList;

		sprintf(hostname, "\\\\");
		dwSize = MAX_COMPUTERNAME_LENGTH+1;
		if(GetComputerName((char *) &hostname + 2, &dwSize)==0)
		{
			WriteLog(MSG_GET_COMPUTER_NAME_FAILED,EVENTLOG_ERROR_TYPE,
				"s",GetSystemErrorText(GetLastError()));
		}

		dwSize = MAX_COUNTER_NAME;
		if (PdhLookupPerfNameByIndex((char *)&hostname, index, (char *)&counterName->name, &dwSize)==ERROR_SUCCESS)
		{
			perfCounterList = counterName;
		} 
		else 
		{
			WriteLog(MSG_LOOKUP_FAILED,EVENTLOG_ERROR_TYPE,
				"s", GetSystemErrorText(GetLastError()));
			free(counterName);
			return "UnknownPerformanceCounter";
		}
	}

	return (char *)&counterName->name;
}

void FreeCounterList(void)
{
	PERFCOUNTER	*curr;
	PERFCOUNTER	*next;
		
	next = perfCounterList;
	while(next!=NULL)
	{
		curr = next;
		next = curr->next;
		free(curr);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: get_param                                                        *
 *                                                                            *
 * Purpose: return parameter by index (num) from parameter list (param)       *
 *                                                                            *
 * Parameters:                                                                *
 * 	param  - parameter list                                               *
 *      num    - requested parameter index                                    *
 *      buf    - pointer og output buffer                                     *
 *      maxlem - size of output buffer                                        *
 *                                                                            *
 * Return value: 1 - on error                                                 *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:  delimeter vor parameters is ','                                 *
 *                                                                            *
 ******************************************************************************/
int	get_param(const char *param, int num, char *buf, int maxlen)
{
	char	tmp[MAX_STRING_LEN];
	char	*s;
	int	ret = 1;
	int	i = 0;
	int	idx = 0;

	strscpy(tmp,param);

	s = &tmp[0];
	
	for(i=0; tmp[i] != '\0'; i++)
	{
		if(tmp[i] == ',')
		{
			idx++;
			if(idx == num)
			{
				tmp[i]='\0';
				strncpy(buf, s, maxlen);
				tmp[i]=','; /* restore source string */
				ret = 0;
				break;
				
			}
			s = &tmp[i+1];
		}
	}

	if(ret != 0)
	{
		idx++;
		if(idx == num)
		{
			strncpy(buf, s, maxlen);
			ret = 0;
		}
	}
	
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: num_param                                                        *
 *                                                                            *
 * Purpose: calculate count of parameters from parameter list (param)         *
 *                                                                            *
 * Parameters:                                                                *
 * 	param  - parameter list                                               *
 *                                                                            *
 * Return value: count of parameters                                          *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:  delimeter vor parameters is ','                                 *
 *                                                                            *
 ******************************************************************************/
int	num_param(const char *param)
{
	int	i;
	int	ret = 1;

	if(param == NULL) 
		return 0;
	
	for(i=0;param[i]!=0;i++)
	{
		if(param[i]==',')	ret++;
	}

	return ret;
}
