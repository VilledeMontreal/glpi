<?php
/*
 * @version $Id$
 ----------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2006 by the INDEPNET Development Team.
 
 http://indepnet.net/   http://glpi.indepnet.org
 ----------------------------------------------------------------------

 LICENSE

	This file is part of GLPI.

    GLPI is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    GLPI is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with GLPI; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 ------------------------------------------------------------------------
*/

// ----------------------------------------------------------------------
// Original Author of file:
// Purpose of file:
// ----------------------------------------------------------------------

function ocsShowNewComputer($check,$start,$tolinked=0){
global $db,$dbocs,$lang,$HTMLRel,$cfg_features;

$query_ocs = "select * from hardware order by LASTDATE";
$result_ocs = $dbocs->query($query_ocs) or die($dbocs->error());

// Existing OCS - GLPI link
$query_glpi = "select * from glpi_ocs_link";
$result_glpi = $db->query($query_glpi) or die($db->error());

// Computers existing in GLPI
$query_glpi_comp = "select ID,name from glpi_computers where deleted = 'N' AND is_template='0'";
$result_glpi_comp = $db->query($query_glpi_comp) or die($db->error());

if ($dbocs->numrows($result_ocs)>0){
	
	// Get all hardware from OCS DB
	$hardware=array();
	while($data=$dbocs->fetch_array($result_ocs)){
		$data=addslashes_deep($data);
		$hardware[$data["DEVICEID"]]["date"]=$data["LASTDATE"];
		$hardware[$data["DEVICEID"]]["name"]=$data["NAME"];
	}
	// Get all links between glpi and OCS
	$already_linked=array();
	if ($db->numrows($result_glpi)>0){
		while($data=$dbocs->fetch_array($result_glpi)){
		$already_linked[$data["ocs_id"]]=$data["last_update"];
		}
	}

	// Get all existing computers name in GLPI
	$computer_names=array();
	if ($db->numrows($result_glpi_comp)>0){
		while($data=$dbocs->fetch_array($result_glpi_comp)){
		$computer_names[$data["name"]]=$data["ID"];
		}
	}
	
	// Clean $hardware from already linked element
	if (count($already_linked)>0){
		foreach ($already_linked as $ID => $date){
			if (isset($hardware[$ID])&&isset($already_linked[$ID]))
			unset($hardware[$ID]);
		}
	}
	
	if ($tolinked&&count($hardware)){
	echo "<div align='center'><strong>".$lang["ocsng"][22]."</strong></div>";
	}

	echo "<div align='center'>";
	if (($numrows=count($hardware))>0){
	
		$parameters="check=$check";
   	 	printPager($start,$numrows,$_SERVER["PHP_SELF"],$parameters);

		// delete end 
		array_splice($hardware,$start+$cfg_features["list_limit"]);
		// delete begin
		if ($start>0)
		array_splice($hardware,0,$start);
		
		echo "<form method='post' action='".$_SERVER["PHP_SELF"]."'>";
		if ($tolinked==0)
			echo "<a href='".$_SERVER["PHP_SELF"]."?check=all&amp;start=$start'>".$lang["buttons"][18]."</a>&nbsp;/&nbsp;<a href='".$_SERVER["PHP_SELF"]."?check=none&amp;start=$start'>".$lang["buttons"][19]."</a>";

		
		echo "<table class='tab_cadre'>";
		echo "<tr><th>".$lang["ocsng"][5]."</th><th>".$lang["common"][27]."</th><th>&nbsp;</th></tr>";
		
		echo "<tr class='tab_bg_1'><td colspan='3' align='center'>";
		echo "<input type='submit' name='import_ok' value='".$lang["buttons"][37]."'>";
		echo "</td></tr>";

		
		foreach ($hardware as $ID => $tab){
			echo "<tr class='tab_bg_2'><td>".$tab["name"]."</td><td>".$tab["date"]."</td><td>";
			
			if ($tolinked==0)
			echo "<input type='checkbox' name='toimport[$ID]' ".($check=="all"?"checked":"").">";
			else {
				if (isset($computer_names[$tab["name"]]))
					dropdownValue("glpi_computers","tolink[$ID]",$computer_names[$tab["name"]]);
				else
					dropdown("glpi_computers","tolink[$ID]");
			}
			echo "</td></tr>";
		
		}
		echo "<tr class='tab_bg_1'><td colspan='3' align='center'>";
		echo "<input type='submit' name='import_ok' value='".$lang["buttons"][37]."'>";
		echo "</td></tr>";
		echo "</table>";
		echo "</form>";
   	 	
		printPager($start,$numrows,$_SERVER["PHP_SELF"],$parameters);

	} else echo "<strong>".$lang["ocsng"][9]."</strong>";

	echo "</div>";

} else echo "<div align='center'><strong>".$lang["ocsng"][9]."</strong></div>";
}

/**
* Make the item link between glpi and ocs.
*
* This make the database link between ocs and glpi databases
*
*@param $ocs_item_id integer : ocs item unique id.
*@param $glpi_computer_id integer : glpi computer id
*
*@return integer : link id.
*
**/
function ocs_link($ocs_item_id, $glpi_computer_id) {
	global $db;
	$query = "insert into glpi_ocs_link (glpi_id,ocs_id,last_update) VALUES ('".$glpi_computer_id."','".$ocs_item_id."',NOW())";
	
	$result=$db->query($query);
	if ($result)
		return ($db->insert_id());
	else return false;
}


function ocsManageDeleted(){
	global $db,$dbocs;
	$query="SELECT * FROM deleted_equiv";
	$result = $dbocs->query($query) or die($dbocs->error().$query);
	if ($dbocs->numrows($result)){
		$deleted=array();
		while ($data=$dbocs->fetch_array($result)){
			$deleted[$data["DELETED"]]=$data["EQUIVALENT"];
		}

		$query="TRUNCATE TABLE deleted_equiv";
		$result = $dbocs->query($query) or die($dbocs->error().$query);
		
		if (count($deleted))
		foreach ($deleted as $del => $equiv){
			if (!empty($equiv)){ // New name
				$query="UPDATE glpi_ocs_link SET ocs_id='$equiv' WHERE ocs_id='$del'";
				$db->query($query) or die($db->error().$query);
			} else { // Deleted
				$query="SELECT * FROM glpi_ocs_link WHERE ocs_id='$del'";
				$result=$db->query($query) or die($db->error().$query);
				if ($db->numrows($result)){
					$del=$db->fetch_array($result);
					deleteComputer(array("ID"=>$del["glpi_id"]),0);
				}
			
			}
		}
	}


}


function ocsImportComputer($DEVICEID){
	global $dbocs;

	// Set OCS checksum to max value
	$query = "UPDATE hardware SET CHECKSUM='".MAX_OCS_CHECKSUM."' WHERE DEVICEID='$DEVICEID'";
	$dbocs->query($query) or die($dbocs->error().$query);

	$query = "UPDATE config SET IVALUE='1' WHERE NAME='TRACE_DELETED'";
	$dbocs->query($query) or die($dbocs->error().$query);


	$query = "SELECT * FROM hardware WHERE DEVICEID='$DEVICEID'";
	$result = $dbocs->query($query) or die($dbocs->error().$query);
	$comp = new Computer;
	if ($dbocs->numrows($result)==1){
		$line=$dbocs->fetch_array($result);
		$dbocs->close();

		$comp->fields["name"] = $line["NAME"];
		$comp->fields["ocs_import"] = 1;
		$glpi_id=$comp->addToDB();

		if ($idlink = ocs_link($line['DEVICEID'], $glpi_id)){
			ocsUpdateComputer($idlink,0);
		}
	}
}

