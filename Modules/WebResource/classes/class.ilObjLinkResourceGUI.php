<?php

/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once "./classes/class.ilObjectGUI.php";
include_once('./Modules/WebResource/classes/class.ilParameterAppender.php');
require_once 'Services/LinkChecker/interfaces/interface.ilLinkCheckerGUIRowHandling.php';

/**
* Class ilObjLinkResourceGUI
*
* @author Stefan Meyer <smeyer.ilias@gmx.de> 
* @version $Id$
* 
* @ilCtrl_Calls ilObjLinkResourceGUI: ilMDEditorGUI, ilPermissionGUI, ilInfoScreenGUI, ilObjectCopyGUI
* @ilCtrl_Calls ilObjLinkResourceGUI: ilExportGUI
* 
*
* @ingroup ModulesWebResource
*/
class ilObjLinkResourceGUI extends ilObjectGUI implements ilLinkCheckerGUIRowHandling
{
	const VIEW_MODE_VIEW = 1;
	const VIEW_MODE_MANAGE = 2;
	const VIEW_MODE_SORT = 3;
	
	const LINK_MOD_CREATE = 1;
	const LINK_MOD_EDIT = 2;
	const LINK_MOD_ADD = 3;
	
	/**
	* Constructor
	* @access public
	*/
	function __construct()
	{
		global $ilCtrl;

		$this->type = "webr";
		parent::__construct('',(int) $_GET['ref_id'],true,false);

		// CONTROL OPTIONS
		$this->ctrl = $ilCtrl;
		$this->ctrl->saveParameter($this,array("ref_id","cmdClass"));

		$this->lng->loadLanguageModule('webr');
	}

	public function executeCommand()
	{
		global $ilCtrl, $ilTabs;
		
		
		//if($this->ctrl->getTargetScript() == 'link_resources.php')
		if($_GET["baseClass"] == 'ilLinkResourceHandlerGUI')
		{
			$_GET['view_mode'] = isset($_GET['switch_mode']) ? $_GET['switch_mode'] : $_GET['view_mode'];
			$ilCtrl->saveParameter($this, 'view_mode');
			$this->__prepareOutput();
		}
		
		if (strtolower($_GET["baseClass"]) == "iladministrationgui" ||
			$this->getCreationMode() == true)
		{
			$this->prepareOutput();
		}


		$next_class = $this->ctrl->getNextClass($this);
		$cmd = $this->ctrl->getCmd();
		
		switch($next_class)
		{
			case "ilinfoscreengui":
				$this->infoScreen();	// forwards command
				break;

			case 'ilmdeditorgui':
				$ilTabs->activateTab('id_meta_data');
				include_once 'Services/MetaData/classes/class.ilMDEditorGUI.php';
				$md_gui =& new ilMDEditorGUI($this->object->getId(), 0, $this->object->getType());
				$md_gui->addObserver($this->object,'MDUpdateListener','General');
				$this->ctrl->forwardCommand($md_gui);
				break;
				
			case 'ilpermissiongui':
				$ilTabs->activateTab('id_permissions');
				include_once("Services/AccessControl/classes/class.ilPermissionGUI.php");
				$perm_gui =& new ilPermissionGUI($this);
				$ret =& $this->ctrl->forwardCommand($perm_gui);
				break;
				
			case 'ilobjectcopygui':
				include_once './Services/Object/classes/class.ilObjectCopyGUI.php';
				$cp = new ilObjectCopyGUI($this);
				$cp->setType('webr');
				$this->ctrl->forwardCommand($cp);
				break;
				
			case 'ilexportgui':
				$this->tabs_gui->setTabActive('export');
				include_once './Services/Export/classes/class.ilExportGUI.php';
				$exp = new ilExportGUI($this);
				$exp->addFormat('xml');
				$this->ctrl->forwardCommand($exp);
				break;
				
			default:

				if(!$cmd)
				{
					$cmd = "view";
				}
				$cmd .= "Object";
				$this->$cmd();
					
				break;
		}
		
		if(!$this->getCreationMode())
		{
			// Fill meta header tags
			include_once('Services/MetaData/classes/class.ilMDUtils.php');
			ilMDUtils::_fillHTMLMetaTags($this->object->getId(),$this->object->getId(),'webr');
		}
		return true;
	}
	
	/**
	 * Overwritten to offer object cloning
	 *
	 * @access public
	 * @param
	 * 
	 */
	public function createObject()
	{
	 	$this->tpl->addBlockFile('ADM_CONTENT','adm_content','tpl.webr_create.html','Modules/WebResource');
		$this->initFormLink(self::LINK_MOD_CREATE);
		$this->tpl->setVariable('LINK_FORM',$this->form->getHTML());
		
	 	$this->fillCloneTemplate('CLONE_WIZARD',$_REQUEST['new_type']);

		$this->initImportForm("webr");
		$this->tpl->setVariable("IMPORT_FORM", $this->form->getHTML());
	}

	/**
	 * Init object import form
	 *
	 * @param        string        new type
	 */
	public function initImportForm($a_new_type = "")
	{
		global $lng, $ilCtrl;

		$lng->loadLanguageModule("webr");

		include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
		$this->form = new ilPropertyFormGUI();
		$this->form->setTableWidth('600px');
		$this->form->setTarget("_top");

		// Import file
		include_once("./Services/Form/classes/class.ilFileInputGUI.php");
		$fi = new ilFileInputGUI($lng->txt("import_file"), "importfile");
		$fi->setSuffixes(array("zip"));
		$fi->setRequired(true);
		$this->form->addItem($fi);

		$this->form->addCommandButton("importFile", $lng->txt("import"));
		$this->form->addCommandButton("cancel", $lng->txt("cancel"));
		$this->form->setTitle($lng->txt($a_new_type."_import"));

		$this->form->setFormAction($ilCtrl->getFormAction($this));
	}
	
