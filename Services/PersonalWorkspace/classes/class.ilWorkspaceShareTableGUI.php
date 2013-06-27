<?php
/* Copyright (c) 2010 Leifos, GPL, see docs/LICENSE */

include_once("./Services/Table/classes/class.ilTable2GUI.php");

/**
 * Workspace share handler table GUI class
 *
 * @author Jörg Lützenkirchen <luetzenkirchen@leifos.de>
 * @version $Id: class.adnCountryTableGUI.php 27876 2011-02-25 16:51:38Z jluetzen $
 *
 * @ingroup ServicesPersonalWorkspace
 */
class ilWorkspaceShareTableGUI extends ilTable2GUI
{
	protected $handler; // [ilWorkspaceAccessHandler]
	protected $parent_node_id; // [int]
	protected $filter; // [array]
	protected $crs_ids; // [array]
	protected $grp_ids; // [array]
	protected $portfolio_mode = false; // [bool]

	/**
	 * Constructor
	 *
	 * @param object $a_parent_obj parent gui object
	 * @param string $a_parent_cmd parent default command
	 * @param object $a_handler workspace access handler
	 * @param bool $a_load_data
	 * @param int $a_parent_node_id
	 */
	function __construct($a_parent_obj, $a_parent_cmd, $a_handler, $a_parent_node_id = null, $a_load_data = false)
	{
		global $ilCtrl, $lng;

		$this->handler = $a_handler;
		
		if(stristr(get_class($a_parent_obj), "portfolio"))
		{
			$this->parent_node_id = $a_parent_node_id;		
			$this->portfolio_mode = true;
		}
		
		parent::__construct($a_parent_obj, $a_parent_cmd);

		$this->setId("il_tbl_wspsh");

		$this->setTitle($lng->txt("wsp_shared_resources"));

		$this->addColumn($this->lng->txt("lastname"), "lastname");
		$this->addColumn($this->lng->txt("firstname"), "firstname");		
		$this->addColumn($this->lng->txt("login"), "login");
		
		if(!$this->portfolio_mode)
		{
			$this->addColumn($this->lng->txt("wsp_shared_object_type"), "obj_type");
		}
		
		$this->addColumn($this->lng->txt("wsp_shared_date"), "acl_date");
		$this->addColumn($this->lng->txt("title"), "title");
		$this->addColumn($this->lng->txt("wsp_shared_type"));
		
		if(!$this->portfolio_mode)
		{
			$this->addColumn($this->lng->txt("action"));
		}
	
		$this->setDefaultOrderField("content");
		$this->setDefaultOrderDirection("asc");

		$this->setFormAction($ilCtrl->getFormAction($a_parent_obj));
		$this->setRowTemplate("tpl.shared_row.html", "Services/PersonalWorkspace");
		
		$this->setDisableFilterHiding(true);
		$this->setResetCommand("resetsharefilter");
		$this->setFilterCommand("applysharefilter");
			
		$this->initFilter();
		
		if($a_load_data)
		{
			if(($this->filter["user"] && strlen($this->filter["user"]) > 3) ||
				($this->filter["title"] && strlen($this->filter["title"]) > 3) ||
				$this->filter["acl_date"] ||
				$this->filter["obj_type"] ||
				$this->filter["acl_type"] ||
				$this->filter["crsgrp"])
			{			
				$this->importData();		
				include_once "Services/User/classes/class.ilUserUtil.php";
				return;
			}

			ilUtil::sendFailure($lng->txt("wsp_shared_mandatory_filter_info"));			
		}

		$this->disable("header");
		$this->disable("content");		
	}
	
