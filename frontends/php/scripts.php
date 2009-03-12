<?php
/* 
** ZABBIX
** Copyright (C) 2000-2007 SIA Zabbix
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
**/
?>
<?php
include_once('include/config.inc.php');
require_once('include/hosts.inc.php');
require_once('include/scripts.inc.php');
require_once('include/users.inc.php');

$page['title'] = "S_SCRIPTS";
$page['file'] = 'scripts.php';
$page['hist_arg'] = array('scriptid','form');

include_once "include/page_header.php";

//---------------------------------- CHECKS ------------------------------------

//		VAR							TYPE	OPTIONAL FLAGS	VALIDATION	EXCEPTION

$fields=array(
        'scriptid'=>			array(T_ZBX_INT, O_OPT, P_SYS,			DB_ID,	null),
        'scripts'=>				array(T_ZBX_INT, O_OPT,	P_SYS,			DB_ID,	null),

    // action
        'action'=>				array(T_ZBX_INT, O_OPT,  P_ACT, 		IN('0,1'),	null),
        'save'=>				array(T_ZBX_STR, O_OPT,	 P_SYS|P_ACT,	NULL,		null),
        'delete'=>				array(T_ZBX_STR, O_OPT,  P_ACT, 		null,	null),

    // form
        'name'=>				array(T_ZBX_STR, O_OPT,  NULL,			NOT_EMPTY,	'isset({save})'),
        'command'=>				array(T_ZBX_STR, O_OPT,  NULL,			NOT_EMPTY,	'isset({save})'),
        'access'=>				array(T_ZBX_INT, O_OPT,  NULL,			IN('0,1,2,3'),	'isset({save})'),
        'groupid'=>				array(T_ZBX_INT, O_OPT,	 P_SYS,			DB_ID,		'isset({save})'),
        'usrgrpid'=>			array(T_ZBX_INT, O_OPT,  P_SYS,			DB_ID,		'isset({save})'),

        'form'=>				array(T_ZBX_STR, O_OPT,  NULL,		  	NULL,		null),
        'form_refresh'=>		array(T_ZBX_INT, O_OPT,	 NULL,			NULL,		null),
);

check_fields($fields);
$frmForm = new CForm();
$frmForm->SetMethod('get');
$frmForm->addItem(new CButton('form',S_CREATE_SCRIPT,"javascript: redirect('scripts.php?form=1');"));
show_table_header(S_SCRIPTS_CONFIGURATION, $frmForm);
echo SBR; 
if(isset($_REQUEST['action'])){

    if(isset($_REQUEST['save'])){

        $cond = (isset($_REQUEST['scriptid']))?(' AND scriptid<>'.$_REQUEST['scriptid']):('');
        $scripts = DBfetch(DBselect('SELECT count(scriptid) as cnt FROM scripts WHERE name='.zbx_dbstr($_REQUEST['name']).$cond.' and '.DBin_node('scriptid', get_current_nodeid(false)),1));

        if($scripts && $scripts['cnt']>0){
            error(S_SCRIPT.SPACE.'['.htmlspecialchars($_REQUEST['name']).']'.SPACE.S_ALREADY_EXISTS_SMALL);
            show_messages(null,S_ERROR,S_CANNOT_ADD_SCRIPT);
        }
        else{

            if(isset($_REQUEST['scriptid'])){
                $result = update_script($_REQUEST['scriptid'],$_REQUEST['name'],$_REQUEST['command'],$_REQUEST['usrgrpid'],$_REQUEST['groupid'],$_REQUEST['access']);

                show_messages($result, S_SCRIPT_UPDATED, S_CANNOT_UPDATE_SCRIPT);
                $scriptid = $_REQUEST['scriptid'];
                $audit_acrion = AUDIT_ACTION_UPDATE;
            }
            else {
                $result = add_script($_REQUEST['name'],$_REQUEST['command'],$_REQUEST['usrgrpid'],$_REQUEST['groupid'],$_REQUEST['access']);

                show_messages($result, S_SCRIPT_ADDED, S_CANNOT_ADD_SCRIPT);
                $scriptid = $result;
                $audit_acrion = AUDIT_ACTION_ADD;
            }

            add_audit_if($result,$audit_acrion,AUDIT_RESOURCE_SCRIPT,' Name ['.$_REQUEST['name'].'] id ['.$scriptid.']');

            if($result){
                unset($_REQUEST['action']);
                unset($_REQUEST['form']);
                unset($_REQUEST['scriptid']);
            }
        }
    }
    else if(isset($_REQUEST['delete'])){
        if(isset($_REQUEST['scriptid'])){
            $scripts[$_REQUEST['scriptid']] = $_REQUEST['scriptid'];
        } else {
            $scripts = $_REQUEST['scripts'];
        }

        $result = true;
        foreach($scripts as $scriptid){
            $result &= delete_script($scriptid);

            if($result){
                add_audit(AUDIT_ACTION_DELETE, AUDIT_RESOURCE_SCRIPT, S_SCRIPT.' ['.$scriptid.']');
            }
        }
        show_messages($result, S_SCRIPT_DELETED, S_CANNOT_DELETE_SCRIPT);

        if($result){
            unset($_REQUEST['form']);
            unset($_REQUEST['scriptid']);
        }
    }
}

$row_count = 0;