	/**
	 * Import
	 *
	 * @access	public
	 */
	protected function importFileObject()
	{
		global $rbacsystem, $objDefinition, $tpl, $lng, $ilErr;

		$new_type = $_POST["new_type"] ? $_POST["new_type"] : $_GET["new_type"];

		// create permission is already checked in createObject. This check here is done to prevent hacking attempts
		if (!$rbacsystem->checkAccess("create", $this->object->getRefId(), $new_type))
		{
			$ilErr->raiseError($this->lng->txt('no_create_permission'),$ilErr->MESSAGE);
		}
		$this->ctrl->setParameter($this, "new_type", $new_type);
		$this->initImportForm($new_type);
		if ($this->form->checkInput())
		{
			include_once './Services/Export/classes/class.ilImport.php';
			$imp = new ilImport((int) $_GET['ref_id']);
			$new_id = $imp->importObject(null, $_FILES["importfile"]["tmp_name"],$_FILES["importfile"]["name"], $new_type);

			// put new object id into tree
			if ($new_id > 0)
			{
				$newObj = ilObjectFactory::getInstanceByObjId($new_id);
				$newObj->createReference();
				$newObj->putInTree($_GET["ref_id"]);
				$newObj->setPermissions($_GET["ref_id"]);
				ilUtil::sendSuccess($lng->txt("msg_obj_modified"), true);
				$this->ctrl->returnToParent($this);
			}
			return;
		}

		$this->form->setValuesByPost();
		$tpl->setContent($this->form->getHtml());
	}
	
	
	/**
	 * Save new object
	 * @access	public
	 */
	public function saveObject()
	{
		global $ilCtrl;
		
		$this->initFormLink(self::LINK_MOD_CREATE);
		if($this->checkLinkInput(self::LINK_MOD_CREATE,0,0))
		{
			// Save new object
			$_POST['Fobject']['title'] = $_POST['tit'];
			$_POST['Fobject']['desc'] = $_POST['des'];
			$link_list = parent::saveObject();

			// Save link
			$this->link->setLinkResourceId($link_list->getId());
			$link_id = $this->link->add();
			
			// Dynamic params
			if(ilParameterAppender::_isEnabled() and is_object($this->dynamic))
			{
				$this->dynamic->setObjId($link_list->getId());
				$this->dynamic->add($link_id);
			}
			
			ilUtil::sendSuccess($this->lng->txt('webr_link_added'));
			ilUtil::redirect("ilias.php?baseClass=ilLinkResourceHandlerGUI&ref_id=".
				$link_list->getRefId()."&cmd=view");
			return true;			
		}
		// Data incomplete or invalid
		ilUtil::sendFailure($this->lng->txt('err_check_input'));
		$this->form->setValuesByPost();
		
	 	$this->tpl->addBlockFile('ADM_CONTENT','adm_content','tpl.webr_create.html','Modules/WebResource');
		$this->tpl->setVariable('LINK_FORM',$this->form->getHTML());
		
		$this->fillCloneTemplate('CLONE_WIZARD',$_REQUEST['new_type']);
		return false;

		
		if ($_POST["Fobject"]["title"] == "")
		{
			ilUtil::sendFailure($this->lng->txt('please_enter_title'));
			$this->createObject();
			return false;
		}
	}
	
	/**
	 * Edit settings
	 * Title, Description, Sorting
	 * @return 
	 */
	protected function settingsObject()
	{
		global $ilTabs;
	
		$this->checkPermission('write');
		$ilTabs->activateTab('id_settings');
		
		$this->initFormSettings();
		$this->tpl->setContent($this->form->getHTML());
	}
	
	/**
	 * Save container settings
	 * @return 
	 */
	protected function saveSettingsObject()
	{
		global $ilTabs;
		
		$this->checkPermission('write');
		$ilTabs->activateTab('id_settings');
		
		$this->initFormSettings();
		if($this->form->checkInput())
		{
			$this->object->setTitle($this->form->getInput('tit'));
			$this->object->setDescription($this->form->getInput('des'));
			$this->object->update();
			
			include_once './Services/Container/classes/class.ilContainerSortingSettings.php';
			$sort = new ilContainerSortingSettings($this->object->getId());
			$sort->setSortMode($this->form->getInput('sor'));
			$sort->update();
			
			ilUtil::sendSuccess($this->lng->txt('settings_saved'),true);
			$this->ctrl->redirect($this,'settings');
		}
		
		$this->form->setValuesByPost();
		ilUtil::sendFailure($this->lng->txt('err_check_input'));
		$this->tpl->setContent($this->form->getHTML());
	}
	
	
	/**
	 * Show settings form
	 * @return 
	 */
	protected function initFormSettings()
	{
		include_once './Services/Form/classes/class.ilPropertyFormGUI.php';
		$this->form = new ilPropertyFormGUI();
		$this->form->setFormAction($this->ctrl->getFormAction($this,'saveSettings'));
		$this->form->setTitle($this->lng->txt('webr_edit_settings'));
		
		// Title
		$tit = new ilTextInputGUI($this->lng->txt('webr_list_title'),'tit');
		$tit->setValue($this->object->getTitle());
		$tit->setRequired(true);
		$tit->setSize(40);
		$tit->setMaxLength(127);
		$this->form->addItem($tit);
		
		// Description
		$des = new ilTextAreaInputGUI($this->lng->txt('webr_list_desc'),'des');
		$des->setValue($this->object->getDescription());
		$des->setCols(40);
		$des->setRows(3);
		$this->form->addItem($des);
		
		// Sorting
		include_once './Services/Container/classes/class.ilContainerSortingSettings.php';
		include_once './Services/Container/classes/class.ilContainer.php';
		
		$sor = new ilRadioGroupInputGUI($this->lng->txt('webr_sorting'),'sor');
		$sor->setRequired(true);
		include_once './Services/Container/classes/class.ilContainerSortingSettings.php';
		$sor->setValue(ilContainerSortingSettings::_lookupSortMode($this->object->getId()));
		
		$opt = new ilRadioOption(
			$this->lng->txt('webr_sort_title'),
			ilContainer::SORT_TITLE
		);
		$sor->addOption($opt);
		
		$opm = new ilRadioOption(
			$this->lng->txt('webr_sort_manual'),
			ilContainer::SORT_MANUAL
		);
		$sor->addOption($opm);
		$this->form->addItem($sor);

		$this->form->addCommandButton('saveSettings', $this->lng->txt('save'));
		$this->form->addCommandButton('view', $this->lng->txt('cancel'));
	}
	

