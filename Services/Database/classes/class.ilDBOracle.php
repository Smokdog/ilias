<?php
/* Copyright (c) 1998-2009 ILIAS open source, Extended GPL, see docs/LICENSE */


include_once ("./Services/Database/classes/class.ilDB.php");

/**
* Oracle Database Wrapper
*
* This class extends the main ILIAS database wrapper ilDB. Only a few
* methods should be overwritten, that contain Oracle specific statements
* and methods.
*
* @author Alex Killing <alex.killing@gmx.de>
* @version $Id: class.ilDB.php 18989 2009-02-15 12:57:19Z akill $
* @ingroup ServicesDatabase
*/
class ilDBOracle extends ilDB
{

	/**
	* Get DSN.
	*/
	function getDSN()
	{
		// TODO: check if there is another solution.
		// This works with 11g
		if(!isset($GLOBALS['_MDB2_dsninfo_default']['charset']) or		
			$GLOBALS['_MDB2_dsninfo_default']['charset'] != 'utf8')
		{
			$GLOBALS['_MDB2_dsninfo_default']['charset'] = 'utf8'; 
		} 

		return "oci8://".$this->getDBUser().":".$this->getDBPassword()."@".
			$this->getDBHost()."/?service=".$this->getDBName();
	}

	/**
	* Get DB Type
	*/
	function getDBType()
	{
		return "oracle";
	}
	
	public function getDBVersion()
	{
		$query = 'SELECT * FROM v$version';
		$res = $this->db->query($query);
		
		if(MDB2::isError($res))
		{
			return parent::getDBVersion();
		}
		$row = $res->fetchRow(DB_FETCHMODE_ASSOC);
		return isset($row['banner']) ? $row['banner'] : parent::getDBVersion();
	}
	
	/**
	* Get reserved words
	*/
	static function getReservedWords()
	{
		// version: 10g
		// url: http://download-west.oracle.com/docs/cd/B14117_01/server.101/b10759/ap_keywd.htm#g691972
		return array(
			"ACCESS", "ADD", "ALL", "ALTER", "AND", "ANY", "AS", "ASC",
			"AUDIT", "BETWEEN", "BY", "CHAR", "CHECK", "CLUSTER", "COLUMN",
			"COMMENT", "COMPRESS", "CONNECT", "CREATE", "CURRENT", "DATE",
			"DECIMAL", "DEFAULT", "DELETE", "DESC", "DISTINCT", "DROP", "ELSE",
			"EXCLUSIVE", "EXISTS", "FILE", "FLOAT", "FOR", "FROM", "GRANT", "GROUP",
			"HAVING", "IDENTIFIED", "IMMEDIATE", "IN", "INCREMENT", "INDEX", "INITIAL",
			"INSERT", "INTEGER", "INTERSECT", "INTO", "IS", "LEVEL", "LIKE", "LOCK", "LONG",
			"MAXEXTENTS", "MINUS", "MLSLABEL", "MODE", "MODIFY", "NOAUDIT", "NOCOMPRESS", "NOT",
			"NOWAIT", "NULL", "NUMBER", "OF", "OFFLINE", "ON", "ONLINE","OPTION",
			"OR", "ORDER", "PCTFREE", "PRIOR", "PRIVILEGES", "PUBLIC", "RAW", "RENAME",
			"RESOURCE", "REVOKE", "ROW", "ROWID", "ROWNUM", "ROWS", "SELECT", "SESSION", "SET",
			"SHARE", "SIZE", "SMALLINT", "START", "SUCCESSFUL", "SYNONYM", "SYSDATE","TABLE",
			"THEN", "TO", "TRIGGER", "UID", "UNION", "UNIQUE", "UPDATE", "USER","VALIDATE",
			"VALUES", "VARCHAR", "VARCHAR2", "VIEW", "WHENEVER", "WHERE", "WITH"
		);
	}

	/**
	* Initialize the database connection
	*/
	function initConnection()
	{
		$GLOBALS['_MDB2_dsninfo_default']['charset'] = 'utf8'; 
	}

/*	function manipulate($sql)
	{
//echo "1";
//if (!is_int(strpos($sql, "frm_thread_access")))
//{
//echo "2";
		return parent::manipulate($sql);
//}
//echo "3";
	}*/

	/**
	* now()
	* @todo fix this
	*/
	function now()
	{
		return "LOCALTIMESTAMP";
	}
	
	/**
	* Constraint names must be "globally" unique in oracle.
	*/
	function constraintName($a_table, $a_constraint)
	{
		return $a_table."_".$a_constraint;
	}

	/**
	* Primary key identifier
	*/
	function getPrimaryKeyIdentifier()
	{
		return "pk";
	}

	/**
	* Is fulltext index supported?
	*/
	function supportsFulltext()
	{
		return false;
	}

}
?>