function ocsLinkComputer($ocs_id,$glpi_id){
	global $db,$dbocs,$lang;
	
	$query="SELECT * FROM glpi_ocs_link WHERE glpi_id='$glpi_id'";
	$result=$db->query($query);
	if ($db->numrows($result)==0){
	
		// Set OCS checksum to max value
		$query = "UPDATE hardware SET CHECKSUM='".MAX_OCS_CHECKSUM."' WHERE DEVICEID='$ocs_id'";
		$dbocs->query($query) or die($dbocs->error().$query);

		$query = "UPDATE config SET IVALUE='1' WHERE NAME='TRACE_DELETED'";
		$dbocs->query($query) or die($dbocs->error().$query);
		$comp = new Computer;
		if ($idlink = ocs_link($ocs_id, $glpi_id)){

			$input["ID"] = $glpi_id;
			$input["ocs_import"] = 1;
			updateComputer($input);

			// Reset using GLPI Config
			$cfg_ocs=getOcsConf(1);
			if($cfg_ocs["import_general_os"]) 
				ocsResetDropdown($glpi_id,"os","glpi_dropdown_os");
			if($cfg_ocs["import_device_processor"]) 
				ocsResetDevices($glpi_id,PROCESSOR_DEVICE);
			if($cfg_ocs["import_device_iface"]) 
				ocsResetDevices($glpi_id,NETWORK_DEVICE);
			if($cfg_ocs["import_device_memory"]) 
				ocsResetDevices($glpi_id,RAM_DEVICE);
			if($cfg_ocs["import_device_hdd"]) 
				ocsResetDevices($glpi_id,HDD_DEVICE);
			if($cfg_ocs["import_device_sound"]) 
				ocsResetDevices($glpi_id,SND_DEVICE);
			if($cfg_ocs["import_device_gfxcard"]) 
				ocsResetDevices($glpi_id,GFX_DEVICE);
			if($cfg_ocs["import_device_drives"]) 
				ocsResetDevices($glpi_id,DRIVE_DEVICE);
			if($cfg_ocs["import_device_modems"] || $cfg_ocs["import_device_ports"]) 
				ocsResetDevices($glpi_id,PCI_DEVICE);
			if($cfg_ocs["import_software"]) 
				ocsResetLicenses($glpi_id);
			if($cfg_ocs["import_periph"]) 
				ocsResetPeriphs($glpi_id);
			if($cfg_ocs["import_monitor"]) 
				ocsResetMonitors($glpi_id);
			if($cfg_ocs["import_printer"]) 
				ocsResetPrinters($glpi_id);

			ocsUpdateComputer($idlink,0);
		}
	} else echo $ocs_id." - ".$lang["ocsng"][23];
}


function ocsUpdateComputer($ID,$dohistory){

    global $db,$dbocs;

     $cfg_ocs=getOcsConf(1);

    $query="SELECT * FROM glpi_ocs_link WHERE ID='$ID'";

    $result=$db->query($query) or die($db->error().$query);;
    if ($db->numrows($result)==1){
        $line=$db->fetch_assoc($result);
	$query_ocs = "SELECT CHECKSUM FROM hardware WHERE DEVICEID='".$line['ocs_id']."'";
	$result_ocs = $dbocs->query($query_ocs) or die($dbocs->error().$query_ocs);
	if ($dbocs->numrows($result_ocs)==1){
		$ocs_checksum=$dbocs->result($result_ocs,0,0);
	

		$mixed_checksum=intval($ocs_checksum) &  intval($cfg_ocs["checksum"]);
//		echo "OCS CS=".decbin($ocs_checksum)." - $ocs_checksum<br>";
//		echo "GLPI CS=".decbin($cfg_ocs["checksum"])." - ".$cfg_ocs["checksum"]."<br>";
//		echo "MIXED CS=".decbin($mixed_checksum)." - $mixed_checksum <br>";

		// Is an update to do ?
		if ($mixed_checksum){

			// Get updates on computers :
			$computer_updates=importArrayFromDB($line["computer_update"]);
			
			if ($mixed_checksum&pow(2,HARDWARE_FL))
				ocsUpdateHardware($line['glpi_id'],$line['ocs_id'],$cfg_ocs,$computer_updates,$dohistory);
			if ($mixed_checksum&pow(2,BIOS_FL))
				ocsUpdateBios($line['glpi_id'],$line['ocs_id'],$cfg_ocs,$computer_updates,$dohistory);

			// Get import devices
			$import_device=importArrayFromDB($line["import_device"]);
			if ($mixed_checksum&pow(2,MEMORIES_FL))
				ocsUpdateDevices(RAM_DEVICE,$line['glpi_id'],$line['ocs_id'],$cfg_ocs,$import_device,$dohistory);
			if ($mixed_checksum&pow(2,STORAGES_FL)){
				ocsUpdateDevices(HDD_DEVICE,$line['glpi_id'],$line['ocs_id'],$cfg_ocs,$import_device,$dohistory);
				ocsUpdateDevices(DRIVE_DEVICE,$line['glpi_id'],$line['ocs_id'],$cfg_ocs,$import_device,$dohistory);
			}
			if ($mixed_checksum&pow(2,HARDWARE_FL))
				ocsUpdateDevices(PROCESSOR_DEVICE,$line['glpi_id'],$line['ocs_id'],$cfg_ocs,$import_device,$dohistory);
			if ($mixed_checksum&pow(2,VIDEOS_FL))
				ocsUpdateDevices(GFX_DEVICE,$line['glpi_id'],$line['ocs_id'],$cfg_ocs,$import_device,$dohistory);
			if ($mixed_checksum&pow(2,SOUNDS_FL))
				ocsUpdateDevices(SND_DEVICE,$line['glpi_id'],$line['ocs_id'],$cfg_ocs,$import_device,$dohistory);
			if ($mixed_checksum&pow(2,NETWORKS_FL))
				ocsUpdateDevices(NETWORK_DEVICE,$line['glpi_id'],$line['ocs_id'],$cfg_ocs,$import_device,$dohistory);
			if ($mixed_checksum&pow(2,MODEMS_FL)||$mixed_checksum&pow(2,PORTS_FL))
				ocsUpdateDevices(PCI_DEVICE,$line['glpi_id'],$line['ocs_id'],$cfg_ocs,$import_device,$dohistory);

			if ($mixed_checksum&pow(2,MONITORS_FL)){
				// Get import monitors
				$import_monitor=importArrayFromDB($line["import_monitor"]);
				ocsUpdatePeripherals(MONITOR_TYPE,$line['glpi_id'],$line['ocs_id'],$cfg_ocs,$import_monitor,$dohistory);
			}

			if ($mixed_checksum&pow(2,PRINTERS_FL)){
				// Get import printers
				$import_printer=importArrayFromDB($line["import_printers"]);
				ocsUpdatePeripherals(PRINTER_TYPE,$line['glpi_id'],$line['ocs_id'],$cfg_ocs,$import_printer,$dohistory);
			}

			if ($mixed_checksum&pow(2,INPUTS_FL)){
				// Get import monitors
				$import_peripheral=importArrayFromDB($line["import_peripheral"]);
				ocsUpdatePeripherals(PERIPHERAL_TYPE,$line['glpi_id'],$line['ocs_id'],$cfg_ocs,$import_peripheral,$dohistory);
			}

			if ($mixed_checksum&pow(2,SOFTWARES_FL)){
				// Get import monitors
				$import_software=importArrayFromDB($line["import_software"]);
				ocsUpdateSoftware($line['glpi_id'],$line['ocs_id'],$cfg_ocs,$import_software);
			} 

			

			// Update OCS Cheksum
			$query_ocs="UPDATE hardware SET CHECKSUM= (CHECKSUM - $mixed_checksum) WHERE DEVICEID='".$line['ocs_id']."'";
			$dbocs->query($query_ocs) or die($dbocs->error().$query_ocs);
		}
	}
    }
}

/**
* Get OCSNG mode configuration
*
* Get all config of the OCSNG mode
*
*
*@return Value of $confVar fields or false if unfound.
*
**/
function getOcsConf($id) {
	global $db;
	$query = "SELECT * FROM glpi_ocs_config WHERE ID='$id'";
	$result = $db->query($query)  or die($db->error().$query);
	if($result) return $db->fetch_assoc($result);
	else return 0;
}


