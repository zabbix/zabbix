#ifndef MON_MONAGENT_H
#define MON_MONAGENT_H

#define COMMAND struct command_type
COMMAND
{
        char    *key;
        void    *function;
        char    *parameter;
};

#endif
