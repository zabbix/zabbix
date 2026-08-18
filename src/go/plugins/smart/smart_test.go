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

//nolint:goconst // Table-driven tests intentionally repeat literal inputs and expected values.
package smart

import (
	"encoding/json"
	"fmt"
	"reflect"
	"testing"
	"time"

	"github.com/google/go-cmp/cmp"
	"golang.zabbix.com/sdk/errs"
)

const (
	nvme = `{
		"smartctl": {
		  "exit_status": 0
		},
		"device": {
		  "name": "/dev/nvme0",
		  "type": "nvme"
		},
		"model_name": "INTEL SSDPEKNW512G8H",
		"serial_number": "BTNH115603K7512A",
		"firmware_version": "HPS1",
		"smart_status": {
		  "passed": true
		},
		"nvme_smart_health_information_log": {
		  "critical_warning": 0,
		  "temperature": 25,
		  "percentage_used": 0,
		  "power_on_hours": 2222,
		  "media_errors": 0
		}
	  }`

	nvmeMediaErrorOverflow = `{
		"smartctl": {
		  "exit_status": 0
		},
		"device": {
		  "name": "/dev/nvme0",
		  "type": "nvme"
		},
		"model_name": "INTEL SSDPEKNW512G8H",
		"serial_number": "BTNH115603K7512A",
		"firmware_version": "HPS1",
		"smart_status": {
		  "passed": true
		},
		"nvme_smart_health_information_log": {
		  "critical_warning": 0,
		  "temperature": 25,
		  "percentage_used": 0,
		  "power_on_hours": 2222,
		  "media_errors": 12345678901234567890
		}
	  }`

	hdd = `{
		"json_format_version": [
			1,
			0
		],
		"smartctl": {
			"version": [
				7,
				2
			],
			"svn_revision": "5155",
			"platform_info": "x86_64-linux-5.13.0-30-generic",
			"build_info": "(local build)",
			"argv": [
				"smartctl",
				"-a",
				"-j",
				"/dev/sda"
			],
			"exit_status": 0
		},
		"device": {
			"name": "/dev/sda",
			"info_name": "/dev/sda [SAT]",
			"type": "sat",
			"protocol": "ATA"
		},
		"model_family": "Seagate Surveillance",
		"model_name": "ST1000VX000-1ES162",
		"serial_number": "Z4Y7SJBD",
		"wwn": {
			"naa": 5,
			"oui": 3152,
			"id": 2071267458
		},
		"firmware_version": "CV26",
		"rotation_rate": 7200,
		"ata_smart_data": {
			"self_test": {
				"status": {
					"value": 0,
					"string": "completed without error",
					"passed": true
				}
			},
			"capabilities": {
				"self_tests_supported": true
			}
		},
		"ata_smart_attributes": {
 			"table": [
				{
					"id": 1,
					"name": "Raw_Read_Error_Rate",
					"raw": {
						"value": 182786912,
						"string": "182786912"
					}
				},
				{
					"id": 3,
					"name": "Spin_Up_Time",
					"raw": {
						"value": 0,
						"string": "0"
					}
				}
			]
		},
		"power_on_time": {
			"hours": 39153
		},
		 "temperature": {
			"current": 30
		}
	}`

	ssd = `
	{
		"json_format_version": [
		  1,
		  0
		],
		"smartctl": {
		  "exit_status": 0
		},
		"device": {
		  "name": "/dev/sda",
		  "info_name": "/dev/sda",
		  "type": "ata",
		  "protocol": "ATA"
		},
		"model_name": "TS128GMTS800",
		"serial_number": "D486530350",
		"firmware_version": "O1225G",
		"rotation_rate": 0,
		"smart_status": {
		  "passed": true
		},
		"ata_smart_data": {
		  "self_test": {
			"status": {
			  "passed": true
			}
		  },
		  "capabilities": {
			"values": [
			  113,
			  2
			],
			"self_tests_supported": true
		  }
		},
		"ata_smart_attributes": {
 		  "table": [
			{
 			  "name": "Raw_Read_Error_Rate",
			  "value": 100,
			  "raw": {
				"value": 0,
				"string": "0"
			  }
			},
			{
 			  "name": "Reallocated_Sector_Ct",
			  "raw": {
				"value": 10,
				"string": "10"
			  }
			},
			{
 			  "name": "Zero_Norm_Value",
			  "value": 0,
			  "raw": {
				"value": 15,
				"string": "15"
			  }
			}
		  ]
		},
		"power_on_time": {
		  "hours": 732
		},
 		"temperature": {
		  "current": 18
		}
	  }`

	ssdUnknown = `
	  {
		  "json_format_version": [
			1,
			0
		  ],
		  "smartctl": {
			"exit_status": 0
		  },
		  "device": {
			"name": "/dev/sda",
			"info_name": "/dev/sda",
			"type": "ata",
			"protocol": "ATA"
		  },
		  "model_name": "TS128GMTS800",
		  "serial_number": "D486530350",
		  "firmware_version": "O1225G",
		  "rotation_rate": 0,
		  "smart_status": {
			"passed": true
		  },
		  "ata_smart_data": {
			"self_test": {
			  "status": {
				"passed": true
			  }
			},
			"capabilities": {
			  "values": [
				113,
				2
			  ],
			  "self_tests_supported": true
			}
		  },
		  "ata_smart_attributes": {
			 "table": [
			  {
				"name": "Raw_Read_Error_Rate",
				"value": 100,
				"raw": {
				  "value": 0,
				  "string": "0"
				}
			  },
			  {
				"name": "Unknown_Attribute",
			   "value": 0,
			   "raw": {
				 "value": 0,
				 "string": "0"
			   }
			 },
			  {
				 "name": "Reallocated_Sector_Ct",
				"raw": {
				  "value": 10,
				  "string": "10"
				}
			  }
			]
		  },
		  "power_on_time": {
			"hours": 732
		  },
		   "temperature": {
			"current": 18
		  }
		}`
)

