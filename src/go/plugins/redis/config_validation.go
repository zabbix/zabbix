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

package redis

import (
	"golang.zabbix.com/sdk/plugin/comms"
	"golang.zabbix.com/sdk/tlsconfig"
)

const (
	forbidden fieldRequirement = iota
	required
	optional
)

type fieldRequirement int

// gives a map which describes what field combinations are allowed depending on TLSConnect value.
func getValidationRules(tlsConnect tlsconfig.TLSConnectionType) map[comms.ConfigSetting]fieldRequirement {
	// common rules.
	rules := map[comms.ConfigSetting]fieldRequirement{
		comms.URI:      optional,
		comms.User:     optional,
		comms.Password: optional,

		comms.TLSCertFile: optional,
		comms.TLSKeyFile:  optional,
	}

	switch tlsConnect {
	case tlsconfig.Disabled:
		rules[comms.TLSCAFile] = forbidden

		rules[comms.TLSKeyFile] = forbidden
		rules[comms.TLSCertFile] = forbidden

	case tlsconfig.Required:
		rules[comms.TLSCAFile] = forbidden

	case tlsconfig.VerifyCA, tlsconfig.VerifyFull:
		rules[comms.TLSCAFile] = required
	default:
		return map[comms.ConfigSetting]fieldRequirement{}
	}

	return rules
}

// Field groups that must come from the same source (session or defaults).
func getSourceConsistencyRules() [][]comms.ConfigSetting {
	return [][]comms.ConfigSetting{
		{comms.TLSCertFile, comms.TLSKeyFile}, // Cert and key must come from the same source
	}
}
