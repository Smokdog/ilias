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
* GUI class for personal bookmark administration. It manages folders and bookmarks
* with the help of the two corresponding core classes ilBookmarkFolder and ilBookmark.
* Their methods are called in this User Interface class.
*
* @author Alex Killing <alex.killing@gmx.de>
* @author Manfred Thaler <manfred.thaler@endo7.com>
* @version $Id$
*
* @package application
*/

require_once ("classes/class.ilBookmarkExplorer.php");
require_once ("classes/class.ilBookmarkFolder.php");
require_once ("classes/class.ilBookmark.php");
require_once ("classes/class.ilTableGUI.php");

class ilBookmarkAdministrationGUI
{
	/**
	* User Id
	* @var integer
	* @access public
	*/
	var $user_id;

	/**
	* ilias object
	* @var object ilias
	* @access public
	*/
	var $ilias;
	var $tpl;
	var $lng;

	var $tree;
	var $id;
	var $data;
	var $textwidth=100;

	/**
	* Constructor
	* @access	public
	* @param	integer		user_id (optional)
	*/
	function ilBookmarkAdministrationGUI()
	{
		global $ilias, $tpl, $lng, $ilCtrl;
		//print_r($_SESSION["error_post_vars"]);
		// if no bookmark folder id is given, take dummy root node id (that is 1)
		$this->id = (empty($_GET["bmf_id"]))
			? $bmf_id = 1
			: $_GET["bmf_id"];

		// initiate variables
		$this->ilias =& $ilias;
		$this->tpl =& $tpl;
		$this->lng =& $lng;
		$this->ctrl =& $ilCtrl;
		$this->ctrl->setParameter($this, "bmf_id", $this->id);
		$this->user_id = $_SESSION["AccountId"];

		$this->tree = new ilTree($_SESSION["AccountId"]);
		$this->tree->setTableNames('bookmark_tree','bookmark_data');
		$this->root_id = $this->tree->readRootId();
		// set current bookmark view mode
		if (!empty($_GET["set_mode"]))
		{
			$this->ilias->account->writePref("il_bkm_mode", $_GET["set_mode"]);
		}
		$this->mode = $this->ilias->account->getPref("il_bkm_mode");
	}

	/**
	* execute command
	*/
	function &executeCommand()
	{
		$next_class = $this->ctrl->getNextClass();

		switch($next_class)
		{
			default:
				$cmd = $this->ctrl->getCmd("view");
				$this->displayHeader();
				$this->$cmd();
				if ($this->getMode() == 'tree')
				{
					$this->explorer();
				}
				break;
		}
		$this->tpl->show(true);
		return true;
	}
	function executeAction()
	{
		switch($_POST["action"])
		{
			case "delete":
				$this->delete();
				break;
			case "export":
				$this->export();
				break;
			default:
				$this->view();
				break;
		}
		return true;
	}

	/**
	* return display mode
	* flat or tree
	*/
	function getMode() {
		return $this->mode;
	}

	/**
	* output explorer tree with bookmark folders
	*/
	function explorer()
	{
		$this->tpl->addBlockFile("ADM_TREE_CONTENT", "adm_tree_content", "tpl.bookmark_explorer.html");
		$exp = new ilBookmarkExplorer($this->ctrl->getLinkTarget($this),$_SESSION["AccountId"]);
		$exp->setAllowedTypes(array('dum','bmf'));
		$exp->setTargetGet("bmf_id");
		$exp->setSessionExpandVariable('mexpand');
		$this->ctrl->setParameter($this, "bmf_id", $this->id);
		$exp->setExpandTarget($this->ctrl->getLinkTarget($this));
		if ($_GET["mexpand"] == "")
		{
			$mtree = new ilTree($_SESSION["AccountId"]);
			$mtree->setTableNames('bookmark_tree','bookmark_data');
			$expanded = $mtree->readRootId();
		}
		else
		{
			$expanded = $_GET["mexpand"];
		}

		$exp->setExpand($expanded);

		// build html-output
		$exp->setOutput(0);
		$output = $exp->getOutput();

		$this->tpl->setCurrentBlock("adm_tree_content");
		$this->tpl->setVariable("TXT_EXPLORER_HEADER", $this->lng->txt("bookmarks"));
		$this->ctrl->setParameter($this, "bmf_id", 1);
		$this->tpl->setVariable("LINK_EXPLORER_HEADER",$this->ctrl->getLinkTarget($this));

		$this->tpl->setVariable("EXPLORER",$output);
		$this->tpl->parseCurrentBlock();
	}


	/**
	* display header and locator
	*/
	function displayHeader()
	{
		// output locator
		$this->displayLocator();

		// output message
		if($this->message)
		{
			sendInfo($this->message);
		}
		infoPanel();

		$this->tpl->setCurrentBlock("header_image");
		$this->tpl->setVariable("IMG_HEADER", ilUtil::getImagePath("icon_pd_b.gif"));
		$this->tpl->parseCurrentBlock();
		$this->tpl->setVariable("HEADER",$this->lng->txt("personal_desktop"));
	}