const (
	ataSelfTestNotCapable = `
	{
		"json_format_version": [
		  1,
		  0
		],
		"smartctl": {
		  "exit_status": 0
		},
		"device": {
		  "name": "/dev/sda",
		  "info_name": "/dev/sda",
		  "type": "ata",
		  "protocol": "ATA"
		},
		"model_name": "TS128GMTS800",
		"serial_number": "D486530350",
		"firmware_version": "O1225G",
		"rotation_rate": 0,
		"smart_status": {
		  "passed": true
		},
		"ata_smart_data": {
		  "capabilities": {
			"values": [
			  113,
			  2
			],
			"self_tests_supported": false
		  }
		},
		"ata_smart_attributes": {
 		  "table": [
			{
 			  "name": "Raw_Read_Error_Rate",
			  "value": 100,
			  "raw": {
				"value": 0,
				"string": "0"
			  }
			},
			{
 			  "name": "Reallocated_Sector_Ct",
			  "raw": {
				"value": 10,
				"string": "10"
			  }
			},
			{
 			  "name": "Zero_Norm_Value",
			  "value": 0,
			  "raw": {
				"value": 15,
				"string": "15"
			  }
			}
		  ]
		},
		"power_on_time": {
		  "hours": 732
		},
 		"temperature": {
		  "current": 18
		}
	  }`

	//nolint:gosec
	ataSelfTestNotPassed = `
	{
		"json_format_version": [
		  1,
		  0
		],
		"smartctl": {
		  "exit_status": 0
		},
		"device": {
		  "name": "/dev/sda",
		  "info_name": "/dev/sda",
		  "type": "ata",
		  "protocol": "ATA"
		},
		"model_name": "TS128GMTS800",
		"serial_number": "D486530350",
		"firmware_version": "O1225G",
		"rotation_rate": 0,
		"smart_status": {
		  "passed": true
		},
		"ata_smart_data": {
		  "self_test": {
			"status": {
			  "value": 80,
			  "string": "completed with error (electrical test element)",
			  "passed": false
			}
		  },
		  "capabilities": {
			"values": [
			  113,
			  2
			],
			"self_tests_supported": true
		  }
		},
		"ata_smart_attributes": {
 		  "table": [
			{
 			  "name": "Raw_Read_Error_Rate",
			  "value": 100,
			  "raw": {
				"value": 0,
				"string": "0"
			  }
			},
			{
 			  "name": "Reallocated_Sector_Ct",
			  "raw": {
				"value": 10,
				"string": "10"
			  }
			},
			{
 			  "name": "Zero_Norm_Value",
			  "value": 0,
			  "raw": {
				"value": 15,
				"string": "15"
			  }
			}
		  ]
		},
		"power_on_time": {
		  "hours": 732
		},
 		"temperature": {
		  "current": 18
		}
	  }`

	ataSelfTestInProgress = `
	{
		"json_format_version": [
		  1,
		  0
		],
		"smartctl": {
		  "exit_status": 0
		},
		"device": {
		  "name": "/dev/sda",
		  "info_name": "/dev/sda",
		  "type": "ata",
		  "protocol": "ATA"
		},
		"model_name": "TS128GMTS800",
		"serial_number": "D486530350",
		"firmware_version": "O1225G",
		"rotation_rate": 0,
		"smart_status": {
		  "passed": true
		},
		"ata_smart_data": {
		  "self_test": {
			"status": {
			  "value": 248,
			   "string": "in progress, 80% remaining",
               "remaining_percent": 80
			}
		  },
		  "capabilities": {
			"values": [
			  113,
			  2
			],
			"self_tests_supported": true
		  }
		},
		"ata_smart_attributes": {
 		  "table": [
			{
 			  "name": "Raw_Read_Error_Rate",
			  "value": 100,
			  "raw": {
				"value": 0,
				"string": "0"
			  }
			},
			{
 			  "name": "Reallocated_Sector_Ct",
			  "raw": {
				"value": 10,
				"string": "10"
			  }
			},
			{
 			  "name": "Zero_Norm_Value",
			  "value": 0,
			  "raw": {
				"value": 15,
				"string": "15"
			  }
			}
		  ]
		},
		"power_on_time": {
		  "hours": 732
		},
 		"temperature": {
		  "current": 18
		}
	  }`

	ataSelfTestInterrupted = `
	{
		"json_format_version": [
		  1,
		  0
		],
		"smartctl": {
		  "exit_status": 0
		},
		"device": {
		  "name": "/dev/sda",
		  "info_name": "/dev/sda",
		  "type": "ata",
		  "protocol": "ATA"
		},
		"model_name": "TS128GMTS800",
		"serial_number": "D486530350",
		"firmware_version": "O1225G",
		"rotation_rate": 0,
		"smart_status": {
		  "passed": true
		},
		"ata_smart_data": {
		  "self_test": {
			"status": {
			  "value": 16,
			  "string": "was aborted by the host"
			}
		  },
		  "capabilities": {
			"values": [
			  113,
			  2
			],
			"self_tests_supported": true
		  }
		},
		"ata_smart_attributes": {
 		  "table": [
			{
 			  "name": "Raw_Read_Error_Rate",
			  "value": 100,
			  "raw": {
				"value": 0,
				"string": "0"
			  }
			},
			{
 			  "name": "Reallocated_Sector_Ct",
			  "raw": {
				"value": 10,
				"string": "10"
			  }
			},
			{
 			  "name": "Zero_Norm_Value",
			  "value": 0,
			  "raw": {
				"value": 15,
				"string": "15"
			  }
			}
		  ]
		},
		"power_on_time": {
		  "hours": 732
		},
 		"temperature": {
		  "current": 18
		}
	  }`
)

