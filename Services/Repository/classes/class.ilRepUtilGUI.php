<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2009 ILIAS open source, University of Cologne            |
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
* Repository GUI Utilities
*
* @author Alex Killing <alex.killing@gmx.de>
* @version $Id$
* @ingroup ServicesRepository
*/
class ilRepUtilGUI
{

	/**
	* Constructor
	*
	* @param	object		parent gui object
	* @param	string		current parent command (like in table2gui)
	*/
	function __construct($a_parent_gui, $a_parent_cmd = "")
	{
		$this->parent_gui = $a_parent_gui;
		$this->parent_cmd = $a_parent_cmd;
	}
	
	
	/**
	* Show delete confirmation table
	*/
	function showDeleteConfirmation($a_ids, $a_supress_message = false)
	{
		global $lng, $ilSetting, $ilCtrl, $tpl;

		if (!is_array($a_ids) || count($a_ids) == 0)
		{
			ilUtil::sendFailure($lng->txt("no_checkbox"), true);
			return false;
		}

		include_once("./Services/Utilities/classes/class.ilConfirmationGUI.php");
		$cgui = new ilConfirmationGUI();

		if(!$a_supress_message)
		{
			$msg = $lng->txt("info_delete_sure");
			
			if (!$ilSetting->get('enable_trash'))
			{
				$msg .= "<br/>".$lng->txt("info_delete_warning_no_trash");
			}
			
			$cgui->setHeaderText($msg);
		}
		$cgui->setFormAction($ilCtrl->getFormAction($this->parent_gui));
		$cgui->setCancel($lng->txt("cancel"), "cancelDelete");
		$cgui->setConfirm($lng->txt("confirm"), "confirmedDelete");

		foreach ($a_ids as $ref_id)
		{
			$obj_id = ilObject::_lookupObjId($ref_id);
			$title = ilObject::_lookupTitle($obj_id);
			$type = ilObject::_lookupType($obj_id);
			$cgui->addItem("id[]", $ref_id, $title, ilUtil::getImagePath("icon_".$type.".gif"),
				$lng->txt("icon")." ".$lng->txt("obj_".$type));
		}
		
		$tpl->setContent($cgui->getHTML());
		return true;
	}
	
	/**
	* Get trashed objects for a container
	*
	* @param	interger	ref id of container
	*/
	function showTrashTable($a_ref_id)
	{
		global $tpl, $tree;
		
		$objects = $tree->getSavedNodeData($a_ref_id);
		
		if (count($objects) == 0)
		{
			ilUtil::sendInfo($this->lng->txt("msg_trash_empty"));
			return;
		}
		include_once("./Services/Repository/classes/class.ilTrashTableGUI.php");
		$ttab = new ilTrashTableGUI($this->parent_gui, "trash");
		$ttab->setData($objects);
		
		$tpl->setContent($ttab->getHTML());
	}
	
	/**
	* Restore objects from trash
	*
	* @param	integer		current ref id
	* @param	array		array of ref ids to be restored
	*/
	function restoreObjects($a_cur_ref_id, $a_ref_ids)
	{
		global $lng;
		
		if (!is_array($a_ref_ids) || count($a_ref_ids) == 0)
		{
			ilUtil::sendFailure($lng->txt("no_checkbox"),true);
			return false;
		}
		else
		{
			try
			{
				include_once("./Services/Repository/classes/class.ilRepUtil.php");
				ilRepUtil::restoreObjects($a_cur_ref_id, $a_ref_ids);
				ilUtil::sendSuccess($lng->txt("msg_undeleted"),true);
			}
			catch (Exception $e)
			{
				ilUtil::sendFailure($e->getMessage(),true);
				return false;
			}
		}
		return true;
	}
	
	/**
	* Delete objects
	*/
	function deleteObjects($a_cur_ref_id, $a_ref_ids)
	{
		global $ilSetting, $lng;
		
		if (!is_array($a_ref_ids) || count($a_ref_ids) == 0)
		{
			ilUtil::sendFailure($lng->txt("no_checkbox"), true);
			return false;
		}
		else
		{
			include_once("./Services/Repository/classes/class.ilRepUtil.php");
			try
			{
				ilRepUtil::deleteObjects($a_cur_ref_id, $a_ref_ids);
				if ($ilSetting->get('enable_trash'))
				{
					ilUtil::sendSuccess($lng->txt("info_deleted"),true);
				}
				else
				{
					ilUtil::sendSuccess($lng->txt("msg_removed"),true);
				}
			}
			catch (Exception $e)
			{
				ilUtil::sendFailure($e->getMessage(), true);
				return false;
			}
		}
	}
	
	/**
	* Remove objects from system
	*/
	function removeObjectsFromSystem($a_ref_ids)
	{
		global $lng;
		
		if (!is_array($a_ref_ids) || count($a_ref_ids) == 0)
		{
			ilUtil::sendFailure($lng->txt("no_checkbox"), true);
			return false;
		}
		else
		{
			include_once("./Services/Repository/classes/class.ilRepUtil.php");
			try
			{
				ilRepUtil::removeObjectsFromSystem($a_ref_ids);
				ilUtil::sendSuccess($lng->txt("msg_removed"),true);
			}
			catch (Exception $e)
			{
				ilUtil::sendFailure($e->getMessage(), true);
				return false;
			}
		}

		return true;
	}
	
	

}
