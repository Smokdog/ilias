<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */
require_once("./Modules/DataCollection/classes/class.ilDataCollectionTable.php");

/**
* Class ilDataCollectionTableEditGUI
*
* @author Martin Studer <ms@studer-raimann.ch>
* @author Marcel Raimann <mr@studer-raimann.ch>
* @author Fabian Schmid <fs@studer-raimann.ch>
*
* @ingroup ModulesDataCollection
*/
	
class ilDataCollectionTableEditGUI
{
	/**
	 * @var int
	 */
	private $table_id;
	/**
	 * @var ilDataCollectionTable
	 */
	private $table;
	/**
	 * Constructor
	 *
	 * @param	object	$a_parent_obj
	 */
	function __construct(ilObjDataCollectionGUI $a_parent_obj)
	{
		$this->parent_object = $a_parent_obj;
		$this->obj_id = $a_parent_obj->obj_id;
		$this->table_id = $_GET['table_id'];
	}

	
	/**
	 * execute command
	 */
	function executeCommand()
	{
		global $tpl, $ilCtrl, $ilUser;
		
		$cmd = $ilCtrl->getCmd();
		$tpl->getStandardTemplate();
		
		switch($cmd)
		{
			case 'update':
				$this->save("update");
				break;
			default:
				$this->$cmd();
				break;
		}

		return true;
	}

	/**
	 * create table add form
	*/
	public function create()
	{
		global $ilTabs, $tpl;
		$this->initForm();
		$this->getStandardValues();

		$tpl->setContent($this->form->getHTML());
	}

	/**
	 * create field edit form
	*/
	public function edit()
	{
		global $ilCtrl, $tpl;

		if(!$this->table_id){
			$ilCtrl->redirectByClass("ildatacollectionfieldeditgui", "listFields");
			return;
		}else{
			$this->table = new ilDataCollectionTable($this->table_id);
		}
		$this->initForm("edit");
		$this->getValues();
		$tpl->setContent($this->form->getHTML());
	}

	/**
	 * getFieldValues
	 */
	public function getValues()
	{
		$values =  array(
			'title'		=>	$this->table->getTitle(),
			'add_perm'		=>	$this->table->getAddPerm(),
			'edit_perm'		=>	$this->table->getEditPerm(),
			'delete_perm'		=>	$this->table->getDeletePerm(),
			'edit_by_owner'		=>	$this->table->getEditByOwner(),
			'limited'		=>	$this->table->getLimited(),
			'limit_start'		=>	array("date" => substr($this->table->getLimitStart(),0,10), "time" => substr($this->table->getLimitStart(),-8)),
			'limit_end'		=>	array("date" => substr($this->table->getLimitEnd(),0,10), "time" => substr($this->table->getLimitEnd(),-8))
		);
		if(!$this->table->getLimitStart())
			$values['limit_start'] = Null;
		if(!$this->table->getLimitEnd())
			$values['limit_end'] = Null;
		$this->form->setValuesByArray($values);
	}
	
	/**
	 * getStandardValues
	 */
	public function getStandardValues()
	{
		$values =  array(
			'title'		=>	"",
			'add_perm'		=>	1,
			'edit_perm'		=>	1,
			'delete_perm'		=>	1,
			'edit_by_owner'		=>	1,
			'limited'		=>	0,
			'limit_start'		=>	null,
			'limit_end'		=>	null
		);
		$this->form->setValuesByArray($values);
	}

	public function cancel(){
		global $ilCtrl;
		$ilCtrl->redirectByClass("ilDataCollectionFieldListGUI", "listFields");
	}