	/**
	 * Edit a single link
	 * @return 
	 */
	public function editLinkObject()
	{
		global $ilCtrl;
		
		$this->checkPermission('write');
		$this->activateTabs('content','id_content_view');
		
		if(!(int) $_GET['link_id'])
		{
			ilUtil::sendFailure($this->lng->txt('select_one'),true);
			$ilCtrl->redirect($this,'view');
		}
		
		$this->initFormLink(self::LINK_MOD_EDIT);
		$this->setValuesFromLink((int) $_GET['link_id']);
		$this->tpl->setContent($this->form->getHTML());
	}
	
	/**
	 * Save after editing
	 * @return 
	 */
	public function updateLinkObject()
	{
		global $ilCtrl;

		$this->initFormLink(self::LINK_MOD_EDIT);
		if($this->checkLinkInput(self::LINK_MOD_EDIT,$this->object->getId(),(int) $_REQUEST['link_id']))
		{
			$this->link->setLinkId((int) $_REQUEST['link_id']);
			$this->link->update();
			if(ilParameterAppender::_isEnabled() and is_object($this->dynamic))
			{
				$this->dynamic->add((int) $_REQUEST['link_id']);
			}
			
			if($this->isContainerMetaDataRequired())
			{
				$this->object->setTitle($this->form->getInput('tit'));
				$this->object->setDescription($this->form->getInput('des'));
				$this->object->update();
			}
			
			ilUtil::sendSuccess($this->lng->txt('settings_saved'),true);
			$ilCtrl->redirect($this,'view');
		}
		ilUtil::sendFailure($this->lng->txt('err_check_input'));
		$this->form->setValuesByPost();
		$this->tpl->setContent($this->form->getHTML());
	}
	
	/**
	 * Add an additional link
	 * @return 
	 */
	public function addLinkObject()
	{
		$this->checkPermission('write');
		$this->activateTabs('content','id_content_view');
	
		$this->initFormLink(self::LINK_MOD_ADD);
		$this->tpl->setContent($this->form->getHTML());
	}
	
	/**
	 * Save form data
	 * @return 
	 */
	public function saveAddLinkObject()
	{
		global $ilCtrl;
		
		$this->checkPermission('write');
	
		$this->initFormLink(self::LINK_MOD_ADD);
		if($this->checkLinkInput(self::LINK_MOD_ADD,$this->object->getId(),0))
		{
			if($this->isContainerMetaDataRequired())
			{
				// Save list data
				$this->object->setTitle($this->form->getInput('lti'));
				$this->object->setDescription($this->form->getInput('lde'));
				$this->object->update();
			}
			
			// Save Link
			$link_id = $this->link->add();
			
			// Dynamic parameters
			if(ilParameterAppender::_isEnabled() and is_object($this->dynamic))
			{
				$this->dynamic->add($link_id);
			}
			ilUtil::sendSuccess($this->lng->txt('webr_link_added'),true);
			$ilCtrl->redirect($this,'view');
		}
		// Error handling
		ilUtil::sendFailure($this->lng->txt('err_check_input'));
		$this->form->setValuesByPost();
		
		$this->activateTabs('content','id_content_view');
		$this->tpl->setContent($this->form->getHTML());
	}
	
	/**
	 * Delete a dynamic parameter
	 * @return 
	 */
	protected function deleteParameterObject()
	{
		global $ilCtrl;

		$this->checkPermission('write');
		
		if(!isset($_GET['param_id']))
		{
			ilUtil::sendFailure($this->lng->txt('select_one'),TRUE);
			$ilCtrl->redirect($this,'view');
		}	

		include_once './Modules/WebResource/classes/class.ilParameterAppender.php';
		$param = new ilParameterAppender($this->object->getId());
		$param->delete((int) $_GET['param_id']);
		
		ilUtil::sendSuccess($this->lng->txt('links_parameter_deleted'),true);
		$ilCtrl->redirect($this,'view');
	}
	
	
	/**
	 * Update all visible links
	 * @return 
	 */
	protected function updateLinksObject()
	{
		global $ilCtrl;
		
		$this->checkPermission('write');
		$this->activateTabs('content','');
		
		if(!is_array($_POST['ids']))
		{
			ilUtil::sendFailure($this->lng->txt('select_one'),TRUE);
			$ilCtrl->redirect($this,'view');
		}
	
		// Validate
		$invalid = array();
		foreach($_POST['ids'] as $link_id)
		{
			$data = $_POST['links'][$link_id];
			
			if(!strlen($data['tit']))
			{
				$invalid[] = $link_id;
				continue;
			}
			if(!strlen($data['tar']))
			{
				$invalid[] = $link_id;
				continue;
			}
			if($data['nam'] and !$data['val'])
			{
				$invalid[] = $link_id;
				continue;
			}
			if(!$data['nam'] and $data['val'])
			{
				$invalid[] = $link_id;
				continue;
			}
		}
		
		if(count($invalid))
		{
			ilUtil::sendFailure($this->lng->txt('err_check_input'));
			$this->tpl->addBlockFile('ADM_CONTENT','adm_content','tpl.webr_manage.html','Modules/WebResource');
			
			include_once './Modules/WebResource/classes/class.ilWebResourceEditableLinkTableGUI.php';
			$table = new ilWebResourceEditableLinkTableGUI($this,'view');
			$table->setInvalidLinks($invalid);
			$table->parseSelectedLinks($_POST['ids']);
			$table->updateFromPost();
			$this->tpl->setVariable('TABLE_LINKS',$table->getHTML());
			return false;
		}
		
		include_once './Modules/WebResource/classes/class.ilLinkResourceItems.php';
		$links = new ilLinkResourceItems($this->object->getId());
		
		// Save Settings
		foreach($_POST['ids'] as $link_id)
		{
			$data = $_POST['links'][$link_id];
			
			$links->setLinkId($link_id);
			$links->setTitle(ilUtil::stripSlashes($data['tit']));
			$links->setDescription(ilUtil::stripSlashes($data['des']));
			$links->setTarget(ilUtil::stripSlashes($data['tar']));
			$links->setActiveStatus((int) $data['act']);
			$links->setDisableCheckStatus((int) $data['che']);
			$links->setValidStatus((int) $data['vali']);
			$links->update();
			
			if(strlen($data['nam']) and $data['val'])
			{
				$param = new ilParameterAppender($this->object->getId());
				$param->setName(ilUtil::stripSlashes($data['nam']));
				$param->setValue((int) $data['val']);
				$param->add($link_id);
			}

			if($this->isContainerMetaDataRequired())
			{
				$this->object->setTitle(ilUtil::stripSlashes($data['tit']));
				$this->object->setDescription(ilUtil::stripSlashes($data['des']));
				$this->object->update();
			}
			
			// TODO: Dynamic parameters
		}
			
		ilUtil::sendSuccess($this->lng->txt('settings_saved'),TRUE);
		$ilCtrl->redirect($this,'view');							
	}
	