if(isset($_REQUEST['form'])){
    $available_groups = get_accessible_groups_by_user($USER_DETAILS,PERM_READ_ONLY);

    show_table_header(S_SCRIPTS);
    echo SBR;

    $frmScr = new CFormTable(S_SCRIPT,'scripts.php','POST',null,'form');
    $frmScr->addOption('id','scripts');

    if(isset($_REQUEST['scriptid'])) $frmScr->addVar('scriptid',$_REQUEST['scriptid']);

    if(!isset($_REQUEST['scriptid']) || isset($_REQUEST['form_refresh'])){
        $name = get_request('name','');
        $command  = get_request('command','');

        $usrgrpid = get_request('usrgrpid',	0);
        $groupid = get_request('groupid',	0);

        $access = get_request('access',	PERM_READ_ONLY);
    }
    if(isset($_REQUEST['scriptid']) && !isset($_REQUEST['form_refresh'])){
        $frmScr->addVar('form_refresh',get_request('form_refresh',1));

        if($script = get_script_by_scriptid($_REQUEST['scriptid'])){
            $name = $script['name'];
            $command  = $script['command'];

            $usrgrpid = $script['usrgrpid'];
            $groupid = $script['groupid'];

            $access = $script['host_access'];
        }
    }

    $frmScr->addRow(S_NAME,new CTextBox('name',$name,80));
    $frmScr->addRow(S_COMMAND,new CTextBox('command',$command,80));

    $usr_groups = new CCombobox('usrgrpid',$usrgrpid);
    $usr_groups->addItem(0,S_ALL_S);

    $sql = 'SELECT DISTINCT ug.name, ug.usrgrpid '.
            ' FROM usrgrp ug '.
                ' WHERE '.DBin_node('ug.usrgrpid').
            ' ORDER BY ug.name';

    $usrgrp_result = DBselect($sql);
    while($usr_group=DBfetch($usrgrp_result)){
        $usr_groups->addItem($usr_group['usrgrpid'],$usr_group['name']);
    }

    $frmScr->addRow(S_USER_GROUPS,$usr_groups);

    $host_groups = new CCombobox('groupid',$groupid);
    $host_groups->addItem(0,S_ALL_S);

    $sql = 'SELECT DISTINCT g.name, g.groupid '.
            ' FROM groups g '.
            ' WHERE '.DBcondition('g.groupid',$available_groups).
            ' ORDER BY g.name';

    $grp_result = DBselect($sql);
    while($group=DBfetch($grp_result)){
        $host_groups->addItem($group['groupid'],$group['name']);
    }

    $frmScr->addRow(S_HOST_GROUPS,$host_groups);

    $select_acc = new CCombobox('access',$access);
    $select_acc->addItem(PERM_READ_ONLY,S_READ);
    $select_acc->addItem(PERM_READ_WRITE,S_WRITE);

    $frmScr->addRow(S_REQUIRED_HOST.SPACE.S_PERMISSIONS_SMALL,$select_acc);

    $frmScr->addItemToBottomRow(new CButton('save',S_SAVE,"javascript: document.getElementById('scripts').action+='?action=1'; "));
    $frmScr->addItemToBottomRow(SPACE);
    if (isset($_REQUEST['scriptid'])) {
        $deleteButton = new CButtonDelete(S_DELETE_SCRIPTS_Q, '&action=1&scriptid='.$_REQUEST['scriptid']);
        $frmScr->addItemToBottomRow($deleteButton);
        $frmScr->addItemToBottomRow(SPACE);
    }
    $frmScr->addItemToBottomRow(new CButtonCancel());
    $frmScr->Show();

}
else {
    validate_sort_and_sortorder('s.name',ZBX_SORT_UP);

    $form = new CForm();
    $form->setName('scripts');
    $form->addOption('id','scripts');
    $form->addVar('action','1');

    $numrows = new CSpan(null,'info');
    $numrows->addOption('name','numrows');
    $header = get_table_header(array(S_SCRIPTS,
            new CSpan(SPACE.SPACE.'|'.SPACE.SPACE, 'divider'),
            S_FOUND.': ',$numrows,)
    );
    show_table_header($header);

    $table=new CTableInfo(S_NO_SCRIPTS_DEFINED);
    $table->setHeader(array(
            array(new CCheckBox('all_scripts',null,"CheckAll('".$form->GetName()."','all_scripts');"),make_sorting_link(S_NAME,'s.name')),
            make_sorting_link(S_COMMAND,'s.command'),
            S_USER_GROUP,
            S_HOST_GROUP,
            S_HOST_ACCESS
        )
    );

    $sql = 'SELECT s.* '.
            ' FROM scripts s '.
            ' WHERE '.DBin_node('s.scriptid').
    order_by('s.name,s.command');

    $scripts=DBselect($sql);

    while($script=DBfetch($scripts)){

        $user_group_name = S_ALL_S;
        if($script['usrgrpid'] > 0){
            $user_group = get_group_by_usrgrpid($script['usrgrpid']);
            $user_group_name = $user_group['name'];
        }

        $host_group_name = S_ALL_S;
        if($script['groupid'] > 0){
            $group = get_hostgroup_by_groupid($script['groupid']);
            $host_group_name = $group['name'];
        }


        $table->addRow(array(
                array(
                    new CCheckBox('scripts['.$script['scriptid'].']','no',NULL,$script['scriptid']),
                    new CLink($script['name'],'scripts.php?form=1'.'&scriptid='.$script['scriptid'].'#form','action')
                ),
                htmlspecialchars($script['command']),
                $user_group_name,
                $host_group_name,
                ((PERM_READ_WRITE == $script['host_access'])?S_WRITE:S_READ)
            ));
        $row_count++;
    }
    $qbutton = new CButtonQMessage('delete', S_DELETE_SELECTED, S_DELETE_SELECTED_SCRIPTS_Q);
    //$qbutton->setAction('javascript:document.getElementById("scripts").action += "?action=1";');

    $tr = new CCol(
        array(
            $qbutton
        )
    );

    $table->setFooter($tr);

    $form->addItem($table);
    $form->show();
    zbx_add_post_js('insert_in_element("numrows","'.$row_count.'");');

}
?>
<?php
include_once('include/page_footer.php');
?>
