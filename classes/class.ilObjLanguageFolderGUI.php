<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2001 ILIAS open source, University of Cologne            |
	|                                                                             |
	| This program is free software; you can redistribute it and/or               |
	| modify it under the terms of the GNU General Public License                 |
	| as published by the Free Software Foundation; either version 2              |
	| of the License, or (at your option) any later version.                      |
	|                                                                             |
	| This program is distributed in the hope that it will be useful,             |
	| but WITHOUT ANY WARRANTY; without even the implied warranty of              |
	| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the               |
	| GNU General Public License for more details.                                |
	|                                                                             |
	| You should have received a copy of the GNU General Public License           |
	| along with this program; if not, write to the Free Software                 |
	| Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA. |
	+-----------------------------------------------------------------------------+
*/


/**
* Class ilObjLanguageFolderGUI
*
* @author	Stefan Meyer <smeyer@databay.de>
* @version	$Id$
*
* @extends	ilObject
* @package	ilias-core
*/

require_once "classes/class.ilObjLanguage.php";
require_once "class.ilObjectGUI.php";

class ilObjLanguageFolderGUI extends ilObjectGUI
{
	//var $LangFolderObject;

	/**
	* Constructor
	* @access public
	*/
	function ilObjLanguageFolderGUI($a_data,$a_id,$a_call_by_reference)
	{
		$this->type = "lngf";
		$this->ilObjectGUI($a_data,$a_id,$a_call_by_reference);
	}

	/**
	* show installed languages
	*
	* @access	public
	*/
	function viewObject()
	{
		global $rbacsystem;
		
		if (!$rbacsystem->checkAccess("visible,read",$this->object->getRefId()))
		{
			$this->ilias->raiseError($this->lng->txt("permission_denied"),$this->ilias->error_obj->MESSAGE);
		}

		//add template for buttons
		$this->tpl->addBlockfile("BUTTONS", "buttons", "tpl.buttons.html");
		
		$this->tpl->setCurrentBlock("btn_cell");
		$this->tpl->setVariable("BTN_LINK","adm_object.php?ref_id=".$this->ref_id."&cmd=refresh");
		$this->tpl->setVariable("BTN_TXT",$this->lng->txt("refresh_languages"));
		$this->tpl->parseCurrentBlock();
		
		$this->tpl->setCurrentBlock("btn_cell");
		$this->tpl->setVariable("BTN_LINK","adm_object.php?ref_id=".$this->ref_id."&cmd=checkLanguage");
		$this->tpl->setVariable("BTN_TXT",$this->lng->txt("check_languages"));
		$this->tpl->parseCurrentBlock();

		//prepare objectlist
		$this->data = array();
		$this->data["data"] = array();
		$this->data["ctrl"] = array();
		$this->data["cols"] = array("","type","language","status","last_change");

		$languages = $this->object->getLanguages();
	
		foreach ($languages as $lang_key => $lang_data)
		{
			$status = "";
	
			// set status info (in use oder systemlanguage)
			if ($lang_data["status"])
			{
				$status = "<span class=\"small\"> (".$this->lng->txt($lang_data["status"]).")</span>";
			}

			// set remark color
			switch ($lang_data["info"])
			{
				case "file_not_found":
					$remark = "<span class=\"smallred\"> ".$this->lng->txt($lang_data["info"])."</span>";
					break;
				case "new_language":
					$remark = "<span class=\"smallgreen\"> ".$this->lng->txt($lang_data["info"])."</span>";
					break;
				default:
					$remark = "";
					break;
			}	

			//visible data part
			$this->data["data"][] = array(
									"type" 			=> "lng",
									"language"		=> $lang_data["name"].$status,
									"status"		=> $this->lng->txt($lang_data["desc"]).$remark,
									"last_change"	=> $lang_data["last_update"],
									"obj_id"		=> $lang_data["obj_id"]
										);

		}
	
		$this->maxcount = count($this->data["data"]);

		// sorting array
		require_once "./include/inc.sort.php";
		$this->data["data"] = sortArray($this->data["data"],$_GET["sort_by"],$_GET["sort_order"]);

		// now compute control information
		foreach ($this->data["data"] as $key => $val)
		{
			$this->data["ctrl"][$key] = array(
											"obj_id"	=> $val["obj_id"],
											"type"		=> $val["type"]
											);		

			unset($this->data["data"][$key]["obj_id"]);
			$this->data["data"][$key]["last_change"] = ilFormat::formatDate($this->data["data"][$key]["last_change"]);
		}

		$this->displayList();
	}

