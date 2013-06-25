<?php
/* Copyright (c) 1998-2010 ILIAS open source, Extended GPL, see docs/LICENSE */

include_once("./Services/Object/classes/class.ilObject2GUI.php");
include_once("./Services/JSON/classes/class.ilJsonUtil.php");
include_once("class.ilCloudPluginFileTreeGUI.php");
include_once("class.ilCloudFileTree.php");
include_once("class.ilCloudConnector.php");

/**
 * Class ilObjCloudGUI
 *
 * @author Timon Amstutz <timon.amstutz@ilub.unibe.ch>
 *
 * @ilCtrl_Calls ilObjCloudGUI: ilPermissionGUI, ilNoteGUI, ilInfoScreenGUI, ilObjectCopyGUI, ilCommonActionDispatcherGUI
 * @ilCtrl_Calls ilObjCloudGUI: ilCloudPluginUploadGUI, ilCloudPluginCreateFolderGUI, ilCloudPluginSettingsGUI,
 * @ilCtrl_Calls ilObjCloudGUI: ilCloudPluginDeleteGUI, ilCloudPluginActionListGUI, ilCloudPluginItemCreationListGUI,
 * @ilCtrl_Calls ilObjCloudGUI: ilCloudPluginFileTreeGUI, ilCloudPluginInitGUI, ilCloudPluginHeaderActionGUI
 *
 * @extends ilObject2GUI
 */
class ilObjCloudGUI extends ilObject2GUI
{

    /**
     *  ilCloudPluginService
     */
    protected $plugin_service;

    /**
     * @param int $a_id
     * @param int $a_id_type
     * @param int $a_parent_node_id
     */
    public function __construct($a_id = 0, $a_id_type = self::REPOSITORY_NODE_ID, $a_parent_node_id = 0)
    {
        global $lng;

        parent::__construct($a_id, $a_id_type, $a_parent_node_id);
        $lng->loadLanguageModule("cld");

    }

    /**
     * Get type.
     */
    final function getType()
    {
        return "cld";
    }

    /*
     * executeCommand
     */
    public function executeCommand()
    {
        global $ilCtrl, $ilTabs, $ilNavigationHistory, $lng;

        // Navigation History
        $link = $ilCtrl->getLinkTarget($this, "render");

        try
        {
            ilCloudConnector::getActiveServices();
        } catch (Exception $e)
        {
            ilUtil::sendFailure($lng->txt("cld_no_service_active"), true);
            ilObjectGUI::redirectToRefId($this->parent_id);
        }

        if ($this->object != NULL)
        {
            $ilNavigationHistory->addItem($this->object->getRefId(), $link, "cld");

            try
            {
                ilCloudConnector::checkServiceActive($this->object->getServiceName());
            }
            catch(Exception $e)
            {
                ilUtil::sendFailure($lng->txt("cld_plugin_not_active"), true);
                ilObjectGUI::redirectToRefId($this->parent_id);
            }

            if($this->object->getAuthComplete() == false && !$_GET["authMode"])
            {
                if ($this->checkPermissionBool("write"))
                {
                    $this->serviceAuth($this->object);
                }
                else
                {
                    ilUtil::sendFailure($lng->txt("cld_auth_failed"),true);
                    ilObjectGUI::redirectToRefId($this->parent_id);
                }
            }
            $this->plugin_service = ilCloudConnector::getServiceClass($this->object->getServiceName(), $this->object->getId(), false);
        }

        $next_class = $ilCtrl->getNextClass($this);

        $cmd = $ilCtrl->getCmd($this);
        switch ($cmd)
        {
            case "editSettings" :
                $next_class = "ilcloudpluginsettingsgui";
                break;
            case "afterServiceAuth" :
                $this->checkPermission("write");
                $this->$cmd();
                return;
            case "render" :
                $this->addHeaderAction();
                break;
        }

        switch ($next_class)
        {
            case "ilcommonactiondispatchergui":
                include_once("Services/Object/classes/class.ilCommonActionDispatcherGUI.php");
                $gui = ilCommonActionDispatcherGUI::getInstanceFromAjaxCall();
                $this->ctrl->forwardCommand($gui);
                break;
            case "ilpermissiongui":
                $this->prepareOutput();
                $ilTabs->activateTab("id_permissions");
                include_once("Services/AccessControl/classes/class.ilPermissionGUI.php");
                $perm_gui = new ilPermissionGUI($this);
                $this->ctrl->forwardCommand($perm_gui);
                break;
            case "ilcloudpluginuploadgui":
                if ($this->checkPermissionBool("upload"))
                {
                    $upload_gui = ilCloudConnector::getUploadGUIClass($this->plugin_service);
                    $this->ctrl->forwardCommand($upload_gui);
                }
                break;
            case "ilcloudplugincreatefoldergui":
                if ($this->checkPermissionBool("create_folders"))
                {
                    $folder_gui = ilCloudConnector::getCreateFolderGUIClass($this->plugin_service);
                    $this->ctrl->forwardCommand($folder_gui);
                }
                break;
            case "ilcloudplugindeletegui":
                if ($this->checkPermissionBool("delete_files") || $this->checkPermissionBool("delete_folders"))
                {
                    $delete_gui = ilCloudConnector::getDeleteGUIClass($this->plugin_service);
                    $this->ctrl->forwardCommand($delete_gui);
                }
                break;
            case "ilcloudpluginsettingsgui":
                $this->prepareOutput();
                if ($this->checkPermissionBool("write"))
                {
                    $settings_gui = ilCloudConnector::getSettingsGUIClass($this->plugin_service);
                    $settings_gui->setCloudObject($this->object);
                    $this->ctrl->forwardCommand($settings_gui);
                }
                break;
            case "ilcloudpluginactionlistgui":
                $action_list_gui = ilCloudConnector::getActionListGUIClass($this->plugin_service);
                $this->ctrl->forwardCommand($action_list_gui);
                break;
            case "ilcloudpluginitemcreationlistgui":
                $item_creation_gui = ilCloudConnector::getItemCreationListGUIClass($this->plugin_service);
                $this->ctrl->forwardCommand($item_creation_gui);
                break;
            case "ilcloudpluginfiletreegui":
                $file_tree_gui = ilCloudConnector::getFileTreeGUIClass($this->plugin_service,ilCloudFileTree::getFileTreeFromSession());
                $this->ctrl->forwardCommand($file_tree_gui);
                break;
            case "ilcloudpluginheaderactiongui":
                $header_action_gui = ilCloudConnector::getHeaderActionGUI($this->plugin_service);
                $this->ctrl->forwardCommand($header_action_gui);
                break;
            case "ilcloudplugininitgui":
                $init_gui = ilCloudConnector::getInitGUIClass($this->plugin_service);
                $this->ctrl->forwardCommand($init_gui);
                break;
            default:
                return parent::executeCommand();
        }

        return true;
    }