	/*
	* display content of bookmark folder
	*/
	function view()
	{
		global $tree;

		include_once("classes/class.ilFrameTargetInfo.php");

		$mtree = new ilTree($_SESSION["AccountId"]);
		$mtree->setTableNames('bookmark_tree','bookmark_data');

		$this->tpl->addBlockFile("ADM_CONTENT", "objects", "tpl.table.html");

		$this->ctrl->setParameter($this, "bmf_id", $this->id);
		$this->tpl->setVariable("FORMACTION", $this->ctrl->getFormAction($this));

		$objects = ilBookmarkFolder::getObjects($this->id);

		$s_mode = ($this->mode == "tree")
				? "flat"
				: "tree";
		$this->tpl->setCurrentBlock("tree_mode");
		$this->ctrl->setParameter($this, "bmf_id", $this->id);
		$this->tpl->setVariable("LINK_MODE", $this->ctrl->getLinkTarget($this)."&set_mode=".$s_mode);
		$this->tpl->setVariable("IMG_TREE",ilUtil::getImagePath("ic_".$s_mode."view.gif"));
		$this->tpl->parseCurrentBlock();


		$this->tpl->setCurrentBlock("objects");
		$this->tpl->addBlockfile("TBL_CONTENT", "tbl_content", "tpl.bookmark_row.html");
		$cnt = 0;

		// return to parent folder
		if ($this->id != $mtree->readRootId() || $this->id =="")
		{
			$this->tpl->setCurrentBlock("tbl_content");

			// color changing
			$css_row = ilUtil::switchColor($cnt++,"tblrow1","tblrow2");

			$this->tpl->setVariable("CHECKBOX", "&nbsp;");
			$this->tpl->setVariable("ROWCOL", $css_row);

			$val = ilUtil::getImagePath("icon_cat.gif");
			$this->tpl->setVariable("IMG", $val);

			// title
			$this->ctrl->setParameter($this, "bmf_id", $mtree->getParentId($this->id));
			$link = $this->ctrl->getLinkTarget($this);
			$this->tpl->setVariable("TXT_TITLE", "..");
			$this->tpl->setVariable("LINK_TARGET", $link);
			$this->tpl->setVariable("FRAME_TARGET", ilFrameTargetInfo::_getFrame("MainContent"));

			$this->tpl->parseCurrentBlock();
		}
		// all checkbox ids
		$a_ids=array();
		foreach ($objects as $key => $object)
		{
			$a_ids[]=$object["type"].":".$object["obj_id"];
			// type icon
			$this->ctrl->setParameter($this, "bmf_id", $this->id);
			$this->ctrl->setParameter($this, "obj_id", $object["obj_id"]);
			$link = ($object["type"] == "bmf")
				? $this->ctrl->getLinkTarget($this, "editFormBookmarkFolder")
				: $this->ctrl->getLinkTarget($this, "editFormBookmark");



			// edit link
			$this->tpl->setCurrentBlock("edit");
			$this->tpl->setVariable("TXT_EDIT", $this->lng->txt("edit"));
			$this->tpl->setVariable("LINK_EDIT", $link);
			$this->tpl->parseCurrentBlock();
			// target
			if (!empty($object["target"]))
			{
				$this->tpl->setCurrentBlock("target");
				$this->tpl->setVariable("TXT_TARGET",
					ilUtil::prepareFormOutput(ilUtil::shortenText(
						$object["target"],$this->textwidth, true)));
				$this->tpl->parseCurrentBlock();
			}
			// description
			if (!empty($object["description"]))
			{
				$this->tpl->setCurrentBlock("description");
				$this->tpl->setVariable("TXT_DESCRIPTION",
					ilUtil::prepareFormOutput($object["description"]));
				$this->tpl->parseCurrentBlock();
			}

			$this->tpl->setCurrentBlock("tbl_content");
			// color changing
			$css_row = ilUtil::switchColor($cnt++,"tblrow1","tblrow2");

			// surpress checkbox for particular object types
			//$this->tpl->setVariable("CHECKBOX_ID", $object["type"].":".$object["obj_id"]);
			$this->tpl->setVariable("CHECKBOX",
				ilUtil::formCheckBox("", "id[]", $object["type"].":".$object["obj_id"]));
			$this->tpl->setVariable("ROWCOL", $css_row);

			$img_type = ($object["type"] == "bmf") ? "cat" : $object["type"];
			$val = ilUtil::getImagePath("icon_".$img_type.".gif");
			$this->tpl->setVariable("IMG", $val);

			// title
			$this->ctrl->setParameter($this, "bmf_id", $object["obj_id"]);
			$link = ($object["type"] == "bmf") ?
				$this->ctrl->getLinkTarget($this) :
				$object["target"];
			$this->tpl->setVariable("TXT_TITLE", ilUtil::prepareFormOutput($object["title"]));
			$this->tpl->setVariable("LINK_TARGET", $link);

			if ($object["type"] == "bmf")
			{
				$this->tpl->setVariable("FRAME_TARGET", ilFrameTargetInfo::_getFrame("MainContent"));
			}
			else
			{
				$this->tpl->setVariable("FRAME_TARGET", ilFrameTargetInfo::_getFrame("ExternalContent"));
			}


			$this->tpl->parseCurrentBlock();
		}

		$tbl = new ilTableGUI();

		if (!empty($this->id) && $this->id != 1)
		{
			$BookmarkFolder = new ilBookmarkFolder($this->id);
			$addstr = " ".$this->lng->txt("in")." ".$BookmarkFolder->getTitle();
		}
		else
		{
			$addstr = "";
		}

		// title & header columns
		$tbl->setTitle($this->lng->txt("bookmarks").$addstr,
			"icon_bm.gif", $this->lng->txt("bookmarks"));
		//$tbl->setHelp("tbl_help.php","icon_help.gif",$this->lng->txt("help"));
		$tbl->setHeaderNames(array("", $this->lng->txt("type"), $this->lng->txt("title"),""));
		$tbl->setHeaderVars(array("", "type", "title","commands"),
			array("bmf_id" => $this->id));
		$tbl->setColumnWidth(array("1%", "1%", "100%", ""));

		//$tbl->setOrderColumn($_GET["sort_by"]);
		//$tbl->setOrderDirection($_GET["sort_order"]);
		$tbl->setLimit($limit);
		$tbl->setOffset($offset);
		$tbl->setMaxCount($maxcount);

		// footer
		$tbl->setFooter("tblfooter", $this->lng->txt("previous"),$this->lng->txt("next"));
		//$tbl->disable("content");
		$tbl->disable("footer");
		$tbl->disable("header");

		// render table
		$tbl->render();

		// SHOW toggle checkboxes
		if (!empty($a_ids))
		{
			$this->tpl->setCurrentBlock("tbl_action_toggle_checkboxes");
			$this->tpl->setVariable("JS_VARNAME","id");
			$this->tpl->setVariable("JS_ONCLICK",ilUtil::array_php2js($a_ids));
			$this->tpl->setVariable("TXT_CHECKALL", $this->lng->txt("check_all"));
			$this->tpl->setVariable("TXT_UNCHECKALL", $this->lng->txt("uncheck_all"));
			$this->tpl->parseCurrentBlock();
		}

		// SHOW POSSIBLE SUB OBJECTS
		$this->tpl->setVariable("IMG_ARROW", ilUtil::getImagePath("arrow_downright.gif"));
		$this->tpl->setVariable("NUM_COLS", 5);
		$this->showPossibleSubObjects();

		$this->tpl->setCurrentBlock("tbl_action_row");
		$this->tpl->setVariable("COLUMN_COUNTS",4);
		$this->tpl->parseCurrentBlock();

	}