//nolint:gochecknoglobals // Shared immutable test fixtures are reused across table-driven tests.
var (
	table1 = table{
		Attrname: "test1",
		ID:       1,
		Thresh:   11,
	}
	table2 = table{
		Attrname: "test2",
		ID:       2,
		Thresh:   22,
	}
	table3 = table{
		Attrname: "test3",
		ID:       3,
		Thresh:   33,
	}
	table4 = table{
		Attrname: "test4",
		ID:       4,
		Thresh:   44,
	}
	attrTable = table{
		Attrname: "Spin_Up_Time",
		ID:       5,
		Thresh:   55,
	}
	unknown = table{
		Attrname: "Unknown_Attribute",
		ID:       0,
		Thresh:   0,
	}
)

func intToPtr(v int) *int {
	return &v
}

func boolToPtr(v bool) *bool {
	return &v
}

func Test_diskGetSingle(t *testing.T) {
	t.Parallel()
	megaraidSCSIDrive := readControllerFixture(
		t,
		"device/megaraid_scsi.json",
	)

	type args struct {
		path     string
		raidType string
	}

	type fields struct {
		ctlOutput []byte
		ctlErr    error
	}

	type want struct {
		output  []byte
		wantErr bool
	}

	tests := []struct {
		name   string
		args   args
		fields fields
		want   want
	}{
		{
			name: "+normalValues",
			args: args{
				path:     "path",
				raidType: "rt",
			},
			fields: fields{
				ctlOutput: []byte(nvme),
				ctlErr:    nil,
			},
			want: want{
				output: []byte(
					`{"critical_warning":0,"disk_type":"nvme","error":"","exit_status":0,"firmware_version":"HPS1",` +
						`"media_errors":0,"model_name":"INTEL SSDPEKNW512G8H",` +
						`"percentage_used":0,"power_on_time":2222,"self_test_in_progress":null,` +
						`"self_test_passed":null,"serial_number":"BTNH115603K7512A","temperature":25}`,
				),
				wantErr: false,
			},
		},
		{
			name: "+valueOverflow",
			args: args{
				path:     "path",
				raidType: "rt",
			},
			fields: fields{
				ctlOutput: []byte(nvmeMediaErrorOverflow),
				ctlErr:    nil,
			},
			want: want{
				output: []byte(
					`{"critical_warning":0,"disk_type":"nvme","error":"","exit_status":0,"firmware_version":"HPS1",` +
						`"media_errors":12345678901234567890,"model_name":"INTEL SSDPEKNW512G8H",` +
						`"percentage_used":0,"power_on_time":2222,"self_test_in_progress":null,` +
						`"self_test_passed":null,"serial_number":"BTNH115603K7512A","temperature":25}`,
				),
				wantErr: false,
			},
		},
		{
			name: "+SCSIMegaRAID",
			args: args{
				path:     "/dev/bus/0",
				raidType: "megaraid,0",
			},
			fields: fields{
				ctlOutput: megaraidSCSIDrive,
				ctlErr:    nil,
			},
			want: want{
				output: []byte(
					`{"critical_warning":0,"disk_type":"hdd","error":"","exit_status":0,"firmware_version":"",` +
						`"media_errors":0,"model_name":"","percentage_used":0,"power_on_time":1000,` +
						`"self_test_in_progress":null,"self_test_passed":null,` +
						`"serial_number":"TEST-SERIAL-0001","temperature":30}`,
				),
				wantErr: false,
			},
		},
		{
			name: "-ctlError",
			args: args{
				path:     "path",
				raidType: "rt",
			},
			fields: fields{
				ctlOutput: []byte{},
				ctlErr:    errs.New("test"),
			},
			want: want{
				output:  []byte{},
				wantErr: true,
			},
		},
		{
			name: "-jsonFormatError",
			args: args{
				path:     "path",
				raidType: "rt",
			},
			fields: fields{
				ctlOutput: []byte(`{abc`),
				ctlErr:    nil,
			},
			want: want{
				output:  []byte{},
				wantErr: true,
			},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()
			ctl := newFixtureController(t, controllerResponse{
				output: tt.fields.ctlOutput,
				err:    tt.fields.ctlErr,
			})

			p := &Plugin{
				ctl: ctl,
			}

			out, err := p.diskGetSingle(tt.args.path, tt.args.raidType)
			if (err != nil) != tt.want.wantErr {
				t.Fatalf("diskGetSingle() wanted error to be %v, but got %q", tt.want.wantErr, err.Error())
			}

			if diff := cmp.Diff(string(out), string(tt.want.output)); diff != "" {
				t.Fatalf("diskGetSingle() output differs from expected %s", diff)
			}
		})
	}
}