	/**
	* display object list
	*
	* @access	public
 	*/
	function displayList()
	{
		global $tree, $rbacsystem;

		require_once "./classes/class.ilTableGUI.php";

		// load template for table
		$this->tpl->addBlockfile("ADM_CONTENT", "adm_content", "tpl.table.html");
		// load template for table content data
		$this->tpl->addBlockfile("TBL_CONTENT", "tbl_content", "tpl.obj_tbl_rows.html");

		$num = 0;

		//$obj_str = ($this->call_by_reference) ? "" : "&obj_id=".$this->obj_id;
		$this->tpl->setVariable("FORMACTION", "adm_object.php?ref_id=".$this->ref_id."$obj_str&cmd=gateway");

		// create table
		$tbl = new ilTableGUI();
		
		// title & header columns
		$tbl->setTitle($this->lng->txt("obj_".$this->object->getType()),"icon_".$this->object->getType()."_b.gif",$this->lng->txt("obj_".$this->object->getType()));
		$tbl->setHelp("tbl_help.php","icon_help.gif",$this->lng->txt("help"));
		
		foreach ($this->data["cols"] as $val)
		{
			$header_names[] = $this->lng->txt($val);
		}
		
		$tbl->setHeaderNames($header_names);

		$header_params = array("ref_id" => $this->ref_id);
		$tbl->setHeaderVars($this->data["cols"],$header_params);
		
		// control
		$tbl->setOrderColumn($_GET["sort_by"]);
		$tbl->setOrderDirection($_GET["sort_order"]);
		$tbl->setLimit(0);
		$tbl->setOffset(0);
		$tbl->setMaxCount($this->maxcount);
		
		// SHOW VALID ACTIONS
		$this->tpl->setVariable("COLUMN_COUNTS",count($this->data["cols"]));
		$this->showActions();
		
		// footer
		$tbl->setFooter("tblfooter",$this->lng->txt("previous"),$this->lng->txt("next"));
		//$tbl->disable("footer");
		
		// render table
		$tbl->render();

		if (is_array($this->data["data"][0]))
		{
			//table cell
			for ($i=0; $i < count($this->data["data"]); $i++)
			{
				$data = $this->data["data"][$i];
				$ctrl = $this->data["ctrl"][$i];

				// color changing
				$css_row = ilUtil::switchColor($i+1,"tblrow1","tblrow2");

				$this->tpl->setCurrentBlock("checkbox");
				$this->tpl->setVariable("CHECKBOX_ID",$ctrl["obj_id"]);
				$this->tpl->setVariable("CSS_ROW",$css_row);
				$this->tpl->parseCurrentBlock();

				$this->tpl->setCurrentBlock("table_cell");
				$this->tpl->setVariable("CELLSTYLE", "tblrow1");
				$this->tpl->parseCurrentBlock();

				foreach ($data as $key => $val)
				{

					$this->tpl->setCurrentBlock("text");

					if ($key == "type")
					{
						$val = ilUtil::getImageTagByType($val,$this->tpl->tplPath);						
					}

					$this->tpl->setVariable("TEXT_CONTENT", $val);					
					$this->tpl->parseCurrentBlock();

					$this->tpl->setCurrentBlock("table_cell");
					$this->tpl->parseCurrentBlock();

				} //foreach

				$this->tpl->setCurrentBlock("tbl_content");
				$this->tpl->setVariable("CSS_ROW", $css_row);
				$this->tpl->parseCurrentBlock();
			} //for
		} //if is_array
	}

	/**
	* install languages
	*/
	function installObject()
	{
		if (!isset($_POST["id"]))
		{
			$this->ilias->raiseError($this->lng->txt("nothing_checked"),$this->ilias->error_obj->MESSAGE);
		}

		foreach ($_POST["id"] as $obj_id)
		{
			$langObj = new ilObjLanguage($obj_id);
			$key = $langObj->install();

			if ($key != "")
			{
				$lang_installed[] = $key;
			}

			unset($langObj);
		}

		if (isset($lang_installed))
		{
			if (count($lang_installed) == 1)
			{
				$this->data = $this->lng->txt("lang_".$lang_installed[0])." ".strtolower($this->lng->txt("installed")).".";
			}
			else
			{
				foreach ($lang_installed as $lang_key)
				{
					$langnames[] = $this->lng->txt("lang_".$lang_key);
				}
				$this->data = implode(", ",$langnames)." ".strtolower($this->lng->txt("installed")).".";
			}
		}
		else
			$this->data = $this->lng->txt("languages_already_installed");

		$this->out();
	}


	/**
	* uninstall language
	*/
	function uninstallObject()
	{
		if (!isset($_POST["id"]))
		{
			$this->ilias->raiseError($this->lng->txt("nothing_checked"),$this->ilias->error_obj->MESSAGE);
		}

		// uninstall all selected languages
		foreach ($_POST["id"] as $obj_id)
		{
			$langObj = new ilObjLanguage($obj_id);
			if (!($sys_lang = $langObj->isSystemLanguage()))
				if (!($usr_lang = $langObj->isUserLanguage()))
				{
					$key = $langObj->uninstall();
					if ($key != "")
						$lang_uninstalled[] = $key;
				}
			unset($langObj);
		}

		// generate output message
		if (isset($lang_uninstalled))
		{
			if (count($lang_uninstalled) == 1)
			{
				$this->data = $this->lng->txt("lang_".$lang_uninstalled[0])." ".$this->lng->txt("uninstalled");
			}
			else
			{
				foreach ($lang_uninstalled as $lang_key)
				{
					$langnames[] = $this->lng->txt("lang_".$lang_key);
				}

				$this->data = implode(", ",$langnames)." ".$this->lng->txt("uninstalled");
			}
		}
		elseif ($sys_lang)
		{
			$this->data = $this->lng->txt("cannot_uninstall_systemlanguage");
		}
		elseif ($usr_lang)
		{
			$this->data = $this->lng->txt("cannot_uninstall_language_in_use");
		}
		else
		{
			$this->data = $this->lng->txt("languages_already_uninstalled");
		}

		$this->out();
	}