	/**
	 * Set form values from link
	 * @param object $a_link_id
	 * @return 
	 */
	protected function setValuesFromLink($a_link_id)
	{
		include_once './Modules/WebResource/classes/class.ilLinkResourceItems.php';
		$link = new ilLinkResourceItems($this->object->getId());
		
		$values = $link->getItem($a_link_id);
		
		if(ilParameterAppender::_isEnabled())
		{
		}
		
		$this->form->setValuesByArray(
			array(
				'tit'		=> $values['title'],
				'tar'		=> $values['target'],
				'des'		=> $values['description'],
				'act'		=> (int) $values['active'],
				'che'		=> (int) $values['disable_check'],
				'vali'		=> (int) $values['valid']
			)
		);				
	}
	
	
	/**
	 * Check input after creating a new link
	 * @param object $a_mode
	 * @param object $a_webr_id [optional]
	 * @param object $a_link_id [optional]
	 * @return 
	 */
	protected function checkLinkInput($a_mode,$a_webr_id = 0,$a_link_id = 0)
	{
		$valid = $this->form->checkInput();
		
		include_once './Modules/WebResource/classes/class.ilLinkResourceItems.php';
		$this->link = new ilLinkResourceItems($a_webr_id);
		$this->link->setTarget($this->form->getInput('tar'));
		$this->link->setTitle($this->form->getInput('tit'));
		$this->link->setDescription($this->form->getInput('des'));
		$this->link->setDisableCheckStatus($this->form->getInput('che'));
		$this->link->setActiveStatus($this->form->getInput('act'));
		
		if($a_mode == self::LINK_MOD_EDIT)
		{
			$this->link->setValidStatus($this->form->getInput('val'));
		}
		
		if(!ilParameterAppender::_isEnabled())
		{
			return $valid;
		}
		
		$this->dynamic = new ilParameterAppender($a_webr_id);
		$this->dynamic->setName($this->form->getInput('nam'));
		$this->dynamic->setValue($this->form->getInput('val'));
		if(!$this->dynamic->validate())
		{
			switch($this->dynamic->getErrorCode())
			{
				case LINKS_ERR_NO_NAME:
					$this->form->getItemByPostVar('nam')->setAlert($this->lng->txt('links_no_name_given'));
					return false;
					
				case LINKS_ERR_NO_VALUE:
					$this->form->getItemByPostVar('val')->setAlert($this->lng->txt('links_no_value_given'));
					return false;
					
				case LINKS_ERR_NO_NAME_VALUE:
					// Nothing entered => no error
					return $valid;
			}
			$this->dynamic = null;
		}
		return $valid;
	}

	
	/**
	 * Show create/edit single link
	 * @param int form mode
	 * @return 
	 */
	protected function initFormLink($a_mode)
	{
		global $ilTabs;
	
		$ilTabs->activateTab("id_content");
	
		include_once './Services/Form/classes/class.ilPropertyFormGUI.php';
		$this->form = new ilPropertyFormGUI();
		
		switch($a_mode)
		{
			case self::LINK_MOD_CREATE:
				// Header
				$this->ctrl->setParameter($this,'new_type','webr');
				$this->form->setTitle($this->lng->txt('webr_new_link'));
				$this->form->setTableWidth('600px');

				// Buttons
				$this->form->addCommandButton('save', $this->lng->txt('webr_add'));
				$this->form->addCommandButton('cancel', $this->lng->txt('cancel'));
				break;
				
			case self::LINK_MOD_ADD:
				// Header
				$this->form->setTitle($this->lng->txt('webr_new_link'));

				// Buttons
				$this->form->addCommandButton('saveAddLink', $this->lng->txt('webr_add'));
				$this->form->addCommandButton('view', $this->lng->txt('cancel'));
				break;

			case self::LINK_MOD_EDIT:
				// Header
				$this->ctrl->setParameter($this,'link_id',(int) $_REQUEST['link_id']);
				$this->form->setTitle($this->lng->txt('webr_edit'));
				
				// Buttons
				$this->form->addCommandButton('updateLink', $this->lng->txt('save'));
				$this->form->addCommandButton('view', $this->lng->txt('cancel'));
				break;			
		}
		
		
		$this->form->setFormAction($this->ctrl->getFormAction($this));
		
		if($a_mode == self::LINK_MOD_ADD and $this->isContainerMetaDataRequired())
		{
			ilUtil::sendInfo($this->lng->txt('webr_container_info'));
			
			
			$this->form->setTitle($this->lng->txt('webr_edit_list'));
			
			// List Title
			$title = new ilTextInputGUI($this->lng->txt('webr_list_title'),'lti');
			$title->setRequired(true);
			$title->setSize(40);
			$title->setMaxLength(127);
			$this->form->addItem($title);
			
			// List Description
			$desc = new ilTextAreaInputGUI($this->lng->txt('webr_list_desc'),'tde');
			$desc->setRows(3);
			$desc->setCols(40);
			$this->form->addItem($desc);
			
			// Addtional section
			$sect = new ilFormSectionHeaderGUI();
			$sect->setTitle($this->lng->txt('webr_add'));
			$this->form->addItem($sect);
		}

		// Target
		$tar = new ilTextInputGUI($this->lng->txt('webr_link_target'),'tar');
		$tar->setValue("http://");
		$tar->setRequired(true);
		$tar->setSize(40);
		$tar->setMaxLength(500);
		$this->form->addItem($tar);
		
		// Title
		$tit = new ilTextInputGUI($this->lng->txt('webr_link_title'),'tit');
		$tit->setRequired(true);
		$tit->setSize(40);
		$tit->setMaxLength(127);
		$this->form->addItem($tit);
		
		// Description
		$des = new ilTextAreaInputGUI($this->lng->txt('description'),'des');
		$des->setRows(3);
		$des->setCols(40);
		$this->form->addItem($des);
		
		// Active
		$act = new ilCheckboxInputGUI($this->lng->txt('active'),'act');
		$act->setChecked(true);
		$act->setValue(1);
		$this->form->addItem($act);
		
		// Check
		$che = new ilCheckboxInputGUI($this->lng->txt('webr_disable_check'),'che');
		$che->setValue(1);
		$this->form->addItem($che);
		
		// Valid
		if($a_mode == self::LINK_MOD_EDIT)
		{
			$val = new ilCheckboxInputGUI($this->lng->txt('valid'),'vali');
			$this->form->addItem($val);
		}
		
		if(ilParameterAppender::_isEnabled())
		{
			$dyn = new ilNonEditableValueGUI($this->lng->txt('links_dyn_parameter'));
			$dyn->setInfo($this->lng->txt('links_dynamic_info'));
			
			// Dynyamic name
			$nam = new ilTextInputGUI($this->lng->txt('links_name'),'nam');
			$nam->setSize(12);
			$nam->setMaxLength(128);
			$dyn->addSubItem($nam);
			
			// Dynamic value
			$val = new ilSelectInputGUI($this->lng->txt('links_value'),'val');
			$val->setOptions(ilParameterAppender::_getOptionSelect());
			$val->setValue(0);
			$dyn->addSubItem($val);
			
			$this->form->addItem($dyn);
		}
	}
	
