<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2006 ILIAS open source, University of Cologne            |
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

require_once './Services/User/classes/class.ilObjUser.php';
require_once "Services/Mail/classes/class.ilMailbox.php";
require_once "Services/Mail/classes/class.ilFormatMail.php";
require_once "Services/Mail/classes/class.ilAddressbook.php";
require_once "Services/Mail/classes/class.ilAddressbookTableGUI.php";


/**
* @author Jens Conze
* @version $Id$
*
* @ingroup ServicesMail
* @ilCtrl_Calls ilMailAddressbookGUI: ilMailSearchCoursesGUI, ilMailSearchGroupsGUI, ilMailingListsGUI
*/
class ilMailAddressbookGUI
{
	private $tpl = null;
	private $ctrl = null;
	private $lng = null;
	private $tabs_gui = null;
	
	private $umail = null;
	private $abook = null;

	public function __construct()
	{
		global $tpl, $ilCtrl, $lng, $ilUser, $ilTabs;

		$this->tpl = $tpl;
		$this->ctrl = $ilCtrl;
		$this->lng = $lng;
		$this->tabs_gui =& $ilTabs;
		
		$this->ctrl->saveParameter($this, "mobj_id");

		$this->umail = new ilFormatMail($ilUser->getId());
		$this->abook = new ilAddressbook($ilUser->getId());
	}

	public function executeCommand()
	{
		$this->showSubTabs();

		$forward_class = $this->ctrl->getNextClass($this);
		switch($forward_class)
		{
			case 'ilmailformgui':
				include_once 'Services/Mail/classes/class.ilMailFormGUI.php';

				$this->ctrl->forwardCommand(new ilMailFormGUI());
				break;

			case 'ilmailsearchcoursesgui':
				include_once 'Services/Mail/classes/class.ilMailSearchCoursesGUI.php';

				$this->tabs_gui->setSubTabActive('mail_my_courses');

				$this->ctrl->setReturn($this, "showAddressbook");
				$this->ctrl->forwardCommand(new ilMailSearchCoursesGUI());
				break;

			case 'ilmailsearchgroupsgui':
				include_once 'Services/Mail/classes/class.ilMailSearchGroupsGUI.php';

				$this->tabs_gui->setSubTabActive('mail_my_groups');

				$this->ctrl->setReturn($this, "showAddressbook");
				$this->ctrl->forwardCommand(new ilMailSearchGroupsGUI());
				break;
			
			case 'ilmailinglistsgui':
				include_once 'Services/Mail/classes/class.ilMailingListsGUI.php';

				$this->tabs_gui->setSubTabActive('mail_my_mailing_lists');

				$this->ctrl->setReturn($this, "showAddressbook");
				$this->ctrl->forwardCommand(new ilMailingListsGUI());
				break;

			default:
				$this->tabs_gui->setSubTabActive('mail_my_entries');

				if (!($cmd = $this->ctrl->getCmd()))
				{
					$cmd = "showAddressbook";
				}

				$this->$cmd();
				break;
		}
		return true;
	}

	/**
	 * Check user's input
	 */
	function checkInput($addr_id = 0)
	{
		// check if user login and e-mail-address are empty 
		if (!strcmp(trim($_POST["login"]), "") &&
			!strcmp(trim($_POST["email"]), ""))
		{
			ilUtil::sendInfo($this->lng->txt("mail_enter_login_or_email_addr"));
			$error = true;
		}
		else if ($_POST["login"] != "" && 
				 !(ilObjUser::_lookupId(ilUtil::stripSlashes($_POST["login"]))))
		{
			ilUtil::sendInfo($this->lng->txt("mail_enter_valid_login"));
			$error = true;
		}
		else if ($_POST["email"] &&
				 !(ilUtil::is_email($_POST["email"])))
		{
			ilUtil::sendInfo($this->lng->txt("mail_enter_valid_email_addr"));
			$error = true;
		}

		if (($this->existingEntry = $this->abook->checkEntryByLogin(ilUtil::stripSlashes($_POST["login"]))) > 0 &&
			(($this->existingEntry != $addr_id && $addr_id > 0) || !$addr_id))
		{
			ilUtil::sendInfo($this->lng->txt("mail_entry_exists"));
			$error = true;
		}

		return $error ? false : true; 
	}