	public function initFilter()
	{		
		global $lng, $ilSetting, $ilUser;
				
		include_once "Services/Membership/classes/class.ilParticipants.php";
		$this->crs_ids = ilParticipants::_getMembershipByType($ilUser->getId(), "crs");
		$this->grp_ids = ilParticipants::_getMembershipByType($ilUser->getId(), "grp");		
				
		$lng->loadLanguageModule("search");
		
		$item = $this->addFilterItemByMetaType("user", self::FILTER_TEXT, false, $lng->txt("wsp_shared_user_filter"));
		$this->filter["user"] = $item->getValue();
		
		$item = $this->addFilterItemByMetaType("title", self::FILTER_TEXT, false, $lng->txt("title"));
		$this->filter["title"] = $item->getValue();
		
		$item = $this->addFilterItemByMetaType("acl_date", self::FILTER_DATE, false, $lng->txt("wsp_shared_date_filter"));
		$this->filter["acl_date"] = $item->getDate();
		
		if(!$this->portfolio_mode)
		{
			// see ilPersonalWorkspaceGUI::renderToolbar
			$options = array();			
			$settings_map = array("blog" => "blogs",
				"file" => "files");
			// see ilObjWorkspaceFolderTableGUI	
			foreach(array("file", "blog") as $type)
			{
				if(isset($settings_map[$type]) && $ilSetting->get("disable_wsp_".$settings_map[$type]))
				{
					continue;
				}							
				$options[$type] = $lng->txt("wsp_type_".$type);
			}								
			if(sizeof($options))
			{
				asort($options);
				$item = $this->addFilterItemByMetaType("obj_type", self::FILTER_SELECT, false, $lng->txt("wsp_shared_object_type"));
				$item->setOptions(array(""=>$lng->txt("search_any"))+$options);
				$this->filter["obj_type"] = $item->getValue();
			}
		}
				
		// see ilWorkspaceAccessGUI::share
		$options = array();
		$options["user"] = $lng->txt("wsp_set_permission_single_user");
		
		if(sizeof($this->grp_ids))
		{			
			$options["group"] = $lng->txt("wsp_set_permission_group");
		}
		
		if(sizeof($this->crs_ids))
		{
			$options["course"] = $lng->txt("wsp_set_permission_course");
		}
		
		if(!$this->handler->hasRegisteredPermission($this->parent_node_id))
		{
			$options["registered"] = $lng->txt("wsp_set_permission_registered");
		}
		
		if($ilSetting->get("enable_global_profiles"))
		{			
			if(!$this->handler->hasGlobalPasswordPermission($this->parent_node_id))
			{
				$options["password"] = $this->lng->txt("wsp_set_permission_all_password");
			}

			if(!$this->handler->hasGlobalPermission($this->parent_node_id))
			{
				$options["all"] = $this->lng->txt("wsp_set_permission_all");		
			}
		}
		
		if(sizeof($options))
		{
			asort($options);
			$item = $this->addFilterItemByMetaType("acl_type", self::FILTER_SELECT, false, $lng->txt("wsp_shared_type"));
			$item->setOptions(array(""=>$lng->txt("search_any"))+$options);
			$this->filter["acl_type"] = $item->getValue();
		}
						
		if(sizeof($this->crs_ids) || sizeof($this->grp_ids))
		{
			$options = array();
			foreach($this->crs_ids as $crs_id)
			{
				$options[$crs_id] = $lng->txt("obj_crs")." ".ilObject::_lookupTitle($crs_id);
			}
			foreach($this->grp_ids as $grp_id)
			{
				$options[$grp_id] = $lng->txt("obj_grp")." ".ilObject::_lookupTitle($grp_id);
			}
			asort($options);			
			$item = $this->addFilterItemByMetaType("crsgrp", self::FILTER_SELECT, false, $lng->txt("wsp_shared_member_filter"));
			$item->setOptions(array(""=>$lng->txt("search_any"))+$options);
			$this->filter["crsgrp"] = $item->getValue();
		}
	}
	
	protected function importData()
	{
		global $lng;
		
		$data = array();
		
		$user_data = array();
		
		$objects = $this->handler->findSharedObjects($this->filter, $this->crs_ids, $this->grp_ids);
		if($objects)
		{
			foreach($objects as $wsp_id => $item)
			{				
				if(!isset($user_data[$item["owner"]]))
				{
					$user_data[$item["owner"]] = ilObjUser::_lookupName($item["owner"]);
				}				
				
				$data[] = array(
					"wsp_id" => $wsp_id,
					"obj_id" => $item["obj_id"],
					"type" => $item["type"],
					"obj_type" => $lng->txt("wsp_type_".$item["type"]),
					"title" => $item["title"],
					"owner_id" => $item["owner"], 
					"lastname" => $user_data[$item["owner"]]["lastname"],
					"firstname" => $user_data[$item["owner"]]["firstname"],
					"login" => $user_data[$item["owner"]]["login"],
					"acl_type" => $item["acl_type"],
					"acl_date" => $item["acl_date"],
				);					
			}			
		}		
		
		$this->setData($data);
		
		include_once('./Services/Link/classes/class.ilLink.php');
	}
	