	/**
	 * Check if a new container title is required
	 * Necessary if there is more than one link
	 * @return 
	 */
	protected function isContainerMetaDataRequired()
	{
		include_once './Modules/WebResource/classes/class.ilLinkResourceItems.php';
		return ilLinkResourceItems::lookupNumberOfLinks($this->object->getId()) == 1;
	}
	
	/**
	 * Switch between "View" "Manage" and "Sort"
	 * @return 
	 */
	protected function switchViewModeObject()
	{
		global $ilCtrl;
		
		$_REQUEST['view_mode'] = $_GET['view_mode'] = (int) $_GET['switch_mode'];
		$this->viewObject();
	}
	
	/**
	 * Start with manage mode
	 * @return 
	 */
	protected function editLinksObject()
	{
		$_GET['switch_mode'] = self::VIEW_MODE_MANAGE;
		$this->switchViewModeObject();
	}
	

	/**
	 * View object 
	 * @return 
	 */
	public function viewObject()
	{
		global $ilAccess, $ilErr, $ilTabs;
		
		$ilTabs->activateTab("id_content");
		
		$this->checkPermission('read');
		
		if (strtolower($_GET["baseClass"]) == "iladministrationgui")
		{
			parent::viewObject();
			return true;
		}
		else
		{
			switch((int) $_REQUEST['view_mode'])
			{
				case self::VIEW_MODE_MANAGE:
					$this->manageObject();
					break;
					
				case self::VIEW_MODE_SORT:
					$this->sortObject();
					break;
				
				default:
					$this->showLinksObject();
					break;
			}
		}
		return true;
	}
	
	/**
	 * Manage links
	 * @return 
	 */
	protected function manageObject()
	{
		$this->checkPermission('write');
		$this->activateTabs('content','id_content_manage');
		
		$this->tpl->addBlockFile('ADM_CONTENT','adm_content','tpl.webr_manage.html','Modules/WebResource');
		$this->showToolbar('ACTION_BUTTONS');
		
		include_once './Modules/WebResource/classes/class.ilWebResourceEditableLinkTableGUI.php';
		$table = new ilWebResourceEditableLinkTableGUI($this,'view');
		$table->parse();

		$this->tpl->setVariable('TABLE_LINKS',$table->getHTML());
	}
	
	/**
	 * Show all active links
	 * @return 
	 */
	protected function showLinksObject()
	{
		$this->checkPermission('read');
		$this->activateTabs('content','id_content_view');
		
		include_once './Modules/WebResource/classes/class.ilWebResourceLinkTableGUI.php';
		$table = new ilWebResourceLinkTableGUI($this,'showLinks');
		$table->parse();
		
		$this->tpl->addBlockFile('ADM_CONTENT','adm_content','tpl.webr_view.html','Modules/WebResource');
		$this->showToolbar('ACTION_BUTTONS');
		$this->tpl->setVariable('LINK_TABLE',$table->getHTML());
	}
	
	/**
	 * Sort web links
	 * @return 
	 */
	protected function sortObject()
	{
		$this->checkPermission('write');
		$this->activateTabs('content','id_content_ordering');
		
		include_once './Modules/WebResource/classes/class.ilWebResourceLinkTableGUI.php';
		$table = new ilWebResourceLinkTableGUI($this,'sort',true);
		$table->parse();
		
		$this->tpl->addBlockFile('ADM_CONTENT','adm_content','tpl.webr_view.html','Modules/WebResource');
		$this->showToolbar('ACTION_BUTTONS');
		$this->tpl->setVariable('LINK_TABLE',$table->getHTML());
	}
	