/**
* Update the computer hardware configuration
*
* Update the computer hardware configuration
*
*@param $ocs_id integer : glpi computer id
*@param $glpi_id integer : ocs computer id.
*
*@return nothing.
*
**/
function ocsUpdateHardware($glpi_id,$ocs_id,$cfg_ocs,$computer_updates,$dohistory=1) {
 	global $dbocs,$lang;
	$query = "select * from hardware WHERE DEVICEID='".$ocs_id."'";
//	echo $query;
	$result = $dbocs->query($query) or die($dbocs->error());
	if ($dbocs->numrows($result)==1) {
		$line=$dbocs->fetch_assoc($result);
		$line=addslashes_deep($line);
		$compudate=array();
		
		if($cfg_ocs["import_general_os"]&&!in_array("os",$computer_updates)) {
			$compupdate["os"] = ocsImportDropdown('glpi_dropdown_os','name',$line["OSNAME"]);
			$compupdate["os_version"] = ocsImportDropdown('glpi_dropdown_os_version','name',$line["OSVERSION"]);
			if (!ereg("CEST",$line["OSCOMMENTS"])) // Not linux comment
				$compupdate["os_sp"] = ocsImportDropdown('glpi_dropdown_os_sp','name',$line["OSCOMMENTS"]);
		}
		
		if($cfg_ocs["import_general_domain"]&&!in_array("domain",$computer_updates)) {
			$compupdate["domain"] = ocsImportDropdown('glpi_dropdown_domain','name',$line["WORKGROUP"]);
		}
		
		if($cfg_ocs["import_general_contact"]&&!in_array("contact",$computer_updates)) {
			$compupdate["contact"] = $line["USERID"];
		}
			
		if($cfg_ocs["import_general_comments"]&&!in_array("comments",$computer_updates)) {
			$compupdate["comments"] = "Swap: ".$line["SWAP"];
		}

		if (count($compupdate)){
			$compupdate["ID"] = $glpi_id;
			updateComputer($compupdate,$dohistory);
		}
		
	}
}


/**
* Update the computer bios configuration
*
* Update the computer bios configuration
*
*@param $ocs_id integer : glpi computer id
*@param $glpi_id integer : ocs computer id.
*
*@return nothing.
*
**/
function ocsUpdateBios($glpi_id,$ocs_id,$cfg_ocs,$computer_updates,$dohistory=1) {
	global $dbocs;
	$query = "select * from bios WHERE DEVICEID='".$ocs_id."'";
//	echo $query;
	$result = $dbocs->query($query) or die($dbocs->error().$query);
	if ($dbocs->numrows($result)==1) {
		$line=$dbocs->fetch_assoc($result);
		$line=addslashes_deep($line);
		$compudate=array();

		if($cfg_ocs["import_general_serial"]&&!in_array("serial",$computer_updates)) {
			$compupdate["serial"] = $line["SSN"];
		}
		
		if($cfg_ocs["import_general_model"]&&!in_array("model",$computer_updates)) {
			$compupdate["model"] = ocsImportDropdown('glpi_dropdown_model','name',$line["SMODEL"]);
		}	
		
		if($cfg_ocs["import_general_enterprise"]&&!in_array("FK_glpi_enterprise",$computer_updates)) {
			$compupdate["FK_glpi_enterprise"] = ocsImportEnterprise($line["SMANUFACTURER"]);
		}
		
		if($cfg_ocs["import_general_type"]&&!empty($line["TYPE"])&&!in_array("type",$computer_updates)) {
			$compupdate["type"] = ocsImportDropdown('glpi_type_computers','name',$line["TYPE"]);
		}
		
		if (count($compupdate)){
			$compupdate["ID"] = $glpi_id;
			updateComputer($compupdate,$dohistory);
		}
		
	}
}


/**
* Import a dropdown from OCS table.
*
* This import a new dropdown if it doesn't exist.
*
*@param $dpdTable string : Name of the glpi dropdown table.
*@param $dpdRow string : Name of the glinclude ($phproot . "/glpi/includes_devices.php");pi dropdown row.
*@param $value string : Value of the new dropdown.
*
*@return integer : dropdown id.
*
**/

function ocsImportDropdown($dpdTable,$dpdRow,$value) {
	global $db;
	$query2 = "select * from ".$dpdTable." where $dpdRow='".$value."'";
	$result2 = $db->query($query2);
	if($db->numrows($result2) == 0) {
		$query3 = "insert into ".$dpdTable." (ID,".$dpdRow.") values ('','".$value."')";
		$db->query($query3) or die("echec de l'importation".$db->error());
		return $db->insert_id();
	} else {
	$line2 = $db->fetch_array($result2);
	return $line2["ID"];
	}
	
}


/**
* Import g��al config of a new enterprise
*
* This function create a new enterprise in GLPI with some general datas.
*
*@param $name : name of the enterprise.
*
*@return integer : inserted enterprise id.
*
**/
function ocsImportEnterprise($name) {
    global $db;
    $query = "SELECT ID FROM glpi_enterprises WHERE name = '".$name."'";
    $result = $db->query($query) or die("Verification existence entreprise :".$name." - ".$db->error());
    if ($db->numrows($result)>0){
        $enterprise_id  = $db->result($result,0,"ID");
    } else {
        $entpr = new Enterprise;
        $entpr->fields["name"] = $name;
        $enterprise_id = $entpr->addToDB();
    }
    return($enterprise_id);
}

function ocsCleanLinks(){
	global $db;

	$query="SELECT glpi_ocs_link.ID AS ID FROM glpi_ocs_link LEFT JOIN glpi_computers ON glpi_computers.ID=glpi_ocs_link.glpi_id WHERE glpi_computers.ID IS NULL";
	
	$result=$db->query($query);
	if ($db->numrows($result)>0){
		while ($data=$db->fetch_array($result)){
			$query2="DELETE FROM glpi_ocs_link WHERE ID='".$data['ID']."'";
			$db->query($query2);
		}
	}
}


