<?php
/*
	+-----------------------------------------------------------------------------+
	| ILIAS open source                                                           |
	+-----------------------------------------------------------------------------+
	| Copyright (c) 1998-2005 ILIAS open source, University of Cologne            |
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

/** @defgroup ServicesInfoScreen Services/InfoScreen
 */

/**
* Class ilInfoScreenGUI
*
* @author Alex Killing <alex.killing@gmx.de>
* @version $Id$
*
* @ilCtrl_Calls ilPermanentLinkGUI: ilNoteGUI, ilFeedbackGUI, ilColumnGUI, ilPublicUserProfileGUI
*
* @ingroup ServicesInfoScreen
*/
class ilPermanentLinkGUI
{
	/**
	* Example: type = "wiki", id (ref_id) = "234", append = "_Start_Page"
	*/
	function __construct($a_type, $a_id, $a_append = "", $a_target = "")
	{
		$this->setType($a_type);
		$this->setId($a_id);
		$this->setAppend($a_append);
		$this->setIncludePermanentLinkText(true);
		$this->setTarget($a_target);
	}
	
	/**
	* Set Include permanent link text.
	*
	* @param	boolean	$a_includepermanentlinktext	Include permanent link text
	*/
	function setIncludePermanentLinkText($a_includepermanentlinktext)
	{
		$this->includepermanentlinktext = $a_includepermanentlinktext;
	}

	/**
	* Get Include permanent link text.
	*
	* @return	boolean	Include permanent link text
	*/
	function getIncludePermanentLinkText()
	{
		return $this->includepermanentlinktext;
	}

	/**
	* Set Type.
	*
	* @param	string	$a_type	Type
	*/
	function setType($a_type)
	{
		$this->type = $a_type;
	}

	/**
	* Get Type.
	*
	* @return	string	Type
	*/
	function getType()
	{
		return $this->type;
	}

	/**
	* Set Id.
	*
	* @param	string	$a_id	Id
	*/
	function setId($a_id)
	{
		$this->id = $a_id;
	}

	/**
	* Get Id.
	*
	* @return	string	Id
	*/
	function getId()
	{
		return $this->id;
	}

	/**
	* Set Append.
	*
	* @param	string	$a_append	Append
	*/
	function setAppend($a_append)
	{
		$this->append = $a_append;
	}

	/**
	* Get Append.
	*
	* @return	string	Append
	*/
	function getAppend()
	{
		return $this->append;
	}

	/**
	* Set Target.
	*
	* @param	string	$a_target	Target
	*/
	function setTarget($a_target)
	{
		$this->target = $a_target;
	}

	/**
	* Get Target.
	*
	* @return	string	Target
	*/
	function getTarget()
	{
		return $this->target;
	}

	/**
	* Add Current Link to Bookmarks
	*/
	function addToBookmarks()
	{
		global $ilObjDataCache, $lng;
		$result = new stdClass();
		
		include_once 'Services/PersonalDesktop/classes/class.ilBookmark.php';
		
		include_once('classes/class.ilLink.php');
		$href = ilLink::_getStaticLink($this->getId(), $this->getType(),
			true, $this->getAppend());

		$bookmark = new ilBookmark();
		
		// try to find title if none is given
		if ($_REQUEST["pm_bm_title"])
		{
			$title = $_REQUEST["pm_bm_title"];
			$bookmark->setTitle($title);
		}
		else
		{
			$obj_id = $ilObjDataCache->lookupObjId($this->getId());
			$title = $ilObjDataCache->lookupTitle($obj_id);
			if ($title)
				$bookmark->setTitle($title);
			else
				$bookmark->setTitle("untitled");
		}
		
		$bookmark->setDescription($lng->txt('perma_link') . ': ' . $title);
		$bookmark->setParent(1);
		$bookmark->setTarget($href);
		$bookmark->create();
		
		ilUtil::sendInfo($lng->txt('bookmark_added'), true);
		ilUtil::redirect($href);
	}
	
	/**
	* Get HTML for link
	*/
	function getHTML()
	{
		global $lng, $ilCtrl, $ilObjDataCache, $ilDB;
		
		$tpl = new ilTemplate("tpl.permanent_link.html", true, true,
			"Services/PermanentLink");
		
		include_once('classes/class.ilLink.php');
		$href = ilLink::_getStaticLink($this->getId(), $this->getType(),
			true, $this->getAppend());

		// check if current page should be bookmarked
		if ($_REQUEST["addToBookmark"] == "true")
		{
			$this->addToBookmarks();
		}


		if ($this->getIncludePermanentLinkText())
		{
			$tpl->setVariable("TXT_PERMA", $lng->txt("perma_link").": ");
		}

		$title = '';
		
		if ($_SESSION["AccountId"] != ANONYMOUS_USER_ID)
		{
			// fetch default title for bookmark

			$obj_id = $ilObjDataCache->lookupObjId($this->getId());
			$title = $ilObjDataCache->lookupTitle($obj_id);
			if (!$title)
				$bookmark->setTitle("untitled");

			$tpl->setVariable("TXT_BOOKMARK_DEFAULT", $title);
		}

		$tpl->setVariable("LINK", $href);
		$tpl->setVariable("TXT_ADD_TO_ILIAS_BM", $lng->txt("bm_add_to_ilias"));
		$tpl->setVariable("URL_ADD_TO_BM", $href . '&addToBookmark=true&bm_title='. urlencode($title));
		
		if ($this->getTarget() != "")
		{
			$tpl->setVariable("TARGET", 'target="'.$this->getTarget().'"');
		}
		
		
		// social bookmarkings
		
		$q = 'SELECT sbm_title, sbm_link, sbm_icon, sbm_active FROM bookmark_social_bm WHERE sbm_active = 1';
		$rset = $ilDB->query($q);
		
		include_once("./Services/AdvancedSelectionList/classes/class.ilAdvancedSelectionListGUI.php");
		
		$current_selection_list = new ilAdvancedSelectionListGUI();
		$current_selection_list->setListTitle($lng->txt("bm_add_to_social_bookmarks"));
		$current_selection_list->setId("socialbm_actions");
		$current_selection_list->setUseImages(true);
		
		$cnt = 0;
		while ($row = $ilDB->fetchObject($rset))
		{
			$linktpl = $row->sbm_link;
			$linktpl = str_replace('{LINK}', urlencode($href), $linktpl);
			$linktpl = str_replace('{TITLE}', urlencode($title), $linktpl);
			//$current_selection_list->addItem($row->sbm_title, '', $linktpl, 'templates/default/images/socialbookmarks/' . $row->sbm_icon , $row->title, '_blank');
			$current_selection_list->addItem($row->sbm_title, '', $linktpl, ilUtil::getImagePath('socialbookmarks/' . $row->sbm_icon) , $row->title, '_blank');
			$cnt++;
		}
		
		if ($cnt)
		{
			$tpl->setVariable('SELECTION_LIST', $current_selection_list->getHTML());
		}
		
		return $tpl->get();
	}
}

?>