	/**
	 * Save nmanual sorting
	 * @return 
	 */
	protected function saveSortingObject()
	{
		$this->checkPermission('write');
		
		include_once './Services/Container/classes/class.ilContainerSorting.php';
		$sort = ilContainerSorting::_getInstance($this->object->getId());
		$sort->savePost((array) $_POST['position']);
		
		ilUtil::sendSuccess($this->lng->txt('settings_saved'),true);
		$this->viewObject();
	}
	
	
	/**
	 * Show toolbar
	 * @param string $a_tpl_var Name of template variable
	 * @return 
	 */
	protected function showToolbar($a_tpl_var)
	{
		global $ilAccess;
		
		if(!$ilAccess->checkAccess('write','',$this->object->getRefId()))
		{
			return;
		}
		
		include_once './Services/UIComponent/Toolbar/classes/class.ilToolbarGUI.php';
		$tool = new ilToolbarGUI();
		$tool->setFormAction($this->ctrl->getFormAction($this));
		$tool->addButton(
			$this->lng->txt('webr_add'),
			$this->ctrl->getLinkTarget($this,'addLink')
		);
		
		$this->tpl->setVariable($a_tpl_var,$tool->getHTML());
		return;
	}
	
	/**
	 * Show delete confirmation screen 
	 * @return 
	 */
	protected function confirmDeleteLinkObject()
	{
		$this->checkPermission('write');
		$this->activateTabs('content','id_content_view');
		
		$link_ids = is_array($_POST['link_ids']) ?
			$_POST['link_ids'] :
			array($_GET['link_id']);
		
		if(!$link_ids)
		{
			ilUtil::sendFailure($this->lng->txt('select_one'));
			$this->viewObject();
			return false;
		}
		
		include_once './Modules/WebResource/classes/class.ilLinkResourceItems.php';
		$links = new ilLinkResourceItems($this->object->getId());
		
		include_once 'Services/Utilities/classes/class.ilConfirmationGUI.php';
		$confirm = new ilConfirmationGUI();
		$confirm->setFormAction($this->ctrl->getFormAction($this,'view'));
		$confirm->setHeaderText($this->lng->txt('webr_sure_delete_items'));
		$confirm->setConfirm($this->lng->txt('delete'), 'deleteLinks');
		$confirm->setCancel($this->lng->txt('cancel'), 'view');
		
		foreach($link_ids as $link_id)
		{
			$link = $links->getItem($link_id);
			$confirm->addItem('link_ids[]', $link_id,$link['title']);
		}
		$this->tpl->setContent($confirm->getHTML());
	}
	
	/**
	 * Delete links
	 * @return 
	 */
	protected function deleteLinksObject()
	{
		global $ilCtrl;
		
		$this->checkPermission('write');
		
		include_once './Modules/WebResource/classes/class.ilLinkResourceItems.php';
		$links = new ilLinkResourceItems($this->object->getId());
		
		foreach($_POST['link_ids'] as $link_id)
		{
			$links->delete($link_id);
		}
		ilUtil::sendSuccess($this->lng->txt('webr_deleted_items'),true);
		$ilCtrl->redirect($this,'view');
	}
	
	/**
	 * Deactivate links
	 * @return 
	 */
	protected function deactivateLinkObject()
	{
		global $ilCtrl;
		
		$this->checkPermission('write');
		
		include_once './Modules/WebResource/classes/class.ilLinkResourceItems.php';
		$links = new ilLinkResourceItems($this->object->getId());

		if(!$_GET['link_id'])
		{
			ilUtil::sendFailure($this->lng->txt('select_one'),true);
			$ilCtrl->redirect($this,'view');
		}
		
		$links->setLinkId((int) $_GET['link_id']);
		$links->updateActive(false);
		
		ilUtil::sendSuccess($this->lng->txt('webr_inactive_success'),true);
		$ilCtrl->redirect($this,'view');
	}
	

	/**
	* this one is called from the info button in the repository
	* not very nice to set cmdClass/Cmd manually, if everything
	* works through ilCtrl in the future this may be changed
	*/
	function infoScreenObject()
	{
		$this->ctrl->setCmd("showSummary");
		$this->ctrl->setCmdClass("ilinfoscreengui");
		$this->infoScreen();
	}

	/**
	* show information screen
	*/
	function infoScreen()
	{
		global $ilAccess, $ilTabs;

		$this->checkPermission('visible');
		$ilTabs->activateTab('id_info');

		include_once("./Services/InfoScreen/classes/class.ilInfoScreenGUI.php");
		$info = new ilInfoScreenGUI($this);
		
		$info->enablePrivateNotes();
		
		// standard meta data
		$info->addMetaDataSections($this->object->getId(),0, $this->object->getType());
		
		// forward the command
		$this->ctrl->forwardCommand($info);
	}


	function historyObject()
	{
		global $ilTabs;
		
		$this->checkPermission('write');
		$ilTabs->activateTab('id_history');

		include_once("classes/class.ilHistoryGUI.php");
		
		$hist_gui =& new ilHistoryGUI($this->object->getId());
		
		$hist_html = $hist_gui->getHistoryTable(array("ref_id" => $_GET["ref_id"], 
													  "cmd" => "history",
													  "cmdClass" =>$_GET["cmdClass"],
													  "cmdNode" =>$_GET["cmdNode"]));
		
		$this->tpl->setVariable("ADM_CONTENT", $hist_html);
	}
	
	/**
	 * 
	 * @see		ilLinkCheckerGUIRowHandling::formatInvalidLinkArray()
	 * @param	array Unformatted array
	 * @return	array Formatted array
	 * @access	public
	 * 
	 */
	public function formatInvalidLinkArray(Array $row)
	{
		$this->object->items_obj->readItem($row['page_id']);
		$row['title'] = $this->object->items_obj->getTitle();	
	
		require_once 'Services/UIComponent/AdvancedSelectionList/classes/class.ilAdvancedSelectionListGUI.php';
		$actions = new ilAdvancedSelectionListGUI();
		$actions->setSelectionHeaderClass('small');	
		$actions->setItemLinkClass('xsmall');		
		$actions->setListTitle($this->lng->txt('actions'));		
		$actions->setId($row['page_id']);
		$this->ctrl->setParameter($this, 'link_id', $row['page_id']);		
		$actions->addItem(
			$this->lng->txt('edit'),
			'',
			$this->ctrl->getLinkTarget($this, 'editLink')
		);
		$this->ctrl->clearParameters($this);
		$row['action_html'] = $actions->getHTML();		
		
		return $row;
	}