function ocsShowUpdateComputer($check,$start){
global $db,$dbocs,$lang,$HTMLRel,$cfg_features;

$cfg_ocs=getOcsConf(1);

$query_ocs = "select * from hardware WHERE (CHECKSUM & ".$cfg_ocs["checksum"].") > 0 order by lastdate";
$result_ocs = $dbocs->query($query_ocs) or die($dbocs->error());

$query_glpi = "select glpi_ocs_link.last_update as last_update,  glpi_ocs_link.glpi_id as glpi_id, glpi_ocs_link.ocs_id as ocs_id, glpi_computers.name as name, glpi_ocs_link.auto_update as auto_update, glpi_ocs_link.ID as ID";
$query_glpi.= " from glpi_ocs_link LEFT JOIN glpi_computers ON (glpi_computers.ID = glpi_ocs_link.glpi_id) ";
$query_glpi.= " ORDER by glpi_ocs_link.last_update, glpi_computers.name";

$result_glpi = $db->query($query_glpi) or die($db->error());
if ($dbocs->numrows($result_ocs)>0){
	
	// Get all hardware from OCS DB
	$hardware=array();
	while($data=$dbocs->fetch_array($result_ocs)){
	$hardware[$data["DEVICEID"]]["date"]=$data["LASTDATE"];
	$hardware[$data["DEVICEID"]]["name"]=addslashes($data["NAME"]);
	}

	// Get all links between glpi and OCS
	$already_linked=array();
	if ($db->numrows($result_glpi)>0){
		while($data=$dbocs->fetch_assoc($result_glpi)){
			$data=addslashes_deep($data);
			if (isset($hardware[$data["ocs_id"]])){ 
				$already_linked[$data["ocs_id"]]["date"]=$data["last_update"];
				$already_linked[$data["ocs_id"]]["name"]=$data["name"];
				$already_linked[$data["ocs_id"]]["ID"]=$data["ID"];
				$already_linked[$data["ocs_id"]]["glpi_id"]=$data["glpi_id"];
			}
		}
	}
	echo "<div align='center'>";
	echo "<h2>".$lang["ocsng"][10]."</h2>";
	
	if (($numrows=count($already_linked))>0){

		$parameters="check=$check";
   		printPager($start,$numrows,$_SERVER["PHP_SELF"],$parameters);

		// delete end 
		array_splice($already_linked,$start+$cfg_features["list_limit"]);
		// delete begin
		if ($start>0)
		array_splice($already_linked,0,$start);

		echo "<form method='post' action='".$_SERVER["PHP_SELF"]."'>";
		
		echo "<a href='".$_SERVER["PHP_SELF"]."?check=all'>".$lang["buttons"][18]."</a>&nbsp;/&nbsp;<a href='".$_SERVER["PHP_SELF"]."?check=none'>".$lang["buttons"][19]."</a>";
		echo "<table class='tab_cadre'>";
		echo "<tr><th>".$lang["ocsng"][11]."</th><th>".$lang["ocsng"][13]."</th><th>".$lang["ocsng"][14]."</th><th>&nbsp;</th></tr>";
		
		echo "<tr class='tab_bg_1'><td colspan='4' align='center'>";
		echo "<input type='submit' name='update_ok' value='".$lang["buttons"][7]."'>";
		echo "</td></tr>";

		foreach ($already_linked as $ID => $tab){

			echo "<tr align='center' class='tab_bg_2'><td><a href='".$HTMLRel."computers/computers-info-form.php?ID=".$tab["glpi_id"]."'>".$tab["name"]."</a></td><td>".$tab["date"]."</td><td>".$hardware[$ID]["date"]."</td><td>";
			
			echo "<input type='checkbox' name='toupdate[".$tab["ID"]."]' ".($check=="all"?"checked":"").">";
			echo "</td></tr>";
		}
		echo "<tr class='tab_bg_1'><td colspan='4' align='center'>";
		echo "<input type='submit' name='update_ok' value='".$lang["buttons"][7]."'>";
		echo "</td></tr>";
		echo "</table>";
		echo "</form>";
   		printPager($start,$numrows,$_SERVER["PHP_SELF"],$parameters);

	} else echo "<br><strong>".$lang["ocsng"][11]."</strong>";

	echo "</div>";

} else echo "<div align='center'><strong>".$lang["ocsng"][12]."</strong></div>";
}


function mergeOcsArray($glpi_id,$tomerge,$field){
	global $db;
	$query="SELECT $field FROM glpi_ocs_link WHERE glpi_id='$glpi_id'";
	if ($result=$db->query($query)){
		$tab=importArrayFromDB($db->result($result,0,0));
		$newtab=array_unique(array_merge($tomerge,$tab));
		$query="UPDATE glpi_ocs_link SET $field='".exportArrayToDB($newtab)."' WHERE glpi_id='$glpi_id'";
		$db->query($query);
	}

}

function deleteInOcsArray($glpi_id,$todel,$field){
	global $db;
	$query="SELECT $field FROM glpi_ocs_link WHERE glpi_id='$glpi_id'";
	if ($result=$db->query($query)){
		$tab=importArrayFromDB($db->result($result,0,0));
		unset($tab[$todel]);
		$query="UPDATE glpi_ocs_link SET $field='".exportArrayToDB($tab)."' WHERE glpi_id='$glpi_id'";
		$db->query($query);
	}

}

function addToOcsArray($glpi_id,$toadd,$field){
	global $db;
	$query="SELECT $field FROM glpi_ocs_link WHERE glpi_id='$glpi_id'";
	if ($result=$db->query($query)){
		$tab=importArrayFromDB($db->result($result,0,0));
		foreach ($toadd as $key => $val)
			$tab[$key]=$val;
		$query="UPDATE glpi_ocs_link SET $field='".exportArrayToDB($tab)."' WHERE glpi_id='$glpi_id'";
		$db->query($query);
	}

}


function ocsEditLock($target,$ID){
	global $db,$lang,$SEARCH_OPTION;

	
	$query="SELECT * FROM glpi_ocs_link WHERE glpi_id='$ID'";
	
	$result=$db->query($query);
	if ($db->numrows($result)==1){
		$data=$db->fetch_assoc($result);
		echo "<div align='center'>";
		// Print lock fields for OCSNG
		$lockable_fields=array("type","FK_glpi_enterprise","model","serial","comments","contact","domain","os");
		$locked=array_intersect(importArrayFromDB($data["computer_update"]),$lockable_fields);
		
		if (count($locked)){
			echo "<form method='post' action=\"$target\">";
			echo "<input type='hidden' name='ID' value='$ID'>";
			echo "<table class='tab_cadre'>";
			echo "<tr><th colspan='2'>".$lang["ocsng"][16]."</th></tr>";
			foreach ($locked as $key => $val){
				foreach ($SEARCH_OPTION[COMPUTER_TYPE] as $key2 => $val2)
				if ($val2["linkfield"]==$val)
				echo "<tr class='tab_bg_1'><td>".$val2["name"]."</td><td><input type='checkbox' name='lockfield[".$key."]'></td></tr>";
			}
			echo "<tr class='tab_bg_2'><td align='center' colspan='2'><input type='submit' name='unlock_field' value='".$lang["buttons"][38]."'></td></tr>";
			echo "</table>";
			echo "</form>";
		} else echo "<strong>".$lang["ocsng"][15]."</strong>";
		echo "</div>";
	}

}

