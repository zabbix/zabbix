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
          "   help            : Display help information\n"
          "   version         : Display version information\n\n"
          "And possible options are:\n"
          "   --config <file> : Specify alternate configuration file\n"
          "                     (default is C:\\zabbix_agentd.conf)\n\n");
}


//
// Parse command line
// Return value:
//    TRUE   if program execution should be continued
//    FALSE  otherwise
//

BOOL ParseCommandLine(int argc,char *argv[])
{
   int i;

   for(i=1;i<argc;i++)
   {
      if (!strcmp(argv[i],"help"))    // Display help and exit
      {
         Help();
         return FALSE;
      }
      else if (!strcmp(argv[i],"version"))    // Display version and exit
      {
         printf("Zabbix Win32 Agent Version " AGENT_VERSION " Build of " __DATE__ "\n");
         return FALSE;
      }
      else if (!strcmp(argv[i],"--config"))  // Config file
      {
         i++;
         strcpy(confFile,argv[i]);     // Next word should contain name of the config file
      }
      else if (!strcmp(argv[i],"standalone"))  // Run in standalone mode
      {
         optStandalone=TRUE;
         return TRUE;
      }
      else if (!strcmp(argv[i],"install"))
      {
         char path[MAX_PATH],*ptr;

         ptr=strrchr(argv[0],'\\');
         if (ptr!=NULL)
            ptr++;
         else
            ptr=argv[0];

         _fullpath(path,ptr,255);

         if (stricmp(&path[strlen(path)-4],".exe"))
            strcat(path,".exe");

         ZabbixCreateService(path);
         return FALSE;
      }
      else if (!strcmp(argv[i],"remove"))
      {
         ZabbixRemoveService();
         return FALSE;
      }
      else if (!strcmp(argv[i],"start"))
      {
         ZabbixStartService();
         return FALSE;
      }
      else if (!strcmp(argv[i],"stop"))
      {
         ZabbixStopService();
         return FALSE;
      }
      else if (!strcmp(argv[i],"check-config"))
      {
         optStandalone=TRUE;
         printf("Checking configuration file:\n\n");
         ReadConfig();
         return FALSE;
      }
      else
      {
         printf("ERROR: Invalid command line argument\n\n");
         Help();
         return FALSE;
      }
   }

   return TRUE;
}


//
// Get system error string by call to FormatMessage
//

char *GetSystemErrorText(DWORD error)
{
   char *msgBuf;
   static char staticBuffer[1024];

   FormatMessage(FORMAT_MESSAGE_ALLOCATE_BUFFER | 
                 FORMAT_MESSAGE_FROM_SYSTEM | 
                 FORMAT_MESSAGE_IGNORE_INSERTS,
                 NULL,error,
                 MAKELANGID(LANG_NEUTRAL,SUBLANG_DEFAULT), // Default language
                 (LPSTR)&msgBuf,0,NULL);

   msgBuf[strcspn(msgBuf,"\r\n")]=0;
   strncpy(staticBuffer,msgBuf,1023);
   LocalFree(msgBuf);

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

   for(ptr=line;(*ptr==' ')||(*ptr=='\t');ptr++);  // Skip initial spaces
   // Copy word to buffer
   for(bptr=buffer;(*ptr!=' ')&&(*ptr!='\t')&&(*ptr!=0);ptr++,bptr++)
      *bptr=*ptr;
   *bptr=0;
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
            while(1)
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

   return *SPtr==0 ? TRUE : FALSE;
}


//
// Strip whitespaces and tabs off the string
//

void StrStrip(char *str)
{
   int i;

   for(i=0;(str[i]!=0)&&((str[i]==' ')||(str[i]=='\t'));i++);
   if (i>0)
      memmove(str,&str[i],strlen(&str[i])+1);
   for(i=strlen(str)-1;(i>=0)&&((str[i]==' ')||(str[i]=='\t'));i--);
   str[i+1]=0;
}
