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
* Class ilObjTestGUI
*
* @author		Helmut Schottmüller <hschottm@tzi.de>
* @version	$Id$
*
* @ilCtrl_Calls ilObjTestGUI: ilObjCourseGUI, ilMDEditorGUI, ilTestOutputGUI
* @ilCtrl_Calls ilObjTestGUI: ilTestEvaluationGUI
*
* @extends ilObjectGUI
* @package ilias-core
* @package assessment
*/

include_once "./assessment/classes/class.ilObjQuestionPool.php";
include_once "./classes/class.ilObjectGUI.php";
include_once "./assessment/classes/class.assQuestionGUI.php";
include_once './classes/Spreadsheet/Excel/Writer.php';
require_once "./classes/class.ilSearch.php";
require_once "./classes/class.ilObjUser.php";
require_once "./classes/class.ilObjGroup.php";

define ("TYPE_XLS_PC", "latin1");
define ("TYPE_XLS_MAC", "macos");
define ("TYPE_SPSS", "csv");

class ilObjTestGUI extends ilObjectGUI
{
	/**
	* Constructor
	* @access public
	*/
	function ilObjTestGUI($a_data,$a_id,$a_call_by_reference = true, $a_prepare_output = true)
	{
		global $lng, $ilCtrl;
		$lng->loadLanguageModule("assessment");
		$this->type = "tst";
		$this->ilObjectGUI($a_data,$a_id,$a_call_by_reference, false);
		if (!defined("ILIAS_MODULE"))
		{
			$this->setTabTargetScript("adm_object.php");
		}
		else
		{
			$this->setTabTargetScript("test.php");
		}
		$this->ctrl =& $ilCtrl;
		$this->ctrl->saveParameter($this, "ref_id");
		if ($a_prepare_output) 
		{
			$this->prepareOutput();
		}


		// Added parameter if called from crs_objectives
		if((int) $_GET['crs_show_result'])
		{
			$this->ctrl->saveParameter($this,'crs_show_result',(int) $_GET['crs_show_result']);
		}
	}
	
	/**
	* execute command
	*/
	function &executeCommand()
	{
		$cmd = $this->ctrl->getCmd("properties");
		$next_class = $this->ctrl->getNextClass($this);
		$this->ctrl->setReturn($this, "properties");

		#echo "<br>nextclass:$next_class:cmd:$cmd:qtype=$q_type";
		switch($next_class)
		{
			case 'ilmdeditorgui':
				$this->setAdminTabs();
				include_once 'Services/MetaData/classes/class.ilMDEditorGUI.php';

				$md_gui =& new ilMDEditorGUI($this->object->getId(), 0, $this->object->getType());
				$md_gui->addObserver($this->object,'MDUpdateListener','General');

				$this->ctrl->forwardCommand($md_gui);
				break;
			case "iltestoutputgui":
				include_once "./assessment/classes/class.ilTestOutputGUI.php";

				$output_gui =& new ilTestOutputGUI($this->object);
				$this->ctrl->forwardCommand($output_gui);
				break;
			case "iltestevaluationgui":
				include_once "./assessment/classes/class.ilTestEvaluationGUI.php";

				$evaluation_gui =& new ilTestEvaluationGUI($this->object);
				$this->ctrl->forwardCommand($evaluation_gui);
				break;
			default:
				switch ($cmd)
				{
					case "eval_a":
					case "run":
					case "passDetails":
					case "eval_stat":
					case "evalStatSelected":
					case "searchForEvaluation":
					case "addFoundGroupsToEval":
					case "removeSelectedGroup":
					case "removeSelectedUser":
					case "addFoundUsersToEval":
					case "evalSelectedUsers":
					case "evalAllUsers":
					case "printAnswers":
						break;
					default:
						$this->setAdminTabs();
				}
				if ((strcmp($cmd, "properties") == 0) && ($_GET["browse"]))
				{
					$this->questionBrowser();
					return;
				}
				if ((strcmp($cmd, "properties") == 0) && ($_GET["up"] || $_GET["down"]))
				{
					$this->questionsObject();
					return;
				}
				$cmd.= "Object";
				$ret =& $this->$cmd();
				break;
		}
	}

	function runObject()
	{
		include_once "./assessment/classes/class.ilTestOutputGUI.php";

		$output_gui =& new ilTestOutputGUI($this->object);
		$this->ctrl->setCmd("outIntroductionPage");
		$this->ctrl->forwardCommand($output_gui);
	}
	
	function eval_aObject()
	{
		include_once "./assessment/classes/class.ilTestEvaluationGUI.php";

		$evaluation_gui =& new ilTestEvaluationGUI($this->object);
		$this->ctrl->setCmd("eval_a");
		$this->ctrl->forwardCommand($evaluation_gui);
	}

	function eval_statObject()
	{
		include_once "./assessment/classes/class.ilTestEvaluationGUI.php";

		$evaluation_gui =& new ilTestEvaluationGUI($this->object);
		$this->ctrl->setCmd("eval_stat");
		$this->ctrl->forwardCommand($evaluation_gui);
	}

	/**
	* Returns the calling script of the GUI class
	*
	* @access	public
	*/
	function getCallingScript()
	{
		return "test.php";
	}

	/**
	* form for new test object import
	*/
	function importFileObject()
	{
		if ($_POST["qpl"] < 1)
		{
			sendInfo($this->lng->txt("tst_select_questionpools"));
			$this->createObject();
			return;
		}
		if (strcmp($_FILES["xmldoc"]["tmp_name"], "") == 0)
		{
			sendInfo($this->lng->txt("tst_select_file_for_import"));
			$this->createObject();
			return;
		}
		$this->uploadTstObject();
	}
	
	/**
	* form for new test object duplication
	*/
	function cloneAllObject()
	{
		if ($_POST["tst"] < 1)
		{
			sendInfo($this->lng->txt("tst_select_tsts"));
			$this->createObject();
			return;
		}
		include_once "./assessment/classes/class.ilObjTest.php";
		ilObjTest::_clone($_POST["tst"]);
		ilUtil::redirect($this->getReturnLocation("save", "adm_object.php?ref_id=" . $_GET["ref_id"]));
	}
	
	/**
	* save object
	* @access	public
	*/
	function saveObject()
	{
		global $rbacadmin;

		// create and insert forum in objecttree
		$newObj = parent::saveObject();

		// setup rolefolder & default local roles
		//$roles = $newObj->initDefaultRoles();

		// ...finally assign role to creator of object
		//$rbacadmin->assignUser($roles[0], $newObj->getOwner(), "y");

		// put here object specific stuff

		// always send a message
		sendInfo($this->lng->txt("object_added"),true);

		$returnlocation = "test.php";
		if (!defined("ILIAS_MODULE"))
		{
			$returnlocation = "adm_object.php";
		}
		ilUtil::redirect($this->getReturnLocation("save","$returnlocation?".$this->link_params));
		exit();
	}

	function getAddParameter()
	{
		return "?ref_id=" . $_GET["ref_id"] . "&cmd=" . $_GET["cmd"] . '&crs_show_result='. (int) $_GET['crs_show_result'];
	}

	/*
	* list all export files
	*/
	function exportObject()
	{
		global $tree;
		global $rbacsystem;

		if ((!$rbacsystem->checkAccess("read", $this->ref_id)) && (!$rbacsystem->checkAccess("write", $this->ref_id))) 
		{
			// allow only read and write access
			sendInfo($this->lng->txt("cannot_edit_test"), true);
			$path = $this->tree->getPathFull($this->object->getRefID());
			ilUtil::redirect($this->getReturnLocation("cancel","../repository.php?ref_id=" . $path[count($path) - 2]["child"]));
			return;
		}

		//$this->setTabs();

		//add template for view button
		$this->tpl->addBlockfile("BUTTONS", "buttons", "tpl.buttons.html");

		// create export file button
		$this->tpl->setCurrentBlock("btn_cell");
		$this->tpl->setVariable("BTN_LINK", "test.php?ref_id=".$_GET["ref_id"]."&cmd=createExportFile&mode=xml");
		$this->tpl->setVariable("BTN_TXT", $this->lng->txt("ass_create_export_file"));
		$this->tpl->parseCurrentBlock();
		
		// create export file button
		if ($this->object->isOnlineTest()) {
			$this->tpl->setCurrentBlock("btn_cell");
			$this->tpl->setVariable("BTN_LINK", "test.php?ref_id=".$_GET["ref_id"]."&cmd=createExportFile&mode=results");
			$this->tpl->setVariable("BTN_TXT", $this->lng->txt("ass_create_export_test_results"));
			$this->tpl->parseCurrentBlock();
		}
		

		// view last export log button
		/*
		if (is_file($this->object->getExportDirectory()."/export.log"))
		{
			$this->tpl->setCurrentBlock("btn_cell");
			$this->tpl->setVariable("BTN_LINK", $this->ctrl->getLinkTarget($this, "viewExportLog"));
			$this->tpl->setVariable("BTN_TXT", $this->lng->txt("cont_view_last_export_log"));
			$this->tpl->parseCurrentBlock();
		}*/

		$export_dir = $this->object->getExportDirectory();

		$export_files = $this->object->getExportFiles($export_dir);

		// create table
		include_once("classes/class.ilTableGUI.php");
		$tbl = new ilTableGUI();

		// load files templates
		$this->tpl->addBlockfile("ADM_CONTENT", "adm_content", "tpl.table.html");

		// load template for table content data
		$this->tpl->addBlockfile("TBL_CONTENT", "tbl_content", "tpl.export_file_row.html", true);

		$num = 0;

		$this->tpl->setVariable("FORMACTION", $this->ctrl->getFormAction($this));

		$tbl->setTitle($this->lng->txt("ass_export_files"));

		$tbl->setHeaderNames(array("", $this->lng->txt("ass_file"),
			$this->lng->txt("ass_size"), $this->lng->txt("date") ));

		$tbl->enabled["sort"] = false;
		$tbl->setColumnWidth(array("1%", "49%", "25%", "25%"));

		// control
		$tbl->setOrderColumn($_GET["sort_by"]);
		$tbl->setOrderDirection($_GET["sort_order"]);
		$tbl->setLimit($_GET["limit"]);
		$tbl->setOffset($_GET["offset"]);
		$tbl->setMaxCount($this->maxcount);		// ???


		$this->tpl->setVariable("COLUMN_COUNTS", 4);

		// footer
		$tbl->setFooter("tblfooter",$this->lng->txt("previous"),$this->lng->txt("next"));
		//$tbl->disable("footer");

		$tbl->setMaxCount(count($export_files));
		$export_files = array_slice($export_files, $_GET["offset"], $_GET["limit"]);

		$tbl->render();
		if(count($export_files) > 0)
		{
			$i=0;
			foreach($export_files as $exp_file)
			{
				$this->tpl->setCurrentBlock("tbl_content");
				$this->tpl->setVariable("TXT_FILENAME", $exp_file);

				$css_row = ilUtil::switchColor($i++, "tblrow1", "tblrow2");
				$this->tpl->setVariable("CSS_ROW", $css_row);

				$this->tpl->setVariable("TXT_SIZE", filesize($export_dir."/".$exp_file));
				$this->tpl->setVariable("CHECKBOX_ID", $exp_file);

				$file_arr = explode("__", $exp_file);
				$this->tpl->setVariable("TXT_DATE", date("Y-m-d H:i:s",$file_arr[0]));

				$this->tpl->parseCurrentBlock();
			}
			$this->tpl->setCurrentBlock("selectall");
			$this->tpl->setVariable("SELECT_ALL", $this->lng->txt("select_all"));
			$this->tpl->setVariable("CSS_ROW", $css_row);
			$this->tpl->parseCurrentBlock();
			// delete button
			$this->tpl->setVariable("IMG_ARROW", ilUtil::getImagePath("arrow_downright.gif"));
			$this->tpl->setCurrentBlock("tbl_action_btn");
			$this->tpl->setVariable("BTN_NAME", "confirmDeleteExportFile");
			$this->tpl->setVariable("BTN_VALUE", $this->lng->txt("delete"));
			$this->tpl->parseCurrentBlock();
	
			$this->tpl->setCurrentBlock("tbl_action_btn");
			$this->tpl->setVariable("BTN_NAME", "downloadExportFile");
			$this->tpl->setVariable("BTN_VALUE", $this->lng->txt("download"));
			$this->tpl->parseCurrentBlock();	
		} //if is_array
		else
		{
			$this->tpl->setCurrentBlock("notfound");
			$this->tpl->setVariable("TXT_OBJECT_NOT_FOUND", $this->lng->txt("obj_not_found"));
			$this->tpl->setVariable("NUM_COLS", 3);
			$this->tpl->parseCurrentBlock();
		}

		$this->tpl->parseCurrentBlock();
	}

	
	/**
	* create export file
	*/
	function createExportFileObject()
	{
		global $rbacsystem;
		
		if ($rbacsystem->checkAccess("write", $this->ref_id))
		{
			include_once("assessment/classes/class.ilTestExport.php");
			$test_exp = new ilTestExport($this->object, $_GET["mode"]);
			$test_exp->buildExportFile();
		}
		else
		{
			sendInfo("cannot_export_test");
		}
		$this->exportObject();
	}
	
	
	/**
	* download export file
	*/
	function downloadExportFileObject()
	{
		if(!isset($_POST["file"]))
		{
			sendInfo($this->lng->txt("no_checkbox"), true);
			$this->ctrl->redirect($this, "export");
		}

		if (count($_POST["file"]) > 1)
		{
			sendInfo($this->lng->txt("select_max_one_item"), true);
			$this->ctrl->redirect($this, "export");
		}


		$export_dir = $this->object->getExportDirectory();
		ilUtil::deliverFile($export_dir."/".$_POST["file"][0],
			$_POST["file"][0]);
	}

	/**
	* confirmation screen for export file deletion
	*/
	function confirmDeleteExportFileObject()
	{
		if(!isset($_POST["file"]))
		{
			$this->ilias->raiseError($this->lng->txt("no_checkbox"),$this->ilias->error_obj->MESSAGE);
		}

		//$this->setTabs();

		// SAVE POST VALUES
		$_SESSION["ilExportFiles"] = $_POST["file"];

		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.confirm_deletion.html", true);

		sendInfo($this->lng->txt("info_delete_sure"));

		$this->tpl->setVariable("FORMACTION", $this->ctrl->getFormAction($this));

		// BEGIN TABLE HEADER
		$this->tpl->setCurrentBlock("table_header");
		$this->tpl->setVariable("TEXT",$this->lng->txt("objects"));
		$this->tpl->parseCurrentBlock();

		// BEGIN TABLE DATA
		$counter = 0;
		foreach($_POST["file"] as $file)
		{
				$this->tpl->setCurrentBlock("table_row");
				$this->tpl->setVariable("CSS_ROW",ilUtil::switchColor(++$counter,"tblrow1","tblrow2"));
				$this->tpl->setVariable("TEXT_CONTENT", $file);
				$this->tpl->parseCurrentBlock();
		}

		// cancel/confirm button
		$this->tpl->setVariable("IMG_ARROW",ilUtil::getImagePath("arrow_downright.gif"));
		$buttons = array( "cancelDeleteExportFile"  => $this->lng->txt("cancel"),
			"deleteExportFile"  => $this->lng->txt("confirm"));
		foreach ($buttons as $name => $value)
		{
			$this->tpl->setCurrentBlock("operation_btn");
			$this->tpl->setVariable("BTN_NAME",$name);
			$this->tpl->setVariable("BTN_VALUE",$value);
			$this->tpl->parseCurrentBlock();
		}
	}


	/**
	* cancel deletion of export files
	*/
	function cancelDeleteExportFileObject()
	{
		session_unregister("ilExportFiles");
		ilUtil::redirect("test.php?cmd=export&ref_id=".$_GET["ref_id"]);
	}


	/**
	* delete export files
	*/
	function deleteExportFileObject()
	{
		$export_dir = $this->object->getExportDirectory();
		foreach($_SESSION["ilExportFiles"] as $file)
		{
			$exp_file = $export_dir."/".$file;
			$exp_dir = $export_dir."/".substr($file, 0, strlen($file) - 4);
			if (@is_file($exp_file))
			{
				unlink($exp_file);
			}
			if (@is_dir($exp_dir))
			{
				ilUtil::delDir($exp_dir);
			}
		}
		ilUtil::redirect("test.php?cmd=export&ref_id=".$_GET["ref_id"]);
	}

	/**
	* display dialogue for importing tests
	*
	* @access	public
	*/
	function importObject()
	{
		$this->getTemplateFile("import", "tst");
		$this->tpl->setCurrentBlock("option_qpl");
		include_once("./assessment/classes/class.ilObjTest.php");
		$tst = new ilObjTest();
		$questionpools =& $tst->getAvailableQuestionpools(true);
		if (count($questionpools) == 0)
		{
		}
		else
		{
			foreach ($questionpools as $key => $value)
			{
				$this->tpl->setCurrentBlock("option_qpl");
				$this->tpl->setVariable("OPTION_VALUE", $key);
				$this->tpl->setVariable("TXT_OPTION", $value);
				$this->tpl->parseCurrentBlock();
			}
		}
		$this->tpl->setVariable("TXT_SELECT_QUESTIONPOOL", $this->lng->txt("select_questionpool"));
		$this->tpl->setVariable("OPTION_SELECT_QUESTIONPOOL", $this->lng->txt("select_questionpool_option"));
		$this->tpl->setVariable("FORMACTION", "adm_object.php?&ref_id=".$_GET["ref_id"]."&cmd=gateway&new_type=".$this->type);
		$this->tpl->setVariable("BTN_NAME", "uploadTst");
		$this->tpl->setVariable("TXT_UPLOAD", $this->lng->txt("upload"));
		$this->tpl->setVariable("NEW_TYPE", $this->type);
		$this->tpl->setVariable("TXT_IMPORT_TST", $this->lng->txt("import_tst"));
		$this->tpl->setVariable("TXT_SELECT_MODE", $this->lng->txt("select_mode"));
		$this->tpl->setVariable("TXT_SELECT_FILE", $this->lng->txt("select_file"));

	}