	/**
	 * Show link validation
	 * @return 
	 */
	protected function linkCheckerObject()
	{
		global $ilias, $ilUser, $tpl, $ilTabs;
		
		$this->checkPermission('write');
		$ilTabs->activateTab('id_link_check');

		$this->__initLinkChecker();
		$this->object->initLinkResourceItemsObject();
		
		require_once 'Services/LinkChecker/classes/class.ilLinkCheckerTableGUI.php';
		
		$toolbar = new ilToolbarGUI();

		if((bool)$ilias->getSetting('cron_web_resource_check'))
		{
			include_once 'classes/class.ilLinkCheckNotify.php';
			include_once 'Services/Form/classes/class.ilPropertyFormGUI.php';
			
			$chb = new ilCheckboxInputGUI($this->lng->txt('link_check_message_a'), 'link_check_message');
			$chb->setValue(1);
			$chb->setChecked((bool)ilLinkCheckNotify::_getNotifyStatus($ilUser->getId(), $this->object->getId()));
			$chb->setOptionTitle($this->lng->txt('link_check_message_b'));
			
			$toolbar->addInputItem($chb);
			$toolbar->addFormButton($this->lng->txt('save'), 'saveLinkCheck');
			$toolbar->setFormAction($this->ctrl->getLinkTarget($this, 'saveLinkCheck'));
		}

		$tgui = new ilLinkCheckerTableGUI($this, 'linkChecker');
		$tgui->setLinkChecker($this->link_checker_obj)
			 ->setRowHandler($this)
			 ->setRefreshButton($this->lng->txt('refresh'), 'refreshLinkCheck');

		return $tpl->setContent($tgui->prepareHTML()->getHTML().$toolbar->getHTML());
	}
	
	function saveLinkCheckObject()
	{
		global $ilDB,$ilUser;

		include_once './classes/class.ilLinkCheckNotify.php';

		$link_check_notify =& new ilLinkCheckNotify($ilDB);
		$link_check_notify->setUserId($ilUser->getId());
		$link_check_notify->setObjId($this->object->getId());

		if($_POST['link_check_message'])
		{
			ilUtil::sendSuccess($this->lng->txt('link_check_message_enabled'));
			$link_check_notify->addNotifier();
		}
		else
		{
			ilUtil::sendSuccess($this->lng->txt('link_check_message_disabled'));
			$link_check_notify->deleteNotifier();
		}
		$this->linkCheckerObject();

		return true;
	}
		


	function refreshLinkCheckObject()
	{
		$this->__initLinkChecker();

		if(!$this->link_checker_obj->checkPear())
		{
			ilUtil::sendFailure($this->lng->txt('missing_pear_library'));
			$this->linkCheckerObject();

			return false;
		}


		$this->object->initLinkResourceItemsObject();

		// Set all link to valid. After check invalid links will be set to invalid
		$this->object->items_obj->updateValidByCheck();
 		
		foreach($this->link_checker_obj->checkWebResourceLinks() as $invalid)
		{
			$this->object->items_obj->readItem($invalid['page_id']);
			$this->object->items_obj->setActiveStatus(false);
			$this->object->items_obj->setValidStatus(false);
			$this->object->items_obj->update(false);
		}
		
		$this->object->items_obj->updateLastCheck();
		ilUtil::sendSuccess($this->lng->txt('link_checker_refreshed'));

		$this->linkCheckerObject();

		return true;
	}

	function __initLinkChecker()
	{
		global $ilDB;

		include_once './classes/class.ilLinkChecker.php';

		$this->link_checker_obj =& new ilLinkChecker($ilDB,false);
		$this->link_checker_obj->setObjId($this->object->getId());

		return true;
	}
	
	
	/**
	 * Activate tab and subtabs
	 * @param string $a_active_tab
	 * @param string $a_active_subtab [optional]
	 * @return 
	 */
	protected function activateTabs($a_active_tab,$a_active_subtab = '')
	{
		global $ilAccess, $ilCtrl, $ilTabs, $lng;
		
		switch($a_active_tab)
		{
			case 'content':
				if($ilAccess->checkAccess('write','',$this->object->getRefId()))
				{
					$this->lng->loadLanguageModule('cntr');
					
					$this->ctrl->setParameter($this,'switch_mode',self::VIEW_MODE_VIEW);
					$ilTabs->addSubTab('id_content_view',
						$lng->txt('view'),
						$this->ctrl->getLinkTarget($this,'switchViewMode')
					);
					$this->ctrl->setParameter($this,'switch_mode',self::VIEW_MODE_MANAGE);
					$ilTabs->addSubTab('id_content_manage',
						$lng->txt('cntr_manage'),
						$this->ctrl->getLinkTarget($this,'switchViewMode')
					);
					include_once './Modules/WebResource/classes/class.ilLinkResourceItems.php';
					include_once './Services/Container/classes/class.ilContainerSortingSettings.php';
					include_once './Services/Container/classes/class.ilContainer.php';
					if((ilLinkResourceItems::lookupNumberOfLinks($this->object->getId()) > 1)
						and ilContainerSortingSettings::_lookupSortMode($this->object->getId()) == ilContainer::SORT_MANUAL)
					{
						$this->ctrl->setParameter($this,'switch_mode',self::VIEW_MODE_SORT);
						$ilTabs->addSubTab('id_content_ordering',
							$this->lng->txt('cntr_ordering'),
							$this->ctrl->getLinkTarget($this,'switchViewMode')
						);
					}
					
					$ilCtrl->clearParameters($this);
					$ilTabs->activateSubTab($a_active_subtab);
				}				
		}
		
		$ilTabs->activateTab('id_content');
	}
	
	
	/**
	* get tabs
	* @access	public
	*/
	function setTabs()
	{
		global $ilAccess, $ilTabs, $lng;

		if ($ilAccess->checkAccess('read','',$this->object->getRefId()))
		{
			$ilTabs->addTab("id_content",
				$lng->txt("content"),
				$this->ctrl->getLinkTarget($this, "view"));
		}
		
		if ($ilAccess->checkAccess('visible','',$this->ref_id))
		{
			$ilTabs->addTab("id_info",
				$lng->txt("info_short"),
				$this->ctrl->getLinkTarget($this, "infoScreen"));
		}
		
		if($ilAccess->checkAccess('write','',$this->object->getRefId()) and !$this->getCreationMode())
		{
			include_once './Modules/WebResource/classes/class.ilLinkResourceItems.php';
			if(ilLinkResourceItems::lookupNumberOfLinks($this->object->getId()) > 1)
			{
				$ilTabs->addTab("id_settings",
					$lng->txt("settings"),
					$this->ctrl->getLinkTarget($this, "settings"));
			}
			
		}

		if ($ilAccess->checkAccess('write','',$this->object->getRefId()))
		{
			$ilTabs->addTab("id_history",
				$lng->txt("history"),
				$this->ctrl->getLinkTarget($this, "history"));
		}

		if ($ilAccess->checkAccess('write','',$this->object->getRefId()))
		{
			// Check if pear library is available
			if(@include_once('HTTP/Request.php'))
			{
				$ilTabs->addTab("id_link_check",
					$lng->txt("link_check"),
					$this->ctrl->getLinkTarget($this, "linkChecker"));
			}
		}

		if ($ilAccess->checkAccess('write','',$this->object->getRefId()))
		{
			$ilTabs->addTab("id_meta_data",
				$lng->txt("meta_data"),
				$this->ctrl->getLinkTargetByClass('ilmdeditorgui','listSection'));
		}

		if($ilAccess->checkAccess('write','',$this->object->getRefId()))
		{
			$ilTabs->addTab(
				'export',
				$this->lng->txt('export'),
				$this->ctrl->getLinkTargetByClass('ilexportgui','')
			);
		}

		if ($ilAccess->checkAccess('edit_permission','',$this->object->getRefId()))
		{
			$ilTabs->addTab("id_permissions",
				$lng->txt("perm_settings"),
				$this->ctrl->getLinkTargetByClass(array(get_class($this),'ilpermissiongui'), "perm"));
		}
		
	}