    /**
     * Get standard command
     */
    function getStandardCmd()
    {
        return "render";
    }

    /**
     * _goto
     * Deep link
     *
     * @param string $a_target
     */
    public function _goto($a_target)
    {
        $id                = explode("_", $a_target);
        $_GET["ref_id"]    = $id[0];
        $_GET["baseClass"] = "ilrepositorygUI";
        $_GET["cmdClass"]  = "ilobjcloudgui";
        $_GET["cmd"]       = "render";
        include("ilias.php");
    }

    function infoScreen()
    {
        return false;
    }

    /**
     * Set tabs
     */
    function setTabs()
    {
        global $ilTabs, $ilCtrl, $ilAccess, $lng;

        // tab for the "show content" command
        if ($ilAccess->checkAccess("read", "", $this->object->getRefId()))
        {
            $ilTabs->addTab("content", $lng->txt("content"), $ilCtrl->getLinkTarget($this, "render"));
        }

        // a "properties" tab
        if ($ilAccess->checkAccess("write", "", $this->object->getRefId()))
        {
            $ilTabs->addTab("settings", $lng->txt("settings"), $ilCtrl->getLinkTargetByClass("ilcloudpluginsettingsgui", "editSettings"));
        }

        // edit permissions
        if ($ilAccess->checkAccess('edit_permission', "", $this->object->getRefId()))
        {
            $ilTabs->addTab("id_permissions", $lng->txt("perm_settings"), $this->ctrl->getLinkTargetByClass("ilpermissiongui", "perm"));
        }
    }


    /**
     * Init creation froms
     *
     * this will create the default creation forms: new, import, clone
     *
     * @param    string $a_new_type
     * @return    array
     */
    protected function initCreationForms($a_new_type)
    {
        $forms = array(
            self::CFORM_NEW    => $this->initCreateForm($a_new_type)
        );

        return $forms;
    }

    /**
     * Init object creation form
     *
     * @param    string $a_new_type
     * @return    ilPropertyFormGUI
     */
    protected function initCreateForm($a_new_type)
    {
        global $lng;

        include_once("Services/Form/classes/class.ilPropertyFormGUI.php");
        $form = new ilPropertyFormGUI();
        $form->setTarget("_top");
        $form->setFormAction($this->ctrl->getFormAction($this, "save"));
        $form->setTitle($this->lng->txt($a_new_type . "_new"));

        // title
        $ti = new ilTextInputGUI($this->lng->txt("title"), "title");
        $ti->setMaxLength(128);
        $ti->setSize(40);
        $ti->setRequired(true);
        $form->addItem($ti);

        // description
        $ta = new ilTextAreaInputGUI($this->lng->txt("description"), "desc");
        $ta->setCols(40);
        $ta->setRows(2);
        $form->addItem($ta);

        $services_group = new ilRadioGroupInputGUI($lng->txt("cld_service"), "service");
        $services_group->setRequired(true);
        foreach (ilCloudConnector::getActiveServices() as $service)
        {
            $option = new ilRadioOption($service, $service);
            $hook_object = ilCloudConnector::getPluginHookClass($option->getValue());
            $option->setTitle($hook_object->txt($service));
            $option->setInfo($hook_object->txt("create_info"));
            $services_group->addOption($option);
        }

        $form->addItem($services_group);

        $form = $this->initDidacticTemplate($form);

        $form->addCommandButton("save", $this->lng->txt($a_new_type . "_add"));
        $form->addCommandButton("cancel", $this->lng->txt("cancel"));

        return $form;
    }