	/**
	* imports test and question(s)
	*/
	function uploadTstObject()
	{
		if ($_POST["qpl"] < 1)
		{
			sendInfo($this->lng->txt("tst_select_questionpools"));
			$this->importObject();
			return;
		}

		if ($_FILES["xmldoc"]["error"] > UPLOAD_ERR_OK)
		{
			sendInfo($this->lng->txt("error_upload"));
			$this->importObject();
			return;
		}
		include_once("./assessment/classes/class.ilObjTest.php");
		// create import directory
		ilObjTest::_createImportDirectory();

		// copy uploaded file to import directory
		$file = pathinfo($_FILES["xmldoc"]["name"]);
		$full_path = ilObjTest::_getImportDirectory()."/".$_FILES["xmldoc"]["name"];
		ilUtil::moveUploadedFile($_FILES["xmldoc"]["tmp_name"], $_FILES["xmldoc"]["name"], $full_path);

		// unzip file
		ilUtil::unzip($full_path);

		// determine filenames of xml files
		$subdir = basename($file["basename"],".".$file["extension"]);
		$xml_file = ilObjTest::_getImportDirectory()."/".$subdir."/".$subdir.".xml";
		$qti_file = ilObjTest::_getImportDirectory()."/".$subdir."/". str_replace("test", "qti", $subdir).".xml";
		// start verification of QTI files
		include_once "./assessment/classes/class.ilQTIParser.php";
		$qtiParser = new ilQTIParser($qti_file, IL_MO_VERIFY_QTI, 0, "");
		$result = $qtiParser->startParsing();
		$founditems =& $qtiParser->getFoundItems();
		
		if (count($founditems) == 0)
		{
			// nothing found

			// delete import directory
			ilUtil::delDir(ilObjTest::_getImportDirectory());

			sendInfo($this->lng->txt("tst_import_no_items"));
			$this->importObject();
			return;
		}
		
		$complete = 0;
		$incomplete = 0;
		foreach ($founditems as $item)
		{
			if (strlen($item["type"]))
			{
				$complete++;
			}
			else
			{
				$incomplete++;
			}
		}
		
		if ($complete == 0)
		{
			// delete import directory
			ilUtil::delDir(ilObjTest::_getImportDirectory());

			sendInfo($this->lng->txt("qpl_import_non_ilias_files"));
			$this->importObject();
			return;
		}
		
		$_SESSION["tst_import_xml_file"] = $xml_file;
		$_SESSION["tst_import_qti_file"] = $qti_file;
		$_SESSION["tst_import_subdir"] = $subdir;
		// display of found questions
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.tst_import_verification.html");
		$row_class = array("tblrow1", "tblrow2");
		$counter = 0;
		foreach ($founditems as $item)
		{
			$this->tpl->setCurrentBlock("verification_row");
			$this->tpl->setVariable("ROW_CLASS", $row_class[$counter++ % 2]);
			$this->tpl->setVariable("QUESTION_TITLE", $item["title"]);
			$this->tpl->setVariable("QUESTION_IDENT", $item["ident"]);
			switch ($item["type"])
			{
				case "MULTIPLE CHOICE QUESTION":
					$this->tpl->setVariable("QUESTION_TYPE", $this->lng->txt("qt_multiple_choice"));
					break;
				case "CLOZE QUESTION":
					$this->tpl->setVariable("QUESTION_TYPE", $this->lng->txt("qt_cloze"));
					break;
				case "IMAGE MAP QUESTION":
					$this->tpl->setVariable("QUESTION_TYPE", $this->lng->txt("qt_imagemap"));
					break;
				case "JAVA APPLET QUESTION":
					$this->tpl->setVariable("QUESTION_TYPE", $this->lng->txt("qt_javaapplet"));
					break;
				case "MATCHING QUESTION":
					$this->tpl->setVariable("QUESTION_TYPE", $this->lng->txt("qt_matching"));
					break;
				case "ORDERING QUESTION":
					$this->tpl->setVariable("QUESTION_TYPE", $this->lng->txt("qt_ordering"));
					break;
				case "TEXT QUESTION":
					$this->tpl->setVariable("QUESTION_TYPE", $this->lng->txt("qt_text"));
					break;
			}
			$this->tpl->parseCurrentBlock();
		}
		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("TEXT_TYPE", $this->lng->txt("question_type"));
		$this->tpl->setVariable("TEXT_TITLE", $this->lng->txt("question_title"));
		$this->tpl->setVariable("FOUND_QUESTIONS_INTRODUCTION", $this->lng->txt("tst_import_verify_found_questions"));
		$this->tpl->setVariable("VERIFICATION_HEADING", $this->lng->txt("import_tst"));
		$this->tpl->setVariable("FORMACTION", $this->getFormAction("save","adm_object.php?cmd=gateway&ref_id=".$_GET["ref_id"]."&new_type=".$this->type));
		$this->tpl->setVariable("ARROW", ilUtil::getImagePath("arrow_downright.gif"));
		$this->tpl->setVariable("QUESTIONPOOL_ID", $_POST["qpl"]);
		$this->tpl->setVariable("VALUE_IMPORT", $this->lng->txt("import"));
		$this->tpl->setVariable("VALUE_CANCEL", $this->lng->txt("cancel"));
		$this->tpl->parseCurrentBlock();
	}
	
	/**
	* imports question(s) into the questionpool (after verification)
	*/
	function importVerifiedFileObject()
	{
		include_once "./assessment/classes/class.ilObjTest.php";
		// create new questionpool object
		$newObj = new ilObjTest(true);
		// set type of questionpool object
		$newObj->setType($_GET["new_type"]);
		// set title of questionpool object to "dummy"
		$newObj->setTitle("dummy");
		// set description of questionpool object
		$newObj->setDescription("test import");
		// create the questionpool class in the ILIAS database (object_data table)
		$newObj->create(true);
		// create a reference for the questionpool object in the ILIAS database (object_reference table)
		$newObj->createReference();
		// put the questionpool object in the administration tree
		$newObj->putInTree($_GET["ref_id"]);
		// get default permissions and set the permissions for the questionpool object
		$newObj->setPermissions($_GET["ref_id"]);
		// notify the questionpool object and all its parent objects that a "new" object was created
		$newObj->notify("new",$_GET["ref_id"],$_GET["parent_non_rbac_id"],$_GET["ref_id"],$newObj->getRefId());
		// empty mark schema
		$newObj->mark_schema->flush();

		// start parsing of QTI files
		include_once "./assessment/classes/class.ilQTIParser.php";
		$qtiParser = new ilQTIParser($_SESSION["tst_import_qti_file"], IL_MO_PARSE_QTI, $_POST["qpl_id"], $_POST["ident"]);
		$qtiParser->setTestObject($newObj);
		$result = $qtiParser->startParsing();
		$newObj->saveToDb();
		
		// import page data
		include_once ("content/classes/class.ilContObjParser.php");
		$contParser = new ilContObjParser($newObj, $_SESSION["tst_import_xml_file"], $_SESSION["tst_import_subdir"]);
		$contParser->setQuestionMapping($qtiParser->getImportMapping());
		$contParser->startParsing();

		// delete import directory
		ilUtil::delDir(ilObjTest::_getImportDirectory());
		
		ilUtil::redirect($this->getReturnLocation("save", "adm_object.php?ref_id=" . $_GET["ref_id"]));
	}
	
	function cancelImportObject()
	{
		ilUtil::redirect($this->getReturnLocation("cancel", "adm_object.php?ref_id=" . $_GET["ref_id"]));
	}
	
	
	/**
	* display status information or report errors messages
	* in case of error
	*
	* @access	public
	*/
	function uploadObject($redirect = true)
	{
		$this->uploadTstObject();
	}

	/**
	* Save the form input of the properties form
	*
	* Save the form input of the properties form
	*
	* @access	public
	*/
	function savePropertiesObject()
	{
		$total = $this->object->evalTotalPersons();
		$deleteuserdata = false;
		$randomtest_switch = false;
		// Check the values the user entered in the form
		if (!$total)
		{
			$data["count_system"] = $_POST["count_system"];
			$data["mc_scoring"] = $_POST["mc_scoring"];
			$data["pass_scoring"] = $_POST["pass_scoring"];
			$data["sel_test_types"] = ilUtil::stripSlashes($_POST["sel_test_types"]);
			if (!strlen($_POST["chb_random"]))
			{
				$data["random_test"] = 0;
			}
			else
			{
				$data["random_test"] = ilUtil::stripSlashes($_POST["chb_random"]);
			}
			if ($data["sel_test_types"] == TYPE_VARYING_RANDOMTEST)
			{
				$data["random_test"] = "1";
			}
		}
		else
		{
			$data["sel_test_types"] = $this->object->getTestType();
			$data["random_test"] = $this->object->random_test;
			$data["count_system"] = $this->object->getCountSystem();
			$data["mc_scoring"] = $this->object->getMCScoring();
			$data["pass_scoring"] = $this->object->getPassScoring();
		}
		if ($data["sel_test_types"] != $this->object->getTestType())
		{
			$deleteuserdata = true;
		}
		if ($data["random_test"] != $this->object->random_test)
		{
			$randomtest_switch = true;
		}
		$data["title"] = ilUtil::stripSlashes($_POST["title"]);
		$data["description"] = ilUtil::stripSlashes($_POST["description"]);
		$data["author"] = ilUtil::stripSlashes($_POST["author"]);
		$data["introduction"] = ilUtil::stripSlashes($_POST["introduction"]);
		$data["sequence_settings"] = ilUtil::stripSlashes($_POST["sequence_settings"]);
		if ($this->object->getTestType() == TYPE_ASSESSMENT || $this->object->getTestType() == TYPE_ONLINE_TEST)
		{
			$data["score_reporting"] = REPORT_AFTER_TEST;
		}
		else
		{
			$data["score_reporting"] = ilUtil::stripSlashes($_POST["score_reporting"]);
		}
		$data["nr_of_tries"] = ilUtil::stripSlashes($_POST["nr_of_tries"]);
		$data["processing_time"] = ilUtil::stripSlashes($_POST["processing_time"]);
		if (!$_POST["chb_starting_time"])
		{
			$data["starting_time"] = "";
		}
		else
		{
			$data["starting_time"] = sprintf("%04d%02d%02d%02d%02d%02d",
				$_POST["starting_date"]["y"],
				$_POST["starting_date"]["m"],
				$_POST["starting_date"]["d"],
				$_POST["starting_time"]["h"],
				$_POST["starting_time"]["m"],
				0
			);
		}
		if (!$_POST["chb_ending_time"])
		{
			$data["ending_time"] = "";
		}
		else
		{
			$data["ending_time"] = sprintf("%04d%02d%02d%02d%02d%02d",
				$_POST["ending_date"]["y"],
				$_POST["ending_date"]["m"],
				$_POST["ending_date"]["d"],
				$_POST["ending_time"]["h"],
				$_POST["ending_time"]["m"],
				0
			);
		}

		if ($_POST["chb_processing_time"])
		{
			$data["enable_processing_time"] = "1";
		}
		else
		{
			$data["enable_processing_time"] = "0";
		}
		if ($_POST["chb_hide_previous_results"])
		{
			$data["hide_previous_results"] = "1";
		}
		else
		{
			$data["hide_previous_results"] = "0";
		}

		if ($data["enable_processing_time"])
		{
			$data["processing_time"] = sprintf("%02d:%02d:%02d",
				$_POST["processing_time"]["h"],
				$_POST["processing_time"]["m"],
				$_POST["processing_time"]["s"]
			);
		}
		else
		{
			$proc_time = $this->object->getEstimatedWorkingTime();
			$data["processing_time"] = sprintf("%02d:%02d:%02d",
				$proc_time["h"],
				$proc_time["m"],
				$proc_time["s"]
			);
		}

		if (!$_POST["chb_reporting_date"] && !$this->object->isOnlineTest())
		{
			$data["reporting_date"] = "";
		}
		else
		{
			$data["reporting_date"] = sprintf("%04d%02d%02d%02d%02d%02d",
				$_POST["reporting_date"]["y"],
				$_POST["reporting_date"]["m"],
				$_POST["reporting_date"]["d"],
				$_POST["reporting_time"]["h"],
				$_POST["reporting_time"]["m"],
				0
			);
		}
		if ($data["nr_of_tries"] == 1)
		{
			$data["pass_scoring"] = SCORE_LAST_PASS;
		}
		$this->object->setTestType($data["sel_test_types"]);
		$this->object->setTitle($data["title"]);
		$this->object->setDescription($data["description"]);
		$this->object->setAuthor($data["author"]);
		$this->object->setIntroduction($data["introduction"]);
		$this->object->setSequenceSettings($data["sequence_settings"]);
		$this->object->setCountSystem($data["count_system"]);
		$this->object->setMCScoring($data["mc_scoring"]);
		$this->object->setPassScoring($data["pass_scoring"]);
		if ($this->object->getTestType() == TYPE_ASSESSMENT || $this->object->getTestType() == TYPE_ONLINE_TEST )
		{
			$this->object->setScoreReporting(REPORT_AFTER_TEST);
		}
		else
		{
			$this->object->setScoreReporting($data["score_reporting"]);
		}
		$this->object->setReportingDate($data["reporting_date"]);
		$this->object->setNrOfTries($data["nr_of_tries"]);
		$this->object->setStartingTime($data["starting_time"]);
		$this->object->setEndingTime($data["ending_time"]);
		$this->object->setProcessingTime($data["processing_time"]);
		$this->object->setRandomTest($data["random_test"]);
		$this->object->setEnableProcessingTime($data["enable_processing_time"]);
		$this->object->setHidePreviousResults($data["hide_previous_results"]);
		
		if ($this->object->getTestType() == TYPE_ONLINE_TEST) 
		{
			$this->object->setScoreReporting(1);
    		$this->object->setSequenceSettings(0);
    		$this->object->setNrOfTries(1);
    		$this->object->setRandomTest(0);
		}
		
		if ($this->object->getTestType() == TYPE_VARYING_RANDOMTEST)
		{
			$this->object->setHidePreviousResults(1);
			$this->object->setRandomTest(1);
		}
		else
		{
			$this->object->setPassScoring(SCORE_LAST_PASS);
		}

		$this->update = $this->object->update();
		$this->object->saveToDb(true);

		if ($deleteuserdata)
		{
			$this->object->removeAllTestEditings();
		}
		sendInfo($this->lng->txt("msg_obj_modified"));
		if ($randomtest_switch)
		{
			if ($this->object->isRandomTest())
			{
				$this->object->removeNonRandomTestData();
			}
			else
			{
				$this->object->removeRandomTestData();
			}
		}
		$this->ctrl->redirect($this, "properties");
	}
	
	/**
	* Cancels the properties form
	*
	* Cancels the properties form and goes back to the parent object
	*
	* @access	public
	*/
	function cancelPropertiesObject()
	{
		sendInfo($this->lng->txt("msg_cancel"), true);
		$path = $this->tree->getPathFull($this->object->getRefID());
		ilUtil::redirect($this->getReturnLocation("cancel","../repository.php?cmd=frameset&ref_id=" . $path[count($path) - 2]["child"]));
	}
	