	/**
	 * initEditCustomForm
	 *
	 * @param string $a_mode
	 */
	public function initForm($a_mode = "create")
	{
		global $ilCtrl, $ilErr, $lng;
		
		include_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
		$this->form = new ilPropertyFormGUI();

		$item = new ilTextInputGUI($lng->txt('title'),'title');
		$item->setRequired(true);
		$this->form->addItem($item);
		$item = new ilCheckboxInputGUI($lng->txt('dcl_add_perm'),'add_perm');
		$this->form->addItem($item);
		$item = new ilCheckboxInputGUI($lng->txt('dcl_edit_perm'),'edit_perm');
		$this->form->addItem($item);
		$item = new ilCheckboxInputGUI($lng->txt('dcl_delete_perm'),'delete_perm');
		$this->form->addItem($item);
		$item = new ilCheckboxInputGUI($lng->txt('dcl_edit_by_owner'),'edit_by_owner');
		$this->form->addItem($item);
		$item = new ilCheckboxInputGUI($lng->txt('dcl_limited'),'limited');
		$sitem1 = new ilDateTimeInputGUI($lng->txt('dcl_limit_start'),'limit_start');
		$sitem2 = new ilDateTimeInputGUI($lng->txt('dcl_limit_end'),'limit_end');
		$item->addSubItem($sitem1);
		$item->addSubItem($sitem2);
		$this->form->addItem($item);
		$this->form->addCommandButton('cancel', 	$lng->txt('cancel'));
		if($a_mode == "edit")
			$this->form->addCommandButton('update', 	$lng->txt('dcl_table_'.$a_mode));
		else
			$this->form->addCommandButton('save', 	$lng->txt('dcl_table_'.$a_mode));

		
		$this->form->setFormAction($ilCtrl->getFormAction($this, $a_mode));

		$this->form->setTitle($lng->txt('dcl_new_table'));
	}

	
	/**
	 * save
	 *
	 * @param string $a_mode values: create | edit
	*/
	public function save($a_mode = "create")
	{
		global $ilCtrl, $ilTabs, $lng;
		
		if(!ilObjDataCollection::_checkAccess($this->obj_id))
		{
			$this->accessDenied();
			return;
		}

		$ilTabs->activateTab("id_fields");
		
		$this->initForm($a_mode);
		
		if ($this->form->checkInput())
		{
			if(!$a_mode == "update")
				$this->table = new ilDataCollectionTable();
			elseif($this->table_id)
				$this->table = new ilDataCollectionTable($this->table_id);
			else
				$ilCtrl->redirectByClass("ildatacollectionfieldeditgui", "listFields");

			$this->table->setTitle($this->form->getInput("title"));
			$this->table->setObjId($this->obj_id);
			$this->table->setAddPerm($this->form->getInput("add_perm"));
			$this->table->setEditPerm($this->form->getInput("edit_perm"));
			$this->table->setDeletePerm($this->form->getInput("delete_perm"));
			$this->table->setEditByOwner($this->form->getInput("edit_by_owner"));
			$this->table->setLimited($this->form->getInput("limited"));
			$limit_start = $this->form->getInput("limit_start");
			$limit_end = $this->form->getInput("limit_end");
			$this->table->setLimitStart($limit_start["date"]." ".$limit_start["time"]);
			$this->table->setLimitEnd($limit_end["date"]." ".$limit_end["time"]);

			if(!$this->table->hasPermissionToAddTable($this->parent_object->ref_id))
			{
				$this->accessDenied();
				return;
			}
			if($a_mode == "update"){
				$this->table->doUpdate();
				ilUtil::sendSuccess($lng->txt("dcl_msg_table_edited"), true);
				$ilCtrl->redirectByClass("ildatacollectiontableeditgui", "edit");
			}else{
				$this->table->doCreate();
				ilUtil::sendSuccess($lng->txt("dcl_msg_table_created"), true);
				$ilCtrl->setParameterByClass("ildatacollectionfieldlistgui","table_id", $this->table->getId());
				$ilCtrl->redirectByClass("ildatacollectionfieldlistgui", "listFields");
			}

		}
		else
		{
			global $tpl;
			$this->form->setValuesByPost();
			$tpl->setContent($this->form->getHTML());
		}
	}
	/*
	 * accessDenied
	 */
	public function accessDenied()
	{
		global $tpl;
		$tpl->setContent("Access denied.");
	}

}

?>