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
* Class ilObjSurveyListGUI
*
* @author Alex Killing <alex.killing@gmx.de>
* $Id$
*
* @extends ilObjectListGUI
*/


include_once "classes/class.ilObjectListGUI.php";

class ilObjSurveyListGUI extends ilObjectListGUI
{
	/**
	* constructor
	*
	*/
	function ilObjSurveyListGUI()
	{
		$this->ilObjectListGUI();
	}

	/**
	* initialisation
	*/
	function init()
	{
		$this->delete_enabled = true;
		$this->cut_enabled = true;
		$this->subscribe_enabled = true;
		$this->link_enabled = true;
		$this->payment_enabled = false;
		$this->type = "svy";
		$this->gui_class_name = "ilobjsurveygui";

		// general commands array
		$this->commands = array
		(
			array("permission" => "read", "cmd" => "run", "lang_var" => "run"),
			array("permission" => "write", "cmd" => "", "lang_var" => "edit"),
			array("permission" => "", "cmd" => "evaluation", "lang_var" => "evaluation")
		);
	}


	/**
	* inititialize new item
	*
	* @param	int			$a_ref_id		reference id
	* @param	int			$a_obj_id		object id
	* @param	string		$a_title		title
	* @param	string		$a_description	description
	*/
	function initItem($a_ref_id, $a_obj_id, $a_title = "", $a_description = "")
	{
		parent::initItem($a_ref_id, $a_obj_id, $a_title, $a_description);
	}


	/**
	* Get command target frame
	*
	* @param	string		$a_cmd			command
	*
	* @return	string		command target frame
	*/
	function getCommandFrame($a_cmd)
	{
		switch($a_cmd)
		{
			case "":
			case "run":
			case "evaluation":
				$frame = ilFrameTargetInfo::_getFrame("MainContent");
				break;

			default:
		}

		return $frame;
	}



	/**
	* Get item properties
	*
	* @return	array		array of property arrays:
	*						"alert" (boolean) => display as an alert property (usually in red)
	*						"property" (string) => property name
	*						"value" (string) => property value
	*/
	function getProperties()
	{
		global $lng, $ilUser;

		$props = array();

		include_once("survey/classes/class.ilObjSurveyAccess.php");
		if (!ilObjSurveyAccess::_lookupCreationComplete($this->obj_id))
		{
			// no completion
			$props[] = array("alert" => true, "property" => $lng->txt("status"),
				"value" => $lng->txt("warning_survey_not_complete"));
		}
		else
		{
			$finished = ilObjSurveyAccess::_lookupFinished($this->obj_id, $ilUser->id);

			// finished
			if ($finished === 1)
			{
				$stat = $this->lng->txt("finished");
			}
			// not finished
			else if ($finished === 0)
			{
				$stat = $this->lng->txt("not_finished");
			}
			// not started
			else
			{
				$stat = $this->lng->txt("not_started");
			}
			$props[] = array("alert" => false, "property" => $lng->txt("status"),
				"value" => $stat);
		}

		return $props;
	}


	/**
	* Get command link url.
	*
	* @param	int			$a_ref_id		reference id
	* @param	string		$a_cmd			command
	*
	*/
	function getCommandLink($a_cmd)
	{
		// separate method for this line
		$cmd_link = "survey/survey.php?ref_id=".$this->ref_id."&cmd=$a_cmd";

		return $cmd_link;
	}



} // END class.ilObjTestListGUI
?>