	/**
	* Display and fill the properties form of the test
	*
	* Display and fill the properties form of the test
	*
	* @access	public
	*/
	function propertiesObject()
	{
		global $rbacsystem;
		$total = $this->object->evalTotalPersons();
		if ($this->object->getTestType() == TYPE_ONLINE_TEST  || $data["sel_test_types"] == TYPE_ONLINE_TEST)
		{
   		// fixed settings
			$this->object->setScoreReporting(1);
			$this->object->setSequenceSettings(0);
			$this->object->setNrOfTries(1);
			$this->object->setRandomTest(0);
    }
		if ($total == 0)
		{
			$this->tpl->setCurrentBlock("change_button");
			$this->tpl->setVariable("BTN_CHANGE", $this->lng->txt("change"));
			$this->tpl->parseCurrentBlock();
		}
		
		if (
			($data["sel_test_types"] == TYPE_ONLINE_TEST) || 
			($data["sel_test_types"] == TYPE_ASSESSMENT) || 
			($data["sel_test_types"] == TYPE_VARYING_RANDOMTEST) || 
			(($this->object->getTestType() == TYPE_ASSESSMENT || $this->object->getTestType() == TYPE_ONLINE_TEST) && strlen($data["sel_test_types"]) == 0)
		) 
		{
			$this->lng->loadLanguageModule("jscalendar");
			$this->tpl->addBlockFile("CALENDAR_LANG_JAVASCRIPT", "calendar_javascript", "tpl.calendar.html");
			$this->tpl->setCurrentBlock("calendar_javascript");
			$this->tpl->setVariable("FULL_SUNDAY", $this->lng->txt("l_su"));
			$this->tpl->setVariable("FULL_MONDAY", $this->lng->txt("l_mo"));
			$this->tpl->setVariable("FULL_TUESDAY", $this->lng->txt("l_tu"));
			$this->tpl->setVariable("FULL_WEDNESDAY", $this->lng->txt("l_we"));
			$this->tpl->setVariable("FULL_THURSDAY", $this->lng->txt("l_th"));
			$this->tpl->setVariable("FULL_FRIDAY", $this->lng->txt("l_fr"));
			$this->tpl->setVariable("FULL_SATURDAY", $this->lng->txt("l_sa"));
			$this->tpl->setVariable("SHORT_SUNDAY", $this->lng->txt("s_su"));
			$this->tpl->setVariable("SHORT_MONDAY", $this->lng->txt("s_mo"));
			$this->tpl->setVariable("SHORT_TUESDAY", $this->lng->txt("s_tu"));
			$this->tpl->setVariable("SHORT_WEDNESDAY", $this->lng->txt("s_we"));
			$this->tpl->setVariable("SHORT_THURSDAY", $this->lng->txt("s_th"));
			$this->tpl->setVariable("SHORT_FRIDAY", $this->lng->txt("s_fr"));
			$this->tpl->setVariable("SHORT_SATURDAY", $this->lng->txt("s_sa"));
			$this->tpl->setVariable("FULL_JANUARY", $this->lng->txt("l_01"));
			$this->tpl->setVariable("FULL_FEBRUARY", $this->lng->txt("l_02"));
			$this->tpl->setVariable("FULL_MARCH", $this->lng->txt("l_03"));
			$this->tpl->setVariable("FULL_APRIL", $this->lng->txt("l_04"));
			$this->tpl->setVariable("FULL_MAY", $this->lng->txt("l_05"));
			$this->tpl->setVariable("FULL_JUNE", $this->lng->txt("l_06"));
			$this->tpl->setVariable("FULL_JULY", $this->lng->txt("l_07"));
			$this->tpl->setVariable("FULL_AUGUST", $this->lng->txt("l_08"));
			$this->tpl->setVariable("FULL_SEPTEMBER", $this->lng->txt("l_09"));
			$this->tpl->setVariable("FULL_OCTOBER", $this->lng->txt("l_10"));
			$this->tpl->setVariable("FULL_NOVEMBER", $this->lng->txt("l_11"));
			$this->tpl->setVariable("FULL_DECEMBER", $this->lng->txt("l_12"));
			$this->tpl->setVariable("SHORT_JANUARY", $this->lng->txt("s_01"));
			$this->tpl->setVariable("SHORT_FEBRUARY", $this->lng->txt("s_02"));
			$this->tpl->setVariable("SHORT_MARCH", $this->lng->txt("s_03"));
			$this->tpl->setVariable("SHORT_APRIL", $this->lng->txt("s_04"));
			$this->tpl->setVariable("SHORT_MAY", $this->lng->txt("s_05"));
			$this->tpl->setVariable("SHORT_JUNE", $this->lng->txt("s_06"));
			$this->tpl->setVariable("SHORT_JULY", $this->lng->txt("s_07"));
			$this->tpl->setVariable("SHORT_AUGUST", $this->lng->txt("s_08"));
			$this->tpl->setVariable("SHORT_SEPTEMBER", $this->lng->txt("s_09"));
			$this->tpl->setVariable("SHORT_OCTOBER", $this->lng->txt("s_10"));
			$this->tpl->setVariable("SHORT_NOVEMBER", $this->lng->txt("s_11"));
			$this->tpl->setVariable("SHORT_DECEMBER", $this->lng->txt("s_12"));
			$this->tpl->setVariable("ABOUT_CALENDAR", $this->lng->txt("about_calendar"));
			$this->tpl->setVariable("ABOUT_CALENDAR_LONG", $this->lng->txt("about_calendar_long"));
			$this->tpl->setVariable("ABOUT_TIME_LONG", $this->lng->txt("about_time"));
			$this->tpl->setVariable("PREV_YEAR", $this->lng->txt("prev_year"));
			$this->tpl->setVariable("PREV_MONTH", $this->lng->txt("prev_month"));
			$this->tpl->setVariable("GO_TODAY", $this->lng->txt("go_today"));
			$this->tpl->setVariable("NEXT_MONTH", $this->lng->txt("next_month"));
			$this->tpl->setVariable("NEXT_YEAR", $this->lng->txt("next_year"));
			$this->tpl->setVariable("SEL_DATE", $this->lng->txt("select_date"));
			$this->tpl->setVariable("DRAG_TO_MOVE", $this->lng->txt("drag_to_move"));
			$this->tpl->setVariable("PART_TODAY", $this->lng->txt("part_today"));
			$this->tpl->setVariable("DAY_FIRST", $this->lng->txt("day_first"));
			$this->tpl->setVariable("CLOSE", $this->lng->txt("close"));
			$this->tpl->setVariable("TODAY", $this->lng->txt("today"));
			$this->tpl->setVariable("TIME_PART", $this->lng->txt("time_part"));
			$this->tpl->setVariable("DEF_DATE_FORMAT", $this->lng->txt("def_date_format"));
			$this->tpl->setVariable("TT_DATE_FORMAT", $this->lng->txt("tt_date_format"));
			$this->tpl->setVariable("WK", $this->lng->txt("wk"));
			$this->tpl->setVariable("TIME", $this->lng->txt("time"));
			$this->tpl->parseCurrentBlock();
			$this->tpl->setCurrentBlock("CalendarJS");
			$this->tpl->setVariable("LOCATION_JAVASCRIPT_CALENDAR", "../assessment/js/calendar/calendar.js");
			$this->tpl->setVariable("LOCATION_JAVASCRIPT_CALENDAR_SETUP", "../assessment/js/calendar/calendar-setup.js");
			$this->tpl->setVariable("LOCATION_JAVASCRIPT_CALENDAR_STYLESHEET", "../assessment/js/calendar/calendar.css");
			$this->tpl->parseCurrentBlock();
			$this->tpl->setCurrentBlock("javascript_call_calendar");
			$this->tpl->setVariable("INPUT_FIELDS_STARTING_DATE", "starting_date");
			$this->tpl->setVariable("INPUT_FIELDS_ENDING_DATE", "ending_date");
			$this->tpl->setVariable("INPUT_FIELDS_REPORTING_DATE", "reporting_date");
			$this->tpl->parseCurrentBlock();
		}
		if ((!$rbacsystem->checkAccess("read", $this->ref_id)) && (!$rbacsystem->checkAccess("write", $this->ref_id))) 
		{
			// allow only read and write access
			sendInfo($this->lng->txt("cannot_edit_test"), true);
			$path = $this->tree->getPathFull($this->object->getRefID());
			ilUtil::redirect($this->getReturnLocation("cancel","../repository.php?cmd=frameset&ref_id=" . $path[count($path) - 2]["child"]));
			return;
		}
		
		$data["sel_test_types"] = $this->object->getTestType();
		$data["author"] = $this->object->getAuthor();
		$data["introduction"] = $this->object->getIntroduction();
		$data["sequence_settings"] = $this->object->getSequenceSettings();
		$data["score_reporting"] = $this->object->getScoreReporting();
		$data["reporting_date"] = $this->object->getReportingDate();
		$data["nr_of_tries"] = $this->object->getNrOfTries();
		$data["hide_previous_results"] = $this->object->getHidePreviousResults();

		$data["enable_processing_time"] = $this->object->getEnableProcessingTime();
		$data["processing_time"] = $this->object->getProcessingTime();
		$data["random_test"] = $this->object->isRandomTest();
		$data["count_system"] = $this->object->getCountSystem();
		$data["mc_scoring"] = $this->object->getMCScoring();
		if ($this->object->getTestType() == TYPE_VARYING_RANDOMTEST)
		{
			$data["pass_scoring"] = $this->object->getPassScoring();
		}
		else
		{
			$data["pass_scoring"] = SCORE_LAST_PASS;
		}
		if ((int)substr($data["processing_time"], 0, 2) + (int)substr($data["processing_time"], 3, 2) + (int)substr($data["processing_time"], 6, 2) == 0)
		{
			$proc_time = $this->object->getEstimatedWorkingTime();
			$data["processing_time"] = sprintf("%02d:%02d:%02d",
				$proc_time["h"],
				$proc_time["m"],
				$proc_time["s"]
			);
		}
		$data["starting_time"] = $this->object->getStartingTime();
		$data["ending_time"] = $this->object->getEndingTime();
		$data["title"] = $this->object->getTitle();
		$data["description"] = $this->object->getDescription();
		
		if ($data["sel_test_types"] == TYPE_ASSESSMENT || ($data["sel_test_types"] == TYPE_ONLINE_TEST) || ($data["sel_test_types"] == TYPE_VARYING_RANDOMTEST))
		{
			$this->tpl->setCurrentBlock("starting_time");
			$this->tpl->setVariable("TEXT_STARTING_TIME", $this->lng->txt("tst_starting_time"));
			if (!$data["starting_time"])
			{
				$date_input = ilUtil::makeDateSelect("starting_date");
				$time_input = ilUtil::makeTimeSelect("starting_time");
			}
			else
			{
				preg_match("/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/", $data["starting_time"], $matches);
				$date_input = ilUtil::makeDateSelect("starting_date", $matches[1], sprintf("%d", $matches[2]), sprintf("%d", $matches[3]));
				$time_input = ilUtil::makeTimeSelect("starting_time", true, sprintf("%d", $matches[4]), sprintf("%d", $matches[5]), sprintf("%d", $matches[6]));
			}
			$this->tpl->setVariable("IMG_STARTING_TIME_CALENDAR", ilUtil::getImagePath("calendar.png"));
			$this->tpl->setVariable("TXT_STARTING_TIME_CALENDAR", $this->lng->txt("open_calendar"));
			$this->tpl->setVariable("TXT_ENABLED", $this->lng->txt("enabled"));
			if ($data["starting_time"])
			{
				$this->tpl->setVariable("CHECKED_STARTING_TIME", " checked=\"checked\"");
			}
			$this->tpl->setVariable("INPUT_STARTING_TIME", $this->lng->txt("date") . ": " . $date_input . $this->lng->txt("time") . ": " . $time_input);
			$this->tpl->parseCurrentBlock();

			$this->tpl->setCurrentBlock("ending_time");
			$this->tpl->setVariable("TEXT_ENDING_TIME", $this->lng->txt("tst_ending_time"));
			if (!$data["ending_time"])
			{
				$date_input = ilUtil::makeDateSelect("ending_date");
				$time_input = ilUtil::makeTimeSelect("ending_time");
			}
			else
			{
				preg_match("/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/", $data["ending_time"], $matches);
				$date_input = ilUtil::makeDateSelect("ending_date", $matches[1], sprintf("%d", $matches[2]), sprintf("%d", $matches[3]));
				$time_input = ilUtil::makeTimeSelect("ending_time", true, sprintf("%d", $matches[4]), sprintf("%d", $matches[5]), sprintf("%d", $matches[6]));
			}
			$this->tpl->setVariable("IMG_ENDING_TIME_CALENDAR", ilUtil::getImagePath("calendar.png"));
			$this->tpl->setVariable("TXT_ENDING_TIME_CALENDAR", $this->lng->txt("open_calendar"));
			$this->tpl->setVariable("TXT_ENABLED", $this->lng->txt("enabled"));
			if ($data["ending_time"])
			{
				$this->tpl->setVariable("CHECKED_ENDING_TIME", " checked=\"checked\"");
			}
			$this->tpl->setVariable("INPUT_ENDING_TIME", $this->lng->txt("date") . ": " . $date_input . $this->lng->txt("time") . ": " . $time_input);
			$this->tpl->parseCurrentBlock();

			$this->tpl->setCurrentBlock("reporting_date");
			$this->tpl->setVariable("TEXT_SCORE_DATE", $this->lng->txt("tst_score_reporting_date"));
			if (!$data["reporting_date"])
			{
				$date_input = ilUtil::makeDateSelect("reporting_date");
				$time_input = ilUtil::makeTimeSelect("reporting_time");
			} else {
				preg_match("/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/", $data["reporting_date"], $matches);
				$date_input = ilUtil::makeDateSelect("reporting_date", $matches[1], sprintf("%d", $matches[2]), sprintf("%d", $matches[3]));
				$time_input = ilUtil::makeTimeSelect("reporting_time", true, sprintf("%d", $matches[4]), sprintf("%d", $matches[5]), sprintf("%d", $matches[6]));
			}
			$this->tpl->setVariable("IMG_REPORTING_DATE_CALENDAR", ilUtil::getImagePath("calendar.png"));
			$this->tpl->setVariable("TXT_REPORTING_DATE_CALENDAR", $this->lng->txt("open_calendar"));
			$this->tpl->setVariable("TXT_ENABLED", $this->lng->txt("enabled"));
			if ($data["reporting_date"] || ($data["sel_test_types"] == TYPE_ONLINE_TEST)) {
				$this->tpl->setVariable("CHECKED_REPORTING_DATE", " checked=\"checked\"");
			}
			$this->tpl->setVariable("INPUT_REPORTING_DATE", $this->lng->txt("date") . ": " . $date_input . $this->lng->txt("time") . ": " . $time_input);
			$this->tpl->parseCurrentBlock();
		}

		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_properties.html", true);
		$this->tpl->setCurrentBlock("test_types");
		foreach ($this->object->test_types as $key => $value) {
			$this->tpl->setVariable("VALUE_TEST_TYPE", $key);
			$this->tpl->setVariable("TEXT_TEST_TYPE", $this->lng->txt($value));
			if ($data["sel_test_types"] == $key) {
				$this->tpl->setVariable("SELECTED_TEST_TYPE", " selected=\"selected\"");
			}
			$this->tpl->parseCurrentBlock();
		}
		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("ACTION_PROPERTIES", $this->ctrl->getFormAction($this));
		if ($rbacsystem->checkAccess("write", $this->ref_id)) {
			$this->tpl->setVariable("SUBMIT_TYPE", $this->lng->txt("change"));
		}
		$this->tpl->setVariable("HEADING_GENERAL", $this->lng->txt("tst_general_properties"));
		$this->tpl->setVariable("TEXT_TEST_TYPES", $this->lng->txt("tst_types"));
		$this->tpl->setVariable("TEST_TYPE_COMMENT", $this->lng->txt("tst_type_comment"));
		$this->tpl->setVariable("TEXT_TITLE", $this->lng->txt("title"));
		$this->tpl->setVariable("VALUE_TITLE", ilUtil::prepareFormOutput($data["title"]));
		$this->tpl->setVariable("TEXT_AUTHOR", $this->lng->txt("author"));
		$this->tpl->setVariable("VALUE_AUTHOR", ilUtil::prepareFormOutput($data["author"]));
		$this->tpl->setVariable("TEXT_DESCRIPTION", $this->lng->txt("description"));
		$this->tpl->setVariable("VALUE_DESCRIPTION", ilUtil::prepareFormOutput($data["description"]));
		$this->tpl->setVariable("TEXT_INTRODUCTION", $this->lng->txt("tst_introduction"));
		$this->tpl->setVariable("VALUE_INTRODUCTION", $data["introduction"]);
		$this->tpl->setVariable("HEADING_SEQUENCE", $this->lng->txt("tst_sequence_properties"));
		$this->tpl->setVariable("TEXT_SEQUENCE", $this->lng->txt("tst_sequence"));
		$this->tpl->setVariable("SEQUENCE_FIXED", $this->lng->txt("tst_sequence_fixed"));
		$this->tpl->setVariable("SEQUENCE_POSTPONE", $this->lng->txt("tst_sequence_postpone"));
		if ($data["sequence_settings"] == 0) {
			$this->tpl->setVariable("SELECTED_FIXED", " selected=\"selected\"");
		} elseif ($data["sequence_settings"] == 1) {
			$this->tpl->setVariable("SELECTED_POSTPONE", " selected=\"selected\"");
		}
		$this->tpl->setVariable("HEADING_SCORE", $this->lng->txt("tst_score_reporting"));
		$this->tpl->setVariable("TEXT_SCORE_TYPE", $this->lng->txt("tst_score_type"));
		$this->tpl->setVariable("REPORT_AFTER_QUESTION", $this->lng->txt("tst_report_after_question"));
		$this->tpl->setVariable("REPORT_AFTER_TEST", $this->lng->txt("tst_report_after_test"));
		if ($data["sel_test_types"] == TYPE_ASSESSMENT || ($data["sel_test_types"] == TYPE_ONLINE_TEST || $this->object->getTestType() == TYPE_ONLINE_TEST)) 
		{
			$this->tpl->setVariable("SELECTED_TEST", " selected=\"selected\"");
			$this->tpl->setVariable("DISABLE_SCORE_REPORTING", " disabled=\"disabled\"");
			if ($this->object->getTestType() == TYPE_ONLINE_TEST || $data["sel_test_types"] == TYPE_ONLINE_TEST) 
			{
				$this->tpl->setVariable("DISABLE_SCORE_REPORTING_DATE_CHECKBOX", " disabled=\"disabled\"");
				$this->tpl->setVariable("DISABLE_SEQUENCE", " disabled=\"disabled\"");
				$this->tpl->setVariable("DISABLE_NR_OF_TRIES", " disabled=\"disabled\"");
				$this->tpl->setVariable("ENABLED_RANDOM_TEST", " disabled=\"disabled\"");
			}
		} 
		else 
		{
			if ($data["score_reporting"] == 0) 
			{
				$this->tpl->setVariable("SELECTED_QUESTION", " selected=\"selected\"");
			} 
			elseif ($data["score_reporting"] == 1) 
			{
				$this->tpl->setVariable("SELECTED_TEST", " selected=\"selected\"");
			}
		}
		$this->tpl->setVariable("TEXT_HIDE_PREVIOUS_RESULTS", $this->lng->txt("tst_hide_previous_results"));
		$this->tpl->setVariable("TEXT_HIDE_PREVIOUS_RESULTS_DESCRIPTION", $this->lng->txt("tst_hide_previous_results_description"));
		if ($data["sel_test_types"] == TYPE_VARYING_RANDOMTEST)
		{
			$data["hide_previous_results"] = 1;
		}
		if ($data["hide_previous_results"] == 1)
		{
			$this->tpl->setVariable("CHECKED_HIDE_PREVIOUS_RESULTS",  " checked=\"checked\"");
		}
		if ($data["sel_test_types"] == TYPE_VARYING_RANDOMTEST)
		{
			$this->tpl->setVariable("DISABLE_HIDE_PREVIOUS_RESULTS", " disabled=\"disabled\"");
		}
		$this->tpl->setVariable("HEADING_SESSION", $this->lng->txt("tst_session_settings"));
		$this->tpl->setVariable("TEXT_NR_OF_TRIES", $this->lng->txt("tst_nr_of_tries"));
		$this->tpl->setVariable("VALUE_NR_OF_TRIES", $data["nr_of_tries"]);
		$this->tpl->setVariable("COMMENT_NR_OF_TRIES", $this->lng->txt("0_unlimited"));
		$this->tpl->setVariable("TEXT_PROCESSING_TIME", $this->lng->txt("tst_processing_time"));
		$time_input = ilUtil::makeTimeSelect("processing_time", false, substr($data["processing_time"], 0, 2), substr($data["processing_time"], 3, 2), substr($data["processing_time"], 6, 2));
		$this->tpl->setVariable("MAX_PROCESSING_TIME", $time_input . " (hh:mm:ss)");
		if ($data["enable_processing_time"]) {
			$this->tpl->setVariable("CHECKED_PROCESSING_TIME", " checked=\"checked\"");
		}
		$this->tpl->setVariable("TEXT_RANDOM_TEST", $this->lng->txt("tst_random_test"));
		$this->tpl->setVariable("TEXT_RANDOM_TEST_DESCRIPTION", $this->lng->txt("tst_random_test_description"));
		if ($data["random_test"]) 
		{
			$this->tpl->setVariable("CHECKED_RANDOM_TEST", " checked=\"checked\"");
		}
		if ($data["sel_test_types"] == TYPE_VARYING_RANDOMTEST)
		{
			$this->tpl->setVariable("CHECKED_RANDOM_TEST", " checked=\"checked\"");
			$this->tpl->setVariable("ENABLED_RANDOM_TEST", " disabled=\"disabled\"");
		}

		$this->tpl->setVariable("HEADING_SCORING", $this->lng->txt("tst_heading_scoring"));
		$this->tpl->setVariable("TEXT_COUNT_SYSTEM", $this->lng->txt("tst_text_count_system"));
		$this->tpl->setVariable("COUNT_PARTIAL_SOLUTIONS", $this->lng->txt("tst_count_partial_solutions"));
		if ($data["count_system"] == COUNT_PARTIAL_SOLUTIONS)
		{
			$this->tpl->setVariable("SELECTED_PARTIAL", " selected=\"selected\"");
		}
		$this->tpl->setVariable("COUNT_CORRECT_SOLUTIONS", $this->lng->txt("tst_count_correct_solutions"));
		if ($data["count_system"] == COUNT_CORRECT_SOLUTIONS)
		{
			$this->tpl->setVariable("SELECTED_CORRECT", " selected=\"selected\"");
		}
		$this->tpl->setVariable("TEXT_SCORE_MCMR", $this->lng->txt("tst_score_mcmr_questions"));
		$this->tpl->setVariable("ZERO_POINTS_WHEN_UNANSWERED", $this->lng->txt("tst_score_mcmr_zero_points_when_unanswered"));
		$this->tpl->setVariable("USE_SCORING_SYSTEM", $this->lng->txt("tst_score_mcmr_use_scoring_system"));
		if ($data["mc_scoring"] == SCORE_ZERO_POINTS_WHEN_UNANSWERED)
		{
			$this->tpl->setVariable("SELECTED_ANTICHEAT", " selected=\"selected\"");
		}
		else
		{
			$this->tpl->setVariable("SELECTED_STANDARD", " selected=\"selected\"");
		}

		$this->tpl->setVariable("TEXT_PASS_SCORING", $this->lng->txt("tst_pass_scoring"));
		$this->tpl->setVariable("TEXT_LASTPASS", $this->lng->txt("tst_pass_last_pass"));
		$this->tpl->setVariable("TEXT_BESTPASS", $this->lng->txt("tst_pass_best_pass"));
		if ($data["pass_scoring"] == SCORE_BEST_PASS)
		{
			$this->tpl->setVariable("SELECTED_BESTPASS", " selected=\"selected\"");
		}
		else
		{
			$this->tpl->setVariable("SELECTED_LASTPASS", " selected=\"selected\"");
		}
		if ($this->object->getTestType() != TYPE_VARYING_RANDOMTEST)
		{
			$this->tpl->setVariable("DISABLE_PASS_SCORING", " disabled=\"disabled\"");
		}
		$this->tpl->setVariable("TXT_REQUIRED_FLD", $this->lng->txt("required_field"));
		if ($rbacsystem->checkAccess("write", $this->ref_id)) {
			$this->tpl->setVariable("SAVE", $this->lng->txt("save"));
			$this->tpl->setVariable("CANCEL", $this->lng->txt("cancel"));
		}
		if ($total > 0)
		{
			$this->tpl->setVariable("DISABLE_COUNT_SYSTEM", " disabled=\"disabled\"");
			$this->tpl->setVariable("DISABLE_MC_SCORING", " disabled=\"disabled\"");
			$this->tpl->setVariable("DISABLE_PASS_SCORING", " disabled=\"disabled\"");
			$this->tpl->setVariable("ENABLED_TEST_TYPES", " disabled=\"disabled\"");
			$this->tpl->setVariable("ENABLED_RANDOM_TEST", " disabled=\"disabled\"");
		}
		$this->tpl->parseCurrentBlock();
	}

	/**
	* download file
	*/
	function downloadFileObject()
	{
		$file = explode("_", $_GET["file_id"]);
		include_once("classes/class.ilObjFile.php");
		$fileObj =& new ilObjFile($file[count($file) - 1], false);
		$fileObj->sendFile();
		exit;
	}
	
	/**
	* show fullscreen view
	*/
	function fullscreenObject()
	{
		include_once("content/classes/Pages/class.ilPageObjectGUI.php");
		$page =& new ilPageObject("qpl", $_GET["pg_id"]);
		$page_gui =& new ilPageObjectGUI($page);
		$page_gui->showMediaFullscreen();
		
	}

	/**
	* download source code paragraph
	*/
	function download_paragraphObject()
	{
		include_once("content/classes/Pages/class.ilPageObject.php");
		$pg_obj =& new ilPageObject("qpl", $_GET["pg_id"]);
		$pg_obj->send_paragraph ($_GET["par_id"], $_GET["downloadtitle"]);
		exit;
	}

	
	function filterObject()
	{
		$filter_type = $_POST["sel_filter_type"];
		if (!$filter_type)
		{
			$filter_type = $_GET["sel_filter_type"];
		}
		$filter_question_type = $_POST["sel_question_type"];
		if (!$filter_question_type)
		{
			$filter_question_type = $_GET["sel_question_type"];
		}
		$filter_questionpool = $_POST["sel_questionpool"];
		if (!$filter_questionpool)
		{
			$filter_questionpool = $_GET["sel_questionpool"];
		}
		$filter_text = $_POST["filter_text"];
		if (!$filter_text)
		{
			$filter_text = $_GET["filter_text"];
		}
		$this->questionBrowser($filter_type, $filter_question_type, $filter_questionpool, $filter_text);
	}