	/**
	 * Save/edit entry
	 */
	public function saveEntry()
	{
		global $lng;
		
		if ($this->checkInput($_GET["addr_id"]))
		{
			if ($_GET["addr_id"])
			{
				$this->abook->updateEntry(ilUtil::stripSlashes($_GET["addr_id"]),
										  ilUtil::stripSlashes($_POST["login"]),
									      ilUtil::stripSlashes($_POST["firstname"]),
										  ilUtil::stripSlashes($_POST["lastname"]),
										  ilUtil::stripSlashes($_POST["email"]));
				ilUtil::sendInfo($lng->txt("mail_entry_changed"));
			}
			else
			{
				$this->abook->addEntry(ilUtil::stripSlashes($_POST["login"]),
									   ilUtil::stripSlashes($_POST["firstname"]),
						 			   ilUtil::stripSlashes($_POST["lastname"]),
									   ilUtil::stripSlashes($_POST["email"]));
				ilUtil::sendInfo($lng->txt("mail_entry_added"));
			}
			
			unset($_SESSION['addr_search']);
			
			$this->showAddressbook();
		}
		else
		{
			$this->showAddressForm();
		}
		
		return true;
	}
	
	/**
	 * Confirm delete entry
	 */
	function confirmDelete()
	{
		global $lng;
		
		if (!isset($_POST['addr_id']))
	 	{
	 		ilUtil::sendInfo($this->lng->txt('mail_select_one_entry'));
	 		$this->showAddressbook();	 		
	 		return true;
	 	}
		
		include_once("Services/Utilities/classes/class.ilConfirmationGUI.php");
		$c_gui = new ilConfirmationGUI();
		
		// set confirm/cancel commands
		$c_gui->setFormAction($this->ctrl->getFormAction($this, "performDelete"));
		$c_gui->setHeaderText($this->lng->txt("mail_sure_delete_entry"));
		$c_gui->setCancel($this->lng->txt("cancel"), "showAddressbook");
		$c_gui->setConfirm($this->lng->txt("confirm"), "performDelete");

		// add items to delete
		foreach($_POST["addr_id"] as $addr_id)
		{
			$entry = $this->abook->getEntry($addr_id);
			$c_gui->addItem("addr_id[]", $addr_id, $entry["login"] ? $entry["login"] : $entry["email"]);
		}
		
		$this->tpl->setVariable("HEADER", $this->lng->txt("mail"));
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.mail_addressbook.html", "Services/Mail");
		$this->tpl->setVariable('DELETE_CONFIRMATION', $c_gui->getHTML());
		
		$this->tpl->show();
		
		return true;
	}
	
	/**
	 * Delete entry
	 */
	function performDelete()
	{
		global $lng;
		
		if (is_array($_POST['addr_id']))
		{			
			if ($this->abook->deleteEntries($_POST['addr_id']))
			{
				ilUtil::sendInfo($lng->txt("mail_deleted_entry"));
			}
			else
			{
				ilUtil::sendInfo($lng->txt("mail_delete_error"));
			}
		}
		else
		{
			ilUtil::sendInfo($lng->txt("mail_delete_error"));
		}
		
		$this->showAddressbook();
	
		return true;	
	}

	/**
	 * Cancel action
	 */
	function cancel()
	{
		$this->showAddressbook();
	}
	
