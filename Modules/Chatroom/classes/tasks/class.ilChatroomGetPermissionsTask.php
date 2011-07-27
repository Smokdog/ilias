<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */


/**
 * Class ilChatroomGetPermissionsTask
 *
 * Provides methods to upload a file.
 *
 * @author Andreas Korodsz <akordosz@databay.de>
 * @version $Id$
 *
 * @ingroup ModulesChatroom
 */
class ilChatroomGetPermissionsTask extends ilDBayTaskHandler
{

	private $gui;

	/**
	 * Constructor
	 *
	 * @param ilDBayObjectGUI $gui
	 */
	public function __construct(ilDBayObjectGUI $gui)
	{
		$this->gui = $gui;
	}

	/**
	 * Default execute method.
	 *
	 * @param string $requestedMethod
	 */
	public function executeDefault($requestedMethod)
	{
		global $ilUser;

		switch($ilUser->getLogin()) {
			case 'root':
				$kick = $ban = true;
				break;
			default:
				$kick = $ban = false;
		}

		$permissions = array(
		    'kick' => $kick,
		    'ban' => $ban,
		);

		echo json_encode($permissions);
		exit;
	}

}

?>