/**
* Import the devices for a computer
*
* 
*
*@param $glpi_id integer : glpi computer id.
*@param $ocs_id integer : ocs computer id (DEVICEID).
*
*@return Nothing (void).
*
**/
function ocsUpdateDevices($device_type,$glpi_id,$ocs_id,$cfg_ocs,$import_device,$dohistory){
	global $dbocs,$db;

	$do_clean=false;
	switch ($device_type){
		case RAM_DEVICE:
		//Memoire
		if ($cfg_ocs["import_device_memory"]){
			$do_clean=true;
			
			$query2 = "select * from memories where DEVICEID = '".$ocs_id."' ORDER BY ID";
			$result2 = $dbocs->query($query2);
			if($dbocs->numrows($result2) > 0) {
				while($line2 = $dbocs->fetch_array($result2)) {
						$line2=addslashes_deep($line2);			
					if(!empty($line2["CAPACITY"])&&$line2["CAPACITY"]!="No") {
						if($line2["DESCRIPTION"]) $ram["designation"] = $line2["DESCRIPTION"];
						else $ram["designation"] = "Unknown";
						if (!in_array(RAM_DEVICE."$$$$$".$ram["designation"],$import_device)){
							$ram["frequence"] =  $line2["SPEED"];
							$ram["type"] = ocsImportDropdown("glpi_dropdown_ram_type","name",$line2["TYPE"]);
							$ram_id = ocsAddDevice(RAM_DEVICE,$ram);
							$devID=compdevice_add($glpi_id,RAM_DEVICE,$ram_id,$line2["CAPACITY"],$dohistory);
							addToOcsArray($glpi_id,array($devID=>RAM_DEVICE."$$$$$".$ram["designation"]),"import_device");
						} else {
							$id=array_search(RAM_DEVICE."$$$$$".$ram["designation"],$import_device);
							unset($import_device[$id]);
						}
					}
				}
			}
		}
		break;
		case HDD_DEVICE:
		//Disque Dur
		if ($cfg_ocs["import_device_hdd"]){
			$do_clean=true;
			
			$query2 = "select * from storages where DEVICEID = '".$ocs_id."' ORDER BY ID";
			$result2 = $dbocs->query($query2);
			if($dbocs->numrows($result2) > 0) {
				while($line2 = $dbocs->fetch_array($result2)) {
						$line2=addslashes_deep($line2);				
					if(!empty($line2["DISKSIZE"])&&eregi("hard disk",$line2["TYPE"])) {
						if($line2["NAME"]) $dd["designation"] = $line2["NAME"];
						else if($line2["MODEL"]) $dd["designation"] = $line2["MODEL"];
						else $dd["designation"] = "Unknown";
						if (!in_array(HDD_DEVICE."$$$$$".$dd["designation"],$import_device)){
							$dd["specif_default"] =  $line2["DISKSIZE"];
							$dd_id = ocsAddDevice(HDD_DEVICE,$dd);
							$devID=compdevice_add($glpi_id,HDD_DEVICE,$dd_id,$line2["DISKSIZE"],$dohistory);
							addToOcsArray($glpi_id,array($devID=>HDD_DEVICE."$$$$$".$dd["designation"]),"import_device");
						} else {
							$id=array_search(HDD_DEVICE."$$$$$".$dd["designation"],$import_device);
							unset($import_device[$id]);
						}

					}
				}
			}
		}
		break;
		case DRIVE_DEVICE:
		//lecteurs
		if ($cfg_ocs["import_device_drives"]){
			$do_clean=true;
			
			$query2 = "select * from storages where DEVICEID = '".$ocs_id."'";
			$result2 = $dbocs->query($query2);
			if($dbocs->numrows($result2) > 0) {
				while($line2 = $dbocs->fetch_array($result2)) {
					$line2=addslashes_deep($line2);
					if(!eregi("hard disk",$line2["TYPE"])) {
						if($line2["NAME"]) $stor["designation"] = $line2["NAME"];
						else if($line2["MODEL"]) $stor["designation"] = $line2["MODEL"];
						else $stor["designation"] = "Unknown";
						if (!in_array(DRIVE_DEVICE."$$$$$".$stor["designation"],$import_device)){
							$stor["specif_default"] =  $line2["DISKSIZE"];
							$stor_id = ocsAddDevice(DRIVE_DEVICE,$stor);
							$devID=compdevice_add($glpi_id,DRIVE_DEVICE,$stor_id,"",$dohistory);
							addToOcsArray($glpi_id,array($devID=>DRIVE_DEVICE."$$$$$".$stor["designation"]),"import_device");
						} else {
							$id=array_search(DRIVE_DEVICE."$$$$$".$stor["designation"],$import_device);
							unset($import_device[$id]);
						}

					}
				}
			}
		}
		break;
		case PCI_DEVICE:
		//Modems
		if ($cfg_ocs["import_device_modems"]){	
			$do_clean=true;
			
			$query2 = "select * from modems where DEVICEID = '".$ocs_id."' ORDER BY ID";
			$result2 = $dbocs->query($query2);
			if($dbocs->numrows($result2) > 0) {
				while($line2 = $dbocs->fetch_array($result2)) {
						$line2=addslashes_deep($line2);				
						$mdm["designation"] = $line2["NAME"];
						if (!in_array(PCI_DEVICE."$$$$$".$mdm["designation"],$import_device)){
							if(!empty($line2["DESCRIPTION"])) $mdm["comment"] = $line2["TYPE"]."\r\n".$line2["DESCRIPTION"];
							$mdm_id = ocsAddDevice(PCI_DEVICE,$mdm);
							$devID=compdevice_add($glpi_id,PCI_DEVICE,$mdm_id,"",$dohistory);
							addToOcsArray($glpi_id,array($devID=>PCI_DEVICE."$$$$$".$mdm["designation"]),"import_device");
						} else {
							$id=array_search(PCI_DEVICE."$$$$$".$mdm["designation"],$import_device);
							unset($import_device[$id]);
						}

				}
			}
		}
		//Ports
		if ($cfg_ocs["import_device_ports"]){
			
			$query2 = "select * from ports where DEVICEID = '".$ocs_id."' ORDER BY ID";
			$result2 = $dbocs->query($query2);
			if($dbocs->numrows($result2) > 0) {
				while($line2 = $dbocs->fetch_array($result2)) {
						$line2=addslashes_deep($line2);			
						$port["designation"]="";	
						if ($line2["TYPE"]!="Other") $port["designation"] .= $line2["TYPE"];
						if ($line2["NAME"]!="Not Specified") $port["designation"] .= " ".$line2["NAME"];
						else if ($line2["CAPTION"]!="None") $port["designation"] .= " ".$line2["CAPTION"];
						if (!empty($port["designation"]))
						if (!in_array(PCI_DEVICE."$$$$$".$port["designation"],$import_device)){
							if(!empty($line2["DESCRIPTION"])&&$line2["DESCRIPTION"]!="None") $port["comment"] = $line2["DESCRIPTION"];
							$port_id = ocsAddDevice(PCI_DEVICE,$port);
							$devID=compdevice_add($glpi_id,PCI_DEVICE,$port_id,"",$dohistory);
							addToOcsArray($glpi_id,array($devID=>PCI_DEVICE."$$$$$".$port["designation"]),"import_device");
						} else {
							$id=array_search(PCI_DEVICE."$$$$$".$port["designation"],$import_device);
							unset($import_device[$id]);
						}						
				}
			}
		}
		break;
		case PROCESSOR_DEVICE:
		//Processeurs : 
		if ($cfg_ocs["import_device_processor"]){
			$do_clean=true;
			
			$query = "select * from hardware WHERE DEVICEID='$ocs_id'";
			$result = $dbocs->query($query) or die($dbocs->error());
			if ($dbocs->numrows($result)==1){
				$line=$dbocs->fetch_array($result);
				$line=addslashes_deep($line);				
				for($i = 0;$i < $line["PROCESSORN"]; $i++) {
					$processor = array();
					$processor["designation"] = $line["PROCESSORT"];
					if (!in_array(PROCESSOR_DEVICE."$$$$$".$processor["designation"],$import_device)){
						$proc_id = ocsAddDevice(PROCESSOR_DEVICE,$processor);
						$devID=compdevice_add($glpi_id,PROCESSOR_DEVICE,$proc_id,$line["PROCESSORS"],$dohistory);
						addToOcsArray($glpi_id,array($devID=>PROCESSOR_DEVICE."$$$$$".$processor["designation"]),"import_device");
					} else {
						$id=array_search(PROCESSOR_DEVICE."$$$$$".$processor["designation"],$import_device);
						unset($import_device[$id]);
					}						
				}
			}
		}
		break;
		case NETWORK_DEVICE:
		//Carte reseau
		if ($cfg_ocs["import_device_iface"]||$cfg_ocs["import_ip"]){
			
			$query2 = "select * from networks where DEVICEID = '".$ocs_id."' ORDER BY ID";
			$result2 = $dbocs->query($query2);
			$i=0;
			// Add network device
			if($dbocs->numrows($result2) > 0) {
				while($line2 = $dbocs->fetch_array($result2)) {
					$line2=addslashes_deep($line2);				
					if ($cfg_ocs["import_device_iface"]){
						$do_clean=true;
						$network["designation"] = $line2["DESCRIPTION"];
						if (!in_array(NETWORK_DEVICE."$$$$$".$network["designation"],$import_device)){
							if(!empty($line2["SPEED"])) $network["bandwidth"] =  $line2["SPEED"];
							$net_id = ocsAddDevice(NETWORK_DEVICE,$network);
							$devID=compdevice_add($glpi_id,NETWORK_DEVICE,$net_id,$line2["MACADDR"],$dohistory);
							addToOcsArray($glpi_id,array($devID=>NETWORK_DEVICE."$$$$$".$network["designation"]),"import_device");
						} else {
							$id=array_search(NETWORK_DEVICE."$$$$$".$network["designation"],$import_device);
							unset($import_device[$id]);
						}						
					}
					if (!empty($line2["IPADDRESS"])&&$cfg_ocs["import_ip"]){
						// Is there an existing networking port ?
						$query="SELECT * FROM glpi_networking_ports WHERE device_type='".COMPUTER_TYPE."' AND on_device='$glpi_id' AND ifaddr='".$line2["IPADDRESS"]."'";
				
						
						$result=$db->query($query);
						$netid=0;
						if ($db->numrows($result)>0)
							$netid=$db->result($result,0,"ID");
						unset($netport);
						$netport["ifaddr"]=$line2["IPADDRESS"];
						$netport["ifmac"]=$line2["MACADDR"];
						$netport["iface"]=ocsImportDropdown("glpi_dropdown_iface","name",$line2["TYPE"]);
						$netport["name"]=$line2["DESCRIPTION"];
						$netport["on_device"]=$glpi_id;
						$netport["logical_number"]=$i;
						$netport["device_type"]=COMPUTER_TYPE;
							
						if ($netid) {
							$netport["ID"]=$netid;
							updateNetport($netport);
						} else {
							addNetport($netport);
						}
						$i++;
					}
				}
			}
			
		}
		break;
		case GFX_DEVICE:
		//carte graphique
		if ($cfg_ocs["import_device_gfxcard"]){
			$do_clean=true;
			
			$query2 = "select distinct(NAME) as NAME, MEMORY from videos where DEVICEID = '".$ocs_id."'and NAME != '' ORDER BY ID";
			$result2 = $dbocs->query($query2);
			if($dbocs->numrows($result2) > 0) {
				while($line2 = $dbocs->fetch_array($result2)) {
						$line2=addslashes_deep($line2);				
						$video["designation"] = $line2["NAME"];
						if (!in_array(GFX_DEVICE."$$$$$".$video["designation"],$import_device)){
							$video["ram"]="";
							if(!empty($line2["MEMORY"])) $video["ram"] =  $line2["MEMORY"];
							$video_id = ocsAddDevice(GFX_DEVICE,$video);
							$devID=compdevice_add($glpi_id,GFX_DEVICE,$video_id,$video["ram"],$dohistory);
							addToOcsArray($glpi_id,array($devID=>GFX_DEVICE."$$$$$".$video["designation"]),"import_device");
						} else {
							$id=array_search(GFX_DEVICE."$$$$$".$video["designation"],$import_device);
							unset($import_device[$id]);
						}						
				}
			}
		}
		break;
		case SND_DEVICE:
		//carte son
		if ($cfg_ocs["import_device_sound"]){
			$do_clean=true;
			
			$query2 = "select distinct(NAME) as NAME, DESCRIPTION from sounds where DEVICEID = '".$ocs_id."' AND NAME != '' ORDER BY ID";
			$result2 = $dbocs->query($query2);
			if($dbocs->numrows($result2) > 0) {
				while($line2 = $dbocs->fetch_array($result2)) {
						$line2=addslashes_deep($line2);				
						$snd["designation"] = $line2["NAME"];
						if (!in_array(SND_DEVICE."$$$$$".$snd["designation"],$import_device)){
							if(!empty($line2["DESCRIPTION"])) $snd[" comment"] =  $line2["DESCRIPTION"];
							$snd_id = ocsAddDevice(SND_DEVICE,$snd);
							$devID=compdevice_add($glpi_id,SND_DEVICE,$snd_id,"",$dohistory);
							addToOcsArray($glpi_id,array($devID=>SND_DEVICE."$$$$$".$snd["designation"]),"import_device");
						} else {
							$id=array_search(SND_DEVICE."$$$$$".$snd["designation"],$import_device);
							unset($import_device[$id]);
						}						
					}
			}
		}
		break;
	}

	// Delete Unexisting Items not found in OCS
	if ($do_clean&&count($import_device)){
		foreach ($import_device as $key => $val)
		if (ereg($device_type."$$$$$",$val)){
			unlink_device_computer($key,$dohistory);
			deleteInOcsArray($glpi_id,$key,"import_device");
			}
	}
		//Alimentation
		//Carte mere
}