	public function showAddressForm()
	{
		global $rbacsystem, $lng, $ilUser;

		$this->tpl->setVariable("HEADER", $this->lng->txt("mail"));		
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.mail_addressbook_form.html", "Services/Mail");
		
		include_once("./Services/Form/classes/class.ilPropertyFormGUI.php");
		$form = new ilPropertyFormGUI();		
		$form->setTitle($_GET['addr_id'] ? $lng->txt("mail_edit_entry") : $lng->txt("mail_new_entry"));	
		
		if ($_GET['addr_id'])
		{
			$this->ctrl->setParameter($this, 'addr_id', $_GET['addr_id']);
		}
		
		$entry = $this->abook->getEntry($_GET['addr_id']);
		$form->setFormAction($this->ctrl->getFormAction($this, "saveEntry"));
		
		$formItem = new ilTextInputGUI($this->lng->txt("username"), "login");
		$formItem->setValue(isset($_POST['login']) ? ilUtil::prepareFormOutput($_POST['login'], true) : ilUtil::prepareFormOutput($entry['login']));
		$form->addItem($formItem);
		
		$formItem = new ilTextInputGUI($this->lng->txt("firstname"), "firstname");
		$formItem->setValue(isset($_POST['firstname']) ? ilUtil::prepareFormOutput($_POST['firstname'], true) : ilUtil::prepareFormOutput($entry['firstname']));
		$form->addItem($formItem);
		
		$formItem = new ilTextInputGUI($this->lng->txt("lastname"), "lastname");
		$formItem->setValue(isset($_POST['lastname']) ? ilUtil::prepareFormOutput($_POST['lastname'], true) : ilUtil::prepareFormOutput($entry['lastname']));
		$form->addItem($formItem);
		
		$formItem = new ilTextInputGUI($this->lng->txt("email"), "email");
		$formItem->setValue(isset($_POST['email']) ? ilUtil::prepareFormOutput($_POST['email'], true) : ilUtil::prepareFormOutput($entry['email']));
		$form->addItem($formItem);
		
		$form->addCommandButton('saveEntry',$this->lng->txt('save'));
		$form->addCommandButton('cancel',$this->lng->txt('cancel'));
		
		$this->tpl->setVariable('FORM', $form->getHTML());
		
		$this->tpl->show();

		return true;
	}
	
	public function mailToUsers()
	{
		global $ilUser;
		
		if (!isset($_POST['addr_id']))
	 	{
	 		ilUtil::sendInfo($this->lng->txt('mail_select_one_entry'));
	 		$this->showAddressbook();	 		
	 		return true;
	 	}
	 	
	 	$mail_data = $this->umail->getSavedData();		
		if(!is_array($mail_data))
		{
			$this->umail->savePostData($ilUser->getId(), array(), '', '', '', '', '', '', '', '');
		}	
		
		$members = array();	
		foreach ($_POST['addr_id'] as $addr_id)
		{
			$entry = $this->abook->getEntry($addr_id);
			
			if(!$this->umail->doesRecipientStillExists($entry['login'], $mail_data['rcp_to']))
			{
				$members[] = $entry['login'];
			}
		}
		
		if(count($members))
		{
			$mail_data = $this->umail->appendSearchResult($members, 'to');
			$this->umail->savePostData(
				$mail_data['user_id'],
				$mail_data['attachments'],
				$mail_data['rcp_to'],
				$mail_data['rcp_cc'],
				$mail_data['rcp_bcc'],
				$mail_data['m_type'],
				$mail_data['m_email'],
				$mail_data['m_subject'],
				$mail_data['m_message'],
				$mail_data['use_placeholders']
			);
		}

		ilUtil::redirect("ilias.php?baseClass=ilMailGUI&type=search_res");
	}
	
	public function search()
	{
		$_SESSION['addr_search'] = $_POST['search_qry'];
		
		$this->showAddressbook();
		
		return true;
	}
	