    /*
    * afterSave
    */
    protected function afterSave(ilObjCloud $a_new_object)
    {
        try
        {
            $form = $this->initCreateForm("cld");

            if ($form->checkInput())
            {
                $a_new_object->setServiceName($form->getInput("service"));
                $a_new_object->setRootFolder("/");
                $a_new_object->setOnline(false);
                $a_new_object->setAuthComplete(false);
                $a_new_object->update();
                $this->serviceAuth($a_new_object);
            }

        } catch (Exception $e)
        {
            ilUtil::sendFailure($e->getMessage());
        }
    }

    protected function serviceAuth($object)
    {
        global $ilCtrl;
        $service = ilCloudConnector::getServiceClass($object->getServiceName(), $object->getId());
        $service->authService($ilCtrl->getLinkTarget($this, "afterServiceAuth") . "&authMode=true");
    }

    protected function afterServiceAuth()
    {
        global $ilCtrl,$lng;

        if($this->plugin_service->afterAuthService())
        {
            $this->object->setRootId("root",true);
            $this->object->setAuthComplete(true);
            $this->object->update();
            $ilCtrl->redirectByClass("ilCloudPluginSettingsGUI", "editSettings");
        }
        else
        {
            ilUtil::sendFailure($lng->txt("cld_auth_failed"),true);
            ilObjectGUI::redirectToRefId($this->parent_id);
        }
    }

    /**
     * Add header action menu
     */
    protected function addHeaderAction()
    {
        $lg = $this->initHeaderAction();
        $header_action_class = ilCloudConnector::getHeaderActionGUI($this->plugin_service);
        $header_action_class->addCustomHeaderAction($lg);
        $this->insertHeaderAction($lg);


    }

    public function render()
    {
        $init_gui = ilCloudConnector::getInitGUIClass($this->plugin_service);
        $init_gui->initGUI($this,   $this->checkPermissionBool("create_folders"),
                                    $this->checkPermissionBool("upload"),
                                    $this->checkPermissionBool("delete_files"),
                                    $this->checkPermissionBool("delete_folders"),
                                    $this->checkPermissionBool("download"),
                                    $this->checkPermissionBool("files_visible"),
                                    $this->checkPermissionBool("folders_visible"));
    }


    public function asyncGetBlock()
    {
        global $tpl;

        $response           = new stdClass();
        $response->message   = null;
        $response->locator  = null;
        $response->content  = null;
        $response->success  = null;

        try{
            $file_tree = ilCloudFileTree::getFileTreeFromSession();
            $file_tree->updateFileTree($_POST["path"]);
            $file_tree_gui = ilCloudConnector::getFileTreeGUIClass($this->plugin_service,$file_tree);
            $response->content = $file_tree_gui->getFolderHtml($this, $_POST["id"], $this->checkPermissionBool("delete_files"), $this->checkPermissionBool("delete_folders"), $this->checkPermissionBool("download"), $this->checkPermissionBool("files_visible"), $this->checkPermissionBool("folders_visible"));
            $response->locator = $file_tree_gui->getLocatorHtml($file_tree->getNodeFromId($_POST["id"]));
            $response->success = true;
        }
        catch(Exception $e)
        {
            $response->message = $tpl->getMessageHTML($e->getMessage(),"failure");
        }

        header('Content-type: application/json');
        echo ilJsonUtil::encode($response);
        exit;
    }

    function getFile()
    {
        global $ilTabs;
        if ($this->checkPermissionBool("download"))
        {
            try{
                $file_tree = ilCloudFileTree::getFileTreeFromSession();
                $file_tree->downloadFromService($_GET['id']);
            } catch (Exception $e)
            {
                $ilTabs->activateTab("content");
                ilUtil::sendFailure($e->getMessage());
            }
        }
    }

    function asyncGetActionListContent()
    {
        $action_list = ilCloudConnector::getActionListGUIClass($this->plugin_service);
        $file_tree = ilCloudFileTree::getFileTreeFromSession();
        return $action_list->asyncGetContent($this->checkPermissionBool("delete_files"), $this->checkPermissionBool("delete_folders"), $file_tree->getNodeFromId($_GET["node_id"]));
    }
}
?>