	/**
	* Resets the filter for the question browser 
	*
	* Resets the filter for the question browser 
	*
	* @access	public
	*/
	function resetFilterObject()
	{
		$_GET["sel_filter_type"] = "";
		$_GET["sel_question_type"] = "";
		$_GET["sel_questionpool"] = "";
		$_GET["filter_text"] = "";
		$this->questionBrowser();
	}

	/**
	* Called when the back button in the question browser was pressed 
	*
	* Called when the back button in the question browser was pressed 
	*
	* @access	public
	*/
	function backObject()
	{
		$this->ctrl->redirect($this, "questions");
	}
	
	/**
	* Insert questions from the questionbrowser into the test 
	*
	* Insert questions from the questionbrowser into the test 
	*
	* @access	public
	*/
	function insertQuestionsObject()
	{
		// insert selected questions into test
		$selected_array = array();
		foreach ($_POST as $key => $value)
		{
			if (preg_match("/cb_(\d+)/", $key, $matches))
			{
				array_push($selected_array, $matches[1]);
			}
		}
		if (!count($selected_array))
		{
			sendInfo($this->lng->txt("tst_insert_missing_question"), true);
			$this->ctrl->redirect($this, "browseForQuestions");
		}
		else
		{

			foreach ($selected_array as $key => $value) 
			{
				$this->object->insertQuestion($value);
			}
			$this->object->saveCompleteStatus();
			sendInfo($this->lng->txt("tst_questions_inserted"), true);
			$this->ctrl->redirect($this, "questions");
			return;
		}
	}

	/**
	* Creates a form to select questions from questionpools to insert the questions into the test 
	*
	* Creates a form to select questions from questionpools to insert the questions into the test 
	*
	* @access	public
	*/
	function questionBrowser($filter_type = "", $filter_question_type = "", $filter_questionpool = "", $filter_text = "")
	{
		global $rbacsystem;

		$this->ctrl->setParameterByClass(get_class($this), "browse", "1");
		if (!$filter_type)
		{
			$filter_type = $_GET["sel_filter_type"];
		}
		$this->ctrl->setParameterByClass(get_class($this), "sel_filter_type", $filter_type);
		if (!$filter_question_type)
		{
			$filter_question_type = $_GET["sel_question_type"];
		}
		$this->ctrl->setParameterByClass(get_class($this), "sel_question_type", $filter_question_type);
		if (!$filter_questionpool)
		{
			$filter_questionpool = $_GET["sel_questionpool"];
		}
		$this->ctrl->setParameterByClass(get_class($this), "sel_questionpool", $filter_questionpool);
		if (!$filter_text)
		{
			$filter_text = $_GET["filter_text"];
		}
		$this->ctrl->setParameterByClass(get_class($this), "filter_text", $filter_text);
		
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_questionbrowser.html", true);
		$this->tpl->addBlockFile("A_BUTTONS", "a_buttons", "tpl.il_as_qpl_action_buttons.html", true);
		$this->tpl->addBlockFile("FILTER_QUESTION_MANAGER", "filter_questions", "tpl.il_as_tst_filter_questions.html", true);

		$questionpools =& $this->object->get_qpl_titles();

		$filter_fields = array(
			"title" => $this->lng->txt("title"),
			"comment" => $this->lng->txt("description"),
			"author" => $this->lng->txt("author"),
		);
		$this->tpl->setCurrentBlock("filterrow");
		foreach ($filter_fields as $key => $value) {
			$this->tpl->setVariable("VALUE_FILTER_TYPE", "$key");
			$this->tpl->setVariable("NAME_FILTER_TYPE", "$value");
			if (strcmp($_POST["cmd"]["resetFilter"], "") == 0) {
				if (strcmp($filter_type, $key) == 0) {
					$this->tpl->setVariable("VALUE_FILTER_SELECTED", " selected=\"selected\"");
				}
			}
			$this->tpl->parseCurrentBlock();
		}

		$questiontypes =& $this->object->_getQuestiontypes();
		foreach ($questiontypes as $key => $value)
		{
			$this->tpl->setCurrentBlock("questiontype_row");
			$this->tpl->setVariable("VALUE_QUESTION_TYPE", $value);
			$this->tpl->setVariable("TEXT_QUESTION_TYPE", $this->lng->txt($value));
			if (strcmp($filter_question_type, $value) == 0)
			{
				$this->tpl->setVariable("SELECTED_QUESTION_TYPE", " selected=\"selected\"");
			}
			$this->tpl->parseCurrentBlock();
		}
		
		foreach ($questionpools as $key => $value)
		{
			$this->tpl->setCurrentBlock("questionpool_row");
			$this->tpl->setVariable("VALUE_QUESTIONPOOL", $key);
			$this->tpl->setVariable("TEXT_QUESTIONPOOL", $value);
			if (strcmp($filter_questionpool, $key) == 0)
			{
				$this->tpl->setVariable("SELECTED_QUESTIONPOOL", " selected=\"selected\"");
			}
			$this->tpl->parseCurrentBlock();
		}

		$this->tpl->setCurrentBlock("filter_questions");
		$this->tpl->setVariable("SHOW_QUESTION_TYPES", $this->lng->txt("filter_show_question_types"));
		$this->tpl->setVariable("TEXT_ALL_QUESTION_TYPES", $this->lng->txt("filter_all_question_types"));
		$this->tpl->setVariable("SHOW_QUESTIONPOOLS", $this->lng->txt("filter_show_questionpools"));
		$this->tpl->setVariable("TEXT_ALL_QUESTIONPOOLS", $this->lng->txt("filter_all_questionpools"));
		$this->tpl->setVariable("FILTER_TEXT", $this->lng->txt("filter"));
		$this->tpl->setVariable("TEXT_FILTER_BY", $this->lng->txt("by"));
		if (strcmp($_POST["cmd"]["resetFilter"], "") == 0) 
		{
			$this->tpl->setVariable("VALUE_FILTER_TEXT", $filter_text);
		}
		$this->tpl->setVariable("VALUE_SUBMIT_FILTER", $this->lng->txt("set_filter"));
		$this->tpl->setVariable("VALUE_RESET_FILTER", $this->lng->txt("reset_filter"));
		$this->tpl->parseCurrentBlock();

		$this->tpl->setCurrentBlock("QTab");

		$startrow = 0;
		if ($_GET["prevrow"])
		{
			$startrow = $_GET["prevrow"];
		}
		if ($_GET["nextrow"])
		{
			$startrow = $_GET["nextrow"];
		}
		if ($_GET["startrow"])
		{
			$startrow = $_GET["startrow"];
		}
		if (!$_GET["sort"])
		{
			// default sort order
			$_GET["sort"] = array("title" => "ASC");
		}
		$table = $this->object->getQuestionsTable($_GET["sort"], $filter_text, $filter_type, $startrow, 1, $filter_question_type, $filter_questionpool);
		// display all questions in accessable question pools
		$colors = array("tblrow1", "tblrow2");
		$counter = 0;
		$existing_questions =& $this->object->getExistingQuestions();
		foreach ($table["rows"] as $data)
		{
			if (!in_array($data["question_id"], $existing_questions))
			{
				if ($data["complete"])
				{
					// make only complete questions selectable
					$this->tpl->setVariable("QUESTION_ID", $data["question_id"]);
				}
				$this->tpl->setVariable("QUESTION_TITLE", "<strong>" . $data["title"] . "</strong>");
				$this->tpl->setVariable("PREVIEW", "[<a href=\"" . $this->getCallingScript() . "$add_parameter&preview=" . $data["question_id"] . "\">" . $this->lng->txt("preview") . "</a>]");
				$this->tpl->setVariable("QUESTION_COMMENT", $data["comment"]);
				$this->tpl->setVariable("QUESTION_TYPE", $this->lng->txt($data["type_tag"]));
				$this->tpl->setVariable("QUESTION_AUTHOR", $data["author"]);
				$this->tpl->setVariable("QUESTION_CREATED", ilFormat::formatDate(ilFormat::ftimestamp2dateDB($data["created"]), "date"));
				$this->tpl->setVariable("QUESTION_UPDATED", ilFormat::formatDate(ilFormat::ftimestamp2dateDB($data["TIMESTAMP14"]), "date"));
				$this->tpl->setVariable("COLOR_CLASS", $colors[$counter % 2]);
				$this->tpl->setVariable("QUESTION_POOL", $questionpools[$data["obj_fi"]]);
				$this->tpl->parseCurrentBlock();
				$counter++;
			}
		}

		if ($table["rowcount"] > count($table["rows"]))
		{
			$nextstep = $table["nextrow"] + $table["step"];
			if ($nextstep > $table["rowcount"])
			{
				$nextstep = $table["rowcount"];
			}
			$sort = "";
			if (is_array($_GET["sort"]))
			{
				$key = key($_GET["sort"]);
				$sort = "&sort[$key]=" . $_GET["sort"]["$key"];
			}
			$counter = 1;
			for ($i = 0; $i < $table["rowcount"]; $i += $table["step"])
			{
				$this->tpl->setCurrentBlock("pages");
				if ($table["startrow"] == $i)
				{
					$this->tpl->setVariable("PAGE_NUMBER", "<span class=\"inactivepage\">$counter</span>");
				}
				else
				{
					$this->tpl->setVariable("PAGE_NUMBER", "<a href=\"" . $this->ctrl->getFormAction($this) . "$sort&nextrow=$i" . "\">$counter</a>");
				}
				$this->tpl->parseCurrentBlock();
				$counter++;
			}
			$this->tpl->setCurrentBlock("navigation_bottom");
			$this->tpl->setVariable("TEXT_ITEM", $this->lng->txt("item"));
			$this->tpl->setVariable("TEXT_ITEM_START", $table["startrow"] + 1);
			$end = $table["startrow"] + $table["step"];
			if ($end > $table["rowcount"])
			{
				$end = $table["rowcount"];
			}
			$this->tpl->setVariable("TEXT_ITEM_END", $end);
			$this->tpl->setVariable("TEXT_OF", strtolower($this->lng->txt("of")));
			$this->tpl->setVariable("TEXT_ITEM_COUNT", $table["rowcount"]);
			$this->tpl->setVariable("TEXT_PREVIOUS", $this->lng->txt("previous"));
			$this->tpl->setVariable("TEXT_NEXT", $this->lng->txt("next"));
			$this->tpl->setVariable("HREF_PREV_ROWS", $this->ctrl->getFormAction($this) . "$sort&prevrow=" . $table["prevrow"]);
			$this->tpl->setVariable("HREF_NEXT_ROWS", $this->ctrl->getFormAction($this) . "$sort&nextrow=" . $table["nextrow"]);
			$this->tpl->parseCurrentBlock();
		}

		// if there are no questions, display a message
		if ($counter == 0) 
		{
			$this->tpl->setCurrentBlock("Emptytable");
			$this->tpl->setVariable("TEXT_EMPTYTABLE", $this->lng->txt("no_questions_available"));
			$this->tpl->parseCurrentBlock();
		}
		else
		{
			// create edit buttons & table footer
			$this->tpl->setCurrentBlock("selection");
			$this->tpl->setVariable("INSERT", $this->lng->txt("insert"));
			$this->tpl->parseCurrentBlock();
	
			$this->tpl->setCurrentBlock("selectall");
			$this->tpl->setVariable("SELECT_ALL", $this->lng->txt("select_all"));
			$counter++;
			$this->tpl->setVariable("COLOR_CLASS", $colors[$counter % 2]);
			$this->tpl->parseCurrentBlock();
	
			$this->tpl->setCurrentBlock("Footer");
			$this->tpl->setVariable("ARROW", "<img src=\"" . ilUtil::getImagePath("arrow_downright.gif") . "\" alt=\"\">");
			$this->tpl->parseCurrentBlock();
		}
		// define the sort column parameters
		$sort = array(
			"title" => $_GET["sort"]["title"],
			"comment" => $_GET["sort"]["comment"],
			"type" => $_GET["sort"]["type"],
			"author" => $_GET["sort"]["author"],
			"created" => $_GET["sort"]["created"],
			"updated" => $_GET["sort"]["updated"],
			"qpl" => $_GET["sort"]["qpl"]
		);
		foreach ($sort as $key => $value) {
			if (strcmp($value, "ASC") == 0) {
				$sort[$key] = "DESC";
			} else {
				$sort[$key] = "ASC";
			}
		}

		$this->tpl->setCurrentBlock("adm_content");
		// create table header
		$this->ctrl->setCmd("questionBrowser");
		$this->ctrl->setParameterByClass(get_class($this), "startrow", $table["startrow"]);
		$this->tpl->setVariable("QUESTION_TITLE", "<a href=\"" . $this->ctrl->getFormAction($this) . "&sort[title]=" . $sort["title"] . "\">" . $this->lng->txt("title") . "</a>" . $table["images"]["title"]);
		$this->tpl->setVariable("QUESTION_COMMENT", "<a href=\"" . $this->ctrl->getFormAction($this) . "&sort[comment]=" . $sort["comment"] . "\">" . $this->lng->txt("description") . "</a>". $table["images"]["comment"]);
		$this->tpl->setVariable("QUESTION_TYPE", "<a href=\"" . $this->ctrl->getFormAction($this) . "&sort[type]=" . $sort["type"] . "\">" . $this->lng->txt("question_type") . "</a>" . $table["images"]["type"]);
		$this->tpl->setVariable("QUESTION_AUTHOR", "<a href=\"" . $this->ctrl->getFormAction($this) . "&sort[author]=" . $sort["author"] . "\">" . $this->lng->txt("author") . "</a>" . $table["images"]["author"]);
		$this->tpl->setVariable("QUESTION_CREATED", "<a href=\"" . $this->ctrl->getFormAction($this) . "&sort[created]=" . $sort["created"] . "\">" . $this->lng->txt("create_date") . "</a>" . $table["images"]["created"]);
		$this->tpl->setVariable("QUESTION_UPDATED", "<a href=\"" . $this->ctrl->getFormAction($this) . "&sort[updated]=" . $sort["updated"] . "\">" . $this->lng->txt("last_update") . "</a>" . $table["images"]["updated"]);
		$this->tpl->setVariable("QUESTION_POOL", "<a href=\"" . $this->ctrl->getFormAction($this) . "&sort[qpl]=" . $sort["qpl"] . "\">" . $this->lng->txt("obj_qpl") . "</a>" . $table["images"]["qpl"]);
		$this->tpl->setVariable("BUTTON_BACK", $this->lng->txt("back"));
		$this->tpl->setVariable("ACTION_QUESTION_FORM", $this->ctrl->getFormAction($this));
		$this->tpl->parseCurrentBlock();
	}

	/**
	* Creates a new questionpool and returns the reference id
	*
	* Creates a new questionpool and returns the reference id
	*
	* @return integer Reference id of the newly created questionpool
	* @access	public
	*/
	function createQuestionPool($name = "dummy")
	{
		global $tree;
		$parent_ref = $tree->getParentId($this->object->getRefId());
		$qpl = new ilObjQuestionPool();
		$qpl->setType("qpl");
		$qpl->setTitle($name);
		$qpl->setDescription("");
		$qpl->create();
		$qpl->createReference();
		$qpl->putInTree($parent_ref);
		$qpl->setPermissions($parent_ref);
		return $qpl->getRefId();
	}

	/**
	* Creates a form for random selection of questions
	*
	* Creates a form for random selection of questions
	*
	* @access	public
	*/
	function randomselectObject()
	{
		global $ilUser;
		$add_parameter = $this->getAddParameter();
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_random_select.html", true);
		$questionpools =& $this->object->getAvailableQuestionpools();
		$this->tpl->setCurrentBlock("option");
		$this->tpl->setVariable("VALUE_OPTION", "0");
		$this->tpl->setVariable("TEXT_OPTION", $this->lng->txt("all_available_question_pools"));
		$this->tpl->parseCurrentBlock();
		foreach ($questionpools as $key => $value)
		{
			$this->tpl->setCurrentBlock("option");
			$this->tpl->setVariable("VALUE_OPTION", $key);
			$this->tpl->setVariable("TEXT_OPTION", $value);
			$this->tpl->parseCurrentBlock();
		}
		$this->tpl->setCurrentBlock("hidden");
		$this->tpl->setVariable("HIDDEN_NAME", "sel_question_types");
		$this->tpl->setVariable("HIDDEN_VALUE", $_POST["sel_question_types"]);
		$this->tpl->parseCurrentBlock();
		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("FORM_ACTION", $this->ctrl->getFormAction($this));
		$this->tpl->setVariable("TXT_QPL_SELECT", $this->lng->txt("tst_random_select_questionpool"));
		$this->tpl->setVariable("TXT_NR_OF_QUESTIONS", $this->lng->txt("tst_random_nr_of_questions"));
		$this->tpl->setVariable("BTN_SUBMIT", $this->lng->txt("submit"));
		$this->tpl->setVariable("BTN_CANCEL", $this->lng->txt("cancel"));
		$this->tpl->parseCurrentBlock();
	}
	
	/**
	* Cancels the form for random selection of questions
	*
	* Cancels the form for random selection of questions
	*
	* @access	public
	*/
	function cancelRandomSelectObject()
	{
		$this->ctrl->redirect($this, "questions");
	}
	
	/**
	* Offers a random selection for insertion in the test
	*
	* Offers a random selection for insertion in the test
	*
	* @access	public
	*/
	function createRandomSelectionObject()
	{
		$question_array = $this->object->randomSelectQuestions($_POST["nr_of_questions"], $_POST["sel_qpl"]);
		$add_parameter = $this->getAddParameter();
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_random_question_offer.html", true);
		$color_class = array("tblrow1", "tblrow2");
		$counter = 0;
		$questionpools =& $this->object->get_qpl_titles();
		foreach ($question_array as $question_id)
		{
			$dataset = $this->object->getQuestionDataset($question_id);
			$this->tpl->setCurrentBlock("QTab");
			$this->tpl->setVariable("COLOR_CLASS", $color_class[$counter % 2]);
			$this->tpl->setVariable("QUESTION_TITLE", $dataset->title);
			$this->tpl->setVariable("QUESTION_COMMENT", $dataset->comment);
			$this->tpl->setVariable("QUESTION_TYPE", $this->lng->txt($dataset->type_tag));
			$this->tpl->setVariable("QUESTION_AUTHOR", $dataset->author);
			$this->tpl->setVariable("QUESTION_POOL", $questionpools[$dataset->obj_fi]);
			$this->tpl->parseCurrentBlock();
			$counter++;
		}
		if (count($question_array) == 0)
		{
			$this->tpl->setCurrentBlock("Emptytable");
			$this->tpl->setVariable("TEXT_NO_QUESTIONS_AVAILABLE", $this->lng->txt("no_questions_available"));
			$this->tpl->parseCurrentBlock();
		}
			else
		{
			$this->tpl->setCurrentBlock("Selectionbuttons");
			$this->tpl->setVariable("BTN_YES", $this->lng->txt("random_accept_sample"));
			$this->tpl->setVariable("BTN_NO", $this->lng->txt("random_another_sample"));
			$this->tpl->parseCurrentBlock();
		}
		$chosen_questions = join($question_array, ",");
		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("FORM_ACTION", $this->ctrl->getFormAction($this));
		$this->tpl->setVariable("QUESTION_TITLE", $this->lng->txt("tst_question_title"));
		$this->tpl->setVariable("QUESTION_COMMENT", $this->lng->txt("description"));
		$this->tpl->setVariable("QUESTION_TYPE", $this->lng->txt("tst_question_type"));
		$this->tpl->setVariable("QUESTION_AUTHOR", $this->lng->txt("author"));
		$this->tpl->setVariable("QUESTION_POOL", $this->lng->txt("qpl"));
		$this->tpl->setVariable("VALUE_CHOSEN_QUESTIONS", $chosen_questions);
		$this->tpl->setVariable("VALUE_QUESTIONPOOL_SELECTION", $_POST["sel_qpl"]);
		$this->tpl->setVariable("VALUE_NR_OF_QUESTIONS", $_POST["nr_of_questions"]);
		$this->tpl->setVariable("TEXT_QUESTION_OFFER", $this->lng->txt("tst_question_offer"));
		$this->tpl->setVariable("BTN_CANCEL", $this->lng->txt("cancel"));
		$this->tpl->parseCurrentBlock();
	}
	
