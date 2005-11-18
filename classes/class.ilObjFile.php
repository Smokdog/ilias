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
* Class ilObjFile
*
* @author Sascha Hofmann <shofmann@databay.de> 
* @version $Id$
*
* @extends ilObject
* @package ilias-core
*/

require_once "class.ilObject.php";

class ilObjFile extends ilObject
{
	var $filename;
	var $filetype;
	var $filemaxsize = "20000000";	// not used yet
	var $raise_upload_error;
	var $mode = "object";

	/**
	* Constructor
	* @access	public
	* @param	integer	reference_id or object_id
	* @param	boolean	treat the id as reference_id (true) or object_id (false)
	*/
	function ilObjFile($a_id = 0,$a_call_by_reference = true)
	{
		$this->version = 0;
		$this->type = "file";
		$this->raise_upload_error = true;
		$this->ilObject($a_id,$a_call_by_reference);
	}

	/**
	* create object
	*/
	function create()
	{
		global $ilDB;
		
		parent::create();

		require_once("classes/class.ilHistory.php");
		ilHistory::_createEntry($this->getId(), "create", $this->getFileName().",1");

		$q = "INSERT INTO file_data (file_id,file_name,file_type,version,mode) "
			."VALUES ('".$this->getId()."','"
			.ilUtil::prepareDBString($this->getFileName())."','"
			.$this->getFileType()."','"
			."1"."',".$ilDB->quote($this->getMode()).")";
		$this->ilias->db->query($q);
		
		// no meta data handling for file list files
		if ($this->getMode() != "filelist")
		{
			$this->createMetaData();
		}
	}
	
	/**
	* create file object meta data
	*/
	function createMetaData()
	{
		parent::createMetaData();
		
		// add technical section with file size and format
		$md_obj =& new ilMD($this->getId(),0,$this->getType());
		$technical = $md_obj->addTechnical();
		$technical->setSize($this->getFileSize());
		$technical->save();
		$format = $technical->addFormat();
		$format->setFormat($this->getFileType());
		$format->save();
		$technical->update();
	}
	
	/**
	* Meta data update listener
	*
	* Important note: Do never call create() or update()
	* method of ilObject here. It would result in an
	* endless loop: update object -> update meta -> update
	* object -> ...
	* Use static _writeTitle() ... methods instead.
	*
	* @param	string		$a_element
	*/
	function MDUpdateListener($a_element)
	{
		// handling for general section
		parent::MDUpdateListener($a_element);
		
		// handling for technical section 
		include_once 'Services/MetaData/classes/class.ilMD.php';
//echo "-".$a_element."-";
		switch($a_element)
		{
			case 'Technical':

				// Update Format (size is not stored in db)
				$md = new ilMD($this->getId(),0, $this->getType());
				if(!is_object($md_technical = $md->getTechnical()))
				{
					return false;
				}

				foreach($md_technical->getFormatIds() as $id)
				{
					$md_format = $md_technical->getFormat($id);
					ilObjFile::_writeFileType($this->getId(),$md_format->getFormat());
					$this->setFileType($md_format->getFormat());
					break;
				}

				break;

			default:
		}
		return true;
	}


	function getDirectory($a_version = 0)
	{
		$version_subdir = "";

		if ($a_version)
		{
			$version_subdir = "/".sprintf("%03d", $a_version);
		}
		
		return ilUtil::getDataDir()."/files/file_".$this->getId().$version_subdir;
	}

	function createDirectory()
	{
		ilUtil::makeDir($this->getDirectory());
	}
	
	function raiseUploadError($a_raise = true)
	{
		$this->raise_upload_error = $a_raise;
	}

	function getUploadFile($a_upload_file, $a_filename)
	{
		$this->setVersion($this->getVersion() + 1);

		if (@!is_dir($this->getDirectory($this->getVersion())))
		{
			ilUtil::makeDir($this->getDirectory($this->getVersion()));
		}

		$file = $this->getDirectory($this->getVersion())."/".$a_filename;
		//move_uploaded_file($a_upload_file, $file);
		ilUtil::moveUploadedFile($a_upload_file, $a_filename, $file, $this->raise_upload_error);
	}

