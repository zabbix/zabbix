package TLD_constants;

use strict;
use warnings;
use base 'Exporter';

use constant true => 1;
use constant false => 0;

use constant TIME_MINUTE => 60;
use constant TIME_HOUR => TIME_MINUTE * 60;
use constant TIME_DAY => TIME_HOUR * 24;


use constant LINUX_TEMPLATEID => 10001;

use constant VALUE_TYPE_AVAIL => 0;
use constant VALUE_TYPE_PERC => 1;
use constant VALUE_TYPE_NUM => 2;
use constant VALUE_TYPE_DOUBLE => 3;

use constant HOST_STATUS_MONITORED => 0;
use constant HOST_STATUS_NOT_MONITORED => 1;

use constant HOST_STATUS_PROXY_ACTIVE => 5;
use constant HOST_STATUS_PROXY_PASSIVE => 6;

use constant ITEM_STATUS_ACTIVE => 0;
use constant ITEM_STATUS_DISABLED => 1;

use constant TRIGGER_STATUS_ENABLED => 0;
use constant TRIGGER_STATUS_DISABLED => 1;

use constant TRIGGER_SEVERITY_NOT_CLASSIFIED => 0;

use constant INTERFACE_TYPE_AGENT => 1;

use constant ITEM_VALUE_TYPE_FLOAT => 0;
use constant ITEM_VALUE_TYPE_STR =>   1;
use constant ITEM_VALUE_TYPE_LOG =>   2;
use constant ITEM_VALUE_TYPE_UINT64=> 3;
use constant ITEM_VALUE_TYPE_TEXT =>  4;

use constant ITEM_TYPE_ZABBIX =>                     0;
use constant ITEM_TYPE_TRAPPER =>                    2;
use constant ITEM_TYPE_SIMPLE =>                     3;
use constant ITEM_TYPE_INTERNAL =>           5;
use constant ITEM_TYPE_ZABBIX_ACTIVE =>      7;
use constant ITEM_TYPE_AGGREGATE =>          8;
use constant ITEM_TYPE_EXTERNAL =>           10;
use constant ITEM_TYPE_CALCULATED =>         15;

use constant ZBX_EC_INTERNAL          => -1;   # internal error (general)
use constant ZBX_EC_DNS_NS_NOREPLY    => -200; # no reply from Name Server
use constant ZBX_EC_DNS_NS_ERRREPLY   => -201; # invalid reply from Name Server
use constant ZBX_EC_DNS_NS_NOTS       => -202; # no UNIX timestamp
use constant ZBX_EC_DNS_NS_ERRTS      => -203; # invalid UNIX timestamp
use constant ZBX_EC_DNS_NS_ERRSIG     => -204; # DNSSEC error
use constant ZBX_EC_DNS_RES_NOREPLY   => -205; # no reply from resolver
use constant ZBX_EC_DNS_RES_NOADBIT   => -206; # no AD bit in the answer from resolver
use constant ZBX_EC_RDDS43_NOREPLY    => -200; # no reply from RDDS43 server
use constant ZBX_EC_RDDS43_NONS       => -201; # Whois server returned no NS
use constant ZBX_EC_RDDS43_NOTS       => -202; # no Unix timestamp
use constant ZBX_EC_RDDS43_ERRTS      => -203; # invalid Unix timestamp
use constant ZBX_EC_RDDS80_NOREPLY    => -204; # no reply from RDDS80 server
use constant ZBX_EC_RDDS_ERRRES       => -205; # cannot resolve a Whois host
use constant ZBX_EC_RDDS80_NOHTTPCODE => -206; # no HTTP response code in response from RDDS80 server
use constant ZBX_EC_RDDS80_EHTTPCODE  => -207; # invalid HTTP response code in response from RDDS80 server
use constant ZBX_EC_EPP_NO_IP         => -200; # IP is missing for EPP server
use constant ZBX_EC_EPP_CONNECT       => -201; # cannot connect to EPP server
use constant ZBX_EC_EPP_CRYPT         => -202; # invalid certificate or private key
use constant ZBX_EC_EPP_FIRSTTO       => -203; # first message timeout
use constant ZBX_EC_EPP_FIRSTINVAL    => -204; # first message is invalid
use constant ZBX_EC_EPP_LOGINTO       => -205; # LOGIN command timeout
use constant ZBX_EC_EPP_LOGININVAL    => -206; # invalid reply to LOGIN command
use constant ZBX_EC_EPP_UPDATETO      => -207; # UPDATE command timeout
use constant ZBX_EC_EPP_UPDATEINVAL   => -208; # invalid reply to UPDATE command
use constant ZBX_EC_EPP_INFOTO        => -209; # INFO command timeout
use constant ZBX_EC_EPP_INFOINVAL     => -210; # invalid reply to INFO command

use constant RSM_ROLLWEEK_THRESHOLDS => '0,5,10,25,50,75,100';