	/**
	* output a cell in object list
	*/
	function add_cell($val, $link = "")
	{
		if (!empty($link))
		{
			$this->tpl->setCurrentBlock("begin_link");
			$this->tpl->setVariable("LINK_TARGET", $link);
			$this->tpl->parseCurrentBlock();
			$this->tpl->touchBlock("end_link");
		}

		$this->tpl->setCurrentBlock("text");
		$this->tpl->setVariable("TEXT_CONTENT", $val);
		$this->tpl->parseCurrentBlock();
		$this->tpl->setCurrentBlock("table_cell");
		$this->tpl->parseCurrentBlock();
	}

	/**
	* display locator
	*/
	function displayLocator()
	{
		global $lng;

		if (empty($this->id))
		{
			return;
		}

		$this->tpl->addBlockFile("LOCATOR", "locator", "tpl.locator.html");

		$path = $this->tree->getPathFull($this->id);
//print_r($path);
		$modifier = 1;

		$this->tpl->setVariable("TXT_LOCATOR",$this->lng->txt("locator"));
		$this->tpl->touchBlock("locator_separator");
		$this->tpl->setCurrentBlock("locator_item");
		$this->tpl->setVariable("ITEM", $this->lng->txt("personal_desktop"));
		$this->tpl->setVariable("LINK_ITEM", $this->ctrl->getLinkTargetByClass("ilpersonaldesktopgui"));
		$this->tpl->setVariable("LINK_TARGET","target=\"bottom\"");
		$this->tpl->parseCurrentBlock();

		foreach ($path as $key => $row)
		{
			if ($key < count($path)-$modifier)
			{
				$this->tpl->touchBlock("locator_separator");
			}

			$this->tpl->setCurrentBlock("locator_item");
			$title = ($row["child"] == 1) ?
				$lng->txt("bookmarks") :
				$row["title"];
			$this->tpl->setVariable("ITEM", $title);
			$this->ctrl->setParameter($this, "bmf_id", $row["child"]);
			$this->tpl->setVariable("LINK_ITEM",
				$this->ctrl->getLinkTarget($this));
			$this->tpl->parseCurrentBlock();
		}

		$this->tpl->setCurrentBlock("locator");

		$this->tpl->parseCurrentBlock();
	}

	/**
	* new form
	*/
	function newForm()
	{
		switch($_POST["type"])
		{
			case "bmf":
				$this->newFormBookmarkFolder();
				break;

			case "bm":
				$this->newFormBookmark();
				break;
		}
	}

	/**
	* display new bookmark folder form
	*/
	function newFormBookmarkFolder()
	{
		global $lng;

		$this->tpl->addBlockFile("ADM_CONTENT", "objects", "tpl.bookmark_newfolder.html");
		$this->tpl->setVariable("TITLE", $this->get_last("title", ""));
		$this->tpl->setVariable("TXT_TITLE", $lng->txt("title"));
		$this->tpl->setVariable("TXT_SAVE", $lng->txt("save"));
		$this->tpl->setVariable("TXT_CANCEL", $lng->txt("cancel"));
		$this->tpl->setVariable("TXT_FOLDER_NEW", $lng->txt("bookmark_folder_new"));
		$this->ctrl->setParameter($this, "bmf_id", $this->id);
		$this->tpl->setVariable("FORMACTION", $this->ctrl->getFormAction($this));
		$this->tpl->setVariable("VAL_CMD", "createBookmarkFolder");
		$this->tpl->parseCurrentBlock();
	}