	/**
	* replace file with new file
	*/
	function replaceFile($a_upload_file, $a_filename)
	{
		//$this->clearDataDirectory();		// ! This has to be changed, if multiple versions should be supported
		$this->getUploadFile($a_upload_file, $a_filename);
		
		require_once("classes/class.ilHistory.php");
		ilHistory::_createEntry($this->getId(), "replace",
			$a_filename.",".$this->getVersion());
	}


	/**
	* copy file
	*/
	function copy($a_source,$a_destination)
	{
		return copy($a_source,$this->getDirectory()."/".$a_destination);
	}
	
	/**
	* clear data directory
	*/
	function clearDataDirectory()
	{
		ilUtil::delDir($this->getDirectory());
		$this->createDirectory();
	}

	/**
	* read file properties
	*/
	function read()
	{
		parent::read();

		$q = "SELECT * FROM file_data WHERE file_id = '".$this->getId()."'";
		$r = $this->ilias->db->query($q);
		$row = $r->fetchRow(DB_FETCHMODE_OBJECT);

		$this->setFileName($row->file_name);
		$this->setFileType($row->file_type);
		$this->setVersion($row->version);
		$this->setMode($row->mode);
	}

	/**
	* update file
	*/
	function update()
	{
		global $ilDB;
		
		// no meta data handling for file list files
		if ($this->getMode() != "filelist")
		{
			$this->updateMetaData();
		}
		parent::update();
		
		$q = "UPDATE file_data SET file_name = '".ilUtil::prepareDBString($this->getFileName()).
			"', file_type = '".$this->getFiletype()."' ".
			", version = '".$this->getVersion()."' ".
			", mode = ".$ilDB->quote($this->getMode())." ".
			"WHERE file_id = '".$this->getId()."'";
		$this->ilias->db->query($q);
		
		return true;
	}
	
	/**
	* update meta data
	*/
	function updateMetaData()
	{
		parent::updateMetaData();
		
		// add technical section with file size and format
		$md_obj =& new ilMD($this->getId(),0,$this->getType());
		if(!is_object($technical = $md_obj->getTechnical()))
		{
			$technical = $md_obj->addTechnical();
			$technical->save();
		}
		$technical->setSize($this->getFileSize());
		
		$format_ids = $technical->getFormatIds();
		if (count($format_ids) > 0)
		{
			$format = $technical->getFormat($format_ids[0]);
			$format->setFormat($this->getFileType());
			$format->update();
		}
		else
		{
			$format = $technical->addFormat();
			$format->setFormat($this->getFileType());
			$format->save();
		}
		$technical->update();
	}

	/**
	* set filename
	*/
	function setFileName($a_name)
	{
		$this->filename = $a_name;
	}

	function getFileName()
	{
		return $this->filename;
	}

	function setFileType($a_type)
	{
		$this->filetype = $a_type;
	}

	function getFileType()
	{
		return $this->filetype;
	}

	function setFileSize($a_size)
	{
		$this->filesize = $a_size;
	}

	function getFileSize()
	{
		return $this->filesize;
	}
	
	function setVersion($a_version)
	{
		$this->version = $a_version;
	}

	function getVersion()
	{
		return $this->version;
	}
	
	/**
	* mode is object or filelist
	*
	* @param	string		$a_mode		mode
	*/
	function setMode($a_mode)
	{
		$this->mode = $a_mode;
	}

	/**
	* mode is object or filelist
	*
	* @return	string		mode
	*/
	function getMode()
	{
		return $this->mode;
	}
	
	function _writeFileType($a_id ,$a_format)
	{
		global $ilDB;
		
		$q = "UPDATE file_data SET ".
			" file_type = ".$ilDB->quote($a_format).
			" WHERE file_id = ".$ilDB->quote($a_id);
		$ilDB->query($q);
		
	}

