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
* Class class.ilregistrationEmailRoleAssignments
*
* @author Stefan Meyer <smeyer@databay.de>
* @version $Id$
* 
* @package ilias-core
*/

define('IL_REG_MISSING_DOMAIN',1);
define('IL_REG_MISSING_ROLE',2);

class ilRegistrationRoleAssignments
{
	var $assignments = array();
	var $default_role = 0;

	function ilRegistrationRoleAssignments()
	{
		global $ilDB;

		$this->db =& $ilDB;
		$this->__read();
	}

	function getRoleByEmail($a_email)
	{
		global $ilObjDataCache;

		foreach($this->assignments as $assignment)
		{
			if(!$assignment['domain'] or !$assignment['role'])
			{
				continue;
			}
			if(stristr($a_email,$assignment['domain']))
			{
				// check if role exists
				if(!$ilObjDataCache->lookupType($assignment['role']))
				{
					continue;
				}
				return $assignment['role'];
			}
		}
		// return default
		return $this->getDefaultRole();
	}
	
	function getAssignments()
	{
		return $this->assignments ? $this->assignments : array();
	}

	function setDomain($a_id,$a_domain)
	{
		$this->assignments[$a_id]['domain'] = $a_domain;
	}
	function setRole($a_id,$a_role)
	{
		$this->assignments[$a_id]['role'] = $a_role;
	}

	function getDefaultRole()
	{
		return $this->default_role;
	}
	function setDefaultRole($a_role_id)
	{
		$this->default_role = $a_role_id;
	}

	function delete($a_id)
	{
		$query = "DELETE FROM reg_email_role_assignments ".
			"WHERE assignment_id = '".$a_id."'";

		$this->db->query($query);

		$this->__read();
		return true;
	}

	function add()
	{
		$query = "INSERT INTO reg_email_role_assignments ".
			"SET domain = '', ".
			"role = ''";

		$this->db->query($query);

		$this->__read();
		return true;
	}

	function save()
	{
		global $ilias;

		// Save default role
		$ilias->setSetting('reg_default_role',$this->getDefaultRole());

		foreach($this->assignments as $assignment)
		{
			$query = "UPDATE reg_email_role_assignments ".
				"SET domain = '".$assignment['domain']."', ".
				"role = '".$assignment['role']."' ".
				"WHERE assignment_id = '".$assignment['id']."'";

			$this->db->query($query);
		}
		return true;
	}

	function validate()
	{
		foreach($this->assignments as $assignment)
		{
			if(!strlen($assignment['domain']))
			{
				return IL_REG_MISSING_DOMAIN;
			}
			if(!$assignment['role'])
			{
				return IL_REG_MISSING_ROLE;
			}
		}
		if(!$this->getDefaultRole())
		{
			return IL_REG_MISSING_ROLE;
		}
		return 0;
	}
			
	


	// Private
	function __read()
	{
		global $ilias;

		$query = "SELECT * FROM reg_email_role_assignments ";
		$res = $this->db->query($query);

		$this->assignments = array();
		while($row = $res->fetchRow(DB_FETCHMODE_OBJECT))
		{
			$this->assignments[$row->assignment_id]['id'] =  $row->assignment_id;
			$this->assignments[$row->assignment_id]['role'] = $row->role;
			$this->assignments[$row->assignment_id]['domain'] = $row->domain;
		}

		$this->default_role = $ilias->getSetting('reg_default_role');

		return true;
	}
}
?>