	/**
	* display edit bookmark folder form
	*/
	function editFormBookmarkFolder()
	{
		global $lng;

		$this->tpl->addBlockFile("ADM_CONTENT", "objects", "tpl.bookmark_newfolder.html");

		$bmf = new ilBookmarkFolder($_GET["obj_id"]);

		$this->tpl->setVariable("TXT_TITLE", $lng->txt("title"));
		$this->tpl->setVariable("TITLE", $this->get_last("title", $bmf->getTitle()));
		$this->tpl->setVariable("TXT_SAVE", $lng->txt("save"));
		$this->tpl->setVariable("TXT_CANCEL", $lng->txt("cancel"));
		$this->tpl->setVariable("TXT_FOLDER_NEW", $lng->txt("bookmark_folder_edit"));
		$this->tpl->setVariable("VAL_CMD", "updateBookmarkFolder");
		$this->ctrl->setParameter($this, "obj_id", $_GET["obj_id"]);
		$this->ctrl->setParameter($this, "bmf_id", $this->id);
		$this->tpl->setVariable("FORMACTION", $this->ctrl->getFormAction($this));

	}


	/**
	* display new bookmark form
	*/
	function newFormBookmark()
	{
		global $lng;

		$this->tpl->addBlockFile("ADM_CONTENT", "objects", "tpl.bookmark_new.html");
		$this->tpl->setVariable("TXT_BOOKMARK_NEW", $lng->txt("bookmark_new"));
		$this->tpl->setVariable("TXT_TARGET", $lng->txt("bookmark_target"));
		$this->tpl->setVariable("TXT_TITLE", $lng->txt("title"));
		$this->tpl->setVariable("TXT_DESCRIPTION", $lng->txt("description"));

		$this->tpl->setVariable("TITLE", $this->get_last("title", ""));
		$this->tpl->setVariable("TARGET", $this->get_last("target", "http://"));
		$this->tpl->setVariable("DESCRIPTION",$this->get_last("description", ""));
		$this->tpl->setVariable("TXT_SAVE", $lng->txt("save"));
		$this->tpl->setVariable("VAL_CMD", "createBookmark");
		$this->tpl->setVariable("TXT_CANCEL", $lng->txt("cancel"));

		$this->ctrl->setParameter($this, "bmf_id", $this->id);
		$this->tpl->setVariable("FORMACTION", $this->ctrl->getFormAction($this));
		$this->tpl->parseCurrentBlock();


		$this->tpl->setCurrentBlock('bkm_import');
		$this->tpl->setVariable("TXT_IMPORT_BKM", $this->lng->txt("bkm_import"));
		$this->tpl->setVariable("TXT_FILE", $this->lng->txt("file_add"));
		$this->tpl->setVariable("TXT_IMPORT", $this->lng->txt("import"));
		$this->tpl->parseCurrentBlock();
		//vd($_POST);
	}


	/**
	* get stored post var in case of an error/warning otherwise return passed value
	*/
	function get_last($a_var, $a_value)
	{
			return 	(!empty($_POST[$a_var])) ?
				ilUtil::prepareFormOutput(($_POST[$a_var]),true) :
				ilUtil::prepareFormOutput($a_value);
	}

	/**
	* display edit bookmark form
	*/
	function editFormBookmark()
	{
		global $lng;

		$this->tpl->addBlockFile("ADM_CONTENT", "objects", "tpl.bookmark_new.html");

		$this->tpl->setVariable("TXT_BOOKMARK_NEW", $lng->txt("bookmark_edit"));
		$this->tpl->setVariable("TXT_TARGET", $lng->txt("bookmark_target"));
		$this->tpl->setVariable("TXT_TITLE", $lng->txt("title"));
		$this->tpl->setVariable("TXT_DESCRIPTION", $lng->txt("description"));

		$Bookmark = new ilBookmark($_GET["obj_id"]);
		$this->tpl->setVariable("TITLE", $this->get_last("title", $Bookmark->getTitle()));
		$this->tpl->setVariable("TARGET", $this->get_last("target", $Bookmark->getTarget()));
		$this->tpl->setVariable("DESCRIPTION", $this->get_last("description", $Bookmark->getDescription()));

		$this->tpl->setVariable("TXT_SAVE", $lng->txt("save"));
		$this->tpl->setVariable("VAL_CMD", "updateBookmark");
		$this->tpl->setVariable("TXT_CANCEL", $lng->txt("cancel"));
		$this->ctrl->setParameter($this, "obj_id", $_GET["obj_id"]);
		$this->ctrl->setParameter($this, "bmf_id", $this->id);
		$this->tpl->setVariable("FORMACTION", $this->ctrl->getFormAction($this));

		$this->tpl->parseCurrentBlock();
	}


	/**
	* create new bookmark folder in db
	*/
	function createBookmarkFolder()
	{
		// check title
		if (empty($_POST["title"]))
		{
			sendInfo($this->lng->txt("please_enter_title"));
			$this->newFormBookmarkFolder();
		}
		else
		{
			// create bookmark folder
			$bmf = new ilBookmarkFolder();
			$bmf->setTitle(ilUtil::stripSlashes($_POST["title"]));
			$bmf->setParent($this->id);
			$bmf->create();

			$this->view();
		}
	}


	/**
	* update bookmark folder
	*/
	function updateBookmarkFolder()
	{
		// check title
		if (empty($_POST["title"]))
		{
			sendInfo($this->lng->txt("please_enter_title"));
			$this->editFormBookmarkFolder();
		}
		else
		{
			// update bookmark folder
			$bmf = new ilBookmarkFolder($_GET["obj_id"]);
			$bmf->setTitle(ilUtil::stripSlashes($_POST["title"]));
			$bmf->update();

			$this->view();
		}
	}


