/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

package mock

import _ "embed"

var (
	//go:embed outputs/version_valid.json
	OutputVersionValid []byte

	//go:embed outputs/version_invalid.json
	OutputVersionInvalid []byte

	//go:embed outputs/scan.json
	OutputScan []byte

	//go:embed outputs/env_mac_scan_basic.json
	OutputEnvMacScanBasic []byte

	//go:embed outputs/env_mac_scan_raid.json
	OutputEnvMacScanRaid []byte

	//go:embed outputs/scan_type_sat.json
	OutputScanTypeSAT []byte

	//go:embed outputs/scan_basic.json
	OutputScanBasic []byte

	//go:embed outputs/all_disc_info_csmi.json
	OutputAllDiscInfoCSMI []byte

	//go:embed outputs/all_disc_info_sda.json
	OutputAllDiscInfoSDA []byte

	//go:embed outputs/all_disc_info_mac.json
	OutputAllDiscInfoMac []byte

	//go:embed outputs/env_1_scan_basic.json
	OutputEnv1ScanBasic []byte

	//go:embed outputs/env_1_scan_raid.json
	OutputEnv1ScanRaid []byte

	//go:embed outputs/env_1_get_raid_sda_3ware_0.json
	OutputEnv1GetRaidSda3Ware0 []byte

	//go:embed outputs/env_1_get_raid_sda_sat.json
	OutputEnv1GetRaidSdaSat []byte

	//go:embed outputs/env_1_get_raid_sda_areca_1.json
	OutputEnv1GetRaidSdaAreca1 []byte

	//go:embed outputs/env_1_get_raid_sda_cciss_0.json
	OutputEnv1GetRaidSdaCciss0 []byte
)