	/**
	* Inserts a random selection into the test
	*
	* Inserts a random selection into the test
	*
	* @access	public
	*/
	function insertRandomSelectionObject()
	{
		$selected_array = split(",", $_POST["chosen_questions"]);
		if (!count($selected_array))
		{
			sendInfo($this->lng->txt("tst_insert_missing_question"));
		}
		else
		{
			$total = $this->object->evalTotalPersons();
			if ($total)
			{
				// the test was executed previously
				sendInfo(sprintf($this->lng->txt("tst_insert_questions_and_results"), $total));
			}
			else
			{
				sendInfo($this->lng->txt("tst_insert_questions"));
			}
			foreach ($selected_array as $key => $value) 
			{
				$this->object->insertQuestion($value);
			}
			$this->object->saveCompleteStatus();
			sendInfo($this->lng->txt("tst_questions_inserted"), true);
			$this->ctrl->redirect($this, "questions");
			return;
		}
	}

	function randomQuestionsObject()
	{
		$total = $this->object->evalTotalPersons();
		$add_parameter = $this->getAddParameter();
		$available_qpl =& $this->object->getAvailableQuestionpools(true);
		foreach ($available_qpl as $key => $value)
		{
			$count = ilObjQuestionPool::_getQuestionCount($key);
			if ($count == 1)
			{
				$available_qpl[$key] = $value . " ($count " . $this->lng->txt("ass_question") . ")";
			}
			else
			{
				$available_qpl[$key] = $value . " ($count " . $this->lng->txt("ass_questions") . ")";
			}
		}
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_random_questions.html", true);
		$found_qpls = array();
		if (count($_POST) == 0)
		{
			$found_qpls = $this->object->getRandomQuestionpools();
		}
		if (count($found_qpls) == 0)
		{
			if (!array_key_exists("countqpl_0", $_POST))
			{
				// create first questionpool row automatically
				foreach ($available_qpl as $key => $value)
				{
					$this->tpl->setCurrentBlock("qpl_value");
					$this->tpl->setVariable("QPL_ID", $key);
					$this->tpl->setVariable("QPL_TEXT", $value);
					$this->tpl->parseCurrentBlock();
				}
				$this->tpl->setCurrentBlock("questionpool_row");
				$this->tpl->setVariable("COUNTQPL", "0");
				$this->tpl->setVariable("VALUE_COUNTQPL", $_POST["countqpl_0"]);
				$this->tpl->setVariable("TEXT_SELECT_QUESTIONPOOL", $this->lng->txt("select_questionpool_option"));
				$this->tpl->setVariable("TEXT_QUESTIONS_FROM", $this->lng->txt("questions_from"));
				$this->tpl->parseCurrentBlock();
			}
		}
		$qpl_unselected = 0;
		foreach ($_POST as $key => $value)
		{
			if (preg_match("/countqpl_(\d+)/", $key, $matches))
			{
				$found_qpls[$matches[1]] = array(
					"index" => $matches[1],
					"count" => sprintf("%d", $value),
					"qpl"   => $_POST["qpl_" . $matches[1]]
				);
				if ($_POST["qpl_" . $matches[1]] == -1)
				{
					$qpl_unselected = 1;
				}
			}
		}
		foreach ($_POST as $key => $value)
		{
			if (preg_match("/deleteqpl_(\d+)/", $key, $matches))
			{
				unset($found_qpls[$matches[1]]);
			}
		}
		sort($found_qpls);
		$found_qpls = array_values($found_qpls);
		$counter = 0;
		foreach ($found_qpls as $key => $value)
		{
			$pools = $available_qpl;
			foreach ($found_qpls as $pkey => $pvalue)
			{
				if ($pvalue["qpl"] != $value["qpl"])
				{
					unset($pools[$pvalue["qpl"]]);
				}
			}
			// create first questionpool row automatically
			foreach ($pools as $pkey => $pvalue)
			{
				$this->tpl->setCurrentBlock("qpl_value");
				$this->tpl->setVariable("QPL_ID", $pkey);
				$this->tpl->setVariable("QPL_TEXT", $pvalue);
				if ($pkey == $value["qpl"])
				{
					$this->tpl->setVariable("SELECTED_QPL", " selected=\"selected\"");
				}
				$this->tpl->parseCurrentBlock();
			}
			$this->tpl->setCurrentBlock("questionpool_row");
			$this->tpl->setVariable("COUNTQPL", $counter);
			$this->tpl->setVariable("VALUE_COUNTQPL", $value["count"]);
			$this->tpl->setVariable("TEXT_SELECT_QUESTIONPOOL", $this->lng->txt("select_questionpool_option"));
			$this->tpl->setVariable("TEXT_QUESTIONS_FROM", $this->lng->txt("questions_from"));
			if (!$total)
			{
				if ($counter > 0)
				{
					$this->tpl->setVariable("BTNCOUNTQPL", $counter);
					$this->tpl->setVariable("BTN_DELETE", $this->lng->txt("delete"));
				}
			}
			$this->tpl->parseCurrentBlock();
			$counter++;
		}
		if ($_POST["cmd"]["addQuestionpool"])
		{
			if ($qpl_unselected)
			{
				sendInfo($this->lng->txt("tst_random_qpl_unselected"));
			}
			else
			{
				$pools = $available_qpl;
				foreach ($found_qpls as $pkey => $pvalue)
				{
					unset($pools[$pvalue["qpl"]]);
				}
				if (count($pools) == 0)
				{
					sendInfo($this->lng->txt("tst_no_more_available_questionpools"));
				}
				else
				{
					foreach ($pools as $key => $value)
					{
						$this->tpl->setCurrentBlock("qpl_value");
						$this->tpl->setVariable("QPL_ID", $key);
						$this->tpl->setVariable("QPL_TEXT", $value);
						$this->tpl->parseCurrentBlock();
					}
					$this->tpl->setCurrentBlock("questionpool_row");
					$this->tpl->setVariable("COUNTQPL", "$counter");
					$this->tpl->setVariable("TEXT_SELECT_QUESTIONPOOL", $this->lng->txt("select_questionpool_option"));
					$this->tpl->setVariable("TEXT_QUESTIONS_FROM", $this->lng->txt("questions_from"));
					$this->tpl->parseCurrentBlock();
				}
			}
		}
		if ($_POST["cmd"]["save"])
		{
			$this->object->saveRandomQuestionCount($_POST["total_questions"]);
			$this->object->saveRandomQuestionpools($found_qpls);
			$this->object->saveCompleteStatus();
		}
		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("TEXT_SELECT_RANDOM_QUESTIONS", $this->lng->txt("tst_select_random_questions"));
		$this->tpl->setVariable("TEXT_TOTAL_QUESTIONS", $this->lng->txt("tst_total_questions"));
		$this->tpl->setVariable("TEXT_TOTAL_QUESTIONS_DESCRIPTION", $this->lng->txt("tst_total_questions_description"));
		$total_questions = $this->object->getRandomQuestionCount();
		if (array_key_exists("total_questions", $_POST))
		{
			$total_questions = $_POST["total_questions"];
		}
		$this->tpl->setVariable("VALUE_TOTAL_QUESTIONS", $total_questions);
		$this->tpl->setVariable("TEXT_QUESTIONPOOLS", $this->lng->txt("tst_random_questionpools"));
		if (!$total)
		{
			$this->tpl->setVariable("BTN_SAVE", $this->lng->txt("save"));
			$this->tpl->setVariable("BTN_ADD_QUESTIONPOOL", $this->lng->txt("add_questionpool"));
		}
		$this->tpl->setVariable("FORM_ACTION", $this->getCallingScript() . $add_parameter);
		$this->tpl->parseCurrentBlock();
	}

	function browseForQuestionsObject()
	{
		$this->questionBrowser();
	}
	
	/**
	* Called when a new question should be created from a test after confirmation
	*
	* Called when a new question should be created from a test after confirmation
	*
	* @access	public
	*/
	function executeCreateQuestionObject()
	{
		$qpl_ref_id = $_POST["sel_qpl"];
		if ((strcmp($_POST["txt_qpl"], "") == 0) && (strcmp($qpl_ref_id, "") == 0))
		{
			sendInfo($this->lng->txt("questionpool_not_entered"));
			$this->createQuestionObject();
			return;
		}
		else
		{
			$_SESSION["test_id"] = $this->object->getRefId();
			if (strcmp($_POST["txt_qpl"], "") != 0)
			{
				// create a new question pool and return the reference id
				$qpl_ref_id = $this->createQuestionPool($_POST["txt_qpl"]);
			}
			ilUtil::redirect("questionpool.php?ref_id=" . $qpl_ref_id . "&cmd=createQuestionForTest&test_ref_id=".$_GET["ref_id"]."&sel_question_types=" . $_POST["sel_question_types"]);
			exit();
		}
	}

	/**
	* Called when the creation of a new question is cancelled
	*
	* Called when the creation of a new question is cancelled
	*
	* @access	public
	*/
	function cancelCreateQuestionObject()
	{
		$this->ctrl->redirect($this, "questions");
	}

	/**
	* Called when a new question should be created from a test
	*
	* Called when a new question should be created from a test
	*
	* @access	public
	*/
	function createQuestionObject()
	{
		global $ilUser;
		$add_parameter = $this->getAddParameter();
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_qpl_select.html", true);
		$questionpools =& $this->object->getAvailableQuestionpools();
		if (count($questionpools) == 0)
		{
			$this->tpl->setCurrentBlock("option");
			$this->tpl->setVariable("VALUE_QPL", "");
			$this->tpl->parseCurrentBlock();
		}
		else
		{
			foreach ($questionpools as $key => $value)
			{
				$this->tpl->setCurrentBlock("option");
				$this->tpl->setVariable("VALUE_OPTION", $key);
				$this->tpl->setVariable("TEXT_OPTION", $value);
				$this->tpl->parseCurrentBlock();
			}
		}
		$this->tpl->setCurrentBlock("hidden");
		$this->tpl->setVariable("HIDDEN_NAME", "sel_question_types");
		$this->tpl->setVariable("HIDDEN_VALUE", $_POST["sel_question_types"]);
		$this->tpl->parseCurrentBlock();
		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("FORM_ACTION", $this->ctrl->getFormAction($this));

		if (count($questionpools) == 0)
		{
			$this->tpl->setVariable("TXT_QPL_SELECT", $this->lng->txt("tst_enter_questionpool"));
		}
		else
		{
			$this->tpl->setVariable("TXT_QPL_SELECT", $this->lng->txt("tst_select_questionpool"));
		}
		$this->tpl->setVariable("BTN_SUBMIT", $this->lng->txt("submit"));
		$this->tpl->setVariable("BTN_CANCEL", $this->lng->txt("cancel"));
		$this->tpl->parseCurrentBlock();
	}

	/**
	* Remove questions from the test after confirmation
	*
	* Remove questions from the test after confirmation
	*
	* @access	public
	*/
	function confirmRemoveQuestionsObject()
	{
		sendInfo($this->lng->txt("tst_questions_removed"));
		$checked_questions = array();
		foreach ($_POST as $key => $value) {
			if (preg_match("/id_(\d+)/", $key, $matches)) {
				array_push($checked_questions, $matches[1]);
			}
		}
		foreach ($checked_questions as $key => $value) {
			$this->object->removeQuestion($value);
		}
		$this->object->saveCompleteStatus();
		$this->ctrl->redirect($this, "questions");
	}
	
	/**
	* Cancels the removal of questions from the test
	*
	* Cancels the removal of questions from the test
	*
	* @access	public
	*/
	function cancelRemoveQuestionsObject()
	{
		$this->ctrl->redirect($this, "questions");
	}
	
	/**
	* Displays a form to confirm the removal of questions from the test
	*
	* Displays a form to confirm the removal of questions from the test
	*
	* @access	public
	*/
	function removeQuestionsForm($checked_questions)
	{
		sendInfo();
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_remove_questions.html", true);
		$query = sprintf("SELECT qpl_questions.*, qpl_question_type.type_tag FROM qpl_questions, qpl_question_type, tst_test_question WHERE qpl_questions.question_type_fi = qpl_question_type.question_type_id AND tst_test_question.test_fi = %s AND tst_test_question.question_fi = qpl_questions.question_id ORDER BY sequence",
			$this->ilias->db->quote($this->object->getTestId())
		);
		$query_result = $this->ilias->db->query($query);
		$colors = array("tblrow1", "tblrow2");
		$counter = 0;
		if ($query_result->numRows() > 0)
		{
			while ($data = $query_result->fetchRow(DB_FETCHMODE_OBJECT))
			{
				if (in_array($data->question_id, $checked_questions))
				{
					$this->tpl->setCurrentBlock("row");
					$this->tpl->setVariable("COLOR_CLASS", $colors[$counter % 2]);
					$this->tpl->setVariable("TXT_TITLE", $data->title);
					$this->tpl->setVariable("TXT_DESCRIPTION", $data->comment);
					$this->tpl->setVariable("TXT_TYPE", $this->lng->txt($data->type_tag));
					$this->tpl->parseCurrentBlock();
					$counter++;
				}
			}
		}
		foreach ($checked_questions as $id)
		{
			$this->tpl->setCurrentBlock("hidden");
			$this->tpl->setVariable("HIDDEN_NAME", "id_$id");
			$this->tpl->setVariable("HIDDEN_VALUE", "1");
			$this->tpl->parseCurrentBlock();
		}

		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("TXT_TITLE", $this->lng->txt("tst_question_title"));
		$this->tpl->setVariable("TXT_DESCRIPTION", $this->lng->txt("description"));
		$this->tpl->setVariable("TXT_TYPE", $this->lng->txt("tst_question_type"));
		$this->tpl->setVariable("BTN_CONFIRM", $this->lng->txt("confirm"));
		$this->tpl->setVariable("BTN_CANCEL", $this->lng->txt("cancel"));
		$this->tpl->setVariable("FORM_ACTION", $this->ctrl->getFormAction($this));
		$this->tpl->parseCurrentBlock();
	}

	/**
	* Called when a selection of questions should be removed from the test
	*
	* Called when a selection of questions should be removed from the test
	*
	* @access	public
	*/
	function removeQuestionsObject()
	{
		$checked_questions = array();
		foreach ($_POST as $key => $value) {
			if (preg_match("/cb_(\d+)/", $key, $matches)) {
				array_push($checked_questions, $matches[1]);
			}
		}
		if (count($checked_questions) > 0) {
			$total = $this->object->evalTotalPersons();
			if ($total) {
				// the test was executed previously
				sendInfo(sprintf($this->lng->txt("tst_remove_questions_and_results"), $total));
			} else {
				sendInfo($this->lng->txt("tst_remove_questions"));
			}
			$this->removeQuestionsForm($checked_questions);
			return;
		} elseif (count($checked_questions) == 0) {
			sendInfo($this->lng->txt("tst_no_question_selected_for_removal"), true);
			$this->ctrl->redirect($this, "questions");
		}
	}
	
	/**
	* Marks selected questions for moving
	*
	* Marks selected questions for moving
	*
	* @access	public
	*/
	function moveQuestionsObject()
	{
		$this->questionsObject();
	}
	
	/**
	* Insert checked questions before the actual selection
	*
	* Insert checked questions before the actual selection
	*
	* @access	public
	*/
	function insertQuestionsBeforeObject()
	{
		// get all questions to move
		$move_questions = array();
		foreach ($_POST as $key => $value)
		{
			if (preg_match("/^move_(\d+)$/", $key, $matches))
			{
				array_push($move_questions, $value);
			}
		}
		// get insert point
		$insert_id = -1;
		foreach ($_POST as $key => $value)
		{
			if (preg_match("/^cb_(\d+)$/", $key, $matches))
			{
				if ($insert_id < 0)
				{
					$insert_id = $matches[1];
				}
			}
		}
		if ($insert_id <= 0)
		{
			sendInfo($this->lng->txt("no_target_selected_for_move"), true);
		}
		else
		{
			$insert_mode = 0;
			$this->object->moveQuestions($move_questions, $insert_id, $insert_mode);
		}
		$this->ctrl->redirect($this, "questions");
	}
	
	/**
	* Insert checked questions after the actual selection
	*
	* Insert checked questions after the actual selection
	*
	* @access	public
	*/
	function insertQuestionsAfterObject()
	{
		// get all questions to move
		$move_questions = array();
		foreach ($_POST as $key => $value)
		{
			if (preg_match("/^move_(\d+)$/", $key, $matches))
			{
				array_push($move_questions, $value);
			}
		}
		// get insert point
		$insert_id = -1;
		foreach ($_POST as $key => $value)
		{
			if (preg_match("/^cb_(\d+)$/", $key, $matches))
			{
				if ($insert_id < 0)
				{
					$insert_id = $matches[1];
				}
			}
		}
		if ($insert_id <= 0)
		{
			sendInfo($this->lng->txt("no_target_selected_for_move"), true);
		}
		else
		{
			$insert_mode = 1;
			$this->object->moveQuestions($move_questions, $insert_id, $insert_mode);
		}
		$this->ctrl->redirect($this, "questions");
	}
	