	/**
	* create new bookmark in db
	*/
	function createBookmark()
	{
		// check title and target
		if (empty($_POST["title"]))
		{
			sendInfo($this->lng->txt("please_enter_title"));
			$this->newFormBookmark();
		}
		else if (empty($_POST["target"]))
		{
			sendInfo($this->lng->txt("please_enter_target"));
			$this->newFormBookmark();
		}
		else
		{
			// create bookmark
			$bm = new ilBookmark();
			$bm->setTitle(ilUtil::stripSlashes($_POST["title"]));
			$bm->setDescription(ilUtil::stripSlashes($_POST["description"]));
			$bm->setTarget(ilUtil::stripSlashes($_POST["target"]));
			$bm->setParent($this->id);
			$bm->create();

			$this->view();
		}
	}

	/**
	* update bookmark in db
	*/
	function updateBookmark()
	{
		// check title and target
		if (empty($_POST["title"]))
		{
			sendInfo($this->lng->txt("please_enter_title"));
			$this->editFormBookmark();
		}
		else if (empty($_POST["target"]))
		{
			sendInfo($this->lng->txt("please_enter_target"));
			$this->editFormBookmark();
		}
		else
		{
			// update bookmark
			$bm = new ilBookmark($_GET["obj_id"]);
			$bm->setTitle(ilUtil::stripSlashes($_POST["title"]));
			$bm->setTarget(ilUtil::stripSlashes($_POST["target"]));
			$bm->setDescription(ilUtil::stripSlashes($_POST["description"]));
			$bm->update();

			$this->view();
		}
	}

	/**
	* export bookmarks
	*/
	function export()
	{
		if (!isset($_POST["id"]))
		{
			$this->ilias->raiseError($this->lng->txt("no_checkbox"),$this->ilias->error_obj->MESSAGE);
		}
		$export_ids=array();
		foreach($_POST["id"] as $id)
		{
			list($type, $obj_id) = explode(":", $id);
			$export_ids[]=$obj_id;
		}
		require_once ("classes/class.ilBookmarkImportExport.php");
		$html_content=ilBookmarkImportExport::_exportBookmark ($export_ids,true,
			$this->lng->txt("bookmarks_of")." ".$this->ilias->account->getFullname());
		ilUtil::deliverData($html_content, 'bookmarks.html', "application/save", $charset = "");
		//echo '<pre>fff'.htmlspecialchars($html_content).'</pre>';

	}

	/**
	* display deletion conformation screen
	*/
	function delete()
	{
		if (!isset($_POST["id"]))
		{
			$this->ilias->raiseError($this->lng->txt("no_checkbox"),$this->ilias->error_obj->MESSAGE);
		}

		$this->tpl->addBlockFile("ADM_CONTENT", "objects", "tpl.obj_confirm.html");

		sendInfo($this->lng->txt("info_delete_sure"));
		$this->ctrl->setParameter($this, "bmf_id", $this->id);
		$this->tpl->setVariable("FORMACTION",
			$this->ctrl->getFormAction($this));

		// output table header
		$cols = array("type", "title");
		foreach ($cols as $key)
		{
			$this->tpl->setCurrentBlock("table_header");
			$this->tpl->setVariable("TEXT",$this->lng->txt($key));
			$this->tpl->parseCurrentBlock();
		}

		$_SESSION["saved_post"] = $_POST["id"];

		foreach($_POST["id"] as $id)
		{
			list($type, $obj_id) = explode(":", $id);
			switch($type)
			{
				case "bmf":
					$BookmarkFolder = new ilBookmarkFolder($obj_id);
					$title = $BookmarkFolder->getTitle();
					$target = "";
					unset($BookmarkFolder);
					break;

				case "bm":
					$Bookmark = new ilBookmark($obj_id);
					$title = $Bookmark->getTitle();
					$target = $Bookmark->getTarget();
					unset($Bookmark);
					break;
			}

			// output type icon
			$this->tpl->setCurrentBlock("table_cell");
			$this->tpl->setVariable("TEXT_CONTENT",ilUtil::getImageTagByType($type, $this->tpl->tplPath));
			$this->tpl->parseCurrentBlock();

			// output title
			$this->tpl->setCurrentBlock("table_cell");
			$this->tpl->setVariable("TEXT_CONTENT",ilUtil::prepareFormOutput($title));

			// output target
			if ($target)
			{
				$this->tpl->setVariable("DESC",ilUtil::prepareFormOutput(ilUtil::shortenText(
					$target,$this->textwidth, true)));
			}
			$this->tpl->parseCurrentBlock();

			// output table row
			$this->tpl->setCurrentBlock("table_row");
			$this->tpl->setVariable("CSS_ROW",ilUtil::switchColor(++$counter,"tblrow1","tblrow2"));
			$this->tpl->parseCurrentBlock();
		}

		// cancel and confirm button
		$buttons = array( "cancel"  => $this->lng->txt("cancel"),
			"confirm"  => $this->lng->txt("confirm"));

		$this->tpl->setVariable("IMG_ARROW", ilUtil::getImagePath("arrow_downright.gif"));
		foreach($buttons as $name => $value)
		{
			$this->tpl->setCurrentBlock("operation_btn");
			$this->tpl->setVariable("BTN_NAME",$name);
			$this->tpl->setVariable("BTN_VALUE",$value);
			$this->tpl->parseCurrentBlock();
		}
	}