/**
* Add a new device.
*
* Add a new device if doesn't exist.
*
*@param $device_type integer : device type identifier.
*@param $dev_array array : device fields.
*
*@return integer : device id.
*
**/
function ocsAddDevice($device_type,$dev_array) {
	
	global $db;
	$query = "select * from ".getDeviceTable($device_type)." WHERE designation='".$dev_array["designation"]."'";
	$result = $db->query($query);
	if($db->numrows($result) == 0) {
		$dev = new Device($device_type);
		foreach($dev_array as $key => $val) {
			$dev->fields[$key] = $val;
		}
		return($dev->addToDB());
	} else {
	$line = $db->fetch_array($result);
	return $line["ID"];
	}

}

/**
* Import the devices for a computer
*
* 
*
*@param $glpi_id integer : glpi computer id.
*@param $ocs_id integer : ocs computer id (DEVICEID).
*
*@return Nothing (void).
*
**/
function ocsUpdatePeripherals($device_type,$glpi_id,$ocs_id,$cfg_ocs,$import_periph,$dohistory){
	global $db,$dbocs;
	$do_clean=false;
	switch ($device_type){
		case MONITOR_TYPE:
		if ($cfg_ocs["import_monitor"]){
			$do_clean=true;
			
			$query = "select DISTINCT CAPTION, MANUFACTURER, DESCRIPTION, SERIAL from monitors where DEVICEID = '".$ocs_id."' and CAPTION <> 'NULL'";
			$result = $dbocs->query($query) or die($dbocs->error());
		
			if($dbocs->numrows($result) > 0) 
			while($line = $dbocs->fetch_array($result)) {
				$line=addslashes_deep($line);
				
				$mon["name"] = $line["CAPTION"];
				if (!in_array($mon["name"],$import_periph)){
					$mon["FK_glpi_enterprise"] = ocsImportEnterprise($line["MANUFACTURER"]);
					$mon["comments"] = $line["DESCRIPTION"];
					$mon["serial"] = $line["SERIAL"];
					$mon["date_mod"] = date("Y-m-d H:i:s");
					$id_monitor=0;

					if($cfg_ocs["import_monitor"] == 1) {
						//Config says : manage monitors as global
						//check if monitors already exists in GLPI
						$mon["is_global"]=1;
						$db = new db;
						$query = "select ID from glpi_monitors where name = '".$line["CAPTION"]."' AND is_global = '1'";
						$result_search = $db->query($query);
						if($db->numrows($result_search) > 0) {
							//Periph is already in GLPI
							//Do not import anything just get periph ID for link
							$id_monitor = $db->result($result_search,0,"ID");
						} else {
							$m=new Monitor;
							$m->fields=$mon;
							$id_monitor=$m->addToDB();
						}
					} else if($cfg_ocs["import_monitor"] == 2) {
						//COnfig says : manage monitors as single units
						//Import all monitors as non global.
						$mon["is_global"]=0;
						$m=new Monitor;
						$m->fields=$mon;
						$id_monitor=$m->addToDB();
					}	
					if ($id_monitor){
						$connID=Connect("",$id_monitor,$glpi_id,MONITOR_TYPE);
						addToOcsArray($glpi_id,array($connID=>$mon["name"]),"import_monitor");
					}
				} else {
					$id=array_search($mon["name"],$import_periph);
					unset($import_periph[$id]);
				}
			}
		}
		break;
		case PRINTER_TYPE:
		if ($cfg_ocs["import_printer"]){
			$do_clean=true;
			
			$query = "select * from printers where DEVICEID = '".$ocs_id."' AND DRIVER <> ''";
			$result = $dbocs->query($query) or die($dbocs->error());
		
			if($dbocs->numrows($result) > 0) 
			while($line = $dbocs->fetch_array($result)) {
				$line=addslashes_deep($line);
				
				$print["name"] = $line["DRIVER"];
				if (!in_array($print["name"],$import_periph)){
					$print["comments"] = $line["PORT"]."\r\n".$line["NAME"];
					$print["date_mod"] = date("Y-m-d H:i:s");
					$id_printer=0;

					if($cfg_ocs["import_printer"] == 1) {
						//Config says : manage printers as global
						//check if printers already exists in GLPI
						$print["is_global"]=1;
						$db = new db;
						$query = "select ID from glpi_printers where name = '".$line["DRIVER"]."' AND is_global = '1'";
						$result_search = $db->query($query);
						if($db->numrows($result_search) > 0) {
							//Periph is already in GLPI
							//Do not import anything just get periph ID for link
							$id_printer = $db->result($result_search,0,"ID");
						} else {
							$p=new Printer;
							$p->fields=$print;
							$id_printer=$p->addToDB();
						}
					} else if($cfg_ocs["import_printer"] == 2) {
						//COnfig says : manage printers as single units
						//Import all printers as non global.
						$print["is_global"]=0;
						$p=new Printer;
						$p->fields=$print;
						$id_printer=$p->addToDB();
					}	
					if ($id_printer){
						$connID=Connect("",$id_printer,$glpi_id,PRINTER_TYPE);
						addToOcsArray($glpi_id,array($connID=>$print["name"]),"import_printers");
					}
				} else {
					$id=array_search($print["name"],$import_periph);
					unset($import_periph[$id]);
				}
			}
		}
		break;
		case PERIPHERAL_TYPE:
		if ($cfg_ocs["import_periph"]){
			$do_clean=true;
			
			$query = "select DISTINCT CAPTION, MANUFACTURER, INTERFACE, TYPE from inputs where DEVICEID = '".$ocs_id."' and CAPTION <> ''";
			$result = $dbocs->query($query) or die($dbocs->error());
			if($dbocs->numrows($result) > 0) 
			while($line = $dbocs->fetch_array($result)) {
				$line=addslashes_deep($line);

				$periph["name"] = $line["CAPTION"];
				if (!in_array($periph["name"],$import_periph)){
					if ($line["MANUFACTURER"]!="NULL") $periph["brand"] = $line["MANUFACTURER"];
					if ($line["INTERFACE"]!="NULL") $periph["comments"] = $line["INTERFACE"];
					$periph["type"] = ocsImportDropdown("glpi_type_peripherals","name",$line["TYPE"]);
					$periph["date_mod"] = date("Y-m-d H:i:s");
					
					$id_periph=0;

					if($cfg_ocs["import_periph"] == 1) {
						//Config says : manage peripherals as global
						//check if peripherals already exists in GLPI
						$periph["is_global"]=1;
						$db = new db;
						$query = "select ID from glpi_peripherals where name = '".$line["CAPTION"]."' AND is_global = '1'";
						$result_search = $db->query($query);
						if($db->numrows($result_search) > 0) {
							//Periph is already in GLPI
							//Do not import anything just get periph ID for link
							$id_periph = $db->result($result_search,0,"ID");
						} else {
							$p=new Peripheral;
							$p->fields=$periph;
							$id_periph=$p->addToDB();
						}
					} else if($cfg_ocs["import_periph"] == 2) {
						//COnfig says : manage peripherals as single units
						//Import all peripherals as non global.
						$periph["is_global"]=0;
						$p=new Peripheral;
						$p->fields=$periph;
						$id_periph=$p->addToDB();
					}	
					if ($id_periph){
						$connID=Connect("",$id_periph,$glpi_id,PERIPHERAL_TYPE);
						addToOcsArray($glpi_id,array($connID=>$periph["name"]),"import_periph");
					}
				} else {
					$id=array_search($periph["name"],$import_periph);
					unset($import_periph[$id]);
				}
			}
		}
		break;
	}
	
	
	// Disconnect Unexisting Items not found in OCS
	if ($do_clean&&count($import_periph)){
		foreach ($import_periph as $key => $val){
			

			$query = "SELECT * FROM glpi_connect_wire where ID = '".$key."'";
			$result=$db->query($query);
			if ($db->numrows($result)>0){
				while ($data=$db->fetch_assoc($result)){
					$query2="SELECT COUNT(*) FROM glpi_connect_wire WHERE end1 = '".$data['end1']."' and type = '".$device_type."'";
					$result2=$db->query($query2);
					if ($db->result($result2,0,0)==1){
						switch ($device_type){
							case MONITOR_TYPE:
							deleteMonitor(array('ID'=>$data['end1']),1);
							break;
							case PRINTER_TYPE:
							deletePrinter(array('ID'=>$data['end1']),1);
							break;
							case PERIPHERAL_TYPE:
							deletePeripheral(array('ID'=>$data['end1']),1);
							break;
						}
					}
				}
			}
			Disconnect($key);
			
			switch ($device_type){
				case MONITOR_TYPE:
				deleteInOcsArray($glpi_id,$key,"import_monitor");
				break;
				case PRINTER_TYPE:
				deleteInOcsArray($glpi_id,$key,"import_printer");
				break;
				case PERIPHERAL_TYPE:
				deleteInOcsArray($glpi_id,$key,"import_peripheral");
				break;
			}
		}
	}

}