	/**
	 * Fill table row
	 *
	 * @param array $a_set data array
	 */
	protected function fillRow($node)
	{
		global $ilCtrl, $lng;
				
		$this->tpl->setVariable("LASTNAME", $node["lastname"]);
		$this->tpl->setVariable("FIRSTNAME", $node["firstname"]);		
		$this->tpl->setVariable("LOGIN", $node["login"]);
							
		$this->tpl->setVariable("TITLE", $node["title"]);
				
		if(!$this->portfolio_mode)
		{
			$this->tpl->setVariable("TYPE", $node["obj_type"]);
			$this->tpl->setVariable("ICON_ALT", $node["obj_type"]);
			$this->tpl->setVariable("ICON", ilObject::_getIcon("", "tiny", $node["type"]));		
			
			$url = $this->handler->getGotoLink($node["wsp_id"], $node["obj_id"]);
		}		
		else
		{
			$url = ilLink::_getStaticLink($node["obj_id"], "prtf", true);
		}
		$this->tpl->setVariable("URL_TITLE", $url);
		
		$this->tpl->setVariable("ACL_DATE", 
			ilDatePresentation::formatDate(new ilDateTime($node["acl_date"], IL_CAL_UNIX))); 
		
		foreach($node["acl_type"] as $obj_id)
		{
			// see ilWorkspaceAccessTableGUI
			switch($obj_id)
			{
				case ilWorkspaceAccessGUI::PERMISSION_REGISTERED:
					$title = $icon_alt = $this->lng->txt("wsp_set_permission_registered");
					$type = "registered";
					$icon = "";
					break;
				
				case ilWorkspaceAccessGUI::PERMISSION_ALL_PASSWORD:
					$title = $icon_alt = $this->lng->txt("wsp_set_permission_all_password");
					$type = "all_password";
					$icon = "";
					break;
				
				case ilWorkspaceAccessGUI::PERMISSION_ALL:
					$title = $icon_alt = $this->lng->txt("wsp_set_permission_all");
					$type = "all_password";
					$icon = "";
					break;	
												
				default:
					$type = ilObject::_lookupType($obj_id);
					$icon = ilUtil::getTypeIconPath($type, null, "tiny");
					$icon_alt = $this->lng->txt("obj_".$type);	
					
					if($type != "usr")
					{					
						$title = ilObject::_lookupTitle($obj_id);											
					}
					else
					{						
						$title = ilUserUtil::getNamePresentation($obj_id, true, true); 
					}
					break;
			}
			
			if($icon)
			{
				$this->tpl->setCurrentBlock("acl_type_icon_bl");
				$this->tpl->setVariable("ACL_ICON", $icon);
				$this->tpl->setVariable("ACL_ICON_ALT", $icon_alt);
				$this->tpl->parseCurrentBlock();
			}
			
			$this->tpl->setCurrentBlock("acl_type_bl");
			$this->tpl->setVariable("ACL_TYPE", $title);
			$this->tpl->parseCurrentBlock();
		}
		
		if(!$this->portfolio_mode)
		{
			// files may be copied to own workspace
			if($node["type"] == "file")
			{
				$ilCtrl->setParameter($this->parent_obj, "wsp_id",
					$this->parent_node_id);									
				$ilCtrl->setParameter($this->parent_obj, "item_ref_id", 
					$node["wsp_id"]);
				$url = $ilCtrl->getLinkTarget($this->parent_obj, "copyshared");

				$this->tpl->setCurrentBlock("action_bl");
				$this->tpl->setVariable("URL_ACTION", $url);
				$this->tpl->setVariable("ACTION", $lng->txt("copy"));
				$this->tpl->parseCurrentBlock();
			}
		}
	}
}

?>