	/**
	* cancel deletion,insert, update
	*/
	function cancel()
	{
		session_unregister("saved_post");
		$this->view();
	}

	/**
	* deletion confirmed -> delete folders / bookmarks
	*/
	function confirm()
	{
		global $tree, $rbacsystem, $rbacadmin, $objDefinition;

		// AT LEAST ONE OBJECT HAS TO BE CHOSEN.
		if (!isset($_SESSION["saved_post"]))
		{
			$this->ilias->raiseError($this->lng->txt("no_checkbox"),$this->ilias->error_obj->MESSAGE);
		}

		// FOR ALL SELECTED OBJECTS
		foreach ($_SESSION["saved_post"] as $id)
		{
			list($type, $id) = explode(":", $id);

			// get node data and subtree nodes
			$node_data = $this->tree->getNodeData($id);
			$subtree_nodes = $this->tree->getSubTree($node_data);

			// delete tree
			$this->tree->deleteTree($node_data);

			// delete objects of subtree nodes
			foreach ($subtree_nodes as $node)
			{
				switch ($node["type"])
				{
					case "bmf":
						$BookmarkFolder = new ilBookmarkFolder($node["obj_id"]);
						$BookmarkFolder->delete();
						break;

					case "bm":
						$Bookmark = new ilBookmark($node["obj_id"]);
						$Bookmark->delete();
						break;
				}
			}
		}

		// Feedback
		sendInfo($this->lng->txt("info_deleted"),true);

		$this->view();
	}



	/**
	* display copy, paste, ... actions
	*/
	function showActions()
	{
		global $objDefinition;

		$notoperations = array();
		// NO PASTE AND CLEAR IF CLIPBOARD IS EMPTY
		if (empty($_SESSION["clipboard"]))
		{
			$notoperations[] = "paste";
			$notoperations[] = "clear";
		}
		// CUT COPY PASTE LINK DELETE IS NOT POSSIBLE IF CLIPBOARD IS FILLED
		if ($_SESSION["clipboard"])
		{
			$notoperations[] = "cut";
			$notoperations[] = "copy";
			$notoperations[] = "link";
		}

		$operations = array();

		$d = $objDefinition->getActions("bmf");

		foreach ($d as $row)
		{
			if (!in_array($row["name"], $notoperations))
			{
				$operations[] = $row;
			}
		}

		if (count($operations)>0)
		{
			foreach ($operations as $val)
			{
				$this->tpl->setCurrentBlock("operation_btn");
				$this->tpl->setVariable("BTN_NAME", $val["lng"]);
				$this->tpl->setVariable("BTN_VALUE", $this->lng->txt($val["lng"]));
				$this->tpl->parseCurrentBlock();
			}

			$this->tpl->setCurrentBlock("operation");
			$this->tpl->parseCurrentBlock();
		}
	}

	/**
	* display subobject addition selection
	*/
	function showPossibleSubObjects()
	{
		global $objDefinition;
		$d = $objDefinition->getCreatableSubObjects("bmf");
		$actions = array(
				"delete"=>$this->lng->txt("delete"),
				"export"=>$this->lng->txt("export"),
		);

		if (count($d) > 0)
		{
			foreach ($d as $row)
			{
				$count = 0;
				if ($row["max"] > 0)
				{
					//how many elements are present?
					for ($i=0; $i<count($this->data["ctrl"]); $i++)
					{
						if ($this->data["ctrl"][$i]["type"] == $row["name"])
						{
							$count++;
						}
					}
				}
				if ($row["max"] == "" || $count < $row["max"])
				{
					$subobj[] = $row["name"];
				}
			}
		}

		if (is_array($subobj))
		{
			//build form
			$opts = ilUtil::formSelect("","type",$subobj);

			$this->tpl->setCurrentBlock("add_object");
			$this->tpl->setVariable("COLUMN_COUNTS", 7);
			$this->tpl->setVariable("SELECT_OBJTYPE", $opts);
			$this->tpl->setVariable("BTN_NAME", "newForm");
			$this->tpl->setVariable("TXT_ADD", $this->lng->txt("add"));
			$this->tpl->parseCurrentBlock();
		}

		$this->tpl->setVariable("TPLPATH",$this->tpl->tplPath);

		$this->tpl->setCurrentBlock("tbl_action_select");
		$this->tpl->setVariable("SELECT_ACTION",ilUtil::formSelect($_SESSION["error_post_vars"]['action'],"action",$actions,false,true));
		$this->tpl->setVariable("BTN_NAME","executeAction");
		$this->tpl->setVariable("BTN_VALUE",$this->lng->txt("execute"));

		/*
		$this->tpl->setVariable("BTN_NAME","delete");
		$this->tpl->setVariable("BTN_VALUE",$this->lng->txt("delete"));
		$this->tpl->parseCurrentBlock();

		$this->tpl->setVariable("BTN_NAME","export");
		$this->tpl->setVariable("BTN_VALUE",$this->lng->txt("export"));
		$this->tpl->parseCurrentBlock();
		*/
		$this->tpl->parseCurrentBlock();

	}

	/**
	* get bookmark list for personal desktop
	*/
	function getPDBookmarkListHTML()
	{
		if ($this->ilias->account->getPref("il_pd_bkm_mode") == 'tree')
		{
			return $this->getPDBookmarkListHTMLTree();
		}
		else
		{
			return $this->getPDBookmarkListHTMLFlat();
		}
	}