/**
* Update config of a new software
*
* This function create a new software in GLPI with some general datas.
*
*@param $computer : id of a computer.
*@param $name : name of the software.
*@param $version : version of the software.
*@param $publisher : id for a enterprise.
*
*@return integer : inserted software id.
*
**/
function ocsUpdateSoftware($glpi_id,$ocs_id,$cfg_ocs,$import_software) {
	global $dbocs,$db;
	if($cfg_ocs["import_software"]){
		
		$query2 = "SELECT softwares.NAME AS INITNAME, dico_soft.FORMATTED AS NAME, softwares.VERSION AS VERSION, softwares.PUBLISHER AS PUBLISHER FROM softwares INNER JOIN dico_soft ON (softwares.NAME = dico_soft.EXTRACTED) WHERE softwares.DEVICEID='$ocs_id'";
		$already_imported=array();
		$result2 = $dbocs->query($query2) or die($dbocs->error());
		if ($dbocs->numrows($result2)>0)
		while ($data2 = $dbocs->fetch_array($result2)){
			$data2=addslashes_deep($data2);
			$initname =  $data2["INITNAME"];
			$name= $data2["NAME"];
			$version = $data2["VERSION"];
			$publisher = $data2["PUBLISHER"];
			
			// Import Software
			if (!in_array($name,$already_imported)){ // Manage multiple software with the same name = only one install
				$already_imported[]=$name;
			if (!in_array($initname,$import_software)){
	        		
				$query_search = "SELECT ID FROM glpi_software WHERE name = '".$name."' ";
				$result_search = $db->query($query_search) or die("Verification existence logiciel :".$name." v:"." - ".$version.$db->error());
				if ($db->numrows($result_search)>0){
					$data = $db->fetch_array($result_search);
					$isNewSoft = $data["ID"];
				} else {
					$isNewSoft = 0;
				}
	
				if (!$isNewSoft) {
					$soft = new Software;
					$soft->fields["name"] = $name;
					$soft->fields["version"] = $version;
					if (!empty($publisher))
						$soft->fields["FK_glpi_enterprise"] = ocsImportEnterprise($publisher);
					$isNewSoft = $soft->addToDB();
				}
				if ($isNewSoft){
					$instID=installSoftware($glpi_id,ocsImportLicense($isNewSoft));
					addToOcsArray($glpi_id,array($instID=>$initname),"import_software");
				}
						
			} else { // Check if software always exists with is real name
				
				$id=array_search($initname,$import_software);
				unset($import_software[$id]);
				
				$query_name="SELECT glpi_software.ID as ID , glpi_software.name AS NAME FROM glpi_inst_software LEFT JOIN glpi_licenses ON (glpi_inst_software.license=glpi_licenses.ID) LEFT JOIN glpi_software ON (glpi_licenses.sID = glpi_software.ID) WHERE glpi_inst_software.ID='$id'";
				$result_name=$db->query($query_name);
				if ($db->numrows($result_name)==1){
					if ($db->result($result_name,0,"NAME")!=$name){
						$updates["name"]=$name;
						$updates["version"]=$version;
						if (!empty($publisher))
							$updates["FK_glpi_enterprise"] = ocsImportEnterprise($publisher);
						$updates["ID"]=$db->result($result_name,0,"ID");
						updateSoftware($updates);
					}
				}
			}
			}
		} 

		// Disconnect Unexisting Items not found in OCS
		if (count($import_software)){
			
			foreach ($import_software as $key => $val){
			
				$query = "SELECT * from glpi_inst_software where ID = '".$key."'";
				$result=$db->query($query);
				if ($db->numrows($result)>0)
				while ($data=$db->fetch_assoc($result)){
					$query2="SELECT COUNT(*) from glpi_inst_software where license = '".$data['license']."'";
					$result2=$db->query($query2);
					if ($db->result($result2,0,0)==1){
						$lic=new License;
						$lic->getfromDB($data['license']);
						$query3="SELECT COUNT(*) FROM glpi_licenses where sID='".$lic->fields['sID']."'";
						$result3=$db->query($query3);
						if ($db->result($result3,0,0)==1){
							deleteSoftware(array('ID'=>$lic->fields['sID']),1);
						}
						deleteLicense($data['license']);
					}
				}
		
				uninstallSoftware($key);
				deleteInOcsArray($glpi_id,$key,"import_software");
			}
		}
	}
}