func Test_setSingleDiskFields(t *testing.T) {
	var nilReference *bool

	type args struct {
		dev []byte
	}
	tests := []struct {
		name    string
		args    args
		wantOut map[string]any
		wantErr bool
	}{
		{
			name: "nvme_device",
			args: args{dev: []byte(nvme)},
			wantOut: map[string]any{
				"critical_warning":      0,
				"disk_type":             "nvme",
				"error":                 "",
				"exit_status":           0,
				"firmware_version":      "HPS1",
				"media_errors":          json.Number("0"),
				"model_name":            "INTEL SSDPEKNW512G8H",
				"percentage_used":       0,
				"power_on_time":         2222,
				"self_test_passed":      nilReference,
				"self_test_in_progress": nilReference,
				"serial_number":         "BTNH115603K7512A",
				"temperature":           25,
			},
			wantErr: false,
		},
		{
			name: "mediaOverflow",
			args: args{dev: []byte(nvmeMediaErrorOverflow)},
			wantOut: map[string]any{
				"critical_warning":      0,
				"disk_type":             "nvme",
				"error":                 "",
				"exit_status":           0,
				"firmware_version":      "HPS1",
				"media_errors":          json.Number("12345678901234567890"),
				"model_name":            "INTEL SSDPEKNW512G8H",
				"percentage_used":       0,
				"power_on_time":         2222,
				"self_test_passed":      nilReference,
				"self_test_in_progress": nilReference,
				"serial_number":         "BTNH115603K7512A",
				"temperature":           25,
			},
			wantErr: false,
		},
		{
			name: "hdd_device",
			args: args{dev: []byte(hdd)},
			wantOut: map[string]any{
				"critical_warning":      0,
				"disk_type":             "hdd",
				"error":                 "",
				"exit_status":           0,
				"firmware_version":      "CV26",
				"media_errors":          json.Number("0"),
				"model_name":            "ST1000VX000-1ES162",
				"percentage_used":       0,
				"power_on_time":         39153,
				"self_test_passed":      boolToPtr(true),
				"self_test_in_progress": boolToPtr(false),
				"serial_number":         "Z4Y7SJBD",
				"temperature":           30,
				"raw_read_error_rate": singleRequestAttribute{
					Value: 182786912,
					Raw:   "182786912",
				},
				"spin_up_time": singleRequestAttribute{
					Value: 0,
					Raw:   "0",
				},
			},
			wantErr: false,
		},
		{
			name: "ssd_device",
			args: args{dev: []byte(ssd)},
			wantOut: map[string]any{
				"critical_warning":      0,
				"disk_type":             "ssd",
				"error":                 "",
				"exit_status":           0,
				"firmware_version":      "O1225G",
				"media_errors":          json.Number("0"),
				"model_name":            "TS128GMTS800",
				"percentage_used":       0,
				"power_on_time":         732,
				"self_test_passed":      boolToPtr(true),
				"self_test_in_progress": boolToPtr(false),
				"serial_number":         "D486530350",
				"temperature":           18,
				"raw_read_error_rate": singleRequestAttribute{
					Value:           0,
					Raw:             "0",
					NormalizedValue: intToPtr(100),
				},
				"reallocated_sector_ct": singleRequestAttribute{
					Value: 10,
					Raw:   "10",
				},
				"zero_norm_value": singleRequestAttribute{
					Value:           15,
					Raw:             "15",
					NormalizedValue: intToPtr(0),
				},
			},
			wantErr: false,
		},
		{
			name: "ssd_device_with_unknown_attribute",
			args: args{dev: []byte(ssdUnknown)},
			wantOut: map[string]any{
				"critical_warning":      0,
				"disk_type":             "ssd",
				"error":                 "",
				"exit_status":           0,
				"firmware_version":      "O1225G",
				"media_errors":          json.Number("0"),
				"model_name":            "TS128GMTS800",
				"percentage_used":       0,
				"power_on_time":         732,
				"self_test_passed":      boolToPtr(true),
				"self_test_in_progress": boolToPtr(false),
				"serial_number":         "D486530350",
				"temperature":           18,
				"raw_read_error_rate": singleRequestAttribute{
					Value:           0,
					Raw:             "0",
					NormalizedValue: intToPtr(100),
				},
				"reallocated_sector_ct": singleRequestAttribute{
					Value: 10,
					Raw:   "10",
				},
			},
			wantErr: false,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			gotOut, err := setSingleDiskFields(tt.args.dev)
			if (err != nil) != tt.wantErr {
				t.Errorf("setSingleDiskFields() error = %v, wantErr %v", err, tt.wantErr)

				return
			}

			if diff := cmp.Diff(gotOut, tt.wantOut); diff != "" {
				t.Errorf("setSingleDiskFields() = \n%v\n, want \n%v\n", gotOut, tt.wantOut)
			}
		})
	}
}

