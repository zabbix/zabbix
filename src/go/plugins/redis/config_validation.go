package redis

import (
	"golang.zabbix.com/sdk/plugin/comms"
)

const (
	forbidden fieldRequirement = iota
	required
	optional
)

type fieldRequirement int

// gives a map which describes what field combinations are allowed depending on TLSConnect value.
func getTLSValidationRules(tlsConnect string) map[comms.TLSConfigSetting]fieldRequirement {
	switch comms.TLSConnectionType(tlsConnect) {
	case comms.Disabled, comms.Required:
		return map[comms.TLSConfigSetting]fieldRequirement{
			comms.TLSCAFile:   forbidden,
			comms.TLSCertFile: forbidden,
			comms.TLSKeyFile:  forbidden,
		}
	case comms.VerifyCA:
		return map[comms.TLSConfigSetting]fieldRequirement{
			comms.TLSCAFile:   required,
			comms.TLSCertFile: forbidden,
			comms.TLSKeyFile:  forbidden,
		}
	case comms.VerifyFull:
		return map[comms.TLSConfigSetting]fieldRequirement{
			comms.TLSCAFile:   required,
			comms.TLSCertFile: required,
			comms.TLSKeyFile:  required,
		}
	default:
		return map[comms.TLSConfigSetting]fieldRequirement{}
	}
}