	function questionsObject()
	{
		global $rbacsystem;

		if ((!$rbacsystem->checkAccess("read", $this->ref_id)) && (!$rbacsystem->checkAccess("write", $this->ref_id))) 
		{
			// allow only read and write access
			sendInfo($this->lng->txt("cannot_edit_test"), true);
			$path = $this->tree->getPathFull($this->object->getRefID());
			ilUtil::redirect($this->getReturnLocation("cancel","../repository.php?cmd=frameset&ref_id=" . $path[count($path) - 2]["child"]));
			return;
		}

		if ($this->object->isRandomTest())
		{
			$this->randomQuestionsObject();
			return;
		}
		
		$add_parameter = $this->getAddParameter();

		if ($_GET["eqid"] and $_GET["eqpl"])
		{
			ilUtil::redirect("questionpool.php?ref_id=" . $_GET["eqpl"] . "&cmd=editQuestionForTest&calling_test=".$_GET["ref_id"]."&q_id=" . $_GET["eqid"]);
		}
		
		if ($_GET["up"] > 0)
		{
			$this->object->questionMoveUp($_GET["up"]);
		}
		if ($_GET["down"] > 0)
		{
			$this->object->questionMoveDown($_GET["down"]);
		}

		if ($_GET["add"])
		{
			$selected_array = array();
			array_push($selected_array, $_GET["add"]);
			$total = $this->object->evalTotalPersons();
			if ($total)
			{
				// the test was executed previously
				sendInfo(sprintf($this->lng->txt("tst_insert_questions_and_results"), $total));
			}
			else
			{
				sendInfo($this->lng->txt("tst_insert_questions"));
			}
			$this->insertQuestions($selected_array);
			return;
		}

		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_questions.html", true);
		$this->tpl->addBlockFile("A_BUTTONS", "question_buttons", "tpl.il_as_tst_question_buttons.html", true);

		if (strcmp($this->ctrl->getCmd(), "moveQuestions") == 0)
		{
			$checked_move = 0;
			foreach ($_POST as $key => $value)
			{
				if (preg_match("/cb_(\d+)/", $key, $matches))
				{
					$checked_move++;
					$this->tpl->setCurrentBlock("move");
					$this->tpl->setVariable("MOVE_COUNTER", $matches[1]);
					$this->tpl->setVariable("MOVE_VALUE", $matches[1]);
					$this->tpl->parseCurrentBlock();
				}
			}
			if ($checked_move)
			{
				sendInfo($this->lng->txt("select_target_position_for_move_question"));
				$this->tpl->setCurrentBlock("move_buttons");
				$this->tpl->setVariable("INSERT_BEFORE", $this->lng->txt("insert_before"));
				$this->tpl->setVariable("INSERT_AFTER", $this->lng->txt("insert_after"));
				$this->tpl->parseCurrentBlock();
			}
			else
			{
				sendInfo($this->lng->txt("no_question_selected_for_move"));
			}
		}
		
		$query = sprintf("SELECT qpl_questions.*, qpl_question_type.type_tag FROM qpl_questions, qpl_question_type, tst_test_question WHERE qpl_questions.question_type_fi = qpl_question_type.question_type_id AND tst_test_question.test_fi = %s AND tst_test_question.question_fi = qpl_questions.question_id ORDER BY sequence",
			$this->ilias->db->quote($this->object->getTestId())
		);
		$query_result = $this->ilias->db->query($query);
		$colors = array("tblrow1", "tblrow2");
		$counter = 0;
		$questionpools =& $this->object->get_qpl_titles();
		$total = $this->object->evalTotalPersons();
		if ($query_result->numRows() > 0)
		{
			while ($data = $query_result->fetchRow(DB_FETCHMODE_OBJECT))
			{
				$this->tpl->setCurrentBlock("QTab");
				$this->tpl->setVariable("QUESTION_ID", $data->question_id);
				if (($rbacsystem->checkAccess("write", $this->ref_id) and ($total == 0))) {
					$q_id = $data->question_id;
					$qpl_ref_id = $this->object->_getRefIdFromObjId($data->obj_fi);
					$this->tpl->setVariable("QUESTION_TITLE", "<a href=\"" . $this->getCallingScript() . $add_parameter . "&eqid=$q_id&eqpl=$qpl_ref_id" . "\">" . $data->title . "</a>");
				} else {
					$this->tpl->setVariable("QUESTION_TITLE", $data->title);
				}
				$this->tpl->setVariable("QUESTION_SEQUENCE", $this->lng->txt("tst_sequence"));

				if (($rbacsystem->checkAccess("write", $this->ref_id) and ($total == 0))) {
					if ($data->question_id != $this->object->questions[1])
					{
						$this->tpl->setVariable("BUTTON_UP", "<a href=\"" . $this->ctrl->getFormAction($this) . "&up=$data->question_id\"><img src=\"" . ilUtil::getImagePath("a_up.gif") . "\" alt=\"" . $this->lng->txt("up") . "\" border=\"0\" /></a>");
					}
					if ($data->question_id != $this->object->questions[count($this->object->questions)])
					{
						$this->tpl->setVariable("BUTTON_DOWN", "<a href=\"" . $this->ctrl->getFormAction($this) . "&down=$data->question_id\"><img src=\"" . ilUtil::getImagePath("a_down.gif") . "\" alt=\"" . $this->lng->txt("down") . "\" border=\"0\" /></a>");
					}
				}
				$this->tpl->setVariable("QUESTION_COMMENT", $data->comment);
				$this->tpl->setVariable("QUESTION_TYPE", $this->lng->txt($data->type_tag));
				$this->tpl->setVariable("QUESTION_AUTHOR", $data->author);
				$this->tpl->setVariable("QUESTION_POOL", $questionpools[$data->obj_fi]);
				$this->tpl->setVariable("COLOR_CLASS", $colors[$counter % 2]);
				$this->tpl->parseCurrentBlock();
				$counter++;
			}
		}
		if ($counter == 0) 
		{
			$this->tpl->setCurrentBlock("Emptytable");
			$this->tpl->setVariable("TEXT_EMPTYTABLE", $this->lng->txt("tst_no_questions_available"));
			$this->tpl->parseCurrentBlock();
		} 
		else 
		{
			if (($rbacsystem->checkAccess("write", $this->ref_id) and ($total == 0))) 
			{
				$this->tpl->setCurrentBlock("selectall");
				$this->tpl->setVariable("SELECT_ALL", $this->lng->txt("select_all"));
				$counter++;
				$this->tpl->setVariable("COLOR_CLASS", $colors[$counter % 2]);
				$this->tpl->parseCurrentBlock();
				$this->tpl->setCurrentBlock("QFooter");
				$this->tpl->setVariable("ARROW", "<img src=\"" . ilUtil::getImagePath("arrow_downright.gif") . "\" alt=\"\">");
				$this->tpl->setVariable("REMOVE", $this->lng->txt("remove_question"));
				$this->tpl->setVariable("MOVE", $this->lng->txt("move"));
				$this->tpl->parseCurrentBlock();
			}
		}

		if (($rbacsystem->checkAccess("write", $this->ref_id) and ($total == 0))) {
			$this->tpl->setCurrentBlock("QTypes");
			$query = "SELECT * FROM qpl_question_type";
			$query_result = $this->ilias->db->query($query);
			while ($data = $query_result->fetchRow(DB_FETCHMODE_OBJECT))
			{
				$this->tpl->setVariable("QUESTION_TYPE_ID", $data->type_tag);
				$this->tpl->setVariable("QUESTION_TYPE", $this->lng->txt($data->type_tag));
				$this->tpl->parseCurrentBlock();
			}
			$this->tpl->parseCurrentBlock();
		}
		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("ACTION_QUESTION_FORM", $this->ctrl->getFormAction($this));
		$this->tpl->setVariable("QUESTION_TITLE", $this->lng->txt("tst_question_title"));
		$this->tpl->setVariable("QUESTION_COMMENT", $this->lng->txt("description"));
		$this->tpl->setVariable("QUESTION_TYPE", $this->lng->txt("tst_question_type"));
		$this->tpl->setVariable("QUESTION_AUTHOR", $this->lng->txt("author"));
		$this->tpl->setVariable("QUESTION_POOL", $this->lng->txt("qpl"));

		if (($rbacsystem->checkAccess("write", $this->ref_id) and ($total == 0))) {
			$this->tpl->setVariable("BUTTON_INSERT_QUESTION", $this->lng->txt("tst_browse_for_questions"));
			$this->tpl->setVariable("TEXT_CREATE_NEW", " " . strtolower($this->lng->txt("or")) . " " . $this->lng->txt("create_new"));
			$this->tpl->setVariable("BUTTON_CREATE_QUESTION", $this->lng->txt("create"));
			$this->tpl->setVariable("TXT_OR", $this->lng->txt("or"));
			$this->tpl->setVariable("TEXT_RANDOM_SELECT", $this->lng->txt("random_selection"));
		}

		$this->tpl->parseCurrentBlock();
	}

	function takenObject() {
	}
	
	/**
	* Add a new mark step to the tests marks
	*
	* Add a new mark step to the tests marks
	*
	* @access	public
	*/
	function addMarkStepObject()
	{
		$this->saveMarkSchemaFormData();
		$this->object->mark_schema->add_mark_step();
		$this->marksObject();
	}

	/**
	* Save the mark schema POST data when the form was submitted
	*
	* Save the mark schema POST data when the form was submitted
	*
	* @access	public
	*/
	function saveMarkSchemaFormData()
	{
		$this->object->mark_schema->flush();
		foreach ($_POST as $key => $value) {
			if (preg_match("/mark_short_(\d+)/", $key, $matches)) {
				$this->object->mark_schema->add_mark_step($_POST["mark_short_$matches[1]"], $_POST["mark_official_$matches[1]"], $_POST["mark_percentage_$matches[1]"], $_POST["passed_$matches[1]"]);
			}
		}
		$this->object->ects_grades["A"] = $_POST["ects_grade_a"];
		$this->object->ects_grades["B"] = $_POST["ects_grade_b"];
		$this->object->ects_grades["C"] = $_POST["ects_grade_c"];
		$this->object->ects_grades["D"] = $_POST["ects_grade_d"];
		$this->object->ects_grades["E"] = $_POST["ects_grade_e"];
		if ($_POST["chbUseFX"])
		{
			$this->object->ects_fx = $_POST["percentFX"];
		}
		else
		{
			$this->object->ects_fx = "";
		}
		$this->object->ects_output = $_POST["chbECTS"];
	}
	
	/**
	* Add a simple mark schema to the tests marks
	*
	* Add a simple mark schema to the tests marks
	*
	* @access	public
	*/
	function addSimpleMarkSchemaObject()
	{
		$this->object->mark_schema->create_simple_schema($this->lng->txt("failed_short"), $this->lng->txt("failed_official"), 0, 0, $this->lng->txt("passed_short"), $this->lng->txt("passed_official"), 50, 1);
		$this->marksObject();
	}
	
	/**
	* Delete selected mark steps
	*
	* Delete selected mark steps
	*
	* @access	public
	*/
	function deleteMarkStepsObject()
	{
		$this->saveMarkSchemaFormData();
		$delete_mark_steps = array();
		foreach ($_POST as $key => $value) {
			if (preg_match("/cb_(\d+)/", $key, $matches)) {
				array_push($delete_mark_steps, $matches[1]);
			}
		}
		if (count($delete_mark_steps)) {
			$this->object->mark_schema->delete_mark_steps($delete_mark_steps);
		} else {
			sendInfo($this->lng->txt("tst_delete_missing_mark"));
		}
		$this->marksObject();
	}

	/**
	* Cancel the mark schema form and return to the properties form
	*
	* Cancel the mark schema form and return to the properties form
	*
	* @access	public
	*/
	function cancelMarksObject()
	{
		sendInfo($this->lng->txt("msg_cancel"), true);
		$this->ctrl->redirect($this, "properties");
	}
	
	/**
	* Save the mark schema
	*
	* Save the mark schema
	*
	* @access	public
	*/
	function saveMarksObject()
	{
		$this->saveMarkSchemaFormData();
		
		$mark_check = $this->object->checkMarks();
		if ($mark_check !== true)
		{
			sendInfo($this->lng->txt($mark_check));
		}
		elseif ($_POST["chbECTS"] && ((strcmp($_POST["ects_grade_a"], "") == 0) or (strcmp($_POST["ects_grade_b"], "") == 0) or (strcmp($_POST["ects_grade_c"], "") == 0) or (strcmp($_POST["ects_grade_d"], "") == 0) or (strcmp($_POST["ects_grade_e"], "") == 0)))
		{
			sendInfo($this->lng->txt("ects_fill_out_all_values"), true);
		}
		elseif (($_POST["ects_grade_a"] > 100) or ($_POST["ects_grade_a"] < 0))
		{
			sendInfo($this->lng->txt("ects_range_error_a"), true);
		}
		elseif (($_POST["ects_grade_b"] > 100) or ($_POST["ects_grade_b"] < 0))
		{
			sendInfo($this->lng->txt("ects_range_error_b"), true);
		}
		elseif (($_POST["ects_grade_c"] > 100) or ($_POST["ects_grade_c"] < 0))
		{
			sendInfo($this->lng->txt("ects_range_error_c"), true);
		}
		elseif (($_POST["ects_grade_d"] > 100) or ($_POST["ects_grade_d"] < 0))
		{
			sendInfo($this->lng->txt("ects_range_error_d"), true);
		}
		elseif (($_POST["ects_grade_e"] > 100) or ($_POST["ects_grade_e"] < 0))
		{
			sendInfo($this->lng->txt("ects_range_error_e"), true);
		}
		else 
		{
			$this->object->mark_schema->saveToDb($this->object->getTestId());
			$this->object->saveCompleteStatus();
			if ($this->object->getReportingDate())
			{
				$fxpercent = "";
				if ($_POST["chbUseFX"])
				{
					$fxpercent = ilUtil::stripSlashes($_POST["percentFX"]);
				}
				$this->object->saveECTSStatus($_POST["chbECTS"], $fxpercent, $this->object->ects_grades["A"], $this->object->ects_grades["B"], $this->object->ects_grades["C"], $this->object->ects_grades["D"], $this->object->ects_grades["E"]);
			}
			sendInfo($this->lng->txt("msg_obj_modified"), true);
		}
		$this->marksObject();
	}
	
	function marksObject() {
		global $rbacsystem;

		if ((!$rbacsystem->checkAccess("read", $this->ref_id)) && (!$rbacsystem->checkAccess("write", $this->ref_id))) 
		{
			// allow only read and write access
			sendInfo($this->lng->txt("cannot_edit_test"), true);
			$path = $this->tree->getPathFull($this->object->getRefID());
			ilUtil::redirect($this->getReturnLocation("cancel","../repository.php?cmd=frameset&ref_id=" . $path[count($path) - 2]["child"]));
			return;
		}

		$this->object->mark_schema->sort();
	
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_marks.html", true);
		$marks = $this->object->mark_schema->mark_steps;
		$rows = array("tblrow1", "tblrow2");
		$counter = 0;
		foreach ($marks as $key => $value) {
			$this->tpl->setCurrentBlock("markrow");
			$this->tpl->setVariable("MARK_SHORT", $value->get_short_name());
			$this->tpl->setVariable("MARK_OFFICIAL", $value->get_official_name());
			$this->tpl->setVariable("MARK_PERCENTAGE", sprintf("%.2f", $value->get_minimum_level()));
			$this->tpl->setVariable("MARK_PASSED", strtolower($this->lng->txt("tst_mark_passed")));
			$this->tpl->setVariable("MARK_ID", "$key");
			$this->tpl->setVariable("ROW_CLASS", $rows[$counter % 2]);
			if ($value->get_passed()) {
				$this->tpl->setVariable("MARK_PASSED_CHECKED", " checked=\"checked\"");
			}
			$this->tpl->parseCurrentBlock();
			$counter++;
		}
		if (count($marks) == 0) 
		{
			$this->tpl->setCurrentBlock("Emptyrow");
			$this->tpl->setVariable("EMPTY_ROW", $this->lng->txt("tst_no_marks_defined"));
			$this->tpl->setVariable("ROW_CLASS", $rows[$counter % 2]);
			$this->tpl->parseCurrentBlock();
		} 
		else 
		{
			if ($rbacsystem->checkAccess("write", $this->ref_id)) {
				$this->tpl->setCurrentBlock("selectall");
				$counter++;
				$this->tpl->setVariable("ROW_CLASS", $rows[$counter % 2]);
				$this->tpl->setVariable("SELECT_ALL", $this->lng->txt("select_all"));
				$this->tpl->parseCurrentBlock();
				$this->tpl->setCurrentBlock("Footer");
				$this->tpl->setVariable("ARROW", "<img src=\"" . ilUtil::getImagePath("arrow_downright.gif") . "\" alt=\"\">");
				$this->tpl->setVariable("BUTTON_EDIT", $this->lng->txt("edit"));
				$this->tpl->setVariable("BUTTON_DELETE", $this->lng->txt("delete"));
				$this->tpl->parseCurrentBlock();
			}
		}
		
		if ($this->object->getReportingDate())
		{
			$this->tpl->setCurrentBlock("ects");
			if ($this->object->ects_output)
			{
				$this->tpl->setVariable("CHECKED_ECTS", " checked=\"checked\"");
			}
			$this->tpl->setVariable("TEXT_OUTPUT_ECTS_GRADES", $this->lng->txt("ects_output_of_ects_grades"));
			$this->tpl->setVariable("TEXT_ALLOW_ECTS_GRADES", $this->lng->txt("ects_allow_ects_grades"));
			$this->tpl->setVariable("TEXT_USE_FX", $this->lng->txt("ects_use_fx_grade"));
			if (preg_match("/\d+/", $this->object->ects_fx))
			{
				$this->tpl->setVariable("CHECKED_FX", " checked=\"checked\"");
				$this->tpl->setVariable("VALUE_PERCENT_FX", sprintf("value=\"%s\" ", $this->object->ects_fx));
			}
			$this->tpl->setVariable("TEXT_PERCENT", $this->lng->txt("ects_use_fx_grade_part2"));
			$this->tpl->setVariable("ECTS_GRADE", $this->lng->txt("ects_grade"));
			$this->tpl->setVariable("PERCENTILE", $this->lng->txt("percentile"));
			$this->tpl->setVariable("ECTS_GRADE_A", "A - " . $this->lng->txt("ects_grade_a_short"));
			$this->tpl->setVariable("VALUE_GRADE_A", $this->object->ects_grades["A"]);
			$this->tpl->setVariable("ECTS_GRADE_B", "B - " . $this->lng->txt("ects_grade_b_short"));
			$this->tpl->setVariable("VALUE_GRADE_B", $this->object->ects_grades["B"]);
			$this->tpl->setVariable("ECTS_GRADE_C", "C - " . $this->lng->txt("ects_grade_c_short"));
			$this->tpl->setVariable("VALUE_GRADE_C", $this->object->ects_grades["C"]);
			$this->tpl->setVariable("ECTS_GRADE_D", "D - " . $this->lng->txt("ects_grade_d_short"));
			$this->tpl->setVariable("VALUE_GRADE_D", $this->object->ects_grades["D"]);
			$this->tpl->setVariable("ECTS_GRADE_E", "E - " . $this->lng->txt("ects_grade_e_short"));
			$this->tpl->setVariable("VALUE_GRADE_E", $this->object->ects_grades["E"]);
			
			$this->tpl->parseCurrentBlock();
		}

		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("ACTION_MARKS", $this->ctrl->getFormAction($this));
		$this->tpl->setVariable("HEADER_SHORT", $this->lng->txt("tst_mark_short_form"));
		$this->tpl->setVariable("HEADER_OFFICIAL", $this->lng->txt("tst_mark_official_form"));
		$this->tpl->setVariable("HEADER_PERCENTAGE", $this->lng->txt("tst_mark_minimum_level"));
		$this->tpl->setVariable("HEADER_PASSED", $this->lng->txt("tst_mark_passed"));
		if ($rbacsystem->checkAccess("write", $this->ref_id)) {
			$this->tpl->setVariable("BUTTON_NEW", $this->lng->txt("tst_mark_create_new_mark_step"));
			$this->tpl->setVariable("BUTTON_NEW_SIMPLE", $this->lng->txt("tst_mark_create_simple_mark_schema"));
			$this->tpl->setVariable("SAVE", $this->lng->txt("save"));
			$this->tpl->setVariable("CANCEL", $this->lng->txt("cancel"));
		}
		$this->tpl->parseCurrentBlock();
	}

	function outEvaluationForm()
	{
		global $ilUser;

		include_once("classes/class.ilObjStyleSheet.php");
		$this->tpl->setCurrentBlock("ContentStyle");
		$this->tpl->setVariable("LOCATION_CONTENT_STYLESHEET", ilObjStyleSheet::getContentStylePath(0));
		$this->tpl->parseCurrentBlock();

		// syntax style
		$this->tpl->setCurrentBlock("SyntaxStyle");
		$this->tpl->setVariable("LOCATION_SYNTAX_STYLESHEET",
			ilObjStyleSheet::getSyntaxStylePath());
		$this->tpl->parseCurrentBlock();

		$test_id = $this->object->getTestId();
		$question_gui = $this->object->createQuestionGUI("", $_GET["evaluation"]);
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_evaluation.html", true);
		$formaction = $this->getCallingScript() . $this->getAddParameter() . "&sequence=$sequence";
		
		switch ($question_gui->getQuestionType())
		{
			case "qt_imagemap":
				$question_gui->outWorkingForm($test_id, "", 1, $formaction);
				break;
			case "qt_javaapplet":
				$question_gui->outWorkingForm("", "", 0);
				break;
			default:
				$question_gui->outWorkingForm($test_id, "", 1);
		}

		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("FORMACTION", $this->getCallingScript() . $this->getAddParameter());
		$this->tpl->setVariable("BACKLINK_TEXT", "&lt;&lt; " . $this->lng->txt("back"));
		$this->tpl->parseCurrentBlock();
	}

	/**
	* Asks for a confirmation to delete all user data of the test object
	*
	* Asks for a confirmation to delete all user data of the test object
	*
	* @access	public
	*/
	function deleteAllUserDataObject()
	{
		sendInfo($this->lng->txt("confirm_delete_all_user_data"));
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_maintenance.html", true);

		$this->tpl->setCurrentBlock("confirm_delete");
		$this->tpl->setVariable("BTN_CONFIRM_DELETE_ALL", $this->lng->txt("confirm"));
		$this->tpl->setVariable("BTN_CANCEL_DELETE_ALL", $this->lng->txt("cancel"));
		$this->tpl->parseCurrentBlock();

		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("FORM_ACTION", $this->ctrl->getFormAction($this));
		$this->tpl->parseCurrentBlock();
	}
	