	/**
	* get tree bookmark list for personal desktop
	*/
	function getPDBookmarkListHTMLTree()
	{
		$showdetails = $this->ilias->account->getPref('show_bookmark_details') == 'y';
		$tpl = new ilTemplate("tpl.bookmark_pd_tree.html", true, true);
		//$this->ctrl->setParameter($this, "bmf_id", NULL);
		$exp = new ilBookmarkExplorer($this->ctrl->getParentReturn($this),$_SESSION["AccountId"]);
		//$exp = new ilBookmarkExplorer($this->ctrl->getParentReturn($this),$_SESSION["AccountId"]);
		//$exp->setClickable('bmf', '');
		//$exp->setClickable('dum', '');
		$exp->setAllowedTypes(array('dum','bmf','bm'));
		$exp->setTargetGet("bmf_id");
		$exp->setSessionExpandVariable('mexpand');
		$this->ctrl->setParameter($this, "bmf_id", $this->id);
		$exp->setExpandTarget($this->ctrl->getParentReturn($this));
		if ($_GET["mexpand"] == "")
		{
			$expanded = $this->id;
		}
		else
		{
			$expanded = $_GET["mexpand"];
		}
		$exp->setExpand($expanded);
		$exp->setShowDetails($showdetails);

		// build html-output
		$exp->setOutput(0);
		$output = $exp->getOutput();
		$tpl->setCurrentBlock();
		if (empty($output))
		{
			$tpl->setCurrentBlock("tbl_no_bm");
			$tpl->setVariable("ROWCOL","tblrow".(($i % 2)+1));
			$tpl->setVariable("TXT_NO_BM", $this->lng->txt("no_bm_in_personal_list"));
			$tpl->parseCurrentBlock();
		}



		$tpl->setVariable("EXPLORER",$output);
		$tpl->setVariable("TXT_BM_HEADER", $this->lng->txt("my_bms"));

		// add details link
		if ($showdetails)
		{
			$text = $this->lng->txt("hide_details");
			$cmd = "hideBookmarkDetails";
		}
		else
		{
			$text = $this->lng->txt("show_details");
			$cmd = "showBookmarkDetails";
		}

		$tpl->setVariable("LINK_BKM_DETAILS",
			$this->ctrl->getLinkTarget($this,$cmd));
		$tpl->setVariable("TXT_BKM_DETAILS", $text);

		$tpl->setVariable("CLASS_BKM_TREE","");
		$tpl->setVariable("LINK_BKM_MODE",
				$this->ctrl->getLinkTarget($this,'setPdFlatMode'));
		$tpl->setVariable("IMG_BKM_TREE",ilUtil::getImagePath("ic_flatview.gif"));

		$tpl->parseCurrentBlock();
		return $tpl->get();
	}
	/**
	* get flat bookmark list for personal desktop
	*/
	function getPDBookmarkListHTMLFlat()
	{
		$showdetails = $this->ilias->account->getPref('show_bookmark_details') == 'y';
		$tpl = new ilTemplate("tpl.bookmark_pd_list.html", true, true);
		include_once("classes/class.ilBookmarkFolder.php");

		$bm_items = ilBookmarkFolder::getObjects($_SESSION["ilCurBMFolder"]);

		if(ilBookmarkFolder::isRootFolder($_SESSION['ilCurBMFolder']) or !$_SESSION['ilCurBMFolder'])
		{
			$colspan = 2;
		}

		$i = 0;
		$title_append = "";
		if (!ilBookmarkFolder::isRootFolder($_SESSION["ilCurBMFolder"])
			&& !empty($_SESSION["ilCurBMFolder"]))
		{
			$i++;
			$tpl->setCurrentBlock("tbl_bm_row");
			$rowcol = ($rowcol == "tblrow1")
				? "tblrow2"
				: "tblrow1";
			$tpl->setVariable("ROWCOL", $rowcol);
			$tpl->setVariable("IMG_BM", ilUtil::getImagePath("icon_cat.gif"));
			$tpl->setVariable("BM_TITLE", "..");
			$this->ctrl->setParameter($this, "curBMFolder",
				ilBookmarkFolder::_getParentId($_SESSION["ilCurBMFolder"]));
			$tpl->setVariable("BM_LINK",
				$this->ctrl->getLinkTarget($this, "setCurrentBookmarkFolder"));

			$tpl->setVariable("BM_DESCRIPTION", "");
			$tpl->setVariable("BM_TARGET", "");
			$tpl->parseCurrentBlock();

			$title_append = ": ".ilBookmarkFolder::_lookupTitle($_SESSION["ilCurBMFolder"]);
		}

		foreach ($bm_items as $bm_item)
		{
			$i++;

			$tpl->setCurrentBlock("tbl_bm_row");
			$rowcol = ($rowcol == "tblrow1")
				? "tblrow2"
				: "tblrow1";
			$tpl->setVariable("ROWCOL", $rowcol);

			switch ($bm_item["type"])
			{
				case "bmf":
					$tpl->setVariable("IMG_BM", ilUtil::getImagePath("icon_cat.gif"));
					$tpl->setVariable("BM_TITLE", ilUtil::prepareFormOutput($bm_item["title"]));
					$this->ctrl->setParameter($this, "curBMFolder", $bm_item["obj_id"]);
					$tpl->setVariable("BM_LINK",
					$this->ctrl->getLinkTarget($this, "setCurrentBookmarkFolder"));
					$tpl->setVariable("BM_TARGET", "");
					break;

				case "bm":
					$tpl->setVariable("IMG_BM", ilUtil::getImagePath("icon_bm.gif"));
					$tpl->setVariable("BM_TITLE", ilUtil::prepareFormOutput($bm_item["title"]));
					$tpl->setVariable("BM_LINK", ilUtil::prepareFormOutput($bm_item["target"]));
					$tpl->setVariable("BM_TARGET", "_blank");
					break;
			}
			if (!empty($bm_item["desc"]))
			{
				if ($showdetails)
				{
					$tpl->setVariable("BM_DESCRIPTION", ilUtil::prepareFormOutput($bm_item["desc"]));
				}
				else
				{
					$tpl->setVariable("BM_TOOLTIP", ilUtil::prepareFormOutput($bm_item["desc"]));
				}
			}

			$tpl->parseCurrentBlock();
		}

		if ($i == 0)
		{
			$tpl->setCurrentBlock("tbl_no_bm");
			$tpl->setVariable("ROWCOL","tblrow".(($i % 2)+1));
			$tpl->setVariable("TXT_NO_BM", $this->lng->txt("no_bm_in_personal_list"));
			$tpl->parseCurrentBlock();
		}

		$tpl->setCurrentBlock();
		$tpl->setVariable("TXT_BM_HEADER", $this->lng->txt("my_bms").$title_append);
		// add details link
		if ($showdetails)
		{
			$text = $this->lng->txt("hide_details");
			$cmd = "hideBookmarkDetails";
		}
		else
		{
			$text = $this->lng->txt("show_details");
			$cmd = "showBookmarkDetails";
		}
		$tpl->setVariable("LINK_BKM_DETAILS",
			$this->ctrl->getLinkTarget($this,$cmd));
		$tpl->setVariable("TXT_BKM_DETAILS", $text);

		$tpl->setVariable("CLASS_BKM_TREE","");
		$tpl->setVariable("LINK_BKM_MODE",
				$this->ctrl->getLinkTarget($this,'setPdTreeMode'));
		$tpl->setVariable("IMG_BKM_TREE",ilUtil::getImagePath("ic_treeview.gif"));
		$tpl->parseCurrentBlock();

		return $tpl->get();
	}