func Test_setSingleDiskFieldsWithSelfTest(t *testing.T) {
	t.Parallel()

	var nilReference *bool

	type args struct {
		dev []byte
	}

	tests := []struct {
		name    string
		args    args
		wantOut map[string]any
		wantErr bool
	}{
		{
			name: "+valid",
			args:// self test in progress case
			args{dev: []byte(ataSelfTestInProgress)},
			wantOut: map[string]any{
				"critical_warning":      0,
				"disk_type":             "ssd",
				"error":                 "",
				"exit_status":           0,
				"firmware_version":      "O1225G",
				"media_errors":          json.Number("0"),
				"model_name":            "TS128GMTS800",
				"percentage_used":       0,
				"power_on_time":         732,
				"self_test_passed":      nilReference,
				"self_test_in_progress": boolToPtr(true),
				"serial_number":         "D486530350",
				"temperature":           18,
				"raw_read_error_rate": singleRequestAttribute{
					Value:           0,
					Raw:             "0",
					NormalizedValue: intToPtr(100),
				},
				"reallocated_sector_ct": singleRequestAttribute{
					Value: 10,
					Raw:   "10",
				},
				"zero_norm_value": singleRequestAttribute{
					Value:           15,
					Raw:             "15",
					NormalizedValue: intToPtr(0),
				},
			},
			wantErr: false,
		},
		{
			name: "+ataSelfTestNotCapable",
			args: args{dev: []byte(ataSelfTestNotCapable)},
			wantOut: map[string]any{
				"critical_warning":      0,
				"disk_type":             "ssd",
				"error":                 "",
				"exit_status":           0,
				"firmware_version":      "O1225G",
				"media_errors":          json.Number("0"),
				"model_name":            "TS128GMTS800",
				"percentage_used":       0,
				"power_on_time":         732,
				"self_test_passed":      nilReference,
				"self_test_in_progress": nilReference,
				"serial_number":         "D486530350",
				"temperature":           18,
				"raw_read_error_rate": singleRequestAttribute{
					Value:           0,
					Raw:             "0",
					NormalizedValue: intToPtr(100),
				},
				"reallocated_sector_ct": singleRequestAttribute{
					Value: 10,
					Raw:   "10",
				},
				"zero_norm_value": singleRequestAttribute{
					Value:           15,
					Raw:             "15",
					NormalizedValue: intToPtr(0),
				},
			},
			wantErr: false,
		},
		{
			name: "+ataSelfTestNotPassed",
			args: args{dev: []byte(ataSelfTestNotPassed)},
			wantOut: map[string]any{
				"critical_warning":      0,
				"disk_type":             "ssd",
				"error":                 "",
				"exit_status":           0,
				"firmware_version":      "O1225G",
				"media_errors":          json.Number("0"),
				"model_name":            "TS128GMTS800",
				"percentage_used":       0,
				"power_on_time":         732,
				"self_test_passed":      boolToPtr(false),
				"self_test_in_progress": boolToPtr(false),
				"serial_number":         "D486530350",
				"temperature":           18,
				"raw_read_error_rate": singleRequestAttribute{
					Value:           0,
					Raw:             "0",
					NormalizedValue: intToPtr(100),
				},
				"reallocated_sector_ct": singleRequestAttribute{
					Value: 10,
					Raw:   "10",
				},
				"zero_norm_value": singleRequestAttribute{
					Value:           15,
					Raw:             "15",
					NormalizedValue: intToPtr(0),
				},
			},
			wantErr: false,
		},
		{
			name: "+ataSelfTestInterrupted",
			args: args{dev: []byte(ataSelfTestInterrupted)},
			wantOut: map[string]any{
				"critical_warning":      0,
				"disk_type":             "ssd",
				"error":                 "",
				"exit_status":           0,
				"firmware_version":      "O1225G",
				"media_errors":          json.Number("0"),
				"model_name":            "TS128GMTS800",
				"percentage_used":       0,
				"power_on_time":         732,
				"self_test_passed":      boolToPtr(false),
				"self_test_in_progress": boolToPtr(false),
				"serial_number":         "D486530350",
				"temperature":           18,
				"raw_read_error_rate": singleRequestAttribute{
					Value:           0,
					Raw:             "0",
					NormalizedValue: intToPtr(100),
				},
				"reallocated_sector_ct": singleRequestAttribute{
					Value: 10,
					Raw:   "10",
				},
				"zero_norm_value": singleRequestAttribute{
					Value:           15,
					Raw:             "15",
					NormalizedValue: intToPtr(0),
				},
			},
			wantErr: false,
		},
	}
	for _, tt := range tests {
		tt := tt
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			gotOut, err := setSingleDiskFields(tt.args.dev)
			if (err != nil) != tt.wantErr {
				t.Fatalf("setSingleDiskFields() w/ self-test error = %s, wantErr %t", err.Error(), tt.wantErr)
			}

			if diff := cmp.Diff(tt.wantOut, gotOut); diff != "" {
				t.Fatalf("setSingleDiskFields() %s", diff)
			}
		})
	}
}