use constant cfg_default_rdds_ns_string => 'Name Server:';

use constant rsm_host => 'rsm'; # global config history
use constant rsm_group => 'rsm';

use constant rsm_value_mappings => {'rsm_dns_result' => 13,
                                'rsm_probe' => 14,
                                'rsm_rdds_result' => 15,
                                'rsm_avail' => 16,
                                'rsm_rdds_probe_result' => 18,
                                'rsm_epp_result' => 19};

use constant rsm_trigger_rollweek_thresholds => { '1' => {'threshold' => '10', 'priority' => 2},
                                    '2' => {'threshold' => '25', 'priority' => 3},
                                    '3' => {'threshold' => '50', 'priority' => 3},
                                    '4' => {'threshold' => '75', 'priority' => 4},
                                    '5' => {'threshold' => '100', 'priority' => 5}
                                  };

use constant cfg_global_macros => {'{$RSM.DNS.UDP.DELAY}' => '', '{$RSM.DNS.TCP.DELAY}' => '', '{$RSM.RDDS.DELAY}' => '', '{$RSM.EPP.DELAY}' => ''};

use constant cfg_probe_status_delay => 60;

use constant APP_SLV_MONTHLY => 'SLV monthly';
use constant APP_SLV_ROLLWEEK => 'SLV rolling week';
use constant APP_SLV_PARTTEST => 'SLV particular test';

use constant TLD_TYPE_G => 'gTLD';
use constant TLD_TYPE_CC => 'ccTLD';
use constant TLD_TYPE_OTHER => 'otherTLD';
use constant TLD_TYPE_TEST => 'testTLD';

use constant rsm_rdds_interfaces => {
	'RDDS43' => {'option' => 'rdds43-servers', 'keypart' => '43', 'update' => true},
	'RDDS80' => {'option' => 'rdds80-servers', 'keypart' => '80', 'update' => false},
	'RDAP' => {'option' => 'rdap-servers', 'keypart' => 'rdap', 'update' => true}
};

use constant rsm_rdds_probe_result => [
	{},												# 0 - down
	{JSON_INTERFACE_RDDS43 => true, JSON_INTERFACE_RDDS80 => true, JSON_INTERFACE_RDAP => true},	# 1 - up
	{JSON_INTERFACE_RDDS43 => true},								# 2 - only 43
	{JSON_INTERFACE_RDDS80 => true},								# 3 - only 80
	{JSON_INTERFACE_RDAP => true},									# 4 - only RDAP
	{JSON_INTERFACE_RDDS80 => true, JSON_INTERFACE_RDAP => true},					# 5 - without 43
	{JSON_INTERFACE_RDDS43 => true, JSON_INTERFACE_RDAP => true},					# 6 - without 80
	{JSON_INTERFACE_RDDS43 => true, JSON_INTERFACE_RDDS80 => true}					# 7 - without RDAP
];

our @EXPORT_OK = qw(true false TIME_MINUTE TIME_HOUR TIME_DAY LINUX_TEMPLATEID VALUE_TYPE_AVAIL VALUE_TYPE_PERC VALUE_TYPE_NUM VALUE_TYPE_DOUBLE
		    ZBX_EC_INTERNAL ZBX_EC_DNS_NS_NOREPLY ZBX_EC_DNS_NS_ERRREPLY ZBX_EC_DNS_NS_NOTS ZBX_EC_DNS_NS_ERRTS ZBX_EC_DNS_NS_ERRTS
                    ZBX_EC_DNS_NS_ERRSIG ZBX_EC_DNS_RES_NOREPLY ZBX_EC_DNS_RES_NOADBIT ZBX_EC_RDDS43_NOREPLY ZBX_EC_RDDS43_NONS ZBX_EC_RDDS43_NOTS
                    ZBX_EC_RDDS43_ERRTS ZBX_EC_RDDS80_NOREPLY ZBX_EC_RDDS_ERRRES ZBX_EC_RDDS80_NOHTTPCODE ZBX_EC_RDDS80_EHTTPCODE ZBX_EC_EPP_NO_IP
                    ZBX_EC_EPP_CONNECT ZBX_EC_EPP_CRYPT ZBX_EC_EPP_FIRSTTO ZBX_EC_EPP_FIRSTINVAL ZBX_EC_EPP_LOGINTO ZBX_EC_EPP_LOGININVAL
                    ZBX_EC_EPP_UPDATETO ZBX_EC_EPP_UPDATETO ZBX_EC_EPP_UPDATEINVAL ZBX_EC_EPP_INFOTO ZBX_EC_EPP_INFOINVAL
		    RSM_ROLLWEEK_THRESHOLDS rsm_host rsm_group rsm_value_mappings cfg_probe_status_delay
		    cfg_default_rdds_ns_string rsm_trigger_rollweek_thresholds cfg_global_macros
		    HOST_STATUS_MONITORED HOST_STATUS_NOT_MONITORED HOST_STATUS_PROXY_ACTIVE HOST_STATUS_PROXY_PASSIVE ITEM_STATUS_ACTIVE
		    ITEM_STATUS_DISABLED INTERFACE_TYPE_AGENT TRIGGER_STATUS_DISABLED TRIGGER_STATUS_ENABLED TRIGGER_SEVERITY_NOT_CLASSIFIED
		    ITEM_VALUE_TYPE_FLOAT ITEM_VALUE_TYPE_STR ITEM_VALUE_TYPE_LOG ITEM_VALUE_TYPE_UINT64 ITEM_VALUE_TYPE_TEXT
		    ITEM_TYPE_ZABBIX ITEM_TYPE_TRAPPER ITEM_TYPE_SIMPLE ITEM_TYPE_INTERNAL ITEM_TYPE_ZABBIX_ACTIVE ITEM_TYPE_AGGREGATE ITEM_TYPE_EXTERNAL ITEM_TYPE_CALCULATED
		    APP_SLV_MONTHLY APP_SLV_ROLLWEEK APP_SLV_PARTTEST TLD_TYPE_G TLD_TYPE_CC TLD_TYPE_OTHER TLD_TYPE_TEST rsm_rdds_interfaces rsm_rdds_probe_result);