	function _lookupFileName($a_id)
	{
		global $ilDB;

		$q = "SELECT * FROM file_data WHERE file_id = '".$a_id."'";
		$r = $ilDB->query($q);
		$row = $r->fetchRow(DB_FETCHMODE_OBJECT);

		return ilUtil::stripSlashes($row->file_name);
	}


	function _lookupFileSize($a_id, $a_as_string = false)
	{
		global $ilDB;

		$q = "SELECT * FROM file_data WHERE file_id = '".$a_id."'";
		$r = $ilDB->query($q);
		$row = $r->fetchRow(DB_FETCHMODE_OBJECT);

		$file = ilUtil::getDataDir()."/files/file_".$a_id."/".$row->file_name;

		if (@!is_file($file))
		{
			$version_subdir = "/".sprintf("%03d", ilObjFile::_lookupVersion($a_id));
			$file = ilUtil::getDataDir()."/files/file_".$a_id.$version_subdir."/".$row->file_name;
		}

		if (is_file($file))
		{
			$size = filesize($file);
		}
		else
		{
			$size = 0;
		}
		
		if ($a_as_string)
		{
			if ($size > 1000000)
			{
				return round($size/1000000,2)." MB";
			}
			else if ($size > 1000)
			{
				return round($size/1000,2)." KBytes";
			}
			else
			{
				return $size." Bytes";
			}
			
		}
		
		return $size;
	}
	
	/**
	* lookup version
	*/
	function _lookupVersion($a_id)
	{
		global $ilDB;

		$q = "SELECT * FROM file_data WHERE file_id = '".$a_id."'";
		$r = $ilDB->query($q);
		$row = $r->fetchRow(DB_FETCHMODE_OBJECT);

		return ilUtil::stripSlashes($row->version);
	}

	function sendFile($a_hist_entry_id = null)
	{	
		if (is_null($a_hist_entry_id))
		{
			$file = $this->getDirectory($this->getVersion())."/".$this->getFileName();

			// if not found lookup for file in file object's main directory for downward c	ompability
			if (@!is_file($file))
			{
				$file = $this->getDirectory()."/".$this->getFileName();
			}
		}
		else
		{
			require_once("classes/class.ilHistory.php");
			$entry = ilHistory::_getEntryByHistoryID($a_hist_entry_id);
			
			if ($entry === false)
			{
				echo "3";return false;
			}

			$data = explode(",",$entry["info_params"]);
			
			// bugfix: first created file had no version number
			// this is a workaround for all files created before the bug was fixed
			if (empty($data[1]))
			{
				$data[1] = "1";
			}

			$file = $this->getDirectory($data[1])."/".$data[0];
			
			// if not found lookup for file in file object's main directory for downward compability
			if (@!is_file($file))
			{
				$file = $this->getDirectory()."/".$data[0];
			}

			if (@is_file($file))
			{
				ilUtil::deliverFile($file, $data[0]);
				return true;
			}
		}

		if (@is_file($file))
		{
			ilUtil::deliverFile($file, $this->getFileName());
			return true;
		}

		return false;
	}

	function ilClone($a_parent_ref)
	{
		global $ilDB;
		
		// always call parent clone function first!!
		$new_ref_id = parent::ilClone($a_parent_ref);

		$fileObj =& $this->ilias->obj_factory->getInstanceByRefId($new_ref_id);
		$fileObj->createDirectory();
		
		// copy all versions of file
		ilUtil::rCopy($this->getDirectory(),$fileObj->getDirectory());
		//copy($this->getDirectory()."/".$this->getFileName(),$fileObj->getDirectory()."/".$this->getFileName());

		$q = "INSERT INTO file_data (file_id,file_name,file_type,version,mode) VALUES ('"
			.$fileObj->getId()."','"
			.ilUtil::prepareDBString($this->getFileName())."','"
			.$this->getFileType()."','".$this->getVersion()
			."',".$ilDB->quote($this->getMode()).")";

		$this->ilias->db->query($q);

		// copy history entries
		require_once("classes/class.ilHistory.php");
		ilHistory::_copyEntriesForObject($this->getId(),$fileObj->getId());

		// dump object
		unset($fileObj);

		// ... and finally always return new reference ID!!
		return $new_ref_id;
	}

