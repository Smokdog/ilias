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
* Class ilPaymentPrices
* 
* @author Stefan Meyer <smeyer@databay.de>
* @version $Id$
*
* @package ilias-core
*/
class ilPaymentPrices
{
	var $ilDB;

	var $pobject_id;
	var $unit_value;
	var $sub_unit_value;
	var $currency;
	var $duration;

	var $prices;

	function ilPaymentPrices($a_pobject_id = 0)
	{
		global $ilDB;

		$this->db =& $ilDB;

		$this->pobject_id = $a_pobject_id;

		$this->__read();
	}

	// SET GET
	function getPobjectId()
	{
		return $this->pobject_id;
	}

	function getPrices()
	{
		return $this->prices ? $this->prices : array();
	}
	function getPrice($a_price_id)
	{
		return $this->prices[$a_price_id] ? $this->prices[$a_price_id] : array();
	}

	// STATIC
	function _getPrice($a_price_id)
	{
		global $ilDB;

		$query = "SELECT * FROM payment_prices ".
			"WHERE price_id = '".$a_price_id."'";

		$res = $ilDB->query($query);

		while($row = $res->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$price['duration'] = $row->duration;
			$price['currency'] = $row->currency;
			$price['unit_value'] = $row->unit_value;
			$price['sub_unit_value'] = $row->sub_unit_value;
		}
		return count($price) ? $price : array();
	}


	function setUnitValue($a_value = 0)
	{
		// substitute leading zeros with ''
		$this->unit_value = preg_replace('/^0+/','',$a_value);
	}
	function setSubUnitValue($a_value = 0)
	{
		$this->sub_unit_value = $a_value;
	}
	function setCurrency($a_currency_id)
	{
		$this->currency = $a_currency_id;
	}
	function setDuration($a_duration)
	{
		$this->duration = $a_duration;
	}

	function add()
	{
		$query = "INSERT INTO payment_prices SET ".
			"pobject_id = '".$this->getPobjectId()."', ".
			"currency = '".$this->__getCurrency()."', ".
			"duration = '".$this->__getDuration()."', ".
			"unit_value = '".$this->__getUnitValue()."', ".
			"sub_unit_value = '".$this->__getSubUnitValue()."'";

		$res = $this->db->query($query);

		$this->__read();
		
		return true;
	}
	function update($a_price_id)
	{

		$query = "UPDATE payment_prices SET ".
			"currency = '".$this->__getCurrency()."', ".
			"duration = '".$this->__getDuration()."', ".
			"unit_value = '".$this->__getUnitValue()."', ".
			"sub_unit_value = '".$this->__getSubUnitValue()."' ".
			"WHERE price_id = '".$a_price_id."'";

		$res = $this->db->query($query);

		$this->__read();

		return true;
	}
	function delete($a_price_id)
	{
		$query = "DELETE FROM payment_prices ".
			"WHERE price_id = '".$a_price_id."'";

		$res = $this->db->query($query);
		

		$this->__read();

		return true;
	}
	function deleteAllPrices()
	{
		$query = "DELETE FROM payment_prices ".
			"WHERE pobject_id = '".$this->getPobjectId()."'";

		$res = $this->db->query($query);
		
		$this->__read();

		return true;
	}

	function validate()
	{
		$duration_valid = false;
		$price_valid = false;

		if(preg_match('/^[1-9][0-9]{0,1}$/',$this->__getDuration()))
		{
			$duration_valid = true;
		}
		
		if(preg_match('/^[1-9]{1,5}$/',$this->__getUnitValue()) and
		   preg_match('/^\d{0,2}$/',$this->__getSubUnitValue()))
		{
			$price_valid = true;
		}
		else if(preg_match('/^\d{0,5}$/',$this->__getUnitValue()) and
				preg_match('/[1-9]/',$this->__getSubUnitValue()))
		{
			return true;
		}
		return $duration_valid and $price_valid;
	}
	// STATIC
	function _priceExists($a_price_id,$a_pobject_id)
	{
		global $ilDB;

		$query = "SELECT * FROM payment_prices ".
			"WHERE price_id = '".$a_price_id."' ".
			"AND pobject_id = '".$a_pobject_id."'";

		$res = $ilDB->query($query);

		return $res->numRows() ? true : false;
	}


				  
	// PRIVATE
	function __getUnitValue()
	{
		return $this->unit_value;
	}
	function __getSubUnitValue()
	{
		return $this->sub_unit_value;
	}
	function __getCurrency()
	{
		return $this->currency;
	}
	function __getDuration()
	{
		return $this->duration;
	}

	function __read()
	{
		$this->prices = array();

		$query = "SELECT * FROM payment_prices ".
			"WHERE pobject_id = '".$this->getPobjectId()."' ".
			"ORDER BY duration";

		$res = $this->db->query($query);
		while($row = $res->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$this->prices[$row->price_id]['pobject_id'] = $row->pobject_id;
			$this->prices[$row->price_id]['price_id'] = $row->price_id;
			$this->prices[$row->price_id]['currency'] = $row->currency;
			$this->prices[$row->price_id]['duration'] = $row->duration;
			$this->prices[$row->price_id]['unit_value'] = $row->unit_value;
			$this->prices[$row->price_id]['sub_unit_value'] = $row->sub_unit_value;
		}
	}
}
?>