	// PRIVATE
	function __prepareOutput()
	{
		// output objects
		$this->tpl->addBlockFile("CONTENT", "content", "tpl.adm_content.html");
		$this->tpl->addBlockFile("STATUSLINE", "statusline", "tpl.statusline.html");

		// output locator
		$this->__setLocator();

		// output message
		if ($this->message)
		{
			ilUtil::sendInfo($this->message);
		}

		// display infopanel if something happened
		ilUtil::infoPanel();

		// set header
		$this->__setHeader();
	}

	function __setHeader()
	{
		include_once './classes/class.ilTabsGUI.php';

		$this->tpl->setCurrentBlock("header_image");
		$this->tpl->setVariable("IMG_HEADER", ilUtil::getImagePath("icon_webr_b.gif"));
		$this->tpl->parseCurrentBlock();
		$this->tpl->setVariable("HEADER",$this->object->getTitle());
		$this->tpl->setVariable("H_DESCRIPTION",$this->object->getDescription());

		$this->setTabs();
	}

	function __setLocator()
	{
		global $tree;
		global $ilias_locator, $lng;

		$this->tpl->addBlockFile("LOCATOR", "locator", "tpl.locator.html", "Services/Locator");

		$counter = 0;
		
		//$this->tpl->touchBlock('locator_separator');
		//$this->tpl->touchBlock('locator_item');
		
		foreach ($tree->getPathFull($this->object->getRefId()) as $key => $row)
		{
			
			//if ($row["child"] == $tree->getRootId())
			//{
			//	continue;
			//}
			
			if($counter++)
			{
				$this->tpl->touchBlock('locator_separator_prefix');
			}

			if ($row["child"] > 0)
			{
				$this->tpl->setCurrentBlock("locator_img");
				$this->tpl->setVariable("IMG_SRC",
					ilUtil::getImagePath("icon_".$row["type"]."_s.gif"));
				$this->tpl->setVariable("IMG_ALT",
					$lng->txt("obj_".$type));
				$this->tpl->parseCurrentBlock();
			}

			$this->tpl->setCurrentBlock("locator_item");

			if($row["type"] == 'webr')
			{
				$this->tpl->setVariable("ITEM",$this->object->getTitle());
				$this->tpl->setVariable("LINK_ITEM",$this->ctrl->getLinkTarget($this));
			}
			elseif ($row["child"] != $tree->getRootId())
			{
				$this->tpl->setVariable("ITEM", $row["title"]);
				$this->tpl->setVariable("LINK_ITEM","./repository.php?ref_id=".$row["child"]);
			}
			else
			{
				$this->tpl->setVariable("ITEM", $this->lng->txt("repository"));
				$this->tpl->setVariable("LINK_ITEM","./repository.php?ref_id=".$row["child"]);
			}

			$this->tpl->parseCurrentBlock();
		}

		$this->tpl->setVariable("TXT_LOCATOR",$this->lng->txt("locator"));
		$this->tpl->parseCurrentBlock();
	}

	function _goto($a_target)
	{
		global $ilAccess, $ilErr, $lng;

		// Will be replaced in future releases by ilAccess::checkAccess()
		if ($ilAccess->checkAccess("read", "", $a_target))
		{
			ilUtil::redirect("ilias.php?baseClass=ilLinkResourceHandlerGUI&ref_id=$a_target");
		}
		else
		{
			// to do: force flat view
			if ($ilAccess->checkAccess("visible", "", $a_target))
			{
				ilUtil::redirect("ilias.php?baseClass=ilLinkResourceHandlerGUI&ref_id=".$a_target."&cmd=infoScreen");
			}
			else
			{
				if ($ilAccess->checkAccess("read", "", ROOT_FOLDER_ID))
				{
					$_GET["cmd"] = "frameset";
					$_GET["target"] = "";
					$_GET["ref_id"] = ROOT_FOLDER_ID;
					ilUtil::sendFailure(sprintf($lng->txt("msg_no_perm_read_item"),
						ilObject::_lookupTitle(ilObject::_lookupObjId($a_target))), true);
					include("repository.php");
					exit;
				}
			}
		}

		$ilErr->raiseError($lng->txt("msg_no_perm_read"), $ilErr->FATAL);
	}
} // END class.ilObjLinkResource
?>