	/**
	* Deletes all user data for the test object
	*
	* Deletes all user data for the test object
	*
	* @access	public
	*/
	function confirmDeleteAllUserDataObject()
	{
		$this->object->removeAllTestEditings();
		sendInfo($this->lng->txt("tst_all_user_data_deleted"), true);
		$this->ctrl->redirect($this, "maintenance");
	}
	
	/**
	* Cancels the deletion of all user data for the test object
	*
	* Cancels the deletion of all user data for the test object
	*
	* @access	public
	*/
	function cancelDeleteAllUserDataObject()
	{
		$this->ctrl->redirect($this, "maintenance");
	}
	
	/**
	* Create random solutions for the test object for every registered user
	*
	* Create random solutions for the test object for every registered user
	* NOTE: This method is only for debug and performance test reasons. Don't use it
	* in your productive system
	*
	* @access	public
	*/
	function createSolutionsObject()
	{
		$this->object->createRandomSolutionsForAllUsers();
		$this->ctrl->redirect($this, "maintenance");
	}

	/**
	* Creates the maintenance form for a test
	*
	* Creates the maintenance form for a test
	*
	* @access	public
	*/
	function maintenanceObject()
	{
		global $rbacsystem;

		if ((!$rbacsystem->checkAccess("read", $this->ref_id)) && (!$rbacsystem->checkAccess("write", $this->ref_id))) 
		{
			// allow only read and write access
			sendInfo($this->lng->txt("cannot_edit_test"), true);
			$path = $this->tree->getPathFull($this->object->getRefID());
			ilUtil::redirect($this->getReturnLocation("cancel","../repository.php?cmd=frameset&ref_id=" . $path[count($path) - 2]["child"]));
			return;
		}
		
		if ($rbacsystem->checkAccess("write", $this->ref_id)) {
			$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_maintenance.html", true);
			$this->tpl->setCurrentBlock("adm_content");
			$this->tpl->setVariable("BTN_DELETE_ALL", $this->lng->txt("tst_delete_all_user_data"));
//			$this->tpl->setVariable("BTN_CREATE_SOLUTIONS", $this->lng->txt("tst_create_solutions"));
			$this->tpl->setVariable("FORM_ACTION", $this->ctrl->getFormAction($this));
			$this->tpl->parseCurrentBlock();
		}
		else
		{
			sendInfo($this->lng->txt("cannot_maintain_test"));
		}
	}	

	/**
	* Creates the status output for a test
	*
	* Creates the status output for a test
	*
	* @access	public
	*/
	function statusObject()
	{
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_status.html", true);
		if (!$this->object->isComplete())
		{
			if (!$this->object->isRandomTest())
			{
				if (count($this->object->questions) == 0)
				{
					$this->tpl->setCurrentBlock("list_element");
					$this->tpl->setVariable("TEXT_ELEMENT", $this->lng->txt("tst_missing_questions"));
					$this->tpl->parseCurrentBlock();
				}
			}
			if (count($this->object->mark_schema->mark_steps) == 0)
			{
				$this->tpl->setCurrentBlock("list_element");
				$this->tpl->setVariable("TEXT_ELEMENT", $this->lng->txt("tst_missing_marks"));
				$this->tpl->parseCurrentBlock();
			}
			if (strcmp($this->object->author, "") == 0)
			{
				$this->tpl->setCurrentBlock("list_element");
				$this->tpl->setVariable("TEXT_ELEMENT", $this->lng->txt("tst_missing_author"));
				$this->tpl->parseCurrentBlock();
			}
			if (strcmp($this->object->title, "") == 0)
			{
				$this->tpl->setCurrentBlock("list_element");
				$this->tpl->setVariable("TEXT_ELEMENT", $this->lng->txt("tst_missing_author"));
				$this->tpl->parseCurrentBlock();
			}
			
			if ($this->object->isRandomTest())
			{
				$arr = $this->object->getRandomQuestionpools();
				if (count($arr) == 0)
				{
					$this->tpl->setCurrentBlock("list_element");
					$this->tpl->setVariable("TEXT_ELEMENT", $this->lng->txt("tst_no_questionpools_for_random_test"));
					$this->tpl->parseCurrentBlock();
				}
				$count = 0;
				foreach ($arr as $array)
				{
					$count += $array["count"];
				}
				if (($count == 0) && ($this->object->getRandomQuestionCount() == 0))
				{
					$this->tpl->setCurrentBlock("list_element");
					$this->tpl->setVariable("TEXT_ELEMENT", $this->lng->txt("tst_no_questions_for_random_test"));
					$this->tpl->parseCurrentBlock();
				}
			}
			
			$this->tpl->setCurrentBlock("status_list");
			$this->tpl->setVariable("TEXT_MISSING_ELEMENTS", $this->lng->txt("tst_status_missing_elements"));
			$this->tpl->parseCurrentBlock();
		}
		$total = $this->object->evalTotalPersons();
		if ($total > 0)
		{
			$this->tpl->setCurrentBlock("list_element");
			$this->tpl->setVariable("TEXT_ELEMENT", sprintf($this->lng->txt("tst_in_use_edit_questions_disabled"), $total));
			$this->tpl->parseCurrentBlock();
		}
		$this->tpl->setCurrentBlock("adm_content");
		if ($this->object->isComplete())
		{
			$this->tpl->setVariable("TEXT_STATUS_MESSAGE", $this->lng->txt("tst_status_ok"));
			$this->tpl->setVariable("STATUS_CLASS", "bold");
		}
		else
		{
			$this->tpl->setVariable("TEXT_STATUS_MESSAGE", $this->lng->txt("tst_status_missing"));
			$this->tpl->setVariable("STATUS_CLASS", "warning");
		}
		$this->tpl->parseCurrentBlock();
	}	

	/**
	* set Locator
	*
	* @param	object	tree object
	* @param	integer	reference id
	* @param	scriptanme that is used for linking; if not set adm_object.php is used
	* @access	public
	*/
	function setLocator($a_tree = "", $a_id = "", $scriptname="repository.php")
	{
//		global $ilias_locator;
		$ilias_locator = new ilLocatorGUI(false);
		if (!is_object($a_tree))
		{
			$a_tree =& $this->tree;
		}
		if (!($a_id))
		{
			$a_id = $_GET["ref_id"];
		}

		//$this->tpl->addBlockFile("LOCATOR", "locator", "tpl.locator.html");

		$path = $a_tree->getPathFull($a_id);
		//check if object isn't in tree, this is the case if parent_parent is set
		// TODO: parent_parent no longer exist. need another marker
		if ($a_parent_parent)
		{
			//$subObj = getObject($a_ref_id);
			$subObj =& $this->ilias->obj_factory->getInstanceByRefId($a_ref_id);

			$path[] = array(
				"id"	 => $a_ref_id,
				"title"  => $this->lng->txt($subObj->getTitle())
				);
		}

		// this is a stupid workaround for a bug in PEAR:IT
		$modifier = 1;

		if (isset($_GET["obj_id"]))
		{
			$modifier = 0;
		}

		// ### AA 03.11.10 added new locator GUI class ###
		$i = 1;
		if (!defined("ILIAS_MODULE")) {
			foreach ($path as $key => $row)
			{
				$ilias_locator->navigate($i++, $row["title"], 
										 ilUtil::removeTrailingPathSeparators(ILIAS_HTTP_PATH).
										 "/adm_object.php?ref_id=".$row["child"],"");
			}
		} else {
			
			// Workaround for crs_objectives
			$frameset = $_GET['crs_show_result'] ? '' : 'cmd=frameset&';

			foreach ($path as $key => $row)
			{
				if (strcmp($row["title"], "ILIAS") == 0) {
					$row["title"] = $this->lng->txt("repository");
				}
				if ($this->ref_id == $row["child"]) {
					if ($_GET["cmd"]) {
						$param = "&cmd=" . $_GET["cmd"];
					} else {
						$param = "";
					}
					$ilias_locator->navigate($i++, $row["title"], 
											 ilUtil::removeTrailingPathSeparators(ILIAS_HTTP_PATH) . 
											 "/assessment/test.php" . "?crs_show_result=".$_GET['crs_show_result'].
											 "&ref_id=".$row["child"] . $param,"");

					if ($this->sequence) {
						if (($this->sequence <= $this->object->getQuestionCount()) and (!$_POST["cmd"]["showresults"])) {
							$ilias_locator->navigate($i++, $this->object->getQuestionTitle($this->sequence), 
													 ilUtil::removeTrailingPathSeparators(ILIAS_HTTP_PATH) . 
													 "/assessment/test.php" . "?crs_show_result=".$_GET['crs_show_result'].
													 "&ref_id=".$row["child"] . $param . 
													 "&sequence=" . $this->sequence,"");
						} else {
						}
						/*if ($_POST["cmd"]["summary"] or isset($_GET["sort_summary"]))
						{
						$ilias_locator->navigate($i++, $this->lng->txt("summary"), 
													 ilUtil::removeTrailingPathSeparators(ILIAS_HTTP_PATH) . 
													 "/assessment/test.php" . "?crs_show_result=0".
													 "&ref_id=".$row["child"] . $param . 
												 "&sequence=" . $_GET["sequence"]."&order=".$_GET["order"]."&sort_summary=".$_GET["sort_summary"],"");
						}*/
					} else {
						if ($_POST["cmd"]["summary"] or isset($_GET["sort_summary"]))
						{
							$ilias_locator->navigate($i++, $this->lng->txt("summary"), 
													 ilUtil::removeTrailingPathSeparators(ILIAS_HTTP_PATH) . 
													 "/assessment/test.php" . "?crs_show_result=0".
													 "&ref_id=".$row["child"] . $param . 
												 "&sequence=" . $_GET["sequence"]."&order=".$_GET["order"]."&sort_summary=".$_GET["sort_summary"],"");
						}/* elseif ($_POST["cmd"]["show_answers"])
						{
							$ilias_locator->navigate($i++, $this->lng->txt("preview"), 
													 ilUtil::removeTrailingPathSeparators(ILIAS_HTTP_PATH) . 
													 "/assessment/test.php" . "?crs_show_result=0".
													 "&ref_id=".$row["child"] . $param . 
												 "&sequence=" . $_GET["sequence"]."&order=".$_GET["order"]."&sort_summary=".$_GET["sort_summary"],"");
							
						}						
						elseif ($_POST["cmd"]["submit_answers"])
						{
							$ilias_locator->navigate($i++, $this->lng->txt("submit"), 
													 ilUtil::removeTrailingPathSeparators(ILIAS_HTTP_PATH) . 
													 "/assessment/test.php" . "?crs_show_result=0".
													 "&ref_id=".$row["child"] . $param . 
												 "&sequence=" . $_GET["sequence"]."&order=".$_GET["order"]."&sort_summary=".$_GET["sort_summary"],"");
							
						}*/
					}
				} else {
					$ilias_locator->navigate($i++, $row["title"], 
											 ilUtil::removeTrailingPathSeparators(ILIAS_HTTP_PATH) . "/" . 
											 $scriptname."?".$frameset."ref_id=".$row["child"],"");
				}
			}

			if (isset($_GET["obj_id"]))
			{
				$obj_data =& $this->ilias->obj_factory->getInstanceByObjId($_GET["obj_id"]);
				$ilias_locator->navigate($i++,$obj_data->getTitle(),
										 $scriptname."?".$frameset."ref_id=".$_GET["ref_id"]."&obj_id=".$_GET["obj_id"],"");
			}
		}
		$ilias_locator->output();
	}

	/**
	* form for new content object creation
	*/
	function createObject()
	{
		global $rbacsystem;
		$new_type = $_POST["new_type"] ? $_POST["new_type"] : $_GET["new_type"];
		if (!$rbacsystem->checkAccess("create", $_GET["ref_id"], $new_type))
		{
			$this->ilias->raiseError($this->lng->txt("permission_denied"),$this->ilias->error_obj->MESSAGE);
		}
		else
		{
			$this->getTemplateFile("create", $new_type);

			include_once("./assessment/classes/class.ilObjTest.php");
			$tst = new ilObjTest();
			
			$tests =& ilObjTest::_getAvailableTests(true);
			if (count($tests) > 0)
			{
				foreach ($tests as $key => $value)
				{
					$this->tpl->setCurrentBlock("option_tst");
					$this->tpl->setVariable("OPTION_VALUE_TST", $key);
					$this->tpl->setVariable("TXT_OPTION_TST", $value);
					if ($_POST["tst"] == $key)
					{
						$this->tpl->setVariable("OPTION_SELECTED_TST", " selected=\"selected\"");				
					}
					$this->tpl->parseCurrentBlock();
				}
			}
			
			$questionpools =& $tst->getAvailableQuestionpools(true);
			if (count($questionpools) == 0)
			{
			}
			else
			{
				foreach ($questionpools as $key => $value)
				{
					$this->tpl->setCurrentBlock("option_qpl");
					$this->tpl->setVariable("OPTION_VALUE", $key);
					$this->tpl->setVariable("TXT_OPTION", $value);
					if ($_POST["qpl"] == $key)
					{
						$this->tpl->setVariable("OPTION_SELECTED", " selected=\"selected\"");				
					}
					$this->tpl->parseCurrentBlock();
				}
			}
			// fill in saved values in case of error
			$data = array();
			$data["fields"] = array();
			$data["fields"]["title"] = ilUtil::prepareFormOutput($_SESSION["error_post_vars"]["Fobject"]["title"],true);
			$data["fields"]["desc"] = ilUtil::stripSlashes($_SESSION["error_post_vars"]["Fobject"]["desc"]);

			foreach ($data["fields"] as $key => $val)
			{
				$this->tpl->setVariable("TXT_".strtoupper($key), $this->lng->txt($key));
				$this->tpl->setVariable(strtoupper($key), $val);

				if ($this->prepare_output)
				{
					$this->tpl->parseCurrentBlock();
				}
			}

			$this->tpl->setVariable("FORMACTION", $this->getFormAction("save","adm_object.php?cmd=gateway&ref_id=".
																	   $_GET["ref_id"]."&new_type=".$new_type));
			$this->tpl->setVariable("TXT_HEADER", $this->lng->txt($new_type."_new"));
			$this->tpl->setVariable("TXT_SELECT_QUESTIONPOOL", $this->lng->txt("select_questionpool"));
			$this->tpl->setVariable("OPTION_SELECT_QUESTIONPOOL", $this->lng->txt("select_questionpool_option"));
			$this->tpl->setVariable("TXT_CANCEL", $this->lng->txt("cancel"));
			$this->tpl->setVariable("TXT_SUBMIT", $this->lng->txt($new_type."_add"));
			$this->tpl->setVariable("CMD_SUBMIT", "save");
			$this->tpl->setVariable("TARGET", $this->getTargetFrame("save"));
			$this->tpl->setVariable("TXT_REQUIRED_FLD", $this->lng->txt("required_field"));

			$this->tpl->setVariable("TXT_IMPORT_TST", $this->lng->txt("import_tst"));
			$this->tpl->setVariable("TXT_TST_FILE", $this->lng->txt("tst_upload_file"));
			$this->tpl->setVariable("TXT_IMPORT", $this->lng->txt("import"));

			$this->tpl->setVariable("TXT_DUPLICATE_TST", $this->lng->txt("duplicate_tst"));
			$this->tpl->setVariable("TXT_SELECT_TST", $this->lng->txt("obj_tst"));
			$this->tpl->setVariable("OPTION_SELECT_TST", $this->lng->txt("select_tst_option"));
			$this->tpl->setVariable("TXT_DUPLICATE", $this->lng->txt("duplicate"));
			$this->tpl->setVariable("NEW_TYPE", $this->type);
			$this->tpl->parseCurrentBlock();
		}
	}

	function prepareOutput()
	{
		$this->tpl->addBlockFile("CONTENT", "content", "tpl.adm_content.html");
		$this->tpl->addBlockFile("STATUSLINE", "statusline", "tpl.statusline.html");
		$title = $this->object->getTitle();
		// catch feedback message
		sendInfo();

		$this->tpl->setCurrentBlock("header_image");
		$this->tpl->setVariable("IMG_HEADER", ilUtil::getImagePath("icon_tst_b.gif"));
		$this->tpl->parseCurrentBlock();
		if (!empty($title))
		{
			$this->tpl->setVariable("HEADER", $title);
		}
		if (!defined("ILIAS_MODULE"))
		{
			$this->setAdminTabs($_POST["new_type"]);
		}
		$this->setLocator();

	}
	
	/**
	* Creates the output for user/group invitation to a test
	*
	* Creates the output for user/group invitation to a test
	*
	* @access	public
	*/
	function participantsObject()
	{
		global $rbacsystem;

		if ((!$rbacsystem->checkAccess("read", $this->ref_id)) && (!$rbacsystem->checkAccess("write", $this->ref_id))) 
		{
			// allow only read and write access
			sendInfo($this->lng->txt("cannot_edit_test"), true);
			$path = $this->tree->getPathFull($this->object->getRefID());
			ilUtil::redirect($this->getReturnLocation("cancel","../repository.php?cmd=frameset&ref_id=" . $path[count($path) - 2]["child"]));
			return;
		}
		
		if ($this->object->getTestType() != TYPE_ONLINE_TEST) 
		{
			// allow only read and write access
			sendInfo($this->lng->txt("tst_must_be_online_exam"), false);
			return;
		}
		
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_invite.html", true);

		if ($_POST["cmd"]["cancel"])
		{
			$path = $this->tree->getPathFull($this->object->getRefID());
			ilUtil::redirect($this->getReturnLocation("cancel",ilUtil::removeTrailingPathSeparators(ILIAS_HTTP_PATH)."/repository.php?cmd=frameset&ref_id=" . $path[count($path) - 2]["child"]));
			exit();
		}

		if (count($_POST))
		{
			$this->handleCommands();
			//return;
		}
		
		if ($_POST["cmd"]["save"])
		{
			$this->object->saveToDb();
		}
		
		{
			if ($rbacsystem->checkAccess('write', $this->ref_id))
			{
				$this->tpl->setCurrentBlock("invitation");
				$this->tpl->setVariable("SEARCH_INVITATION", $this->lng->txt("search"));
				$this->tpl->setVariable("SEARCH_TERM", $this->lng->txt("search_term"));
				$this->tpl->setVariable("SEARCH_FOR", $this->lng->txt("search_for"));
				$this->tpl->setVariable("SEARCH_USERS", $this->lng->txt("search_users"));
				$this->tpl->setVariable("SEARCH_GROUPS", $this->lng->txt("search_groups"));
				$this->tpl->setVariable("SEARCH_ROLES", $this->lng->txt("search_roles"));
				$this->tpl->setVariable("TEXT_CONCATENATION", $this->lng->txt("concatenation"));
				$this->tpl->setVariable("TEXT_AND", $this->lng->txt("and"));
				$this->tpl->setVariable("TEXT_OR", $this->lng->txt("or"));
				$this->tpl->setVariable("VALUE_SEARCH_TERM", $_POST["search_term"]);
				if (is_array($_POST["search_for"]))
				{
					if (in_array("usr", $_POST["search_for"]))
					{
						$this->tpl->setVariable("CHECKED_USERS", " checked=\"checked\"");
					}
					if (in_array("grp", $_POST["search_for"]))
					{
						$this->tpl->setVariable("CHECKED_GROUPS", " checked=\"checked\"");
					}
					if (in_array("role", $_POST["search_for"]))
					{
						$this->tpl->setVariable("CHECKED_ROLES", " checked=\"checked\"");
					}
					
				}
				if (strcmp($_POST["concatenation"], "and") == 0)
				{
					$this->tpl->setVariable("CHECKED_AND", " checked=\"checked\"");
				}
				else if (strcmp($_POST["concatenation"], "or") == 0)
				{
					$this->tpl->setVariable("CHECKED_OR", " checked=\"checked\"");
				}
				$this->tpl->setVariable("SEARCH", $this->lng->txt("search"));
				$this->tpl->parseCurrentBlock();
			}
		}
		$invited_users = $this->object->getInvitedUsers();

		$buttons = array("save","remove","print_answers","print_results");
		
		if (count($invited_users))
		{
			$this->outUserGroupTable("iv_usr", $invited_users, "invited_user_result", "invited_user_row", $this->lng->txt("tst_participating_users"), "TEXT_INVITED_USER_TITLE",$buttons);
		}
		
		$this->tpl->setCurrentBlock("adm_content");
		$this->tpl->setVariable("TEXT_INVITATION", $this->lng->txt("invitation"));
		$this->tpl->setVariable("VALUE_ON", $this->lng->txt("on"));
		$this->tpl->setVariable("VALUE_OFF", $this->lng->txt("off"));
		$this->tpl->setVariable("TXT_REQUIRED_FLD", $this->lng->txt("required_field"));

    	if ($rbacsystem->checkAccess("write", $this->ref_id)) {
			$this->tpl->setVariable("SAVE", $this->lng->txt("save"));
			$this->tpl->setVariable("CANCEL", $this->lng->txt("cancel"));
		}
		$this->tpl->parseCurrentBlock();
	}