	/**
	* update all installed languages
	*/
	function refreshObject()
	{
		$languages = getObjectList("lng");

		foreach ($languages as $lang)
		{
			$langObj = new ilObjLanguage($lang["obj_id"],false);

			if ($langObj->getStatus() == "installed")
			{
				if ($langObj->check())
				{
					$langObj->flush();
					$langObj->insert();
					$langObj->setTitle($langObj->getKey());
					$langObj->setDescription($langObj->getStatus());
					$langObj->update();
					$langObj->optimizeData();
				}
			}

			unset($langObj);
		}

		$this->data = $this->lng->txt("languages_updated");

		$this->out();
	}


	/**
	* set user language
	*/
	function setUserLanguageObject()
	{
		require_once "classes/class.ilObjUser.php";

		if (!isset($_POST["id"]))
		{
			$this->ilias->raiseError($this->lng->txt("nothing_checked"),$this->ilias->error_obj->MESSAGE);
		}

		if (count($_POST["id"]) != 1)
		{
			$this->ilias->raiseError($this->lng->txt("choose_only_one_language")."<br/>".$this->lng->txt("action_aborted"),$this->ilias->error_obj->MESSAGE);
		}

		$obj_id = $_POST["id"][0];

		$newUserLangObj = new ilObjLanguage($obj_id);

		if ($newUserLangObj->isUserLanguage())
		{
			$this->ilias->raiseError($this->lng->txt("lang_".$newUserLangObj->getKey())." ".$this->lng->txt("is_already_your")." ".$this->lng->txt("user_language")."<br/>".$this->lng->txt("action_aborted"),$this->ilias->error_obj->MESSAGE);
		}

		if ($newUserLangObj->getStatus() != "installed")
		{
			$this->ilias->raiseError($this->lng->txt("lang_".$newUserLangObj->getKey())." ".$this->lng->txt("language_not_installed")."<br/>".$this->lng->txt("action_aborted"),$this->ilias->error_obj->MESSAGE);
		}

		$curUser = new ilObjUser($_SESSION["AccountId"]);
		$curUser->setLanguage($newUserLangObj->getKey());
		$curUser->update();
		//$this->setUserLanguage($new_lang_key);

		$this->data = $this->lng->txt("user_language")." ".$this->lng->txt("changed_to")." ".$this->lng->txt("lang_".$newUserLangObj->getKey()).".";

		$this->out();
	}


	/**
	* set the system language
	*/
	function setSystemLanguageObject ()
	{
		if (!isset($_POST["id"]))
		{
			$this->ilias->raiseError($this->lng->txt("nothing_checked"),$this->ilias->error_obj->MESSAGE);
		}

		if (count($_POST["id"]) != 1)
		{
			$this->ilias->raiseError($this->lng->txt("choose_only_one_language")."<br/>".$this->lng->txt("action_aborted"),$this->ilias->error_obj->MESSAGE);
		}

		$obj_id = $_POST["id"][0];

		$newSysLangObj = new ilObjLanguage($obj_id);

		if ($newSysLangObj->isSystemLanguage())
		{
			$this->ilias->raiseError($this->lng->txt("lang_".$newSysLangObj->getKey())." is already the system language!<br>Action aborted!",$this->ilias->error_obj->MESSAGE);
		}

		if ($newSysLangObj->getStatus() != "installed")
		{
			$this->ilias->raiseError($this->lng->txt("lang_".$newSysLangObj->getKey())." is not installed. Please install that language first.<br>Action aborted!",$this->ilias->error_obj->MESSAGE);
		}

		$this->ilias->setSetting("language", $newSysLangObj->getKey());

		// update ini-file
		$this->ilias->ini->setVariable("language","default",$newSysLangObj->getKey());
		$this->ilias->ini->write();

		$this->data = $this->lng->txt("system_language")." ".$this->lng->txt("changed_to")." ".$this->lng->txt("lang_".$newSysLangObj->getKey()).".";

		$this->out();
	}


	/**
	* check all languages
	*/
	function checkLanguageObject ()
	{
		//$langFoldObj = new ilObjLanguageFolder($_GET["obj_id"]);
		//$this->data = $langFoldObj->checkAllLanguages();
		$this->data = $this->object->checkAllLanguages();
		$this->out();
	}


	function out()
	{
		sendInfo($this->data,true);
		header("location: adm_object.php?ref_id=".$_GET["ref_id"]);
		exit();
	}
} // END class.LanguageFolderObjectOut
?>
