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
func getValidationRules(tlsConnect comms.TLSConnectionType) map[comms.ConfigSetting]fieldRequirement {
	switch tlsConnect {
	case comms.Disabled, comms.Required:
		return map[comms.ConfigSetting]fieldRequirement{
			comms.URI:      required,
			comms.User:     optional,
			comms.Password: required,

			comms.TLSCAFile:   forbidden,
			comms.TLSCertFile: forbidden,
			comms.TLSKeyFile:  forbidden,
		}
	case comms.VerifyCA:
		return map[comms.ConfigSetting]fieldRequirement{
			comms.URI:      required,
			comms.User:     optional,
			comms.Password: required,

			comms.TLSCAFile:   required,
			comms.TLSCertFile: forbidden,
			comms.TLSKeyFile:  forbidden,
		}
	case comms.VerifyFull:
		return map[comms.ConfigSetting]fieldRequirement{
			comms.URI:      required,
			comms.User:     optional,
			comms.Password: required,

			comms.TLSCAFile:   required,
			comms.TLSCertFile: required,
			comms.TLSKeyFile:  required,
		}
	default:
		return map[comms.ConfigSetting]fieldRequirement{}
	}
}

// Field groups that must come from the same source (session or defaults).
func getSourceConsistencyRules() [][]comms.ConfigSetting {
	return [][]comms.ConfigSetting{
		{comms.TLSCertFile, comms.TLSKeyFile}, // Cert and key must come from the same source
	}
}