	/**
	* Extracts the results of a posted invitation form
	*
	* Extracts the results of a posted invitation form
	*
	* @access	public
	*/
	function handleCommands()
	{
		global $ilUser;

		$message = "";
		
		if (is_array($_POST["invited_users"]))
		{			
			if ($_POST["cmd"]["print_answers"]) {
				$this->_printAnswerSheets($_POST["invited_users"]);
				return;
			} elseif ($_POST["cmd"]["print_results"]) {
				$this->_printResultSheets($_POST["invited_users"]);
				return;					
			}
			
			for ($i = 0; $i < count($_POST["invited_users"]); $i++)
			{
				$user_id = $_POST["invited_users"][$i];	
				if ($_POST["cmd"]["remove"])
					$this->object->disinviteUser($user_id);				
			}
		}
		if (is_array($_POST["clientip"])) {
			foreach ($_POST["clientip"] as $user_id => $client_ip)
			{
				if ($_POST["cmd"]["save_client_ip"])
					$this->object->setClientIP($user_id, $client_ip);
			}			
		}
		
		if ($_POST["cmd"]["add"])
		{
			// add users 
			if (is_array($_POST["user_select"]))
			{
				$i = 0;
				foreach ($_POST["user_select"] as $user_id)
				{					
					$client_ip = $_POST["client_ip"][$i];
					$this->object->inviteUser($user_id, $client_ip);
					$i++;				
				}
			}
			// add groups members
			if (is_array($_POST["group_select"]))
			{
				foreach ($_POST["group_select"] as $group_id)
				{
					$this->object->inviteGroup($group_id);
				}
			}
			// add role members
			if (is_array($_POST["role_select"]))
			{
				foreach ($_POST["role_select"] as $role_id)
				{					
					$this->object->inviteRole($role_id);
				}
			}
			
		}

		if ($_POST["cmd"]["search"])
		{
			if (is_array($_POST["search_for"]))
			{
				if (in_array("usr", $_POST["search_for"]) or in_array("grp", $_POST["search_for"]) or in_array("role", $_POST["search_for"]))
				{					
					
					$search =& new ilSearch($ilUser->id);
					$search->setSearchString($_POST["search_term"]);
					$search->setCombination($_POST["concatenation"]);
					$search->setSearchFor($_POST["search_for"]);
					$search->setSearchType("new");
					if($search->validate($message))
					{
						$search->performSearch();
					}
					if ($message)
					{
						sendInfo($message);
					}
					
					if(!$search->getNumberOfResults() && $search->getSearchFor())
					{
						sendInfo($this->lng->txt("search_no_match"));
						return;
					}
					$buttons = array("add");

					$invited_users = $this->object->getInvitedUsers();
				
					if ($searchresult = $search->getResultByType("usr"))
					{												
						$users = array();
						foreach ($searchresult as $result_array)
						{
							if (!array_key_exists($result_array["id"], $invited_users))
							{								
								array_push($users, $result_array["id"]);
							}
						}
						
						$users = $this->object->getUserData($users);
						
						if (count ($users))
							$this->outUserGroupTable("usr", $users, "user_result", "user_row", $this->lng->txt("search_user"),"TEXT_USER_TITLE", $buttons);
					}

					$searchresult = array();
					
					if ($searchresult = $search->getResultByType("grp"))
					{
						$groups = array();
						
						foreach ($searchresult as $result_array)
						{							
							array_push($groups, $result_array["id"]);
						}
						$groups = $this->object->getGroupData ($groups);
						
						if (count ($groups))
							$this->outUserGroupTable("grp", $groups, "group_result", "group_row", $this->lng->txt("search_group"), "TEXT_GROUP_TITLE", $buttons);
					}
					
					$searchresult = array();
					
					if ($searchresult = $search->getResultByType("role"))
					{
						$roles = array();
						
						foreach ($searchresult as $result_array)
						{							
							array_push($roles, $result_array["id"]);
						}
						
						$roles = $this->object->getRoleData ($roles);			
								
						if (count ($roles))
							$this->outUserGroupTable("role", $roles, "role_result", "role_row", $this->lng->txt("role"), "TEXT_ROLE_TITLE", $buttons);
					}
					
				}
				
			}
			else
			{
				sendInfo($this->lng->txt("no_user_or_group_selected"));
			}
		}
	}

	/**
	* Print tab to create a print of all questions with points and solutions
	*
	* Print tab to create a print of all questions with points and solutions
	*
	* @access	public
	*/
	function printobject() 
	{
		global $rbacsystem, $ilUser;
		
		if ((!$rbacsystem->checkAccess("read", $this->ref_id)) && (!$rbacsystem->checkAccess("write", $this->ref_id))) 
		{
			// allow only read and write access
			sendInfo($this->lng->txt("cannot_edit_test"), true);
			$path = $this->tree->getPathFull($this->object->getRefID());
			ilUtil::redirect($this->getReturnLocation("cancel","../repository.php?cmd=frameset&ref_id=" . $path[count($path) - 2]["child"]));
			return;
		}

		if ($_POST["cmd"]["print"]) 
		{
			$this->outPrinttest();
			return;
		}
		
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.il_as_tst_print_test_confirm.html", true);
		$this->tpl->setVariable("TEXT_CONFIRM_PRINT_TEST", $this->lng->txt("tst_confirm_print"));
		$this->tpl->setVariable("FORM_PRINT_ACTION", $this->getCallingScript().$this->getAddParameter());
		$this->tpl->setVariable("BTN_PRINT", $this->lng->txt("print"));
		
	}
	
	/**
	* Creates the print output for the print tab of the test
	*
	* Creates the print output for the print tab of the test
	*
	* @access	private
	*/
	function outPrinttest() 
	{
		global $ilUser;
		
		
		$print_date = mktime(date("H"), date("i"), date("s"), date("m")  , date("d"), date("Y"));
		$this->tpl = new ilTemplate("./assessment/templates/default/tpl.il_as_tst_print_test.html", true, true);
		
		$this->tpl->setVariable("PRINT_CSS", "./templates/default/print_test.css");				
		$this->tpl->setVariable("SYNTAX_CSS","./templates/default/print_syntax.css");
		
		$this->tpl->setVariable("TITLE", $this->object->getTitle());		
		$this->tpl->setVariable("PRINT_TEST", $this->lng->txt("tst_print"));
		$this->tpl->setVariable("TXT_PRINT_DATE", $this->lng->txt("date"));
		$this->tpl->setVariable("VALUE_PRINT_DATE", strftime("%c",$print_date));
			
		$tpl = &$this->tpl;			
		
		$tpl->setVariable("TITLE", $this->object->getTitle());	

		$max_points= 0;
		$counter = 1;
					
		foreach ($this->object->questions as $question) {		
			$tpl->setCurrentBlock("question");			
			$question_gui = $this->object->createQuestionGUI("", $question);
			
			$tpl->setVariable("EDIT_QUESTION", $this->getCallingScript().$this->getAddParameter()."&sequence=".$counter);
			$tpl->setVariable("COUNTER_QUESTION", $counter.".");
			$tpl->setVariable("QUESTION_TITLE", $question_gui->object->getTitle());
			
			switch ($question_gui->getQuestionType()) {
				
				case "qt_imagemap" :
					$question_gui->outWorkingForm($idx = "", $postponed = false, $show_solution = true, $formaction, $show_pages= true, $show_solutions_only= true);
					break;
				case "qt_javaapplet" :
					$question_gui->outWorkingForm($idx = "", $postponed = false, $show_solution = true, $show_pages = true, $show_solutions_only= true);
					break;
				default :
					$question_gui->outWorkingForm($idx = "", $postponed = false, $show_solution = true, $show_pages = true, $show_solutions_only= true);
			}
			$tpl->parseCurrentBlock("question");
			$counter ++;					
			$max_points += $question_gui->object->getMaximumPoints();			
		}
		$this->tpl->setVariable("TXT_MAXIMUM_POINTS", $this->lng->txt("tst_maximum_points"));
		$this->tpl->setVariable("VALUE_MAXIMUM_POINTS", $max_points);
	}

	/**
	* Output of the table structures for selected users and selected groups
	*
	* Output of the table structures for selected users and selected groups
	* for the invite participants tab
	*
	* @access	private
	*/
	function outUserGroupTable($a_type, $data_array, $block_result, $block_row, $title_text, $title_label, $buttons)
	{
		global $rbacsystem;
		$rowclass = array("tblrow1", "tblrow2");
		
		switch($a_type)
		{
			case "iv_usr":
				$add_parameter = "?ref_id=" . $_GET["ref_id"];
				$finished = "<a target=\"_BLANK\" href=\"".$this->getCallingScript().$add_parameter."&cmd=resultsheet&user_id=\"><img border=\"0\" align=\"middle\" src=\"".ilUtil::getImagePath("right.png", true) . "\" alt=\"\" />&nbsp;".$this->lng->txt("tst_qst_result_sheet")."</a>" ;
				$finished .= "&nbsp;<a target=\"_BLANK\" href=\"".$this->getCallingScript().$add_parameter."&cmd=answersheet&user_id=\">&nbsp;".$this->lng->txt("tst_show_answer_sheet")."</a>" ;
				$started   = "<img border=\"0\" align=\"middle\" src=\"".ilUtil::getImagePath("right.png", true) . "\" alt=\"\" />" ;
				
				foreach ($data_array as $data)
				{
					$finished_line = str_replace ("&user_id=","&user_id=".$data->usr_id,$finished);
					$started_line = str_replace ("&user_id=","&user_id=".$data->usr_id,$started); 
					$counter = 0;
					$this->tpl->setCurrentBlock($block_row);
					$this->tpl->setVariable("COLOR_CLASS", $rowclass[$counter % 2]);
					$this->tpl->setVariable("COUNTER", $data->usr_id);
					$this->tpl->setVariable("VALUE_IV_USR_ID", $data->usr_id);
					$this->tpl->setVariable("VALUE_IV_LOGIN", $data->login);
					$this->tpl->setVariable("VALUE_IV_FIRSTNAME", $data->firstname);
					$this->tpl->setVariable("VALUE_IV_LASTNAME", $data->lastname);
					$this->tpl->setVariable("VALUE_IV_CLIENT_IP", $data->clientip);
					$this->tpl->setVariable("VALUE_IV_TEST_FINISHED", ($data->test_finished==1)?$finished_line:"&nbsp;");
					$this->tpl->setVariable("VALUE_IV_TEST_STARTED", ($data->test_started==1)?$started_line:"&nbsp;");
					$counter++;
					$this->tpl->parseCurrentBlock();
				}
				$this->tpl->setCurrentBlock($block_result);
				$this->tpl->setVariable("$title_label", "<img src=\"" . ilUtil::getImagePath("icon_usr_b.gif") . "\" alt=\"\" /> " . $title_text);
				$this->tpl->setVariable("TEXT_IV_LOGIN", $this->lng->txt("login"));
				$this->tpl->setVariable("TEXT_IV_FIRSTNAME", $this->lng->txt("firstname"));
				$this->tpl->setVariable("TEXT_IV_LASTNAME", $this->lng->txt("lastname"));
				$this->tpl->setVariable("TEXT_IV_CLIENT_IP", $this->lng->txt("clientip"));
				$this->tpl->setVariable("TEXT_IV_TEST_FINISHED", $this->lng->txt("tst_finished"));
				$this->tpl->setVariable("TEXT_IV_TEST_STARTED", $this->lng->txt("tst_started"));
					
				if ($rbacsystem->checkAccess('write', $this->object->getRefId()))
				{
					foreach ($buttons as $cat)
					{
						$this->tpl->setVariable("VALUE_" . strtoupper($cat), $this->lng->txt($cat));
					}
					$this->tpl->setVariable("ARROW", "<img src=\"" . ilUtil::getImagePath("arrow_downright.gif") . "\" alt=\"\">");
				}
				$this->tpl->parseCurrentBlock();
				break;
			case "usr":
				$add_parameter = "?ref_id=" . $_GET["ref_id"] . "&cmd=resultsheet";
				$finished = "<a target=\"_BLANK\" href=\"".$this->getCallingScript().$add_parameter."\"><img border=\"0\" align=\"middle\" src=\"".ilUtil::getImagePath("right.png", true) . "\" alt=\"\" />&nbsp;".$this->lng->txt("tst_qst_result_sheet")."</a>" ;
				foreach ($data_array as $data)
				{
					$counter = 0;
					$this->tpl->setCurrentBlock($block_row);
					$this->tpl->setVariable("COLOR_CLASS", $rowclass[$counter % 2]);
					$this->tpl->setVariable("COUNTER", $data->usr_id);
					$this->tpl->setVariable("VALUE_LOGIN", $data->login);
					$this->tpl->setVariable("VALUE_FIRSTNAME", $data->firstname);
					$this->tpl->setVariable("VALUE_LASTNAME", $data->lastname);
					$this->tpl->setVariable("VALUE_CLIENT_IP", $data->clientip);
					$counter++;
					$this->tpl->parseCurrentBlock();
				}
				$this->tpl->setCurrentBlock($block_result);
				$this->tpl->setVariable("$title_label", "<img src=\"" . ilUtil::getImagePath("icon_usr_b.gif") . "\" alt=\"\" /> " . $title_text);
				$this->tpl->setVariable("TEXT_LOGIN", $this->lng->txt("login"));
				$this->tpl->setVariable("TEXT_FIRSTNAME", $this->lng->txt("firstname"));
				$this->tpl->setVariable("TEXT_LASTNAME", $this->lng->txt("lastname"));
				$this->tpl->setVariable("TEXT_CLIENT_IP", $this->lng->txt("clientip"));
					
				if ($rbacsystem->checkAccess('write', $this->object->getRefId()))
				{
					foreach ($buttons as $cat)
					{
						$this->tpl->setVariable("VALUE_" . strtoupper($cat), $this->lng->txt($cat));
					}
					$this->tpl->setVariable("ARROW", "<img src=\"" . ilUtil::getImagePath("arrow_downright.gif") . "\" alt=\"\">");
				}
				$this->tpl->parseCurrentBlock();
				break;
				
			case "role":
				
			case "grp":
				foreach ($data_array as $key => $data)
				{
					$counter = 0;
					$this->tpl->setCurrentBlock($block_row);
					$this->tpl->setVariable("COLOR_CLASS", $rowclass[$counter % 2]);
					$this->tpl->setVariable("COUNTER", $key);
					$this->tpl->setVariable("VALUE_TITLE", $data->title);
					$this->tpl->setVariable("VALUE_DESCRIPTION", $data->description);
					$counter++;
					$this->tpl->parseCurrentBlock();
				}
				$this->tpl->setCurrentBlock($block_result);
				$this->tpl->setVariable("$title_label", "<img src=\"" . ilUtil::getImagePath("icon_".$a_type."_b.gif") . "\" alt=\"\" /> " . $title_text);
				$this->tpl->setVariable("TEXT_TITLE", $this->lng->txt("title"));
				$this->tpl->setVariable("TEXT_DESCRIPTION", $this->lng->txt("description"));
				if ($rbacsystem->checkAccess('write', $this->object->getRefId()))
				{
					foreach ($buttons as $cat)
					{
						$this->tpl->setVariable("VALUE_" . strtoupper($cat), $this->lng->txt($cat));
					}
					$this->tpl->setVariable("ARROW", "<img src=\"" . ilUtil::getImagePath("arrow_downright.gif") . "\" alt=\"\">");
				}
				$this->tpl->parseCurrentBlock();
				break;
		}
	}
		
	/**
	* adds tabs to tab gui object
	*
	* @param	object		$tabs_gui		ilTabsGUI object
	*/
	function getTabs(&$tabs_gui)
	{
		// properties
		$force_active = ($this->ctrl->getCmdClass() == "" &&
			$this->ctrl->getCmd() == "")
			? true
			: false;
		$tabs_gui->addTarget("properties",
			 $this->ctrl->getLinkTarget($this,'properties'),
			 array("properties", "saveProperties", "cancelProperties"),
			 "",
			 "", $force_active);

		// questions
		$force_active = ($_GET["up"] != "" || $_GET["down"] != "")
			? true
			: false;
		if (!$force_active)
		{
			if ($_GET["browse"] == 1) $force_active = true;
		}
		$tabs_gui->addTarget("ass_questions",
			 $this->ctrl->getLinkTarget($this,'questions'),
			 array("questions", "browseForQuestions", "createQuestion", 
			 "randomselect", "filter", "resetFilter", "insertQuestions",
			 "back", "createRandomSelection", "cancelRandomSelect",
			 "insertRandomSelection", "removeQuestions", "moveQuestions",
			 "insertQuestionsBefore", "insertQuestionsAfter", "confirmRemoveQuestions",
			 "cancelRemoveQuestions", "executeCreateQuestion", "cancelCreateQuestion"), 
			 "", "", $force_active);
			 
		// mark schema
		$tabs_gui->addTarget("mark_schema",
			 $this->ctrl->getLinkTarget($this,'marks'),
			 array("marks", "addMarkStep", "deleteMarkSteps", "addSimpleMarkSchema",
			 	"saveMarks", "cancelMarks"),
			 "");

		if ($this->object->isOnlineTest())
		{
			// participants
			$tabs_gui->addTarget("participants",
				 $this->ctrl->getLinkTarget($this,'participants'),
				 array("participants", "search", "add", "save_client_ip",
				 "remove", "print_answers", "print_results"), 
				 "");
		}

		// print
		$tabs_gui->addTarget("print",
			 $this->ctrl->getLinkTarget($this,'print'),
			 "print", "");

		// export
		$tabs_gui->addTarget("export",
			 $this->ctrl->getLinkTarget($this,'export'),
			 array("export", "createExportFile", "confirmDeleteExportFile",
			 "downloadExportFile", "deleteExportFile", "cancelDeleteExportFile"),
			 "");
			
		// maintenance
		$tabs_gui->addTarget("maintenance",
			 $this->ctrl->getLinkTarget($this,'maintenance'),
			 array("maintenance", "deleteAllUserData", "confirmDeleteAllUserData",
			 "cancelDeleteAllUserData"), 
			 "");

		// status
		$tabs_gui->addTarget("status",
			 $this->ctrl->getLinkTarget($this,'status'),
			 "status", "");
		
		// permissions
		$tabs_gui->addTarget("perm_settings",
			 $this->ctrl->getLinkTarget($this,'perm'),
			 array("perm", "info"),
			 "");
			 
		// meta data
		$tabs_gui->addTarget("meta_data",
			 $this->ctrl->getLinkTargetByClass('ilmdeditorgui','listSection'),
			 "", "ilmdeditorgui");
	}

				
} // END class.ilObjTestGUI

?>