/**
* Import config of a new license
*
* This function create a new license in GLPI with some general datas.
*
*@param $software : id of a software.
*
*@return integer : inserted license id.
*
**/
function ocsImportLicense($software) {
    global $db,$langOcs;
    
    $query = "SELECT ID FROM glpi_licenses WHERE sid = '".$software."'";
    $result = $db->query($query) or die("Verification existence License du soft-id :".$software." - ".$db->error());
    if ($db->numrows($result)>0){
        $data = $db->fetch_array($result);
        $isNewLicc = $data["ID"];
    } else {
        $isNewLicc = 0;
    }
    if (!$isNewLicc) {
        $licc = new License;
        $licc->fields["sid"] = $software;
        $licc->fields["serial"] = "global";
        $isNewLicc = $licc->addToDB();
    }
    return($isNewLicc);
}


/**
* Delete old licenses
*
* Delete all old licenses of a computer.
*
*@param $glpi_computer_id integer : glpi computer id.
*
*@return nothing.
*
**/
function ocsResetLicenses($glpi_computer_id) {

    global $db;


	$query = "SELECT * from glpi_inst_software where cid = '".$glpi_computer_id."'";
	$result=$db->query($query);
	if ($db->numrows($result)>0){
		while ($data=$db->fetch_assoc($result)){
			$query2="SELECT COUNT(*) from glpi_inst_software where license = '".$data['license']."'";
			$result2=$db->query($query2);
			if ($db->result($result2,0,0)==1){
				$lic=new License;
				$lic->getfromDB($data['license']);
				$query3="SELECT COUNT(*) FROM glpi_licenses where sID='".$lic->fields['sID']."'";
				$result3=$db->query($query3);
				if ($db->result($result3,0,0)==1){
					deleteSoftware(array('ID'=>$lic->fields['sID']),1);
				}
				deleteLicense($data['license']);
				
			}
		}

		$query = "delete from glpi_inst_software where cid = '".$glpi_computer_id."'";
		$db->query($query);
	}

}

/**
* Delete old devices settings
*
* Delete Old device settings.
*
*@param $device_type integer : device type identifier.
*@param $glpi_computer_id integer : glpi computer id.
*
*@return nothing.
*
**/
function ocsResetDevices($glpi_computer_id, $device_type) {
	global $db;
	$query = "delete from glpi_computer_device where device_type = '".$device_type."' AND FK_computers = '".$glpi_computer_id."'";
	$db->query($query);
}

/**
* Delete old periphs
*
* Delete all old periphs for a computer.
*
*@param $glpi_computer_id integer : glpi computer id.
*
*@return nothing.
*
**/
function ocsResetPeriphs($glpi_computer_id) {

	global $db;

	$query = "SELECT * FROM glpi_connect_wire where end2 = '".$glpi_computer_id."' and type = '".PERIPHERAL_TYPE."'";
	$result=$db->query($query);
	if ($db->numrows($result)>0){
		while ($data=$db->fetch_assoc($result)){
			$query2="SELECT COUNT(*) FROM glpi_connect_wire WHERE end1 = '".$data['end1']."' and type = '".PERIPHERAL_TYPE."'";
			$result2=$db->query($query2);
			if ($db->result($result2,0,0)==1){
				deletePeripheral(array('ID'=>$data['end1']),1);
			}
		}
		
		$query2 = "delete from glpi_connect_wire where end2 = '".$glpi_computer_id."' and type = '".PERIPHERAL_TYPE."'";
		$db->query($query2);
	}

}
/**
* Delete old monitors
*
* Delete all old licenses of a computer.
*
*@param $glpi_computer_id integer : glpi computer id.
*
*@return nothing.
*
**/
function ocsResetMonitors($glpi_computer_id) {

	global $db;
	$query = "SELECT * FROM glpi_connect_wire where end2 = '".$glpi_computer_id."' and type = '".MONITOR_TYPE."'";
	$result=$db->query($query);
	if ($db->numrows($result)>0){
		while ($data=$db->fetch_assoc($result)){
			$query2="SELECT COUNT(*) FROM glpi_connect_wire WHERE end1 = '".$data['end1']."' and type = '".MONITOR_TYPE."'";
			$result2=$db->query($query2);
			if ($db->result($result2,0,0)==1){
				deleteMonitor(array('ID'=>$data['end1']),1);
			}
		}
		
		$query2 = "delete from glpi_connect_wire where end2 = '".$glpi_computer_id."' and type = '".MONITOR_TYPE."'";
		$db->query($query2) or die("Impossible d'effacer les anciens monitors.".$db->error());
	}

}
/**
* Delete old printers
*
* Delete all old printers of a computer.
*
*@param $glpi_computer_id integer : glpi computer id.
*
*@return nothing.
*
**/
function ocsResetPrinters($glpi_computer_id) {

	global $db;

	$query = "SELECT * FROM glpi_connect_wire where end2 = '".$glpi_computer_id."' and type = '".PRINTER_TYPE."'";
	$result=$db->query($query);
	if ($db->numrows($result)>0){
		while ($data=$db->fetch_assoc($result)){
			$query2="SELECT COUNT(*) FROM glpi_connect_wire WHERE end1 = '".$data['end1']."' and type = '".PRINTER_TYPE."'";
			$result2=$db->query($query2);
			if ($db->result($result2,0,0)==1){
				deletePrinter(array('ID'=>$data['end1']),1);
			}
		}
		
		$query2 = "delete from glpi_connect_wire where end2 = '".$glpi_computer_id."' and type = '".PRINTER_TYPE."'";
		$db->query($query2) or die("Impossible d'effacer les anciens monitors.".$db->error());
	}
}

/**
* Delete old dropdown value
*
* Delete all old dropdown value of a computer.
*
*@param $glpi_computer_id integer : glpi computer id.
*@param $field string : string of the computer table
*@param $table string : dropdown table name
*
*@return nothing.
*
**/
function ocsResetDropdown($glpi_computer_id,$field,$table) {

	global $db;
	$query = "SELECT $field AS VAL FROM glpi_computers where ID = '".$glpi_computer_id."'";
	$result=$db->query($query);
	if ($db->numrows($result)==1){
		$value=$db->result($result,0,"VAL");
		$query = "SELECT COUNT(*) AS CPT FROM glpi_computers where $field = '$value'";
		$result=$db->query($query);
		if ($db->result($result,0,"CPT")==1){
			$query2 = "delete from $table where ID = '$value'";
			$db->query($query2);
		}
	}
}


?>