	/**
	 * Show user's addressbook
	 */
	public function showAddressbook()
	{
		global $rbacsystem, $lng, $ilUser;

		$this->tpl->setVariable("HEADER", $this->lng->txt("mail"));		
		$this->tpl->addBlockFile("ADM_CONTENT", "adm_content", "tpl.mail_addressbook.html", "Services/Mail");		

		$this->tpl->setVariable('ACTION', $this->ctrl->getFormAction($this, "saveEntry"));
		$this->tpl->setVariable("TXT_SEARCH_FOR",$this->lng->txt("search_for"));
		$this->tpl->setVariable("BUTTON_SEARCH",$this->lng->txt("send"));
		$this->tpl->setVariable("BUTTON_CANCEL",$this->lng->txt("reset"));
		if (strlen(trim($_SESSION["addr_search"])) > 0)
		{
			$this->tpl->setVariable("VALUE_SEARCH_FOR", ilUtil::prepareFormOutput(trim($_SESSION["addr_search"]), true));
		}
		
		$this->ctrl->setParameter($this, "cmd", "post");		
		$tbl = new ilAddressbookTableGUI($this);
		$tbl->setTitle($lng->txt("mail_addr_entries"));
		$tbl->setRowTemplate("tpl.mail_addressbook_row.html", "Services/Mail");				

	 	$tbl->setDefaultOrderField('login');	
		
		$result = array();
		$this->abook->setSearchQuery($_SESSION['addr_search']);
		$entries = $this->abook->getEntries();
		
		$tbl->addColumn('', 'check', '10%');
	 	$tbl->addColumn($this->lng->txt('login'), 'login', '20%');
	 	$tbl->addColumn($this->lng->txt('firstname'), 'firstname', '20%');
		$tbl->addColumn($this->lng->txt('lastname'), 'lastname', '20%');
		$tbl->addColumn($this->lng->txt('email'), 'email', '20%');
		$tbl->addColumn('', 'edit', '10%');
		
		if (count($entries))
		{		
			$tbl->enable('select_all');				
			$tbl->setSelectAllCheckbox('addr_id');
			
			$counter = 0;
			foreach ($entries as $entry)
			{				
				$result[$counter]['check'] = ilUtil::formCheckbox(0, 'addr_id[]', $entry["addr_id"]);
				
				if ($entry["login"] != "")
				{
					$this->ctrl->setParameterByClass("ilmailformgui", "type", "address");
					$this->ctrl->setParameterByClass("ilmailformgui", "rcp", urlencode($entry["login"]));
					$result[$counter]['login'] = "<a class=\"navigation\" href=\"" .  $this->ctrl->getLinkTargetByClass("ilmailformgui") . "\">" . $entry["login"] . "</a>";
					$this->ctrl->clearParametersByClass("ilmailformgui");
				}				
				
				$result[$counter]['firstname'] = $entry["firstname"];
				$result[$counter]['lastname'] = $entry["lastname"];
				
				if ($rbacsystem->checkAccess("smtp_mail", $this->umail->getMailObjectReferenceId()))
				{
					$this->ctrl->setParameterByClass("ilmailformgui", "type", "address");
					$this->ctrl->setParameterByClass("ilmailformgui", "rcp", urlencode($entry["email"]));
					$result[$counter]['email'] = "<a class=\"navigation\" href=\"" .  $this->ctrl->getLinkTargetByClass("ilmailformgui") . "\">" . $entry["email"] . "</a>";
					$this->ctrl->clearParametersByClass("ilmailformgui");
				}
				else
				{
					$result[$counter]['email'] = $entry["email"];
				}
				
				$this->ctrl->setParameter($this, 'addr_id',  $entry['addr_id']);
				$result[$counter]['edit_text'] = $this->lng->txt("edit");
				$result[$counter]['edit_url'] = $this->ctrl->getLinkTarget($this, "showAddressForm");
				
				++$counter;
			}			
			
			$tbl->addMultiCommand('mailToUsers', $this->lng->txt('send_mail_to'));
			$tbl->addMultiCommand('confirmDelete', $this->lng->txt('delete'));			
		}
		else
		{
			$tbl->disable('header');
			$tbl->disable('footer');

			$tbl->setNoEntriesText($this->lng->txt('mail_search_no'));			
		}

		$tbl->setData($result);
		
		$tbl->addCommandButton('showAddressForm', $this->lng->txt('add'));
		
		$this->tpl->setVariable('TABLE', $tbl->getHTML());		
		
		$this->tpl->show();
		
		return true;
	}

	function showSubTabs()
	{
		$this->tabs_gui->addSubTabTarget('mail_my_entries',
										 $this->ctrl->getLinkTarget($this));
		$this->tabs_gui->addSubTabTarget('mail_my_mailing_lists',
										 $this->ctrl->getLinkTargetByClass('ilmailinglistsgui'));
		$this->tabs_gui->addSubTabTarget('mail_my_courses',
										 $this->ctrl->getLinkTargetByClass('ilmailsearchcoursesgui'));
		$this->tabs_gui->addSubTabTarget('mail_my_groups',
										 $this->ctrl->getLinkTargetByClass('ilmailsearchgroupsgui'));		
	}
}
?>