func Test_setDiskFields(t *testing.T) {
	jsonSdaStr := `{
		"device": {"name": "/dev/sda","info_name": "/dev/sda [SAT]","type": "sat","protocol": "ATA"},"rotation_rate": 0
		}`
	sdaOutStr := map[string]any{
		"device": map[string]any{
			"name": "/dev/sda", "info_name": "/dev/sda [SAT]", "type": "sat", "protocol": "ATA",
		},
		"disk_name": "sda", "disk_type": "ssd", "rotation_rate": 0,
	}

	type args struct {
		deviceJsons map[string]jsonDevice
	}

	tests := []struct {
		name    string
		args    args
		want    []any
		wantErr bool
	}{
		{
			name:    "+one_drive",
			args:    args{deviceJsons: map[string]jsonDevice{"/dev/sda": {jsonData: jsonSdaStr}}},
			want:    []any{sdaOutStr},
			wantErr: false,
		},
		{
			name:    "-failed_json",
			args:    args{deviceJsons: map[string]jsonDevice{"/dev/sda": {jsonData: `{"device":}`}}},
			want:    nil,
			wantErr: true,
		},
		{
			name: "-failed_device_data_json",
			args: args{
				deviceJsons: map[string]jsonDevice{
					"/dev/sda": {jsonData: `{"device": foo,"rotation_rate": 0}`},
				},
			},
			want:    nil,
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got, err := setDiskFields(tt.args.deviceJsons)
			if (err != nil) != tt.wantErr {
				t.Errorf("setDiskFields() error = %v, wantErr %v", err, tt.wantErr)
				return
			}

			if fmt.Sprint(got) != fmt.Sprint(tt.want) {
				t.Errorf("setDiskFields() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_getRateFromJson(t *testing.T) {
	type args struct {
		in map[string]any
	}
	tests := []struct {
		name    string
		args    args
		wantOut int
	}{
		{
			name:    "rate",
			args:    args{in: map[string]any{"rotation_rate": 10}},
			wantOut: 10,
		},
		{
			name:    "multiple_fields",
			args:    args{in: map[string]any{"foobar": "abc", "rotation_rate": 10}},
			wantOut: 10,
		},
		{
			name:    "no_rate",
			args:    args{in: map[string]any{"foobar": "abc"}},
			wantOut: 0,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if gotOut := getRateFromJSON(tt.args.in); gotOut != tt.wantOut {
				t.Errorf("getRateFromJSON() = %v, want %v", gotOut, tt.wantOut)
			}
		})
	}
}

func Test_getTypeFromJson(t *testing.T) {
	map1 := make(map[string]any)
	map1["device"] = map[string]any{"type": "sat"}

	map2 := make(map[string]any)
	map2["device"] = map[string]any{"type": "sat", "foobar": "abc"}

	map3 := make(map[string]any)
	map3["device"] = map[string]any{"foobar": "abc"}

	type args struct {
		in map[string]any
	}
	tests := []struct {
		name    string
		args    args
		wantOut string
	}{
		{
			name:    "type",
			args:    args{in: map1},
			wantOut: "sat",
		},
		{
			name:    "multiple_fields",
			args:    args{in: map2},
			wantOut: "sat",
		},
		{
			name:    "no_type",
			args:    args{in: map3},
			wantOut: "",
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if gotOut := getTypeFromJSON(tt.args.in); gotOut != tt.wantOut {
				t.Errorf("getTypeFromJSON() = %v, want %v", gotOut, tt.wantOut)
			}
		})
	}
}

func Test_getTablesFromJson(t *testing.T) {
	map1 := make(map[string]any)
	map1["table"] = []any{table1, table2, attrTable}

	map2 := make(map[string]any)
	map2["table"] = []any{table1, table2, table4}

	attrTable1 := map[string]any{"ata_smart_attributes": map1}
	attrTable2 := map[string]any{"ata_smart_attributes": map2}
	attrTable3 := map[string]any{"ata_smart_attributes": nil}
	attrTable4 := map[string]any{"ata_smart_attributes": []table{}}
	attrTable5 := map[string]any{"ata_smart_attributes": map[string][]table{}}

	type args struct {
		in map[string]any
	}
	tests := []struct {
		name string
		args args
		want []table
	}{
		{
			name: "attr_table",
			args: args{in: attrTable1},
			want: []table{table1, table2, attrTable},
		},
		{
			name: "no_attr_table",
			args: args{in: attrTable2},
			want: []table{table1, table2, table4},
		},
		{
			name: "no_table",
			args: args{in: attrTable3},
			want: nil,
		},
		{
			name: "incorrect_table_value",
			args: args{in: attrTable4},
			want: nil,
		},
		{
			name: "empty_map",
			args: args{in: attrTable5},
			want: nil,
		},
		{
			name: "no_ata_attributes",
			args: args{in: nil},
			want: nil,
		},
		{
			name: "empty_ata_attributes",
			args: args{in: map[string]any{}},
			want: nil,
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := getTablesFromJSON(tt.args.in); !reflect.DeepEqual(got, tt.want) {
				t.Errorf("getTablesFromJSON() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_getAttributeType(t *testing.T) {
	type args struct {
		devType string
		rate    int
		tables  []table
	}
	tests := []struct {
		name string
		args args
		want string
	}{
		{
			name: "ssd_no_tables",
			args: args{
				devType: "SAT",
				rate:    0,
				tables:  nil,
			},
			want: "ssd",
		},
		{
			name: "ssd_tables_no_spin_up_table",
			args: args{
				devType: "SAT",
				rate:    0,
				tables:  []table{table1, table2, table4},
			},
			want: "ssd",
		},
		{
			name: "hdd_no_tables",
			args: args{
				devType: "SAT",
				rate:    12,
				tables:  nil,
			},
			want: "hdd",
		},
		{
			name: "hdd_rate_spin_up_table",
			args: args{
				devType: "SAT",
				rate:    12,
				tables:  []table{table1, table2, table4, attrTable},
			},
			want: "hdd",
		},
		{
			name: "hdd_no_rate_spin_up_table",
			args: args{
				devType: "SAT",
				rate:    0,
				tables:  []table{table1, table2, table4, attrTable},
			},
			want: "hdd",
		},
		{
			name: "hdd_no_spin_up_table",
			args: args{
				devType: "SAT",
				rate:    12,
				tables:  []table{table1, table2, table4},
			},
			want: "hdd",
		},
		{
			name: "unknown_no_attr_table",
			args: args{
				devType: "unknown",
				rate:    1000,
				tables:  []table{table1, table2, table4},
			},
			want: "unknown",
		},
		{
			name: "unknown_value_table",
			args: args{
				devType: "unknown",
				rate:    1000,
				tables:  []table{table1, table2, table4, attrTable},
			},
			want: "unknown",
		},
		{
			name: "unknown_no_rate_no_tables",
			args: args{
				devType: "unknown",
				rate:    0,
				tables:  nil,
			},
			want: "unknown",
		},
		{
			name: "unknown_no_rate_no_attr_table",
			args: args{
				devType: "unknown",
				rate:    0,
				tables:  []table{table1, table2, table4},
			},
			want: "unknown",
		},
		{
			name: "unknown_no_rate_value_table",
			args: args{
				devType: "unknown",
				rate:    0,
				tables:  []table{table1, table2, table4, attrTable},
			},
			want: "unknown",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := getAttributeType(tt.args.devType, tt.args.rate, tt.args.tables); got != tt.want {
				t.Errorf("getAttributeType() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_getAttributes(t *testing.T) {
	type args struct {
		in deviceParser
	}
	tests := []struct {
		name string
		args args
		want string
	}{
		{
			name: "attributes_set",
			args: args{in: deviceParser{SmartAttributes: smartAttributes{Table: []table{table1, table2}}}},
			want: "test1 test2",
		},
		{
			name: "attributes_table_empty",
			args: args{in: deviceParser{SmartAttributes: smartAttributes{Table: []table{}}}},
			want: "",
		},
		{
			name: "unknown_attributes_table_empty",
			args: args{in: deviceParser{SmartAttributes: smartAttributes{Table: []table{table1, unknown, table2}}}},
			want: "test1 test2",
		},
		{
			name: "attributes_missing",
			args: args{in: deviceParser{}},
			want: "",
		},
		{
			name: "parser_missing",
			args: args{},
			want: "",
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := getAttributes(tt.args.in); got != tt.want {
				t.Errorf("getAttributes() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_getType(t *testing.T) {
	type args struct {
		devType string
		rate    int
		tables  []table
	}
	tests := []struct {
		name    string
		args    args
		wantOut string
	}{
		{
			name: "ssd_no_tables",
			args: args{
				devType: "SAT",
				rate:    0,
				tables:  nil,
			},
			wantOut: "ssd",
		},
		{
			name: "ssd_tables_no_spin_up_table",
			args: args{
				devType: "SAT",
				rate:    0,
				tables:  []table{table1, table2, table4},
			},
			wantOut: "ssd",
		},
		{
			name: "hdd_no_tables",
			args: args{
				devType: "SAT",
				rate:    12,
				tables:  nil,
			},
			wantOut: "hdd",
		},
		{
			name: "hdd_rate_spin_up_table",
			args: args{
				devType: "SAT",
				rate:    12,
				tables:  []table{table1, table2, table4, attrTable},
			},
			wantOut: "hdd",
		},
		{
			name: "hdd_no_rate_spin_up_table",
			args: args{
				devType: "SAT",
				rate:    0,
				tables:  []table{table1, table2, table4, attrTable},
			},
			wantOut: "hdd",
		},
		{
			name: "hdd_no_spin_up_table",
			args: args{
				devType: "SAT",
				rate:    12,
				tables:  []table{table1, table2, table4},
			},
			wantOut: "hdd",
		},
		{
			name: "nvme_no_tables",
			args: args{
				devType: "nvme",
				rate:    1000,
				tables:  nil,
			},
			wantOut: "nvme",
		},
		{
			name: "nvme_no_attr_table",
			args: args{
				devType: "nvme",
				rate:    1000,
				tables:  []table{table1, table2, table4},
			},
			wantOut: "nvme",
		},
		{
			name: "nvme_value_table",
			args: args{
				devType: "nvme",
				rate:    1000,
				tables:  []table{table1, table2, table4, attrTable},
			},
			wantOut: "nvme",
		},
		{
			name: "nvme_no_rate_no_tables",
			args: args{
				devType: "nvme",
				rate:    0,
				tables:  nil,
			},
			wantOut: "nvme",
		},
		{
			name: "nvme_no_rate_no_attr_table",
			args: args{
				devType: "nvme",
				rate:    0,
				tables:  []table{table1, table2, table4},
			},
			wantOut: "nvme",
		},
		{
			name: "nvme_no_rate_value_table",
			args: args{
				devType: "nvme",
				rate:    0,
				tables:  []table{table1, table2, table4, attrTable},
			},
			wantOut: "nvme",
		},
		{
			name: "unknown_no_tables",
			args: args{
				devType: "unknown",
				rate:    1000,
				tables:  nil,
			},
			wantOut: "unknown",
		},
		{
			name: "unknown_no_attr_table",
			args: args{
				devType: "unknown",
				rate:    1000,
				tables:  []table{table1, table2, table4},
			},
			wantOut: "unknown",
		},
		{
			name: "unknown_value_table",
			args: args{
				devType: "unknown",
				rate:    1000,
				tables:  []table{table1, table2, table4, attrTable},
			},
			wantOut: "unknown",
		},
		{
			name: "unknown_no_rate_no_tables",
			args: args{
				devType: "unknown",
				rate:    0,
				tables:  nil,
			},
			wantOut: "unknown",
		},
		{
			name: "unknown_no_rate_no_attr_table",
			args: args{
				devType: "unknown",
				rate:    0,
				tables:  []table{table1, table2, table4},
			},
			wantOut: "unknown",
		},
		{
			name: "unknown_no_rate_value_table",
			args: args{
				devType: "unknown",
				rate:    0,
				tables:  []table{table1, table2, table4, attrTable},
			},
			wantOut: "unknown",
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if gotOut := getType(tt.args.devType, tt.args.rate, tt.args.tables); gotOut != tt.wantOut {
				t.Errorf("getType() = %v, want %v", gotOut, tt.wantOut)
			}
		})
	}
}

func Test_getTypeByRateAndAttr(t *testing.T) {
	type args struct {
		rate   int
		tables []table
	}
	tests := []struct {
		name string
		args args
		want string
	}{
		{
			name: "zero_rate_zero_spin_up",
			args: args{
				rate:   0,
				tables: []table{table1, table2},
			},
			want: "ssd",
		},
		{
			name: "zero_rate_no_tables",
			args: args{
				rate:   0,
				tables: nil,
			},
			want: "ssd",
		},
		{
			name: "negative_rate_no_tables",
			args: args{
				rate:   -1000,
				tables: nil,
			},
			want: "ssd",
		},
		{
			name: "positive_rate_spin_up_table",
			args: args{
				rate:   12,
				tables: []table{table1, table2, table3, attrTable},
			},
			want: "hdd",
		},
		{
			name: "positive_rate_no_tables",
			args: args{
				rate:   12,
				tables: nil,
			},
			want: "hdd",
		},
		{
			name: "zero_rate_spin_up_table",
			args: args{
				rate:   0,
				tables: []table{table1, table2, table3, attrTable},
			},
			want: "hdd",
		},
	}
	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			if got := getTypeByRateAndAttr(tt.args.rate, tt.args.tables); got != tt.want {
				t.Errorf("getTypeByRate() = %v, want %v", got, tt.want)
			}
		})
	}
}

func Test_validateParams(t *testing.T) {
	t.Parallel()

	type args struct {
		params []string
	}

	tests := []struct {
		name    string
		args    args
		wantErr bool
	}{
		{
			name:    "+valid",
			args:    args{params: []string{"/dev/sda"}},
			wantErr: false,
		},
		{
			name:    "+keyNoParams",
			args:    args{params: []string{}},
			wantErr: false,
		},
		{
			name:    "+spaceHypen",
			args:    args{params: []string{"/dev/sda -B/some/file/path"}},
			wantErr: false,
		},
		{
			name:    "+manySpacesHypen",
			args:    args{params: []string{"/dev/sda    -B/some/file/path"}},
			wantErr: false,
		},
		{
			name:    "+tabHypen",
			args:    args{params: []string{"/dev/sda\t-B/some/file/path"}},
			wantErr: false,
		},
		{
			name:    "+noSpacesHypen",
			args:    args{params: []string{"/dev/sda-B/some/file/path"}},
			wantErr: false,
		},
		{
			name:    "+hypenInSpaces",
			args:    args{params: []string{"/dev/sda - B/some/file/path"}},
			wantErr: false,
		},
		{
			name:    "+hypenEnd",
			args:    args{params: []string{"/dev/sda-"}},
			wantErr: false,
		},
		{
			name:    "+empty",
			args:    args{params: []string{""}},
			wantErr: false,
		},
		{
			name:    "+twoParams",
			args:    args{params: []string{"/dev/sda", "megaraid"}},
			wantErr: false,
		},
		{
			name:    "+threeParams",
			args:    args{params: []string{"/dev/sda", "megaraid", "three"}},
			wantErr: false,
		},
		{
			name:    "-hypenStart",
			args:    args{params: []string{"-B/some/file/path"}},
			wantErr: true,
		},
		{
			name:    "-hypenStartSpace",
			args:    args{params: []string{"- B/some/file/path"}},
			wantErr: true,
		},
		{
			name:    "-hypenStartApostr",
			args:    args{params: []string{"'-B/some/file/path'"}},
			wantErr: true,
		},
		{
			name:    "-hypenStartApostrSpace",
			args:    args{params: []string{"'   -B/some/file/path'"}},
			wantErr: true,
		},
		{
			name:    "-hypenStartApostrTab",
			args:    args{params: []string{"'\t-B/some/file/path'"}},
			wantErr: true,
		},
		{
			name:    "-hypenStartApostrTabSpace",
			args:    args{params: []string{"'\t -B/some/file/path'"}},
			wantErr: true,
		},
		{
			name:    "-hypenStart2Apostr",
			args:    args{params: []string{"''-B/some/file/path''"}},
			wantErr: true,
		},
		{
			name:    "-hypenStart3Apostr",
			args:    args{params: []string{"'''-B/some/file/path'''"}},
			wantErr: true,
		},
		{
			name:    "-hypenStartApostrQuote",
			args:    args{params: []string{"\"-B/some/file/path\""}},
			wantErr: true,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			err := validateParams(tt.args.params)

			if (err != nil) != tt.wantErr {
				t.Fatalf("validateParams() error = %s, wantErr %t", err.Error(), tt.wantErr)
			}
		})
	}
}

//nolint:paralleltest
func Test_validateExport(t *testing.T) {
	type expect struct {
		exec bool
	}

	type fields struct {
		execErr      error
		execOut      []byte
		lastVerCheck time.Time
	}

	type args struct {
		params []string
	}

	tests := []struct {
		name    string
		expect  expect
		fields  fields
		args    args
		wantErr bool
	}{
		{
			name:    "+valid",
			expect:  expect{exec: true},
			fields:  fields{execOut: readControllerFixture(t, "version/valid.json")},
			args:    args{params: nil},
			wantErr: false,
		},
		{
			name:    "+nothingToValidate",
			expect:  expect{exec: true},
			fields:  fields{execOut: readControllerFixture(t, "version/valid.json")},
			args:    args{params: nil},
			wantErr: false,
		},
		{
			name:    "+paramOk",
			expect:  expect{exec: true},
			fields:  fields{execOut: readControllerFixture(t, "version/valid.json")},
			args:    args{params: []string{"smth"}},
			wantErr: false,
		},
		{
			name:    "-badParam",
			expect:  expect{exec: false},
			fields:  fields{execOut: readControllerFixture(t, "version/valid.json")},
			args:    args{params: []string{"-Bsmth"}},
			wantErr: true,
		},
		{
			name:    "-badVersion",
			expect:  expect{exec: true},
			fields:  fields{execOut: readControllerFixture(t, "version/invalid.json")},
			args:    args{params: []string{"smth"}},
			wantErr: true,
		},
	}

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
			err := p.validateExport(tt.args.params)

			if (err != nil) != tt.wantErr {
				t.Fatalf("validateExport(key, params) error = %s, wantErr %t", err.Error(), tt.wantErr)
			}
		})
	}
}