	/**
	* delete file and all related data	
	*
	* @access	public
	* @return	boolean	true if all object data were removed; false if only a references were removed
	*/
	function delete()
	{
		// check, if file is used somewhere
		$usages = $this->getUsages();

		if (count($usages) == 0)
		{
			// always call parent delete function first!!
			if (!parent::delete())
			{
				return false;
			}

			// delete file data entry
			$q = "DELETE FROM file_data WHERE file_id = '".$this->getId()."'";
			$this->ilias->db->query($q);
			
			// delete history entries
			require_once("classes/class.ilHistory.php");
			ilHistory::_removeEntriesForObject($this->getId());
			
			// delete entire directory and its content
			if (@is_dir($this->getDirectory()))
			{
				ilUtil::delDir($this->getDirectory());
			}
			
			// delete meta data
			if ($this->getMode() != "filelist")
			{
				$this->deleteMetaData();
			}

			return true;
		}

		return false;
	}

	/**
	* export files of object to target directory
	* note: target directory must be the export target directory,
	* "/objects/il_<inst>_file_<file_id>/..." will be appended to this directory
	*
	* @param	string		$a_target_dir		target directory
	*/
	function export($a_target_dir)
	{
		$subdir = "il_".IL_INST_ID."_file_".$this->getId();
		ilUtil::makeDir($a_target_dir."/objects/".$subdir);

		$filedir = $this->getDirectory($this->getVersion());
		
		if (@!is_dir($filedir))
		{
			$filedir = $this->getDirectory();
		}
		
		ilUtil::rCopy($filedir, $a_target_dir."/objects/".$subdir);
	}

	/**
	* static delete all usages of
	*/
	function _deleteAllUsages($a_type, $a_id)
	{
		$q = "DELETE FROM file_usage WHERE usage_type='$a_type' AND usage_id='$a_id'";
		$this->ilias->db->query($q);
	}

	/**
	* save usage
	*/
	function _saveUsage($a_mob_id, $a_type, $a_id)
	{
		$q = "REPLACE INTO file_usage (id, usage_type, usage_id) VALUES".
			" ('$a_mob_id', '$a_type', '$a_id')";
		$this->ilias->db->query($q);
	}

	/**
	* get all usages of file object
	*/
	function getUsages()
	{
		global $ilDB;

		// get usages in learning modules
		$q = "SELECT * FROM file_usage WHERE id = '".$this->getId()."'";
		$us_set = $ilDB->query($q);
		$ret = array();
		while($us_rec = $us_set->fetchRow(DB_FETCHMODE_ASSOC))
		{
			$ret[] = array("type" => $us_rec["usage_type"],
				"id" => $us_rec["usage_id"]);
		}

		return $ret;
	}

	/**
	* get all files of an object
	*
	* @param	string		$a_type		object type (e.g. "lm:pg")
	* @param	int			$a_id		object id
	*
	* @return	array		array of file ids
	*/
	function _getFilesOfObject($a_type, $a_id)
	{
		global $ilDB;

		// get usages in learning modules
		$q = "SELECT * FROM file_usage WHERE usage_id = ".$ilDB->quote($a_id).
			" AND usage_type = ".$ilDB->quote($a_type);
		$file_set = $ilDB->query($q);
		$ret = array();
		while($file_rec = $file_set->fetchRow(DB_FETCHMODE_ASSOC))
		{
			$ret[$file_rec["id"]] = $file_rec["id"];
		}

		return $ret;
	}

	// TODO: What is this function good for??
	function getXMLZip()
	{
		global $ilias;

		$zip = PATH_TO_ZIP;

		exec($zip.' '.ilUtil::escapeShellArg($this->getDirectory().'/'.$this->getFileName())." ".
			 ilUtil::escapeShellArg($this->getDirectory().'/'.'1.zip'));

		return $this->getDirectory().'/1.zip';
	}
} // END class.ilObjFile
?>