our %EXPORT_TAGS = ( general => [ qw(true false TIME_MINUTE TIME_HOUR TIME_DAY) ],
		     templates => [ qw(LINUX_TEMPLATEID) ],
		     value_types => [ qw(VALUE_TYPE_AVAIL VALUE_TYPE_PERC VALUE_TYPE_NUM VALUE_TYPE_DOUBLE) ],
		     ec => [ qw(ZBX_EC_INTERNAL ZBX_EC_DNS_NS_NOREPLY ZBX_EC_DNS_NS_ERRREPLY ZBX_EC_DNS_NS_NOTS ZBX_EC_DNS_NS_ERRTS ZBX_EC_DNS_NS_ERRTS
				ZBX_EC_DNS_NS_ERRSIG ZBX_EC_DNS_RES_NOREPLY ZBX_EC_DNS_RES_NOADBIT ZBX_EC_RDDS43_NOREPLY ZBX_EC_RDDS43_NONS ZBX_EC_RDDS43_NOTS
				ZBX_EC_RDDS43_ERRTS ZBX_EC_RDDS80_NOREPLY ZBX_EC_RDDS_ERRRES ZBX_EC_RDDS80_NOHTTPCODE ZBX_EC_RDDS80_EHTTPCODE ZBX_EC_EPP_NO_IP
				ZBX_EC_EPP_CONNECT ZBX_EC_EPP_CRYPT ZBX_EC_EPP_FIRSTTO ZBX_EC_EPP_FIRSTINVAL ZBX_EC_EPP_LOGINTO ZBX_EC_EPP_LOGININVAL
				ZBX_EC_EPP_UPDATETO ZBX_EC_EPP_UPDATETO ZBX_EC_EPP_UPDATEINVAL ZBX_EC_EPP_INFOTO ZBX_EC_EPP_INFOINVAL) ],
		    rsm => [ qw(RSM_ROLLWEEK_THRESHOLDS rsm_host rsm_group) ],
		    api => [ qw(HOST_STATUS_MONITORED HOST_STATUS_NOT_MONITORED HOST_STATUS_PROXY_ACTIVE HOST_STATUS_PROXY_PASSIVE ITEM_STATUS_ACTIVE
				ITEM_STATUS_DISABLED INTERFACE_TYPE_AGENT
				ITEM_VALUE_TYPE_FLOAT ITEM_VALUE_TYPE_STR ITEM_VALUE_TYPE_LOG ITEM_VALUE_TYPE_UINT64 ITEM_VALUE_TYPE_TEXT
				ITEM_TYPE_ZABBIX ITEM_TYPE_TRAPPER ITEM_TYPE_SIMPLE ITEM_TYPE_INTERNAL ITEM_TYPE_ZABBIX_ACTIVE
				ITEM_TYPE_AGGREGATE ITEM_TYPE_EXTERNAL ITEM_TYPE_CALCULATED
				TRIGGER_STATUS_DISABLED TRIGGER_STATUS_ENABLED TRIGGER_SEVERITY_NOT_CLASSIFIED rsm_rdds_probe_result)],
		    config => [ qw(cfg_probe_status_delay cfg_default_rdds_ns_string rsm_value_mappings rsm_trigger_rollweek_thresholds
				    cfg_global_macros TLD_TYPE_G TLD_TYPE_CC TLD_TYPE_OTHER TLD_TYPE_TEST rsm_rdds_interfaces) ],
		    slv => [ qw(APP_SLV_MONTHLY APP_SLV_ROLLWEEK APP_SLV_PARTTEST) ] );

1;