	/**
	* set current bookmarkfolder on personal desktop
	*/
	function setCurrentBookmarkFolder()
	{
		$_SESSION["ilCurBMFolder"] = $_GET["curBMFolder"];
		$this->ctrl->returnToParent($this);
	}
	/**
	* imports a bookmark file into database
	* display status information or report errors messages
	* in case of error
	*
	* @access	public
	*/
	function importFile()
	{
		if ($_FILES["bkmfile"]["error"] > UPLOAD_ERR_OK)
		{
			sendInfo($this->lng->txt("import_file_not_valid"));
			$this->newFormBookmark();
			return;
		}
		require_once ("classes/class.ilBookmarkImportExport.php");
		$objects=ilBookmarkImportExport::_parseFile ($_FILES["bkmfile"]['tmp_name']);
		if ($objects===false)
		{
			sendInfo($this->lng->txt("import_file_not_valid"));
			$this->newFormBookmark();
			return;
		}
		// holds the number of created objects
		$num_create=array('bm'=>0,'bmf'=>0);
		$this->__importBookmarks($objects,$num_create,$this->id,0);

		sendInfo(sprintf($this->lng->txt("bkm_import_ok"),$num_create['bm'],
			$num_create[ 'bmf']));
		$this->view();


	}
	/**
	* creates the bookmarks and folders
	* @param	array		array of objects
	* @param	array		stores the number of created objects
	* @param	folder_id		id where to store the bookmarks
	* @param	start_key		key of the objects array where to start
	* @access	private
	*/
	function __importBookmarks(&$objects,&$num_create,$folder_id,$start_key=0)
	{
		if (is_array($objects[$start_key]))
		{
			foreach ($objects[$start_key] as $obj_key=>$object)
			{
				switch ($object['type'])
				{
					case 'bm':
						if (!$object["title"]) continue;
						if (!$object["target"]) continue;
						$bm = new ilBookmark();
						$bm->setTitle($object["title"]);
						$bm->setDescription($object["description"]);
						$bm->setTarget($object["target"]);
						$bm->setParent($folder_id);
						$bm->create();
						$num_create['bm']++;
					break;
					case 'bmf':
						if (!$object["title"]) continue;
						$bmf = new ilBookmarkFolder();
						$bmf->setTitle($object["title"]);
						$bmf->setParent($folder_id);
						$bmf->create();
						$num_create['bmf']++;
						if (is_array($objects[$obj_key]))
						{
							$this->__importBookmarks($objects,$num_create,
								$bmf->getId(),$obj_key);
						}
					break;
				}
			}
		}
	}
	/**
	* show details for Bookmark
	*/
	function showBookmarkDetails()
	{
		$this->ilias->account->writePref('show_bookmark_details','y');
		$this->ctrl->returnToParent($this);
	}

	/**
	* hide details for Bookmark
	*/
	function hideBookmarkDetails()
	{
		$this->ilias->account->writePref('show_bookmark_details','n');
		$this->ctrl->returnToParent($this);
	}
	/**
	* set current desktop view mode to flat
	*/
	function setPdFlatMode()
	{
		$this->ilias->account->writePref("il_pd_bkm_mode", 'flat');
		$this->ctrl->returnToParent($this);
	}
	/**
	* set current desktop view mode to tree
	*/
	function setPdTreeMode()
	{
		$this->ilias->account->writePref("il_pd_bkm_mode", 'tree');
		$this->ctrl->returnToParent($this);
	}
}
?>
