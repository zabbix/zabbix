/*
** Copyright (C) 2001-2026 Zabbix SIA
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

package smart

import (
	"encoding/json"
	"errors"
	"testing"
	"time"

	"github.com/google/go-cmp/cmp"
	"golang.zabbix.com/sdk/errs"
	"golang.zabbix.com/sdk/log"
	"golang.zabbix.com/sdk/plugin"
)

func TestPlugin_execute(t *testing.T) {
	t.Parallel()

	//nolint:lll
	sampleFailedAllSmartInfoScan := []byte(
		`{
				"json_format_version": [1, 0],
				"smartctl": {
				  "version": [7, 3],
				  "svn_revision": "5338",
				  "platform_info": "x86_64-w64-mingw32-2016-1607",
				  "build_info": "(sf-7.3-1)",
				  "argv": ["smartctl", "-a", "/dev/sda", "-d", "3ware,0", "-j"],
				  "messages": [
					{
					  "string": "/dev/sda: Unknown device type '3ware,0'",
					  "severity": "error"
					},
					{
					  "string": "=======> VALID ARGUMENTS ARE: ata, scsi[+TYPE], nvme[,NSID], sat[,auto][,N][+TYPE], usbcypress[,X], usbjmicron[,p][,x][,N], usbprolific, usbsunplus, sntasmedia, sntjmicron[,NSID], sntrealtek, intelliprop,N[+TYPE], jmb39x[-q],N[,sLBA][,force][+TYPE], jms56x,N[,sLBA][,force][+TYPE], aacraid,H,L,ID, areca,N[/E], auto, test <=======",
					  "severity": "error"
					}
				  ],
				  "exit_status": 1
				},
				"local_time": {
				  "time_t": 1663357978,
				  "asctime": "Fri Sep 16 22:52:58 2022 BST"
				}
			  }`,
	)

	type args struct {
		byID       bool
		jsonRunner bool
	}

	type expectation struct {
		args []string
		err  error
		out  []byte
	}

	tests := []struct {
		name         string
		args         args
		expectations []expectation
		wantRunner   *runner
		wantErr      bool
	}{
		{
			name: "+validBasicDevices",
			args: args{
				jsonRunner: false,
			},
			expectations: []expectation{
				{
					args: []string{"--scan", "-j"},
					err:  nil,
					//nolint:lll
					out: []byte(`{
												"json_format_version": [1, 0],
												"smartctl": {
													"version": [7, 1],
													"svn_revision": "5022",
													"platform_info": "x86_64-w64-mingw32-w10-b19045",
													"build_info": "(sf-7.1-1)"
												},
												"devices": [
													{
													"name": "/dev/sda",
													"info_name": "/dev/sda",
													"type": "nvme",
													"protocol": "NVMe"
													},
													{
													"name": "IOService:/AppleARMPE/arm-io@10F00000/AppleT811xIO/ans@77400000/AppleASCWrapV4/iop-ans-nub/RTBuddy(ANS2)/RTBuddyService/AppleANS3NVMeController/NS_01@1",
													"info_name": "IOService:/AppleARMPE/arm-io@10F00000/AppleT811xIO/ans@77400000/AppleASCWrapV4/iop-ans-nub/RTBuddy(ANS2)/RTBuddyService/AppleANS3NVMeController/NS_01@1",
													"type": "nvme",
													"protocol": "NVMe"
													}
												]
												}
											`),
				},
				{
					args: []string{"--scan", "-d", "sat", "-j"},
					err:  nil,
					out:  []byte(`{}`),
				},
				{
					args: []string{"-a", "/dev/sda", "-j"},
					err:  nil,
					out:  readControllerFixture(t, "device/all_info_sda.json"),
				},
				{
					args: []string{
						"-a",
						"IOService:/AppleARMPE/arm-io@10F00000/AppleT811xIO/ans@77400000/AppleASCWrapV4/iop-ans-nub/RTBuddy(ANS2)/RTBuddyService/AppleANS3NVMeController/NS_01@1", //nolint:lll
						"-j",
					},
					err: nil,
					out: readControllerFixture(t, "device/all_info_macos.json"),
				},
			},
			wantRunner: &runner{
				jsonDevices: nil,
				devices: map[string]deviceParser{
					"/dev/sda": {
						ModelName:    "SAMSUNG MZVL21T0HCLR-00BH1",
						SerialNumber: "S641NX0T509005",
						Info: deviceInfo{
							Name:     "/dev/sda",
							InfoName: "/dev/sda",
							DevType:  "nvme",
							name:     "/dev/sda",
						},
						Smartctl: smartctlField{
							Version: []int{7, 1},
						},
						SmartStatus:     &smartStatus{SerialNumber: true},
						SmartAttributes: smartAttributes{},
					},
					"IOService:/AppleARMPE/arm-io@10F00000/AppleT811xIO/ans@77400000/AppleASCWrapV4/iop-ans-nub/RTBuddy(ANS2)/RTBuddyService/AppleANS3NVMeController/NS_01@1": { //nolint:lll
						ModelName:    "APPLE SSD AP0512Z",
						SerialNumber: "0ba02202c4bc1a1e",
						Info: deviceInfo{
							Name:     "IOService:/AppleARMPE/arm-io@10F00000/AppleT811xIO/ans@77400000/AppleASCWrapV4/iop-ans-nub/RTBuddy(ANS2)/RTBuddyService/AppleANS3NVMeController/NS_01@1", //nolint:lll
							InfoName: "IOService:/AppleARMPE/arm-io@10F00000/AppleT811xIO/ans@77400000/AppleASCWrapV4/iop-ans-nub/RTBuddy(ANS2)/RTBuddyService/AppleANS3NVMeController/NS_01@1", //nolint:lll
							DevType:  "nvme",
							name:     "IOService:/AppleARMPE/arm-io@10F00000/AppleT811xIO/ans@77400000/AppleASCWrapV4/iop-ans-nub/RTBuddy(ANS2)/RTBuddyService/AppleANS3NVMeController/NS_01@1", //nolint:lll
						},
						Smartctl: smartctlField{
							Version:    []int{7, 4},
							ExitStatus: 4,
							Messages: []message{
								{
									Str: "Read 1 entries from Error Information Log failed: GetLogPage failed: system=0x38, sub=0x0, code=745", //nolint:lll
								},
							},
						},
						SmartStatus:     &smartStatus{SerialNumber: true},
						SmartAttributes: smartAttributes{},
					},
				},
			},
			wantErr: false,
		},
		{
			name: "+validByIDDevices",
			args: args{
				byID:       true,
				jsonRunner: false,
			},
			expectations: []expectation{
				{
					args: []string{"--scan", "-d", "by-id", "-j"},
					err:  nil,
					out: []byte(`{
												  "json_format_version": [
												    1,
												    0
												  ],
												  "smartctl": {
												    "version": [
												      7,
												      3
												    ],
												    "svn_revision": "5338",
												    "platform_info": "x86_64-linux-6.1.0-32-amd64",
												    "build_info": "(local build)",
												    "argv": [
												      "smartctl",
												      "--scan",
												      "-d",
												      "by-id",
												      "-j"
												    ],
												    "exit_status": 0
												  },
												  "devices": [
												    {
												      "name": "/dev/disk/by-id/ata-TOSHIBA_MQ01ABF050_X6GMTKX2T",
												      "info_name": "/dev/disk/by-id/ata-TOSHIBA_MQ01ABF050_X6GMTKX2T",
												      "type": "scsi",
												      "protocol": "SCSI"
												    }
												  ]
												}
											`),
				},
				{
					args: []string{"--scan", "-d", "by-id", "-d", "sat", "-j"},
					err:  nil,
					out:  []byte(`{}`),
				},
				{
					args: []string{"-a", "/dev/disk/by-id/ata-TOSHIBA_MQ01ABF050_X6GMTKX2T", "-j"},
					err:  nil,
					out:  readControllerFixture(t, "device/all_info_by_id.json"),
				},
			},
			wantRunner: &runner{
				jsonDevices: nil,
				devices: map[string]deviceParser{
					"/dev/disk/by-id/ata-TOSHIBA_MQ01ABF050_X6GMTKX2T": {
						ModelName:    "TOSHIBA MQ01ABF050",
						SerialNumber: "X6GMTKX2T",
						RotationRate: 5400,
						Info: deviceInfo{
							Name:     "/dev/disk/by-id/ata-TOSHIBA_MQ01ABF050_X6GMTKX2T",
							InfoName: "/dev/disk/by-id/ata-TOSHIBA_MQ01ABF050_X6GMTKX2T [SAT]",
							DevType:  "sat",
							name:     "/dev/disk/by-id/ata-TOSHIBA_MQ01ABF050_X6GMTKX2T",
						},
						Smartctl: smartctlField{
							ExitStatus: 64,
							Version:    []int{7, 3},
						},
						SmartStatus: &smartStatus{SerialNumber: true},
						SmartAttributes: smartAttributes{Table: []table{
							{
								Attrname: "Raw_Read_Error_Rate",
								ID:       1,
								Thresh:   50,
							},
							{
								Attrname: "Throughput_Performance",
								ID:       2,
								Thresh:   50,
							},
							{
								Attrname: "Spin_Up_Time",
								ID:       3,
								Thresh:   1,
							},
							{
								Attrname: "Start_Stop_Count",
								ID:       4,
							},
							{
								Attrname: "Reallocated_Sector_Ct",
								ID:       5,
								Thresh:   50,
							},
							{
								Attrname: "Seek_Error_Rate",
								ID:       7,
								Thresh:   50,
							},
							{
								Attrname: "Seek_Time_Performance",
								ID:       8,
								Thresh:   50,
							},
							{
								Attrname: "Power_On_Hours",
								ID:       9,
							},
							{
								Attrname: "Spin_Retry_Count",
								ID:       10,
								Thresh:   30,
							},
							{
								Attrname: "Power_Cycle_Count",
								ID:       12,
							},
							{
								Attrname: "G-Sense_Error_Rate",
								ID:       191,
							},
							{
								Attrname: "Power-Off_Retract_Count",
								ID:       192,
							},
							{
								Attrname: "Load_Cycle_Count",
								ID:       193,
							},
							{
								Attrname: "Temperature_Celsius",
								ID:       194,
							},
							{
								Attrname: "Reallocated_Event_Count",
								ID:       196,
							},
							{
								Attrname: "Current_Pending_Sector",
								ID:       197,
							},
							{
								Attrname: "Offline_Uncorrectable",
								ID:       198,
							},
							{
								Attrname: "UDMA_CRC_Error_Count",
								ID:       199,
							},
							{
								Attrname: "Disk_Shift",
								ID:       220,
							},
							{
								Attrname: "Loaded_Hours",
								ID:       222,
							},
							{
								Attrname: "Load_Retry_Count",
								ID:       223,
							},
							{
								Attrname: "Load_Friction",
								ID:       224,
							},
							{
								Attrname: "Load-in_Time",
								ID:       226,
							},
							{
								Attrname: "Head_Flying_Hours",
								ID:       240,
								Thresh:   1,
							},
						},
						},
					},
				},
			},
			wantErr: false,
		},
		{
			name: "+basicMac",
			args: args{
				jsonRunner: false,
			},
			expectations: []expectation{
				{
					args: []string{"--scan", "-j"},
					err:  nil,
					//nolint:lll
					out: []byte(`{
												"json_format_version": [1, 0],
												"smartctl": {
													"version": [7, 1],
													"svn_revision": "5022",
													"platform_info": "x86_64-w64-mingw32-w10-b19045",
													"build_info": "(sf-7.1-1)"
												},
												"devices": [
													{
													"name": "IOService:/AppleARMPE/arm-io@10F00000/AppleT811xIO/ans@77400000/AppleASCWrapV4/iop-ans-nub/RTBuddy(ANS2)/RTBuddyService/AppleANS3NVMeController/NS_01@1",
													"info_name": "IOService:/AppleARMPE/arm-io@10F00000/AppleT811xIO/ans@77400000/AppleASCWrapV4/iop-ans-nub/RTBuddy(ANS2)/RTBuddyService/AppleANS3NVMeController/NS_01@1",
													"type": "nvme",
													"protocol": "NVMe"
													}
												]
												}
											`),
				},
				{
					args: []string{"--scan", "-d", "sat", "-j"},
					err:  nil,
					out:  []byte(`{}`),
				},
				{
					args: []string{
						"-a",
						"IOService:/AppleARMPE/arm-io@10F00000/AppleT811xIO/ans@77400000/AppleASCWrapV4/iop-ans-nub/RTBuddy(ANS2)/RTBuddyService/AppleANS3NVMeController/NS_01@1", //nolint:lll
						"-j",
					},
					err: nil,
					out: readControllerFixture(t, "device/all_info_macos.json"),
				},
			},
			wantRunner: &runner{
				jsonDevices: nil,
				devices: map[string]deviceParser{
					"IOService:/AppleARMPE/arm-io@10F00000/AppleT811xIO/ans@77400000/AppleASCWrapV4/iop-ans-nub/RTBuddy(ANS2)/RTBuddyService/AppleANS3NVMeController/NS_01@1": { //nolint:lll
						ModelName:    "APPLE SSD AP0512Z",
						SerialNumber: "0ba02202c4bc1a1e",
						Info: deviceInfo{
							Name:     "IOService:/AppleARMPE/arm-io@10F00000/AppleT811xIO/ans@77400000/AppleASCWrapV4/iop-ans-nub/RTBuddy(ANS2)/RTBuddyService/AppleANS3NVMeController/NS_01@1", //nolint:lll
							InfoName: "IOService:/AppleARMPE/arm-io@10F00000/AppleT811xIO/ans@77400000/AppleASCWrapV4/iop-ans-nub/RTBuddy(ANS2)/RTBuddyService/AppleANS3NVMeController/NS_01@1", //nolint:lll
							DevType:  "nvme",
							name:     "IOService:/AppleARMPE/arm-io@10F00000/AppleT811xIO/ans@77400000/AppleASCWrapV4/iop-ans-nub/RTBuddy(ANS2)/RTBuddyService/AppleANS3NVMeController/NS_01@1", //nolint:lll
						},
						Smartctl: smartctlField{
							Version:    []int{7, 4},
							ExitStatus: 4,
							Messages: []message{
								{
									Str: "Read 1 entries from Error Information Log failed: GetLogPage failed: system=0x38, sub=0x0, code=745", //nolint:lll
								},
							},
						},
						SmartStatus:     &smartStatus{SerialNumber: true},
						SmartAttributes: smartAttributes{},
					},
				},
			},
			wantErr: false,
		},
		{
			name: "+basicJSONRunner",
			args: args{
				jsonRunner: true,
			},
			expectations: []expectation{
				{
					args: []string{"--scan", "-j"},
					err:  nil,
					out: []byte(`{
											"json_format_version": [1, 0],
											"smartctl": {
												"version": [7, 1],
												"svn_revision": "5022",
												"platform_info": "x86_64-w64-mingw32-w10-b19045",
												"build_info": "(sf-7.1-1)"
											},
											"devices": [
												{
												"name": "/dev/sda",
												"info_name": "/dev/sda",
												"type": "nvme",
												"protocol": "NVMe"
												}
											]
											}`),
				},
				{
					args: []string{"--scan", "-d", "sat", "-j"},
					err:  nil,
					out:  []byte(`{}`),
				},
				{
					args: []string{"-a", "/dev/sda", "-j"},
					err:  nil,
					out:  readControllerFixture(t, "device/all_info_sda.json"),
				},
			},
			wantRunner: &runner{
				jsonDevices: map[string]jsonDevice{
					"/dev/sda": {
						serialNumber: "S641NX0T509005",
						jsonData:     string(readControllerFixture(t, "device/all_info_sda.json")),
					},
				},
				devices: nil,
			},
			wantErr: false,
		},
		{
			name: "+HBAWithSAS1",
			args: args{
				jsonRunner: false,
			},
			expectations: []expectation{
				{
					args: []string{"--scan", "-j"},
					err:  nil,
					out:  []byte(`{}`),
				},
				{
					args: []string{"--scan", "-d", "sat", "-j"},
					err:  nil,
					out: []byte(`{
											"json_format_version": [1, 0],
											"smartctl": {
												"version": [7, 1],
												"svn_revision": "5022",
												"platform_info": "x86_64-w64-mingw32-w10-b19045",
												"build_info": "(sf-7.1-1)"
											},
											"devices": [
												{
												"name": "/dev/sda",
												"info_name": "/dev/sda",
												"type": "sat",
												"protocol": "sat"
												}
											]}`),
				},
				{
					args: []string{
						"-a", "/dev/sda", "-d", "3ware,0", "-j",
					},
					out: sampleFailedAllSmartInfoScan,
				},
				{
					args: []string{
						"-a", "/dev/sda", "-d", "areca,1", "-j",
					},
					out: sampleFailedAllSmartInfoScan,
				},
				{
					args: []string{
						"-a", "/dev/sda", "-d", "cciss,0", "-j",
					},
					out: sampleFailedAllSmartInfoScan,
				},
				{
					args: []string{
						"-a", "/dev/sda", "-d", "sat", "-j",
					},
					out: sampleFailedAllSmartInfoScan,
				},
				{
					args: []string{
						"-a", "/dev/sda", "-d", "scsi", "-j",
					},
					out: readControllerEnvironment(t, "HBA_with_SAS_1").
						AllSmartInfoScans.get(t, "-a /dev/sda -d scsi -j"),
				},
			},
			wantRunner: &runner{
				jsonDevices: nil,
				devices: map[string]deviceParser{
					"/dev/sda scsi": {
						SerialNumber: "S5G1NC0W102239",
						Info: deviceInfo{
							Name:     "/dev/sda scsi",
							InfoName: "/dev/sda",
							DevType:  "scsi",
							name:     "/dev/sda",
							raidType: "scsi",
						},
						Smartctl:    smartctlField{Version: []int{7, 3}},
						SmartStatus: &smartStatus{SerialNumber: true},
					},
				},
			},
			wantErr: false,
		},
		{
			name: "+validRAID",
			args: args{
				jsonRunner: false,
			},
			expectations: []expectation{
				{
					args: []string{"--scan", "-j"},
					err:  nil,
					out:  []byte(`{}`),
				},
				{
					args: []string{"--scan", "-d", "sat", "-j"},
					err:  nil,
					out: []byte(`{
											"json_format_version": [1, 0],
											"smartctl": {
												"version": [7, 1],
												"svn_revision": "5022",
												"platform_info": "x86_64-w64-mingw32-w10-b19045",
												"build_info": "(sf-7.1-1)"
											},
											"devices": [
												{
												"name": "/dev/sda",
												"info_name": "/dev/sda",
												"type": "sat",
												"protocol": "sat"
												}
											]}`),
				},
				{
					args: []string{
						"-a", "/dev/sda", "-d", "3ware,0", "-j",
					},
					out: readControllerEnvironment(t, "env_1").
						AllSmartInfoScans.get(t, "-a /dev/sda -d 3ware,0 -j"),
				},
				{
					args: []string{
						"-a", "/dev/sda", "-d", "areca,1", "-j",
					},
					out: readControllerEnvironment(t, "env_1").
						AllSmartInfoScans.get(t, "-a /dev/sda -d areca,1 -j"),
				},
				{
					args: []string{
						"-a", "/dev/sda", "-d", "cciss,0", "-j",
					},
					out: readControllerEnvironment(t, "env_1").
						AllSmartInfoScans.get(t, "-a /dev/sda -d cciss,0 -j"),
				},
				{
					args: []string{
						"-a", "/dev/sda", "-d", "sat", "-j",
					},
					out: readControllerEnvironment(t, "env_1").
						AllSmartInfoScans.get(t, "-a /dev/sda -d sat -j"),
				},
				{
					args: []string{
						"-a", "/dev/sda", "-d", "scsi", "-j",
					},
					out: sampleFailedAllSmartInfoScan,
				},
			},
			wantRunner: &runner{
				jsonDevices: nil,
				devices: map[string]deviceParser{
					"/dev/sda sat": {
						ModelName:    "INTEL SSDSC2BB120G6",
						SerialNumber: "PHWA619301M9120CGN",
						Info: deviceInfo{
							Name:     "/dev/sda sat",
							InfoName: "/dev/sda [SAT]",
							DevType:  "sat",
							name:     "/dev/sda",
							raidType: "sat",
						},
						Smartctl:    smartctlField{Version: []int{7, 3}},
						SmartStatus: &smartStatus{SerialNumber: true},
						SmartAttributes: smartAttributes{
							Table: []table{
								{
									Attrname: "Reallocated_Sector_Ct",
									ID:       5,
								},
								{
									Attrname: "Power_On_Hours",
									ID:       9,
								},
								{
									Attrname: "Power_Cycle_Count",
									ID:       12,
								},
								{
									Attrname: "Available_Reservd_Space",
									ID:       170,
									Thresh:   10,
								},
								{
									Attrname: "Program_Fail_Count",
									ID:       171,
								},
								{
									Attrname: "Erase_Fail_Count",
									ID:       172,
								},
								{
									Attrname: "Unsafe_Shutdown_Count",
									ID:       174,
								},
								{
									Attrname: "Power_Loss_Cap_Test",
									ID:       175,
									Thresh:   10,
								},
								{
									Attrname: "SATA_Downshift_Count",
									ID:       183,
								},
								{
									Attrname: "End-to-End_Error",
									ID:       184,
									Thresh:   90,
								},
								{
									Attrname: "Reported_Uncorrect",
									ID:       187,
								},
								{
									Attrname: "Temperature_Case",
									ID:       190,
								},
								{
									Attrname: "Unsafe_Shutdown_Count",
									ID:       192,
								},
								{
									Attrname: "Temperature_Internal",
									ID:       194,
								},
								{
									Attrname: "Current_Pending_Sector",
									ID:       197,
								},
								{
									Attrname: "CRC_Error_Count",
									ID:       199,
								},
								{
									Attrname: "Host_Writes_32MiB",
									ID:       225,
								},
								{
									Attrname: "Workld_Media_Wear_Indic",
									ID:       226,
								},
								{
									Attrname: "Workld_Host_Reads_Perc",
									ID:       227,
								},
								{
									Attrname: "Workload_Minutes",
									ID:       228,
								},
								{
									Attrname: "Available_Reservd_Space",
									ID:       232,
									Thresh:   10,
								},
								{
									Attrname: "Media_Wearout_Indicator",
									ID:       233,
								},
								{
									Attrname: "Thermal_Throttle",
									ID:       234,
								},
								{
									Attrname: "Host_Writes_32MiB",
									ID:       241,
								},
								{
									Attrname: "Host_Reads_32MiB",
									ID:       242,
								},
								{
									Attrname: "NAND_Writes_32MiB",
									ID:       243,
								},
							},
						},
					},
				},
			},
			wantErr: false,
		},
		{
			name: "+singleRAIDDeviceFromEnv1",
			args: args{
				jsonRunner: true,
			},
			expectations: []expectation{
				{
					args: []string{"--scan", "-j"},
					err:  nil,
					out:  []byte(`{}`),
				},
				{
					args: []string{"--scan", "-d", "sat", "-j"},
					err:  nil,
					out: []byte(`{
											"json_format_version": [1, 0],
											"smartctl": {
												"version": [7, 1],
												"svn_revision": "5022",
												"platform_info": "x86_64-w64-mingw32-w10-b19045",
												"build_info": "(sf-7.1-1)"
											},
											"devices": [
												{
												"name": "/dev/sda",
												"info_name": "/dev/sda",
												"type": "sat",
												"protocol": "sat"
												}
											]}`),
				},
				{
					args: []string{
						"-a", "/dev/sda", "-d", "3ware,0", "-j",
					},
					out: readControllerEnvironment(t, "env_1").
						AllSmartInfoScans.get(t, "-a /dev/sda -d 3ware,0 -j"),
				},
				{
					args: []string{
						"-a", "/dev/sda", "-d", "areca,1", "-j",
					},
					out: readControllerEnvironment(t, "env_1").
						AllSmartInfoScans.get(t, "-a /dev/sda -d areca,1 -j"),
				},
				{
					args: []string{
						"-a", "/dev/sda", "-d", "cciss,0", "-j",
					},
					out: readControllerEnvironment(t, "env_1").
						AllSmartInfoScans.get(t, "-a /dev/sda -d cciss,0 -j"),
				},
				{
					args: []string{
						"-a", "/dev/sda", "-d", "sat", "-j",
					},
					out: readControllerEnvironment(t, "env_1").
						AllSmartInfoScans.get(t, "-a /dev/sda -d sat -j"),
				},
				{
					args: []string{
						"-a", "/dev/sda", "-d", "scsi", "-j",
					},
					out: sampleFailedAllSmartInfoScan,
				},
			},
			wantRunner: &runner{
				jsonDevices: map[string]jsonDevice{
					"/dev/sda sat": {
						serialNumber: "PHWA619301M9120CGN",
						jsonData: string(
							readControllerEnvironment(t, "env_1").
								AllSmartInfoScans.get(t, "-a /dev/sda -d sat -j"),
						),
					},
				},
				devices: nil,
			},
			wantErr: false,
		},
		{
			name: "+validMegaraid",
			args: args{
				jsonRunner: false,
			},
			expectations: []expectation{
				{
					args: []string{"--scan", "-j"},
					err:  nil,
					out:  []byte(`{}`),
				},
				{
					args: []string{"--scan", "-d", "sat", "-j"},
					err:  nil,
					out: []byte(`{
											"json_format_version": [1, 0],
											"smartctl": {
												"version": [7, 1],
												"svn_revision": "5022",
												"platform_info": "x86_64-w64-mingw32-w10-b19045",
												"build_info": "(sf-7.1-1)"
											},
											"devices": [
												{
												"name": "optimus_prime",
												"info_name": "optimus_prime",
												"type": "megaraid",
												"protocol": "transformer"
												}
											]}`),
				},
				{
					args: []string{
						"-a", "optimus_prime", "-d", "megaraid", "-j",
					},
					err: nil,
					out: readControllerFixture(t, "device/all_info_sda.json"),
				},
			},
			wantRunner: &runner{
				jsonDevices: nil,
				devices: map[string]deviceParser{
					"optimus_prime megaraid": {
						ModelName:    "SAMSUNG MZVL21T0HCLR-00BH1",
						SerialNumber: "S641NX0T509005",
						Info: deviceInfo{
							Name:     "optimus_prime megaraid",
							InfoName: "/dev/sda",
							DevType:  "nvme",
							name:     "optimus_prime",
							raidType: "megaraid",
						},
						Smartctl: smartctlField{
							Version: []int{7, 1},
						},
						SmartStatus:     &smartStatus{SerialNumber: true},
						SmartAttributes: smartAttributes{},
					},
				},
			},
			wantErr: false,
		},
		{
			name: "+nonZeroStatusCode",
			args: args{
				jsonRunner: true,
			},
			expectations: []expectation{
				{
					args: []string{
						"--scan", "-j",
					},
					out: readControllerEnvironment(t, "env_2").AllDevicesScan,
				},
				{
					args: []string{"--scan", "-d", "sat", "-j"},
					err:  nil,
					out:  []byte(`{}`),
				},
				{
					args: []string{"-a", "/dev/sda", "-j"},
					err:  nil,
					out:  readControllerEnvironment(t, "env_2").AllSmartInfoScans.get(t, "-a /dev/sda -d sat -j"),
				},
			},
			wantRunner: &runner{
				jsonDevices: map[string]jsonDevice{
					"/dev/sda": {
						serialNumber: "X6GMTKX2T",
						jsonData: string(
							readControllerEnvironment(t, "env_2").
								AllSmartInfoScans.get(t, "-a /dev/sda -d sat -j"),
						),
					},
				},
				devices: nil,
			},
			wantErr: false,
		},
		{
			name: "-basicDeviceScanError",
			args: args{jsonRunner: false},
			expectations: []expectation{
				{
					args: []string{"--scan", "-j"},
					err:  errs.New("test error"),
					out:  []byte(""),
				},
			},
			wantRunner: nil,
			wantErr:    true,
		},
		{
			name: "-basicSmartScanError",
			args: args{jsonRunner: false},
			expectations: []expectation{
				{
					args: []string{"--scan", "-j"},
					err:  nil,
					out:  readControllerEnvironment(t, "manually_created_2_basic_devices").AllDevicesScan,
				},
				{
					args: []string{"--scan", "-d", "sat", "-j"},
					err:  nil,
					out:  []byte("{}"),
				},
				{
					args: []string{"-a", "/dev/sda", "-j"},
					err:  errs.New("unknown error"),
					out: readControllerEnvironment(t, "manually_created_2_basic_devices").
						AllSmartInfoScans.get(t, "-a /dev/sda -j"),
				},
			},
			wantRunner: nil,
			wantErr:    true,
		},
		{
			name: "-basicDeviceNoSmart",
			args: args{jsonRunner: false},
			expectations: []expectation{
				{
					args: []string{"--scan", "-j"},
					err:  nil,
					out:  readControllerEnvironment(t, "manually_created_2_basic_devices").AllDevicesScan,
				},
				{
					args: []string{"--scan", "-d", "sat", "-j"},
					err:  nil,
					out:  []byte("{}"),
				},
				{
					args: []string{"-a", "/dev/sda", "-j"},
					err:  nil,
					out: []byte(`{
												"json_format_version": [1, 0],
												"smartctl": {
												"version": [7, 1],
												"svn_revision": "5022",
												"platform_info": "xstas",
												"build_info": "(2001)",
												"argv": ["smartctl", "-a", "/dev/sdb", "-j"],
												"exit_status": 0
												},
												"device": {
												"name": "/dev/sdb",
												"info_name": "/dev/sdb",
												"type": "nvme",
												"protocol": "NVMe"
												},
												"model_name": "LEFT LEG",
												"serial_number": "42070",
												"firmware_version": "NEW"
											}`),
				},
				{
					args: []string{"-a", "/dev/sdb", "-j"},
					err:  nil,
					out: readControllerEnvironment(t, "manually_created_2_basic_devices").
						AllSmartInfoScans.get(t, "-a /dev/sdb -j"),
				},
			},
			wantRunner: &runner{
				devices: map[string]deviceParser{
					"/dev/sdb": {
						ModelName:    "LEFT LEG",
						SerialNumber: "42070",
						Info: deviceInfo{
							Name:     "/dev/sdb",
							InfoName: "/dev/sdb",
							DevType:  "nvme",
							name:     "/dev/sdb",
						},
						Smartctl:    smartctlField{Version: []int{7, 1}},
						SmartStatus: &smartStatus{SerialNumber: true},
					},
				},
			},
			wantErr: false,
		},
		{
			name: "-megaraidSmartScanError",
			args: args{jsonRunner: false},
			expectations: []expectation{
				{
					args: []string{"--scan", "-j"},
					err:  nil,
					out:  []byte("{}"),
				},
				{
					args: []string{"--scan", "-d", "sat", "-j"},
					err:  nil,
					out: []byte(`{
											"json_format_version": [1, 0],
											"smartctl": {
												"version": [7, 1],
												"svn_revision": "5022",
												"platform_info": "x86_64-w64-mingw32-w10-b19045",
												"build_info": "(sf-7.1-1)"
											},
											"devices": [
												{
												"name": "optimus_prime",
												"info_name": "optimus_prime",
												"type": "megaraid",
												"protocol": "transformer"
												}
											]}`),
				},
				{
					args: []string{
						"-a", "optimus_prime", "-d", "megaraid", "-j",
					},
					err: errs.New("unexpected error"),
					out: readControllerFixture(t, "device/all_info_sda.json"),
				},
			},
			wantRunner: &runner{
				devices: map[string]deviceParser{},
			},
			wantErr: false,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			responses := make([]controllerResponse, 0, len(tt.expectations))
			for _, e := range tt.expectations {
				responses = append(responses, controllerResponse{
					args:   e.args,
					output: e.out,
					err:    e.err,
				})
			}

			ctl := newFixtureController(t, responses...)

			p := &Plugin{
				cpuCount: 1,
				ctl:      ctl,
				Base:     plugin.Base{Logger: log.New("test")},
			}

			r, err := p.execute(tt.args.byID, tt.args.jsonRunner)
			if (err != nil) != tt.wantErr {
				t.Fatalf("Plugin.execute() error = %v, wantErr %v", err, tt.wantErr)
			}

			if diff := cmp.Diff(
				tt.wantRunner,
				r,
				cmp.AllowUnexported(jsonDevice{}, deviceInfo{}, runner{}),
			); diff != "" {
				t.Fatalf("Plugin.execute() runner = %s", diff)
			}
		})
	}
}

func Test_getBasicDeviceInfo(t *testing.T) {
	t.Parallel()

	type expectation struct {
		args []string
		err  error
		out  []byte
	}

	type args struct {
		basicDev   []deviceInfo
		jsonRunner bool
	}

	tests := []struct {
		name           string
		deviceName     string
		expectations   expectation
		args           args
		expectedResult *SmartCtlDeviceData
		wantErr        bool
	}{
		{
			name:       "+valid",
			deviceName: "/dev/sda",
			expectations: expectation{
				args: []string{"-a", "/dev/sda", "-j"},
				err:  nil,
				out:  readControllerFixture(t, "device/all_info_sda.json"),
			},
			args: args{
				basicDev: []deviceInfo{
					{
						Name:     "/dev/sda",
						InfoName: "/dev/sda",
						DevType:  "nvme",
					},
				},
				jsonRunner: false,
			},
			expectedResult: &SmartCtlDeviceData{
				Device: &deviceParser{
					ModelName:    "SAMSUNG MZVL21T0HCLR-00BH1",
					SerialNumber: "S641NX0T509005",
					Info: deviceInfo{
						Name:     "/dev/sda",
						InfoName: "/dev/sda",
						DevType:  "nvme",
						name:     "/dev/sda",
					},
					Smartctl: smartctlField{
						Version: []int{7, 1},
					},
					SmartStatus:     &smartStatus{SerialNumber: true},
					SmartAttributes: smartAttributes{},
				},
				Data: readControllerFixture(t, "device/all_info_sda.json"),
			},
			wantErr: false,
		},
		{
			name:       "-invalidJSON",
			deviceName: "/dev/sda",
			expectations: expectation{
				args: []string{"-a", "/dev/sda", "-j"},
				err:  nil,
				out:  []byte(`{`), // Corrupted or incomplete JSON
			},
			args: args{
				basicDev: []deviceInfo{
					{
						Name:     "/dev/sda",
						InfoName: "/dev/sda",
						DevType:  "nvme",
					},
				},
				jsonRunner: false,
			},
			expectedResult: nil,
			wantErr:        true,
		},
		{
			name:       "-noSmartStatus",
			deviceName: "/dev/sda",
			expectations: expectation{
				args: []string{"-a", "/dev/sda", "-j"},
				err:  nil,
				out:  []byte(`{"smartctl":{},"device":{},"model_name":"Example Model"}`), // No SmartStatus field
			},
			args: args{
				basicDev: []deviceInfo{
					{
						Name:     "/dev/sda",
						InfoName: "/dev/sda",
						DevType:  "nvme",
					},
				},
				jsonRunner: false,
			},
			expectedResult: nil,
			wantErr:        true,
		},
		{
			name:       "-smartError",
			deviceName: "/dev/sda",
			expectations: expectation{
				args: []string{"-a", "/dev/sda", "-j"},
				err:  errors.New("failed to exec smart control"),
				out:  []byte{},
			},
			args: args{
				basicDev: []deviceInfo{
					{
						Name:     "/dev/sda",
						InfoName: "/dev/sda",
						DevType:  "nvme",
					},
				},
				jsonRunner: false,
			},
			expectedResult: nil,
			wantErr:        true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			ctl := newFixtureController(t, controllerResponse{
				args:   tt.expectations.args,
				output: tt.expectations.out,
				err:    tt.expectations.err,
			})

			result, err := getBasicDeviceInfo(ctl, tt.deviceName)
			if (err != nil) != tt.wantErr {
				t.Fatalf(
					"getBasicDeviceInfo() error = %v, wantErr %v",
					err,
					tt.wantErr,
				)
			}

			if diff := cmp.Diff(
				tt.expectedResult, result,
				cmp.AllowUnexported(deviceInfo{}),
			); diff != "" {
				t.Fatalf(
					"getBasicDeviceInfo() result mismatch (-want +got):\n%s",
					diff,
				)
			}
		})
	}
}

func Test_evaluateVersion(t *testing.T) {
	t.Parallel()

	type args struct {
		versionDigits []int
	}
	tests := []struct {
		name    string
		args    args
		wantErr bool
	}{
		{
			name:    "+correctVersion",
			args:    args{versionDigits: []int{7, 1}},
			wantErr: false,
		},
		{
			name:    "+correctVersionOneDigit",
			args:    args{versionDigits: []int{8}},
			wantErr: false,
		},
		{
			name:    "+correctVersionMultipleDigits",
			args:    args{versionDigits: []int{7, 1, 2}},
			wantErr: false,
		},
		{
			name:    "-incorrectVersion",
			args:    args{versionDigits: []int{7, 0}},
			wantErr: true,
		},
		{
			name:    "-malformedVersion",
			args:    args{versionDigits: []int{-7, 0}},
			wantErr: true,
		},
		{
			name:    "-empty",
			args:    args{},
			wantErr: true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()
			if err := evaluateVersion(tt.args.versionDigits); (err != nil) != tt.wantErr {
				t.Fatalf(
					"evaluateVersion() error = %v, wantErr %v",
					err,
					tt.wantErr,
				)
			}
		})
	}
}

func Test_cutPrefix(t *testing.T) {
	t.Parallel()
	type args struct {
		in string
	}
	tests := []struct {
		name string
		args args
		want string
	}{
		{
			name: "+hasPrefix",
			args: args{in: "/dev/sda"},
			want: "sda",
		},
		{
			name: "-noPrefix",
			args: args{in: "sda"},
			want: "sda",
		},
		{
			name: "-empty",
			args: args{in: ""},
			want: "",
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()
			if got := cutPrefix(tt.args.in); got != tt.want {
				t.Fatalf("cutPrefix() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_deviceParser_checkErr(t *testing.T) {
	t.Parallel()

	type fields struct {
		Smartctl smartctlField
		rawResp  []byte
	}

	tests := []struct {
		name    string
		fields  fields
		wantErr bool
		wantMsg string
	}{
		{
			name: "+noErr",
			fields: fields{Smartctl: smartctlField{
				Messages:   nil,
				ExitStatus: 0,
			}},
			wantErr: false,
			wantMsg: "",
		},
		{
			name: "+rawRespErr",
			fields: fields{
				rawResp: readControllerEnvironment(t, "env_1").
					AllSmartInfoScans.get(t, "-a /dev/sda -d 3ware,0 -j"),
			},
			wantErr: true,
			wantMsg: "/dev/sda: Unknown device type '3ware,0', =======> VALID " +
				"ARGUMENTS ARE: ata, scsi[+TYPE], nvme[,NSID], " +
				"sat[,auto][,N][+TYPE], usbcypress[,X], " +
				"usbjmicron[,p][,x][,N], usbprolific, usbsunplus, " +
				"sntasmedia, sntjmicron[,NSID], sntrealtek, " +
				"intelliprop,N[+TYPE], jmb39x[-q],N[,sLBA][,force][+TYPE], " +
				"jms56x,N[,sLBA][,force][+TYPE], aacraid,H,L,ID, areca,N[/E], " +
				"auto, test <=======.",
		},
		{
			name: "+rawRespNoErr",
			fields: fields{
				rawResp: readControllerEnvironment(t, "env_1").
					AllSmartInfoScans.get(t, "-a /dev/sda -d sat -j"),
			},
			wantErr: false,
			wantMsg: "",
		},
		{
			name: "+noErr",
			fields: fields{Smartctl: smartctlField{
				Messages:   nil,
				ExitStatus: 4,
			}},
			wantErr: false,
			wantMsg: "",
		},
		{
			name: "+warning",
			fields: fields{
				Smartctl: smartctlField{
					Messages: []message{{Str: "barfoo"}}, ExitStatus: 3,
				},
			},
			wantErr: true,
			wantMsg: "Barfoo.",
		},
		{
			name: "-errorStatusOne",
			fields: fields{
				Smartctl: smartctlField{
					Messages: []message{{Str: "barfoo"}}, ExitStatus: 1,
				},
			},
			wantErr: true,
			wantMsg: "Barfoo.",
		},
		{
			name: "-errorStatusTwo",
			fields: fields{
				Smartctl: smartctlField{
					Messages: []message{{Str: "foobar"}}, ExitStatus: 2,
				},
			},
			wantErr: true,
			wantMsg: "Foobar.",
		},
		{
			name: "-twoErr",
			fields: fields{
				Smartctl: smartctlField{
					Messages: []message{{Str: "foobar"}, {Str: "barfoo"}}, ExitStatus: 2,
				},
			},
			wantErr: true,
			wantMsg: "Foobar, barfoo.",
		},
		{
			name: "-unknownErr/noMessage",
			fields: fields{
				Smartctl: smartctlField{
					Messages: []message{}, ExitStatus: 2,
				},
			},
			wantErr: true,
			wantMsg: "Unknown error from smartctl.",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()
			dp := deviceParser{Smartctl: tt.fields.Smartctl}

			if tt.fields.rawResp != nil {
				dpFromRaw := deviceParser{}

				err := json.Unmarshal(tt.fields.rawResp, &dpFromRaw)
				if err != nil {
					t.Fatalf("deviceParser.checkErr() error = %v", err)
				}

				dp = dpFromRaw
			}

			err := dp.checkErr()
			if (err != nil) != tt.wantErr {
				t.Fatalf(
					"deviceParser.checkErr() error = %v, wantErr %v",
					err,
					tt.wantErr,
				)
			}

			if err != nil {
				if diff := cmp.Diff(tt.wantMsg, err.Error()); diff != "" {
					t.Fatalf(
						"deviceParser.checkErr() error message mismatch (-want +got):\n%s",
						diff,
					)
				}
			}
		})
	}
}

func TestPlugin_checkVersion(t *testing.T) { //nolint:paralleltest
	type expect struct {
		exec bool
	}

	type fields struct {
		execErr      error
		execOut      []byte
		lastVerCheck time.Time
	}

	tests := []struct {
		name    string
		expect  expect
		fields  fields
		wantErr bool
	}{
		{
			name:    "+valid",
			expect:  expect{exec: true},
			fields:  fields{execOut: readControllerFixture(t, "version/valid.json")},
			wantErr: false,
		},
		{
			name:   "-noCheck",
			expect: expect{exec: false},
			fields: fields{
				lastVerCheck: time.Now(),
			},
			wantErr: false,
		},
		{
			name:   "-executeErr",
			expect: expect{exec: true},
			fields: fields{
				execOut: readControllerFixture(t, "version/valid.json"),
				execErr: errors.New("fail"),
			},
			wantErr: true,
		},
		{
			name:    "-unmarshalErr",
			expect:  expect{exec: true},
			fields:  fields{execOut: []byte("{")},
			wantErr: true,
		},
		{
			name:    "-evaluateVersionErr",
			expect:  expect{exec: true},
			fields:  fields{execOut: readControllerFixture(t, "version/invalid.json")},
			wantErr: true,
		},
	}
	//nolint:paralleltest
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			lastVerCheck = tt.fields.lastVerCheck

			responses := []controllerResponse{}
			if tt.expect.exec {
				responses = append(responses, controllerResponse{
					args:   []string{"-j", "-V"},
					output: tt.fields.execOut,
					err:    tt.fields.execErr,
				})
			}

			p := &Plugin{ctl: newFixtureController(t, responses...)}

			if err := p.checkVersion(); (err != nil) != tt.wantErr {
				t.Fatalf(
					"Plugin.checkVersion() error = %v, wantErr %v",
					err, tt.wantErr,
				)
			}
		})
	}
}

func Test_getAllDeviceInfoByType(t *testing.T) {
	t.Parallel()

	sampleValidSmartInfoScan := []byte(`{
		"json_format_version": [1, 0],
		"smartctl": {
		  "version": [7, 3],
		  "svn_revision": "5338",
		  "platform_info": "x86_64-linux-6.1.0-13-amd64",
		  "build_info": "(local build)",
		  "argv": ["smartctl", "-a", "-j", "/dev/sda", "-d", "scsi"],
		  "exit_status": 0
		},
		"local_time": {
		  "time_t": 1705569652,
		  "asctime": "Thu Jan 18 10:20:52 2024 CET"
		},
		"device": {
		  "name": "/dev/sda",
		  "info_name": "/dev/sda",
		  "type": "scsi",
		  "protocol": "SCSI"
		},
		"scsi_vendor": "SAMSUNG",
		"scsi_product": "MZILT960HBHQ/007",
		"scsi_model_name": "SAMSUNG MZILT960HBHQ/007",
		"scsi_revision": "GXA0",
		"scsi_version": "SPC-5",
		"user_capacity": {
		  "blocks": 1875385008,
		  "bytes": 960197124096
		},
		"logical_block_size": 512,
		"physical_block_size": 4096,
		"scsi_lb_provisioning": {
		  "name": "resource provisioned",
		  "value": 1,
		  "management_enabled": {
			"name": "LBPME",
			"value": 1
		  },
		  "read_zeros": {
			"name": "LBPRZ",
			"value": 1
		  }
		},
		"rotation_rate": 0,
		"form_factor": {
		  "scsi_value": 3,
		  "name": "2.5 inches"
		},
		"logical_unit_id": "0x5002538b731302a0",
		"serial_number": "S5G1NC0W102239",
		"device_type": {
		  "scsi_terminology": "Peripheral Device Type [PDT]",
		  "scsi_value": 0,
		  "name": "disk"
		},
		"scsi_transport_protocol": {
		  "name": "SAS (SPL-4)",
		  "value": 6
		},
		"smart_support": {
		  "available": true,
		  "enabled": true
		},
		"temperature_warning": {
		  "enabled": true
		},
		"smart_status": {
		  "passed": true
		},
		"scsi_percentage_used_endurance_indicator": 0,
		"temperature": {
		  "current": 35,
		  "drive_trip": 74
		},
		"power_on_time": {
		  "hours": 3862,
		  "minutes": 30
		},
		"scsi_start_stop_cycle_counter": {
		  "year_of_manufacture": "2023",
		  "week_of_manufacture": "01",
		  "accumulated_start_stop_cycles": 5,
		  "specified_load_unload_count_over_device_lifetime": 0,
		  "accumulated_load_unload_cycles": 0
		},
		"scsi_grown_defect_list": 0,
		"scsi_error_counter_log": {
		  "read": {
			"errors_corrected_by_eccfast": 0,
			"errors_corrected_by_eccdelayed": 0,
			"errors_corrected_by_rereads_rewrites": 0,
			"total_errors_corrected": 0,
			"correction_algorithm_invocations": 0,
			"gigabytes_processed": "12.699",
			"total_uncorrected_errors": 0
		  },
		  "write": {
			"errors_corrected_by_eccfast": 0,
			"errors_corrected_by_eccdelayed": 0,
			"errors_corrected_by_rereads_rewrites": 0,
			"total_errors_corrected": 0,
			"correction_algorithm_invocations": 0,
			"gigabytes_processed": "6082.745",
			"total_uncorrected_errors": 0
		  },
		  "verify": {
			"errors_corrected_by_eccfast": 0,
			"errors_corrected_by_eccdelayed": 0,
			"errors_corrected_by_rereads_rewrites": 0,
			"total_errors_corrected": 0,
			"correction_algorithm_invocations": 0,
			"gigabytes_processed": "0.001",
			"total_uncorrected_errors": 0
		  }
		},
		"scsi_pending_defects": {
		  "count": 0
		},
		"scsi_self_test_0": {
		  "code": {
			"value": 1,
			"string": "Background short"
		  },
		  "result": {
			"value": 0,
			"string": "Completed"
		  },
		  "power_on_time": {
			"hours": 5,
			"aka": "accumulated_power_on_hours"
		  }
		},
		"scsi_extended_self_test_seconds": 3600
	  }`)
	//nolint:lll
	sampleFailedSmartInfoScan := []byte(`{
		"json_format_version": [1, 0],
		"smartctl": {
		  "version": [7, 3],
		  "svn_revision": "5338",
		  "platform_info": "x86_64-w64-mingw32-2016-1607",
		  "build_info": "(sf-7.3-1)",
		  "argv": ["smartctl", "-a", "/dev/sda", "-d", "3ware,0", "-j"],
		  "messages": [
			{
			  "string": "/dev/sda: Unknown device type '3ware,0'",
			  "severity": "error"
			},
			{
			  "string": "=======> VALID ARGUMENTS ARE: ata, scsi[+TYPE], nvme[,NSID], sat[,auto][,N][+TYPE], usbcypress[,X], usbjmicron[,p][,x][,N], usbprolific, usbsunplus, sntasmedia, sntjmicron[,NSID], sntrealtek, intelliprop,N[+TYPE], jmb39x[-q],N[,sLBA][,force][+TYPE], jms56x,N[,sLBA][,force][+TYPE], aacraid,H,L,ID, areca,N[/E], auto, test <=======",
			  "severity": "error"
			}
		  ],
		  "exit_status": 1
		},
		"local_time": {
		  "time_t": 1663357978,
		  "asctime": "Fri Sep 16 22:52:58 2022 BST"
		}
	  }`)
	sampleMissingSmartStatusSmartInfoScan := []byte(`{
		"json_format_version": [1, 0],
		"smartctl": {
		  "version": [7, 3],
		  "svn_revision": "5338",
		  "platform_info": "x86_64-linux-6.1.0-13-amd64",
		  "build_info": "(local build)",
		  "argv": ["smartctl", "-a", "-j", "/dev/sda", "-d", "scsi"],
		  "exit_status": 0
		},
		"local_time": {
		  "time_t": 1705569652,
		  "asctime": "Thu Jan 18 10:20:52 2024 CET"
		},
		"device": {
		  "name": "/dev/sda",
		  "info_name": "/dev/sda",
		  "type": "scsi",
		  "protocol": "SCSI"
		},
		"scsi_vendor": "SAMSUNG",
		"scsi_product": "MZILT960HBHQ/007",
		"scsi_model_name": "SAMSUNG MZILT960HBHQ/007",
		"scsi_revision": "GXA0",
		"scsi_version": "SPC-5",
		"user_capacity": {
		  "blocks": 1875385008,
		  "bytes": 960197124096
		},
		"logical_block_size": 512,
		"physical_block_size": 4096,
		"scsi_lb_provisioning": {
		  "name": "resource provisioned",
		  "value": 1,
		  "management_enabled": {
			"name": "LBPME",
			"value": 1
		  },
		  "read_zeros": {
			"name": "LBPRZ",
			"value": 1
		  }
		},
		"rotation_rate": 0,
		"form_factor": {
		  "scsi_value": 3,
		  "name": "2.5 inches"
		},
		"logical_unit_id": "0x5002538b731302a0",
		"serial_number": "S5G1NC0W102239",
		"device_type": {
		  "scsi_terminology": "Peripheral Device Type [PDT]",
		  "scsi_value": 0,
		  "name": "disk"
		},
		"scsi_transport_protocol": {
		  "name": "SAS (SPL-4)",
		  "value": 6
		},
		"smart_support": {
		  "available": true,
		  "enabled": true
		},
		"temperature_warning": {
		  "enabled": true
		},
		"scsi_percentage_used_endurance_indicator": 0,
		"temperature": {
		  "current": 35,
		  "drive_trip": 74
		},
		"power_on_time": {
		  "hours": 3862,
		  "minutes": 30
		},
		"scsi_start_stop_cycle_counter": {
		  "year_of_manufacture": "2023",
		  "week_of_manufacture": "01",
		  "accumulated_start_stop_cycles": 5,
		  "specified_load_unload_count_over_device_lifetime": 0,
		  "accumulated_load_unload_cycles": 0
		},
		"scsi_grown_defect_list": 0,
		"scsi_error_counter_log": {
		  "read": {
			"errors_corrected_by_eccfast": 0,
			"errors_corrected_by_eccdelayed": 0,
			"errors_corrected_by_rereads_rewrites": 0,
			"total_errors_corrected": 0,
			"correction_algorithm_invocations": 0,
			"gigabytes_processed": "12.699",
			"total_uncorrected_errors": 0
		  },
		  "write": {
			"errors_corrected_by_eccfast": 0,
			"errors_corrected_by_eccdelayed": 0,
			"errors_corrected_by_rereads_rewrites": 0,
			"total_errors_corrected": 0,
			"correction_algorithm_invocations": 0,
			"gigabytes_processed": "6082.745",
			"total_uncorrected_errors": 0
		  },
		  "verify": {
			"errors_corrected_by_eccfast": 0,
			"errors_corrected_by_eccdelayed": 0,
			"errors_corrected_by_rereads_rewrites": 0,
			"total_errors_corrected": 0,
			"correction_algorithm_invocations": 0,
			"gigabytes_processed": "0.001",
			"total_uncorrected_errors": 0
		  }
		},
		"scsi_pending_defects": {
		  "count": 0
		},
		"scsi_self_test_0": {
		  "code": {
			"value": 1,
			"string": "Background short"
		  },
		  "result": {
			"value": 0,
			"string": "Completed"
		  },
		  "power_on_time": {
			"hours": 5,
			"aka": "accumulated_power_on_hours"
		  }
		},
		"scsi_extended_self_test_seconds": 3600
	  }`)

	type fields struct {
		out []byte
		err error
	}

	type args struct {
		deviceName string
		deviceType string
	}

	tests := []struct {
		name    string
		fields  fields
		args    args
		want    *SmartCtlDeviceData
		wantErr bool
	}{
		{
			name: "+valid",
			fields: fields{
				out: sampleValidSmartInfoScan,
			},
			args: args{
				deviceName: "/dev/sda",
				deviceType: "raid,1,2,3",
			},
			want: &SmartCtlDeviceData{
				Device: &deviceParser{
					SerialNumber: "S5G1NC0W102239",
					Info: deviceInfo{
						Name:     "/dev/sda raid,1,2,3",
						InfoName: "/dev/sda",
						DevType:  "scsi",
						name:     "/dev/sda",
						raidType: "raid,1,2,3",
					},
					Smartctl:    smartctlField{Version: []int{7, 3}},
					SmartStatus: &smartStatus{SerialNumber: true},
				},
				Data: sampleValidSmartInfoScan,
			},
			wantErr: false,
		},
		{
			name: "-executeErr",
			fields: fields{
				out: sampleValidSmartInfoScan,
				err: errs.New("fail"),
			},
			args: args{
				deviceName: "/dev/sda",
				deviceType: "raid,1,2,3",
			},
			want:    nil,
			wantErr: true,
		},
		{
			name:   "-unmarshalErr",
			fields: fields{out: []byte("{")},
			args: args{
				deviceName: "/dev/sda",
				deviceType: "raid,1,2,3",
			},
			want:    nil,
			wantErr: true,
		},
		{
			name:   "-smartctlErr",
			fields: fields{out: sampleFailedSmartInfoScan},
			args: args{
				deviceName: "/dev/sda",
				deviceType: "raid,1,2,3",
			},
			want:    nil,
			wantErr: true,
		},
		{
			name:   "-missingSmartStatus",
			fields: fields{out: sampleMissingSmartStatusSmartInfoScan},
			args: args{
				deviceName: "/dev/sda",
				deviceType: "raid,1,2,3",
			},
			want:    nil,
			wantErr: true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			ctl := newFixtureController(t, controllerResponse{
				args:   []string{"-a", "/dev/sda", "-d", "raid,1,2,3", "-j"},
				output: tt.fields.out,
				err:    tt.fields.err,
			})

			got, err := getAllDeviceInfoByType(
				ctl,
				tt.args.deviceName,
				tt.args.deviceType,
			)
			if (err != nil) != tt.wantErr {
				t.Fatalf(
					"getAllDeviceInfoByType() error = %v, wantErr %v",
					err, tt.wantErr,
				)
			}

			if diff := cmp.Diff(
				tt.want, got,
				cmp.AllowUnexported(deviceParser{}, deviceInfo{}),
			); diff != "" {
				t.Fatalf(
					"getAllDeviceInfoByType() mismatch (-want +got):\n%s", diff,
				)
			}
		})
	}
}

func Test_getRaidDevices(t *testing.T) {
	t.Parallel()

	type expectation struct {
		args []string
		err  error
		out  []byte
	}

	type args struct {
		deviceName string
		deviceType DeviceType
	}

	tests := []struct {
		name         string
		expectations []expectation
		args         args
		want         []*SmartCtlDeviceData
	}{
		{
			name: "+sat",
			expectations: []expectation{
				{
					args: []string{"-a", "/dev/sda", "-d", "sat", "-j"},
					out: readControllerEnvironment(t, "env_1").
						AllSmartInfoScans.get(t, "-a /dev/sda -d sat -j"),
				},
			},
			args: args{
				deviceName: "/dev/sda",
				deviceType: SAT,
			},
			want: []*SmartCtlDeviceData{
				{
					Device: &deviceParser{
						ModelName:    "INTEL SSDSC2BB120G6",
						SerialNumber: "PHWA619301M9120CGN",
						Info: deviceInfo{
							Name:     "/dev/sda sat",
							InfoName: "/dev/sda [SAT]",
							DevType:  "sat",
							name:     "/dev/sda",
							raidType: "sat",
						},
						Smartctl:    smartctlField{Version: []int{7, 3}},
						SmartStatus: &smartStatus{SerialNumber: true},
						SmartAttributes: smartAttributes{
							Table: []table{
								{
									Attrname: "Reallocated_Sector_Ct",
									ID:       5,
								},
								{
									Attrname: "Power_On_Hours",
									ID:       9,
								},
								{
									Attrname: "Power_Cycle_Count",
									ID:       12,
								},
								{
									Attrname: "Available_Reservd_Space",
									ID:       170,
									Thresh:   10,
								},
								{
									Attrname: "Program_Fail_Count",
									ID:       171,
								},
								{
									Attrname: "Erase_Fail_Count",
									ID:       172,
								},
								{
									Attrname: "Unsafe_Shutdown_Count",
									ID:       174,
								},
								{
									Attrname: "Power_Loss_Cap_Test",
									ID:       175,
									Thresh:   10,
								},
								{
									Attrname: "SATA_Downshift_Count",
									ID:       183,
								},
								{
									Attrname: "End-to-End_Error",
									ID:       184,
									Thresh:   90,
								},
								{
									Attrname: "Reported_Uncorrect",
									ID:       187,
								},
								{
									Attrname: "Temperature_Case",
									ID:       190,
								},
								{
									Attrname: "Unsafe_Shutdown_Count",
									ID:       192,
								},
								{
									Attrname: "Temperature_Internal",
									ID:       194,
								},
								{
									Attrname: "Current_Pending_Sector",
									ID:       197,
								},
								{
									Attrname: "CRC_Error_Count",
									ID:       199,
								},
								{
									Attrname: "Host_Writes_32MiB",
									ID:       225,
								},
								{
									Attrname: "Workld_Media_Wear_Indic",
									ID:       226,
								},
								{
									Attrname: "Workld_Host_Reads_Perc",
									ID:       227,
								},
								{
									Attrname: "Workload_Minutes",
									ID:       228,
								},
								{
									Attrname: "Available_Reservd_Space",
									ID:       232,
									Thresh:   10,
								},
								{
									Attrname: "Media_Wearout_Indicator",
									ID:       233,
								},
								{
									Attrname: "Thermal_Throttle",
									ID:       234,
								},
								{
									Attrname: "Host_Writes_32MiB",
									ID:       241,
								},
								{
									Attrname: "Host_Reads_32MiB",
									ID:       242,
								},
								{
									Attrname: "NAND_Writes_32MiB",
									ID:       243,
								},
							},
						},
					},
					Data: readControllerEnvironment(t, "env_1").
						AllSmartInfoScans.get(t, "-a /dev/sda -d sat -j"),
				},
			},
		},
		{
			name: "+scsi",
			expectations: []expectation{
				{
					args: []string{"-a", "/dev/sda", "-d", "scsi", "-j"},
					out: readControllerEnvironment(t, "HBA_with_SAS_1").
						AllSmartInfoScans.get(t, "-a /dev/sda -d scsi -j"),
				},
			},
			args: args{
				deviceName: "/dev/sda",
				deviceType: SCSI,
			},
			want: []*SmartCtlDeviceData{
				{
					Device: &deviceParser{
						SerialNumber: "S5G1NC0W102239",
						Info: deviceInfo{
							Name:     "/dev/sda scsi",
							InfoName: "/dev/sda",
							DevType:  "scsi",
							name:     "/dev/sda",
							raidType: "scsi",
						},
						Smartctl:    smartctlField{Version: []int{7, 3}},
						SmartStatus: &smartStatus{SerialNumber: true},
					},
					Data: readControllerEnvironment(t, "HBA_with_SAS_1").
						AllSmartInfoScans.get(t, "-a /dev/sda -d scsi -j"),
				},
			},
		},
		{
			name: "+ccissLinux",
			expectations: []expectation{
				{
					args: []string{"-a", "/dev/sg0", "-d", "cciss,0", "-j"},
					out:  readControllerFixture(t, "device/cciss/scsi.json"),
				},
				{
					args: []string{"-a", "/dev/sg0", "-d", "cciss,1", "-j"},
					out:  readControllerFixture(t, "device/cciss/ata.json"),
				},
				{
					args: []string{"-a", "/dev/sg0", "-d", "cciss,2", "-j"},
					out:  readControllerFixture(t, "device/cciss/device_open_error.json"),
					err:  errs.New("exit status 2"),
				},
			},
			args: args{
				deviceName: "/dev/sg0",
				deviceType: CCISS,
			},
			want: []*SmartCtlDeviceData{
				{
					Device: &deviceParser{
						SerialNumber: "TEST-CCISS-SCSI-0001",
						RotationRate: 7200,
						Info: deviceInfo{
							Name:     "/dev/sg0 cciss,0",
							InfoName: "/dev/sg0 [cciss_disk_00] [SCSI]",
							DevType:  "cciss",
							name:     "/dev/sg0",
							raidType: "cciss,0",
						},
						Smartctl:    smartctlField{Version: []int{7, 4}},
						SmartStatus: &smartStatus{SerialNumber: true},
					},
					Data: readControllerFixture(t, "device/cciss/scsi.json"),
				},
				{
					Device: &deviceParser{
						ModelName:    "TEST_SATA_SSD",
						SerialNumber: "TEST-CCISS-ATA-0002",
						Info: deviceInfo{
							Name:     "/dev/sg0 cciss,1",
							InfoName: "/dev/sg0 [cciss_disk_01] [SAT]",
							DevType:  "sat",
							name:     "/dev/sg0",
							raidType: "cciss,1",
						},
						Smartctl: smartctlField{
							Messages: []message{
								{Str: "Warning: This result is based on an Attribute check."},
							},
							Version: []int{7, 4},
						},
						SmartStatus: &smartStatus{SerialNumber: true},
						SmartAttributes: smartAttributes{
							Table: []table{
								{
									Attrname: "Reallocated_Sector_Ct",
									ID:       5,
								},
								{
									Attrname: "Power_On_Hours",
									ID:       9,
								},
								{
									Attrname: "Available_Reservd_Space",
									ID:       170,
									Thresh:   10,
								},
							},
						},
					},
					Data: readControllerFixture(t, "device/cciss/ata.json"),
				},
			},
		},
		{
			name: "-ccissUnavailableOnFirstDisk",
			expectations: []expectation{
				{
					args: []string{"-a", "/dev/sg0", "-d", "cciss,0", "-j"},
					out: readControllerFixture(
						t,
						"device/cciss/first_device_open_error.json",
					),
					err: errs.New("exit status 2"),
				},
			},
			args: args{
				deviceName: "/dev/sg0",
				deviceType: CCISS,
			},
			want: nil,
		},
		{
			name: "-ccissUnsupportedOnWindows",
			expectations: []expectation{
				{
					args: []string{"-a", "/dev/sda", "-d", "cciss,0", "-j"},
					out: readControllerEnvironment(t, "env_1").
						AllSmartInfoScans.get(t, "-a /dev/sda -d cciss,0 -j"),
				},
			},
			args: args{
				deviceName: "/dev/sda",
				deviceType: CCISS,
			},
			want: nil,
		},
		{
			name: "-invalidType3ware",
			expectations: []expectation{
				{
					args: []string{"-a", "/dev/sda", "-d", "3ware,0", "-j"},
					out: readControllerEnvironment(t, "env_1").
						AllSmartInfoScans.get(t, "-a /dev/sda -d 3ware,0 -j"),
				},
			},
			args: args{
				deviceName: "/dev/sda",
				deviceType: ThreeWare,
			},
			want: nil,
		},
		// missing cases for:
		// - 3ware
		// - areca
		// because of lack of test data
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			responses := make([]controllerResponse, 0, len(tt.expectations))
			for _, e := range tt.expectations {
				responses = append(responses, controllerResponse{
					args:   e.args,
					output: e.out,
					err:    e.err,
				})
			}

			got := getRaidDevices(
				newFixtureController(t, responses...),
				log.New(""),
				tt.args.deviceName,
				tt.args.deviceType,
			)
			if diff := cmp.Diff(
				tt.want, got,
				cmp.AllowUnexported(deviceParser{}, deviceInfo{}),
			); diff != "" {
				t.Fatalf("getRaidDevices() = %s", diff)
			}
		})
	}
}

func Test_setDeviceData(t *testing.T) {
	t.Parallel()

	type args struct {
		jsonRunner bool
		data       *SmartCtlDeviceData
	}

	tests := []struct {
		name       string
		args       args
		wantRunner *runner
	}{
		{
			name: "+validJsonRunner",
			args: args{jsonRunner: true, data: &SmartCtlDeviceData{
				Device: &deviceParser{
					ModelName:    "SAMSUNG MZVL21T0HCLR-00BH1",
					SerialNumber: "S641NX0T509005",
					Info: deviceInfo{
						Name:     "/dev/sda",
						InfoName: "/dev/sda",
						DevType:  "nvme",
						name:     "/dev/sda",
					},
					Smartctl: smartctlField{
						Version: []int{7, 1},
					},
					SmartStatus:     &smartStatus{SerialNumber: true},
					SmartAttributes: smartAttributes{},
				},
				Data: readControllerFixture(t, "device/all_info_sda.json"),
			},
			},
			wantRunner: //jsonRunner
			&runner{
				devices: map[string]deviceParser{},
				jsonDevices: map[string]jsonDevice{
					"/dev/sda": {
						serialNumber: "S641NX0T509005",
						jsonData:     string(readControllerFixture(t, "device/all_info_sda.json")),
					},
				},
			},
		},
		{
			name: "+validDeviceRunner",
			args: args{jsonRunner: false, data: &SmartCtlDeviceData{
				Device: &deviceParser{
					ModelName:    "SAMSUNG MZVL21T0HCLR-00BH1",
					SerialNumber: "S641NX0T509005",
					Info: deviceInfo{
						Name:     "/dev/sda",
						InfoName: "/dev/sda",
						DevType:  "nvme",
						name:     "/dev/sda",
					},
					Smartctl: smartctlField{
						Version: []int{7, 1},
					},
					SmartStatus:     &smartStatus{SerialNumber: true},
					SmartAttributes: smartAttributes{},
				},
				Data: readControllerFixture(t, "device/all_info_sda.json"),
			},
			},
			wantRunner: //jsonRunner
			&runner{
				devices: map[string]deviceParser{
					"/dev/sda": {
						ModelName:    "SAMSUNG MZVL21T0HCLR-00BH1",
						SerialNumber: "S641NX0T509005",
						Info: deviceInfo{
							Name:     "/dev/sda",
							InfoName: "/dev/sda",
							DevType:  "nvme",
							name:     "/dev/sda",
						},
						Smartctl: smartctlField{
							Version: []int{7, 1},
						},
						SmartStatus:     &smartStatus{SerialNumber: true},
						SmartAttributes: smartAttributes{},
					}},
				jsonDevices: map[string]jsonDevice{},
			},
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			r := &runner{
				devices:     make(map[string]deviceParser),
				jsonDevices: make(map[string]jsonDevice),
			}

			r.setDevicesData(tt.args.data, tt.args.jsonRunner)

			if diff := cmp.Diff(
				tt.wantRunner,
				r,
				cmp.AllowUnexported(jsonDevice{}, deviceInfo{}, runner{}),
			); diff != "" {
				t.Fatalf("runner.setDeviceData() runner = %s", diff)
			}
		})
	}
}

func Test_runner_parseOutput(t *testing.T) {
	t.Parallel()

	type fields struct {
		devices     map[string]deviceParser
		jsonDevices map[string]jsonDevice
	}

	type args struct {
		jsonRunner bool
	}

	tests := []struct {
		name       string
		args       args
		fields     fields
		wantRunner runner
	}{
		{
			name: "+validJSONRunner",
			args: args{jsonRunner: true},
			fields: fields{
				jsonDevices: map[string]jsonDevice{
					"/dev/sda": {
						serialNumber: "Serial123",
						jsonData:     "data1",
					},
					"/dev/sdb": {
						serialNumber: "Serial456",
						jsonData:     "data2",
					},
				},
			},
			wantRunner: runner{
				jsonDevices: map[string]jsonDevice{
					"/dev/sda": {
						serialNumber: "Serial123",
						jsonData:     "data1",
					},
					"/dev/sdb": {
						serialNumber: "Serial456",
						jsonData:     "data2",
					},
				},
			},
		},
		{
			name: "+jsonRunnerWithDuplicateDevices",
			args: args{jsonRunner: true},
			fields: fields{
				jsonDevices: map[string]jsonDevice{
					"/dev/sda": {
						serialNumber: "Serial123",
						jsonData:     "data1",
					},
					"/dev/sdb": {
						serialNumber: "Serial123",
						jsonData:     "data2",
					},
				},
			},
			wantRunner: runner{
				jsonDevices: map[string]jsonDevice{
					"/dev/sda": {
						serialNumber: "Serial123",
						jsonData:     "data1",
					},
				},
			},
		},
		{
			name: "+JSONRunnerWithPrevData",
			args: args{jsonRunner: true},
			fields: fields{
				devices: map[string]deviceParser{
					"/dev/sda": {SerialNumber: "Serial123"},
					"/dev/sdb": {SerialNumber: "Serial123"},
				},
				jsonDevices: map[string]jsonDevice{
					"/dev/sda": {
						serialNumber: "Serial123",
						jsonData:     "data1",
					},
					"/dev/sdb": {
						serialNumber: "Serial123",
						jsonData:     "data2",
					},
				},
			},
			wantRunner: runner{
				devices: map[string]deviceParser{
					"/dev/sda": {SerialNumber: "Serial123"},
					"/dev/sdb": {SerialNumber: "Serial123"},
				},
				jsonDevices: map[string]jsonDevice{
					"/dev/sda": {
						serialNumber: "Serial123",
						jsonData:     "data1",
					},
				},
			},
		},
		{
			name: "+deviceRunnerWithPrevData",
			args: args{jsonRunner: false},
			fields: fields{
				devices: map[string]deviceParser{
					"/dev/sda": {SerialNumber: "Serial123"},
					"/dev/sdb": {SerialNumber: "Serial123"},
				},
				jsonDevices: map[string]jsonDevice{
					"/dev/sda": {
						serialNumber: "Serial123",
						jsonData:     "data1",
					},
					"/dev/sdb": {
						serialNumber: "Serial123",
						jsonData:     "data2",
					},
				},
			},
			wantRunner: runner{
				devices: map[string]deviceParser{
					"/dev/sda": {SerialNumber: "Serial123"},
				},
				jsonDevices: map[string]jsonDevice{
					"/dev/sda": {
						serialNumber: "Serial123",
						jsonData:     "data1",
					},
					"/dev/sdb": {
						serialNumber: "Serial123",
						jsonData:     "data2",
					},
				},
			},
		},
		{
			name: "+nonJSONRunnerWithUniqueDevices",
			args: args{jsonRunner: false},
			fields: fields{
				devices: map[string]deviceParser{
					"/dev/sda": {SerialNumber: "Serial123"},
					"/dev/sdb": {SerialNumber: "Serial456"},
				},
			},
			wantRunner: runner{
				devices: map[string]deviceParser{
					"/dev/sda": {SerialNumber: "Serial123"},
					"/dev/sdb": {SerialNumber: "Serial456"},
				},
			},
		},
		{
			name: "+nonJSONRunnerWithDuplicateDevices",
			args: args{jsonRunner: false},
			fields: fields{
				devices: map[string]deviceParser{
					"/dev/sda": {SerialNumber: "Serial123"},
					"/dev/sdb": {SerialNumber: "Serial123"},
				},
			},
			wantRunner: runner{
				devices: map[string]deviceParser{
					"/dev/sda": {SerialNumber: "Serial123"},
				},
			},
		},
		{
			name: "+jsonRunnerWithTwoDuplicateDevices",
			args: args{jsonRunner: false},
			fields: fields{
				devices: map[string]deviceParser{
					"/dev/sda": {SerialNumber: "Serial123"},
					"/dev/sdb": {SerialNumber: "Serial123"},
					"/dev/sdc": {SerialNumber: "Serial123"},
				},
			},
			wantRunner: runner{
				devices: map[string]deviceParser{
					"/dev/sda": {SerialNumber: "Serial123"},
				},
			},
		},
		{
			name: "-jsonRunnerWithoutDevices",
			args: args{jsonRunner: false},
			fields: fields{
				devices: map[string]deviceParser{},
			},
			wantRunner: runner{
				devices: map[string]deviceParser{},
			},
		},
		{
			name: "-dataDifferenceLoss",
			args: args{jsonRunner: true},
			fields: fields{
				jsonDevices: map[string]jsonDevice{
					"/dev/sda": {
						serialNumber: "Serial123",
						jsonData:     "data1",
					},
					"/dev/sdb": {
						serialNumber: "Serial123",
						jsonData:     "data2",
					},
				},
			},
			wantRunner: runner{
				jsonDevices: map[string]jsonDevice{
					"/dev/sda": {
						serialNumber: "Serial123",
						jsonData:     "data1",
					},
				},
			},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			r := &runner{
				devices:     tt.fields.devices,
				jsonDevices: tt.fields.jsonDevices,
			}

			r.parseOutput(tt.args.jsonRunner)

			if diff := cmp.Diff(
				*r,
				tt.wantRunner,
				cmp.AllowUnexported(jsonDevice{}, deviceInfo{}, runner{}),
			); diff != "" {
				t.Fatalf("runner.parseOutput() runner = %s", diff)
			}
		})
	}
}

func TestPlugin_getDevices(t *testing.T) {
	t.Parallel()

	type expect struct {
		raidScanExec bool
		scanCMD      []string
		raidCMD      []string
	}

	type fields struct {
		basicScanOut []byte
		basicScanErr error

		raidScanOut []byte
		raidScanErr error
	}

	type args struct {
		scanCMD []string
		raidCMD []string
	}

	defaultScan := []string{"--scan", "-j"}
	defaultRaidScan := []string{"--scan", "-d", "sat", "-j"}

	tests := []struct {
		name         string
		args         args
		expect       expect
		fields       fields
		wantBasic    []deviceInfo
		wantRaid     []deviceInfo
		wantMegaraid []deviceInfo
		wantErr      bool
	}{
		{
			name: "+env1",
			args: args{
				scanCMD: defaultScan,
				raidCMD: defaultRaidScan,
			},
			expect: expect{
				raidScanExec: true,
				scanCMD:      defaultScan,
				raidCMD:      defaultRaidScan,
			},
			fields: fields{
				basicScanOut: readControllerEnvironment(t, "env_1").AllDevicesScan,
				raidScanOut:  readControllerEnvironment(t, "env_1").RaidDevicesScan,
			},
			wantBasic: []deviceInfo{
				{
					Name:     "/dev/csmi0,0",
					InfoName: "/dev/csmi0,0",
					DevType:  "ata",
				},
				{
					Name:     "/dev/csmi0,2",
					InfoName: "/dev/csmi0,2",
					DevType:  "ata",
				},
				{
					Name:     "/dev/csmi0,3",
					InfoName: "/dev/csmi0,3",
					DevType:  "ata",
				},
				{
					Name:     "/dev/sdb",
					InfoName: "/dev/sdb",
					DevType:  "scsi",
				},
			},
			wantRaid: []deviceInfo{
				{
					Name:     "/dev/sda",
					InfoName: "/dev/sda [SAT]",
					DevType:  "sat",
				},
			},
			wantMegaraid: nil,
			wantErr:      false,
		},
		{
			name: "+envMac",
			args: args{
				scanCMD: defaultScan,
				raidCMD: defaultRaidScan,
			},
			expect: expect{
				raidScanExec: true,
				scanCMD:      defaultScan,
				raidCMD:      defaultRaidScan,
			},
			fields: fields{
				basicScanOut: readControllerFixture(t, "discovery/macos_basic_scan.json"),
				raidScanOut:  readControllerFixture(t, "discovery/macos_raid_scan.json"),
			},
			wantBasic: []deviceInfo{
				{
					Name:     "IOService:/AppleARMPE/arm-io@10F00000/AppleT811xIO/ans@77400000/AppleASCWrapV4/iop-ans-nub/RTBuddy(ANS2)/RTBuddyService/AppleANS3NVMeController/NS_01@1", //nolint:lll
					InfoName: "IOService:/AppleARMPE/arm-io@10F00000/AppleT811xIO/ans@77400000/AppleASCWrapV4/iop-ans-nub/RTBuddy(ANS2)/RTBuddyService/AppleANS3NVMeController/NS_01@1", //nolint:lll
					DevType:  "nvme",
				},
			},
			wantRaid:     nil,
			wantMegaraid: nil,
			wantErr:      false,
		},
		{
			name: "+HBA_with_SAS_1",
			args: args{
				scanCMD: defaultScan,
				raidCMD: defaultRaidScan,
			},
			expect: expect{
				raidScanExec: true,
				scanCMD:      defaultScan,
				raidCMD:      defaultRaidScan,
			},
			fields: fields{
				basicScanOut: readControllerEnvironment(t, "HBA_with_SAS_1").AllDevicesScan,
				raidScanOut: readControllerEnvironment(t,
					"HBA_with_SAS_1",
				).RaidDevicesScan,
			},
			wantBasic: nil,
			wantRaid: []deviceInfo{
				{
					Name:     "/dev/sda",
					InfoName: "/dev/sda",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdaa",
					InfoName: "/dev/sdaa",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdab",
					InfoName: "/dev/sdab",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdac",
					InfoName: "/dev/sdac",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdad",
					InfoName: "/dev/sdad",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdae",
					InfoName: "/dev/sdae",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdaf",
					InfoName: "/dev/sdaf",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdb",
					InfoName: "/dev/sdb",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdc",
					InfoName: "/dev/sdc",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdd",
					InfoName: "/dev/sdd",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sde",
					InfoName: "/dev/sde",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdf",
					InfoName: "/dev/sdf",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdg",
					InfoName: "/dev/sdg",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdh",
					InfoName: "/dev/sdh",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdi",
					InfoName: "/dev/sdi",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdj",
					InfoName: "/dev/sdj",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdk",
					InfoName: "/dev/sdk",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdl",
					InfoName: "/dev/sdl",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdm",
					InfoName: "/dev/sdm",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdn",
					InfoName: "/dev/sdn",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdo",
					InfoName: "/dev/sdo",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdp",
					InfoName: "/dev/sdp",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdq",
					InfoName: "/dev/sdq",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdr",
					InfoName: "/dev/sdr",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sds",
					InfoName: "/dev/sds",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdt",
					InfoName: "/dev/sdt",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdu",
					InfoName: "/dev/sdu",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdv",
					InfoName: "/dev/sdv",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdw",
					InfoName: "/dev/sdw",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdx",
					InfoName: "/dev/sdx",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdy",
					InfoName: "/dev/sdy",
					DevType:  "scsi",
				},
				{
					Name:     "/dev/sdz",
					InfoName: "/dev/sdz",
					DevType:  "scsi",
				},
			},
			wantMegaraid: nil,
			wantErr:      false,
		},
		{
			name: "+byIDSCan",
			args: args{
				scanCMD: []string{"--scan", "-d", "by-id", "-j"},
				raidCMD: []string{"--scan", "-d", "by-id", "-d", "sat", "-j"},
			},
			expect: expect{
				raidScanExec: true,
				scanCMD:      []string{"--scan", "-d", "by-id", "-j"},
				raidCMD:      []string{"--scan", "-d", "by-id", "-d", "sat", "-j"},
			},
			fields: fields{
				basicScanOut: []byte(`{
												  "json_format_version": [
												    1,
												    0
												  ],
												  "smartctl": {
												    "version": [
												      7,
												      3
												    ],
												    "svn_revision": "5338",
												    "platform_info": "x86_64-linux-6.1.0-32-amd64",
												    "build_info": "(local build)",
												    "argv": [
												      "smartctl",
												      "--scan",
												      "-d",
												      "by-id",
												      "-j"
												    ],
												    "exit_status": 0
												  },
												  "devices": [
												    {
												      "name": "/dev/disk/by-id/ata-TOSHIBA_MQ01ABF050_X6GMTKX2T",
												      "info_name": "/dev/disk/by-id/ata-TOSHIBA_MQ01ABF050_X6GMTKX2T",
												      "type": "scsi",
												      "protocol": "SCSI"
												    }
												  ]
												}
											`),
				raidScanOut: []byte(`{
												  "json_format_version": [
													1,
													0
												  ],
												  "smartctl": {
													"version": [
													  7,
													  3
													],
													"svn_revision": "5338",
													"platform_info": "x86_64-linux-6.1.0-32-amd64",
													"build_info": "(local build)",
													"argv": [
													  "smartctl",
													  "--scan",
													  "-d",
													  "by-id",
													  "-d",
													  "sat",
													  "-j"
													],
													"exit_status": 0
												  },
												  "devices": [
													{
													  "name": "/dev/disk/by-id/ata-TOSHIBA_MQ01ABF050_X6GMTKX2T",
													  "info_name": "/dev/disk/by-id/ata-TOSHIBA_MQ01ABF050_X6GMTKX2T",
													  "type": "scsi",
													  "protocol": "SCSI"
													}
												  ]
												}
											`),
			},
			wantBasic: nil,
			wantRaid: []deviceInfo{
				{
					Name:     "/dev/disk/by-id/ata-TOSHIBA_MQ01ABF050_X6GMTKX2T",
					InfoName: "/dev/disk/by-id/ata-TOSHIBA_MQ01ABF050_X6GMTKX2T",
					DevType:  "scsi",
				},
			},
			wantMegaraid: nil,
			wantErr:      false,
		},
		{
			name: "-basicScanErr",
			args: args{
				scanCMD: defaultScan,
				raidCMD: defaultRaidScan,
			},
			expect: expect{
				raidScanExec: false,
				scanCMD:      defaultScan,
				raidCMD:      defaultRaidScan,
			},
			fields: fields{
				basicScanOut: readControllerFixture(t, "discovery/basic_scan.json"),
				basicScanErr: errors.New("fail"),
			},
			wantBasic:    nil,
			wantRaid:     nil,
			wantMegaraid: nil,
			wantErr:      true,
		},
		{
			name: "-raidScanErr",
			args: args{
				scanCMD: defaultScan,
				raidCMD: defaultRaidScan,
			},
			expect: expect{
				raidScanExec: true,
				scanCMD:      defaultScan,
				raidCMD:      defaultRaidScan,
			},
			fields: fields{
				basicScanOut: readControllerFixture(t, "discovery/basic_scan.json"),
				raidScanOut:  readControllerFixture(t, "discovery/sat_scan.json"),
				raidScanErr:  errors.New("fail"),
			},
			wantBasic:    nil,
			wantRaid:     nil,
			wantMegaraid: nil,
			wantErr:      true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			responses := []controllerResponse{
				{
					args:   tt.expect.scanCMD,
					output: tt.fields.basicScanOut,
					err:    tt.fields.basicScanErr,
				},
			}

			if tt.expect.raidScanExec {
				responses = append(responses, controllerResponse{
					args:   tt.expect.raidCMD,
					output: tt.fields.raidScanOut,
					err:    tt.fields.raidScanErr,
				})
			}

			p := &Plugin{ctl: newFixtureController(t, responses...)}

			gotBasic, gotRaid, gotMegaraid, err := p.getDevices(
				tt.args.scanCMD, tt.args.raidCMD,
			)
			if (err != nil) != tt.wantErr {
				t.Fatalf(
					"Plugin.getDevices() error = %v, wantErr %v",
					err,
					tt.wantErr,
				)
			}

			if diff := cmp.Diff(
				tt.wantBasic, gotBasic,
				cmp.AllowUnexported(deviceInfo{}),
			); diff != "" {
				t.Fatalf(
					"Plugin.getDevices() Basic devices mismatch (-want +got):\n%s",
					diff,
				)
			}

			if diff := cmp.Diff(
				tt.wantRaid, gotRaid,
				cmp.AllowUnexported(deviceInfo{}),
			); diff != "" {
				t.Fatalf(
					"Plugin.getDevices() Raid devices mismatch (-want +got):\n%s",
					diff,
				)
			}

			if diff := cmp.Diff(
				tt.wantMegaraid, gotMegaraid,
				cmp.AllowUnexported(deviceInfo{}),
			); diff != "" {
				t.Fatalf(
					"Plugin.getDevices() MegaRaid devices mismatch (-want +got):\n%s",
					diff,
				)
			}
		})
	}
}

func Test_formatDeviceOutput(t *testing.T) {
	t.Parallel()

	sampleBasicDev1 := deviceInfo{
		Name:     "/dev/csmi0,0",
		InfoName: "/dev/csmi0,0",
		DevType:  "ata",
	}

	sampleBasicDev2 := deviceInfo{
		Name:     "/dev/csmi0,2",
		InfoName: "/dev/csmi0,2",
		DevType:  "ata",
	}

	sampleRaidDev1 := deviceInfo{
		Name:     "/dev/sda",
		InfoName: "/dev/sda [SAT]",
		DevType:  "sat",
	}

	sampleRaidDev2 := deviceInfo{
		Name:     "/dev/sdb",
		InfoName: "/dev/sdb [SAT]",
		DevType:  "sat",
	}

	sampleMegaraidDev1 := deviceInfo{
		Name:     "frogs_hallucination",
		InfoName: "frogs_hallucination",
		DevType:  "megaraid",
	}

	sampleMegaraidDev2 := deviceInfo{
		Name:     "cows_imagination",
		InfoName: "cows_imagination",
		DevType:  "megaraid",
	}

	type args struct {
		basic []deviceInfo
		raid  []deviceInfo
	}

	tests := []struct {
		name            string
		args            args
		wantBasicDev    []deviceInfo
		wantRaidDev     []deviceInfo
		wantMegaraidDev []deviceInfo
	}{
		{
			name: "+valid",
			args: args{
				basic: []deviceInfo{sampleBasicDev1, sampleBasicDev2},
				raid: []deviceInfo{
					sampleRaidDev1, sampleRaidDev2,
					sampleMegaraidDev1, sampleMegaraidDev2,
				},
			},
			wantBasicDev:    []deviceInfo{sampleBasicDev1, sampleBasicDev2},
			wantRaidDev:     []deviceInfo{sampleRaidDev1, sampleRaidDev2},
			wantMegaraidDev: []deviceInfo{sampleMegaraidDev1, sampleMegaraidDev2},
		},
		{
			name: "+megaraidDevices",
			args: args{
				basic: []deviceInfo{},
				raid: []deviceInfo{
					sampleRaidDev1, sampleRaidDev2,
					sampleMegaraidDev1, sampleMegaraidDev2,
				},
			},
			wantBasicDev:    nil,
			wantRaidDev:     []deviceInfo{sampleRaidDev1, sampleRaidDev2},
			wantMegaraidDev: []deviceInfo{sampleMegaraidDev1, sampleMegaraidDev2},
		},
		{
			name: "-duplicateDevInRaidAndBasic",
			args: args{
				basic: []deviceInfo{sampleBasicDev1, sampleRaidDev1},
				raid:  []deviceInfo{sampleRaidDev1, sampleRaidDev2},
			},
			wantBasicDev:    []deviceInfo{sampleBasicDev1},
			wantRaidDev:     []deviceInfo{sampleRaidDev1, sampleRaidDev2},
			wantMegaraidDev: nil,
		},
		{
			name: "-noDevices",
			args: args{
				basic: []deviceInfo{},
				raid:  []deviceInfo{},
			},
			wantBasicDev:    nil,
			wantRaidDev:     nil,
			wantMegaraidDev: nil,
		},
		{
			name: "-nilDevices",
			args: args{
				basic: nil,
				raid:  nil,
			},
			wantBasicDev:    nil,
			wantRaidDev:     nil,
			wantMegaraidDev: nil,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			gotBasicDev, gotRaidDev, gotMegaraidDev := formatDeviceOutput(
				tt.args.basic, tt.args.raid,
			)

			if diff := cmp.Diff(
				tt.wantBasicDev, gotBasicDev,
				cmp.AllowUnexported(deviceInfo{}),
			); diff != "" {
				t.Fatalf("formatDeviceOutput() gotBasicDev = %s", diff)
			}

			if diff := cmp.Diff(
				tt.wantRaidDev, gotRaidDev,
				cmp.AllowUnexported(deviceInfo{}),
			); diff != "" {
				t.Fatalf("formatDeviceOutput() gotRaidDev = %s", diff)
			}

			if diff := cmp.Diff(
				tt.wantMegaraidDev, gotMegaraidDev,
				cmp.AllowUnexported(deviceInfo{}),
			); diff != "" {
				t.Fatalf("formatDeviceOutput() gotMegaraidDev = %s", diff)
			}
		})
	}
}

func TestPlugin_scanDevices(t *testing.T) {
	t.Parallel()

	type fields struct {
		execErr error
		execOut []byte
	}

	type args struct {
		args []string
	}

	tests := []struct {
		name    string
		fields  fields
		args    args
		want    []deviceInfo
		wantErr bool
	}{
		{
			name: "+valid",
			fields: fields{
				execOut: readControllerFixture(t, "discovery/basic_scan.json"),
			},
			args: args{
				args: []string{"--scan", "-j"},
			},
			want: []deviceInfo{
				{
					Name:     "/dev/csmi0,0",
					InfoName: "/dev/csmi0,0",
					DevType:  "ata",
				},
				{
					Name:     "/dev/csmi0,2",
					InfoName: "/dev/csmi0,2",
					DevType:  "ata",
				},
				{
					Name:     "/dev/csmi0,3",
					InfoName: "/dev/csmi0,3",
					DevType:  "ata",
				},
			},
			wantErr: false,
		},
		{
			name: "-execErr",
			fields: fields{
				execOut: readControllerFixture(t, "discovery/basic_scan.json"),
				execErr: errors.New("fail"),
			},
			args:    args{args: []string{"--scan", "-j"}},
			want:    nil,
			wantErr: true,
		},
		{
			name:    "-marshalErr",
			fields:  fields{execOut: []byte("{")},
			args:    args{args: []string{"--scan", "-j"}},
			want:    nil,
			wantErr: true,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			ctl := newFixtureController(t, controllerResponse{
				args:   tt.args.args,
				output: tt.fields.execOut,
				err:    tt.fields.execErr,
			})

			p := &Plugin{ctl: ctl}

			got, err := p.scanDevices(tt.args.args...)
			if (err != nil) != tt.wantErr {
				t.Fatalf(
					"Plugin.scanDevices() error = %v, wantErr %v",
					err, tt.wantErr,
				)
			}

			if diff := cmp.Diff(
				tt.want, got, cmp.AllowUnexported(deviceInfo{}),
			); diff != "" {
				t.Fatalf("Plugin.scanDevices() = %s", diff)
			}
		})
	}
}

func TestPlugin_scanDevicesWithSCSIAndMegaRAID(t *testing.T) {
	t.Parallel()

	ctl := newFixtureController(t, controllerResponse{
		args:   []string{"--scan", "-j"},
		output: readControllerFixture(t, "discovery/mixed_devices_scan.json"),
	})

	p := &Plugin{ctl: ctl}

	got, err := p.scanDevices("--scan", "-j")
	if err != nil {
		t.Fatalf("Plugin.scanDevices() error = %v", err)
	}

	const wantDeviceCount = 5
	if len(got) != wantDeviceCount {
		t.Fatalf("Plugin.scanDevices() device count = %d, want %d", len(got), wantDeviceCount)
	}

	want := map[string]deviceInfo{
		"/dev/sda:scsi": {
			Name:     "/dev/sda",
			InfoName: "/dev/sda",
			DevType:  "scsi",
		},
		"/dev/bus/0:megaraid,0": {
			Name:     "/dev/bus/0",
			InfoName: "/dev/bus/0 [megaraid_disk_0]",
			DevType:  "megaraid,0",
		},
		"/dev/nvme0:nvme": {
			Name:     "/dev/nvme0",
			InfoName: "/dev/nvme0",
			DevType:  "nvme",
		},
	}

	found := make(map[string]deviceInfo, len(want))

	for _, device := range got {
		key := device.Name + ":" + device.DevType
		if _, ok := want[key]; ok {
			found[key] = device
		}
	}

	if diff := cmp.Diff(want, found, cmp.AllowUnexported(deviceInfo{})); diff != "" {
		t.Fatalf("Plugin.scanDevices() representative devices mismatch (-want +got):\n%s", diff)
	}
}
