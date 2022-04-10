<?php declare(strict_types=0);
/******************************************************************************
 * This file is part of ILIAS, a powerful learning management system.
 * ILIAS is licensed with the GPL-3.0, you should have received a copy
 * of said license along with the source code.
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *****************************************************************************/

/**
 * Course XML Parser
 * @author  Stefan Meyer <smeyer.ilias@gmx.de>
 * @version $Id$
 * @extends ilMDSaxParser
 */
class ilCourseXMLParser extends ilMDSaxParser implements ilSaxSubsetParser
{
    private bool $in_meta_data = false;
    private bool $in_availability = false;
    private bool $in_registration = false;
    private bool $in_period = false;
    private bool $in_period_with_time = false;

    private ?ilDateTime $period_start = null;
    private ?ilDateTime $period_end = null;

    private string $cdata = '';

    private string $current_container_setting = '';            // current meta data object
    private ?ilObjCourse $course_obj;
    private ?ilLogger $log;
    protected ilSetting $setting;
    /**
     * @var false
     */
    protected ?ilMD $md_obj = null;
    protected ilSaxController $sax_controller;
    protected ilCourseParticipants $course_members;
    protected ilCourseWaitingList $course_waiting_list;
    protected ?ilAdvancedMDValueParser $adv_md_handler = null;
    protected array $course_members_array = [];

    public function __construct(ilObjCourse $a_course_obj, string $a_xml_file = '')
    {
        global $DIC;

        parent::__construct($a_xml_file);
        $this->sax_controller = new ilSaxController();
        $this->log = $DIC->logger()->crs();
        $this->setting = $DIC->settings();
        $this->course_obj = $a_course_obj;
        $this->course_members = ilCourseParticipants::_getInstanceByObjId($this->course_obj->getId());
        $this->course_waiting_list = new ilCourseWaitingList($this->course_obj->getId());
        // flip the array so we can use array_key_exists
        $this->course_members_array = array_flip($this->course_members->getParticipants());

        $this->md_obj = new ilMD($this->course_obj->getId(), 0, 'crs');
        $this->setMDObject($this->md_obj);

    }

    /**
     * @inheritDoc
     */
    public function setHandlers($a_xml_parser) : void
    {
        $this->sax_controller->setHandlers($a_xml_parser);
        $this->sax_controller->setDefaultElementHandler($this);
        $this->sax_controller->setElementHandler(
            $this->adv_md_handler = new ilAdvancedMDValueParser($this->course_obj->getId()),
            'AdvancedMetaData'
        );
    }

    /**
     * @inheritDoc
     */
    public function handlerBeginTag($a_xml_parser, string $a_name, array $a_attribs) : void
    {
        if ($this->in_meta_data) {
            parent::handlerBeginTag($a_xml_parser, $a_name, $a_attribs);
            return;
        }

        switch ($a_name) {
            case 'Course':
                if (strlen($a_attribs['importId'])) {
                    $this->log->write("CourseXMLParser: importId = " . $a_attribs['importId']);
                    $this->course_obj->setImportId($a_attribs['importId']);
                    ilObject::_writeImportId($this->course_obj->getId(), $a_attribs['importId']);
                }
                if (strlen($a_attribs['showMembers'])) {
                    $this->course_obj->setShowMembers(
                        $a_attribs['showMembers'] == 'Yes'
                    );
                }
                break;

            case 'Admin':
                if ($id_data = $this->__parseId($a_attribs['id'])) {
                    if ($id_data['local'] or $id_data['imported']) {
                        $this->handleAdmin($a_attribs, $id_data);
                    }
                }
                break;

            case 'Tutor':
                if ($id_data = $this->__parseId($a_attribs['id'])) {
                    if ($id_data['local'] or $id_data['imported']) {
                        $this->handleTutor($a_attribs, $id_data);
                    }
                }
                break;

            case 'Member':
                if ($id_data = $this->__parseId($a_attribs['id'])) {
                    if ($id_data['local'] or $id_data['imported']) {
                        $this->handleMember($a_attribs, $id_data);
                    }
                }
                break;

            case 'Subscriber':
                if ($id_data = $this->__parseId($a_attribs['id'])) {
                    if ($id_data['local'] or $id_data['imported']) {
                        $this->handleSubscriber($a_attribs, $id_data);
                    }
                }
                break;

            case 'WaitingList':
                if ($id_data = $this->__parseId($a_attribs['id'])) {
                    if ($id_data['local'] or $id_data['imported']) {
                        $this->handleWaitingList($a_attribs, $id_data);
                    }
                }
                break;

            case 'Owner':
                if ($id_data = $this->__parseId($a_attribs['id'])) {
                    if ($id_data['local'] or $id_data['imported']) {
                        $this->course_obj->setOwner($id_data['usr_id']);
                        $this->course_obj->updateOwner();
                    }
                }
                break;

            case 'Settings':
                break;
            case 'Availability':
                $this->in_availability = true;
                break;

            case 'NotAvailable':
                if ($this->in_availability) {
                    $this->course_obj->setOfflineStatus(true);
                } elseif ($this->in_registration) {
                    $this->course_obj->setSubscriptionLimitationType(ilCourseConstants::IL_CRS_SUBSCRIPTION_DEACTIVATED);
                }

                break;

            case 'Unlimited':
                if ($this->in_availability) {
                    $this->course_obj->setOfflineStatus(false);
                } elseif ($this->in_registration) {
                    $this->course_obj->setSubscriptionLimitationType(ilCourseConstants::IL_CRS_SUBSCRIPTION_UNLIMITED);
                }

                break;
            case 'TemporarilyAvailable':
                if ($this->in_availability) {
                    $this->course_obj->setOfflineStatus(false);
                } elseif ($this->in_registration) {
                    $this->course_obj->setSubscriptionLimitationType(ilCourseConstants::IL_CRS_SUBSCRIPTION_LIMITED);
                }
                break;

            case 'Start':
                break;

            case 'End':
                break;

            case 'Syllabus':
                break;

            case 'TargetGroup':
                break;

            case 'Contact':
                break;

            case 'Name':
            case 'Responsibility':
            case 'Phone':
            case 'Email':
            case 'Consultation':
                break;

            case 'Registration':
                $this->in_registration = true;

                switch ($a_attribs['registrationType']) {
                    case 'Confirmation':
                        $this->course_obj->setSubscriptionType(ilCourseConstants::IL_CRS_SUBSCRIPTION_CONFIRMATION);
                        break;

                    case 'Direct':
                        $this->course_obj->setSubscriptionType(ilCourseConstants::IL_CRS_SUBSCRIPTION_DIRECT);
                        break;

                    case 'Password':
                        $this->course_obj->setSubscriptionType(ilCourseConstants::IL_CRS_SUBSCRIPTION_PASSWORD);
                        break;
                }

                $this->course_obj->setSubscriptionMaxMembers((int) $a_attribs['maxMembers']);
                $this->course_obj->enableSubscriptionMembershipLimitation($this->course_obj->getSubscriptionMaxMembers() > 0);
                $this->course_obj->setSubscriptionNotify($a_attribs['notification'] == 'Yes' ? true : false);
                $this->course_obj->enableWaitingList($a_attribs['waitingList'] == 'Yes' ? true : false);
                break;

            case 'Sort':
                ilContainerSortingSettings::_importContainerSortingSettings($a_attribs, $this->course_obj->getId());

                //#17837
                $this->course_obj->setOrderType(
                    ilContainerSortingSettings::getInstanceByObjId($this->course_obj->getId())->getSortMode()
                );
                break;

            case 'Disabled':
                $this->course_obj->setSubscriptionLimitationType(ilCourseConstants::IL_CRS_SUBSCRIPTION_DEACTIVATED);
                break;

            case "MetaData":
                $this->in_meta_data = true;
                parent::handlerBeginTag($a_xml_parser, $a_name, $a_attribs);
                break;

            case 'ContainerSetting':
                $this->current_container_setting = $a_attribs['id'];
                break;

            case 'Period':
                $this->in_period = true;
                $this->in_period_with_time = $a_attribs['withTime'];
                break;

            case 'WaitingListAutoFill':
            case 'CancellationEnd':
            case 'MinMembers':
                break;

            case 'WelcomeMail':
                if (array_key_exists('status', $a_attribs)) {
                    $this->course_obj->setAutoNotification((bool) $a_attribs['status']);
                }
                break;

            case 'SessionLimit':
                if (isset($a_attribs['active'])) {
                    $this->course_obj->enableSessionLimit((bool) $a_attribs['active']);
                }
                if (isset($a_attribs['previous'])) {
                    $this->course_obj->setNumberOfPreviousSessions((int) $a_attribs['previous']);
                }
                if (isset($a_attribs['next'])) {
                    $this->course_obj->setNumberOfNextSessions((int) $a_attribs['next']);
                }
                break;
        }
    }

    public function __parseId($a_id) : array
    {
        $fields = explode('_', $a_id);

        if (!is_array($fields) or
            $fields[0] != 'il' or
            !is_numeric($fields[1]) or
            $fields[2] != 'usr' or
            !is_numeric($fields[3])) {
            return [];
        }
        if ($id = ilObjUser::_getImportedUserId($a_id)) {
            return array('imported' => true,
                         'local' => false,
                         'usr_id' => $id
            );
        }
        if (($fields[1] == $this->setting->get('inst_id', '0')) and strlen(ilObjUser::_lookupLogin($fields[3]))) {
            return array('imported' => false,
                         'local' => true,
                         'usr_id' => $fields[3]
            );
        }
        return [];
    }

    /**
     * attach or detach admin from course member
     */
    private function handleAdmin(array $a_attribs, array $id_data) : void
    {
        if (!isset($a_attribs['action']) || $a_attribs['action'] == 'Attach') {
            // if action not set, or attach
            if (!array_key_exists($id_data['usr_id'], $this->course_members_array)) {
                // add only if member is not assigned yet
                $this->course_members->add($id_data['usr_id'], ilParticipants::IL_CRS_ADMIN);
                if ($a_attribs['notification'] == 'Yes') {
                    $this->course_members->updateNotification($id_data['usr_id'], true);
                }
                if ($a_attribs['passed'] == 'Yes') {
                    $this->course_members->updatePassed($id_data['usr_id'], true);
                }
                if (isset($a_attribs['contact']) && $a_attribs['contact'] == 'Yes') {
                    // default for new course admin/tutors is "no contact"
                    $this->course_members->updateContact($id_data['usr_id'], true);
                }
                $this->course_members_array[$id_data['usr_id']] = "added";
            } else {
                // update
                if ($a_attribs['notification'] == 'Yes') {
                    $this->course_members->updateNotification($id_data['usr_id'], true);
                }
                if ($a_attribs['passed'] == 'Yes') {
                    $this->course_members->updatePassed($id_data['usr_id'], true);
                }
                if (isset($a_attribs['contact']) && $a_attribs['contact'] == 'Yes') {
                    $this->course_members->updateContact($id_data['usr_id'], true);
                } elseif (isset($a_attribs['contact']) && $a_attribs['contact'] == 'No') {
                    $this->course_members->updateContact($id_data['usr_id'], false);
                }
                $this->course_members->updateBlocked($id_data['usr_id'], false);
            }
        } elseif (isset($a_attribs['action']) && $a_attribs['action'] == 'Detach' && $this->course_members->isAdmin($id_data['usr_id'])) {
            // if action set and detach and is admin of course
            $this->course_members->delete($id_data['usr_id']);
        }
    }

    private function handleTutor(array $a_attribs, array $id_data) : void
    {
        if (!isset($a_attribs['action']) || $a_attribs['action'] == 'Attach') {
            // if action not set, or attach
            if (!array_key_exists($id_data['usr_id'], $this->course_members_array)) {
                // add only if member is not assigned yet
                $this->course_members->add($id_data['usr_id'], ilParticipants::IL_CRS_TUTOR);
                if ($a_attribs['notification'] == 'Yes') {
                    $this->course_members->updateNotification($id_data['usr_id'], true);
                }
                if ($a_attribs['passed'] == 'Yes') {
                    $this->course_members->updatePassed($id_data['usr_id'], true);
                }
                if (isset($a_attribs['contact']) && $a_attribs['contact'] == 'Yes') {
                    // default for new course admin/tutors is "no contact"
                    $this->course_members->updateContact($id_data['usr_id'], true);
                }
                $this->course_members_array[$id_data['usr_id']] = "added";
            } else {
                if ($a_attribs['notification'] == 'Yes') {
                    $this->course_members->updateNotification($id_data['usr_id'], true);
                }
                if ($a_attribs['passed'] == 'Yes') {
                    $this->course_members->updatePassed($id_data['usr_id'], true);
                }
                if (isset($a_attribs['contact']) && $a_attribs['contact'] == 'Yes') {
                    $this->course_members->updateContact($id_data['usr_id'], true);
                } elseif (isset($a_attribs['contact']) && $a_attribs['contact'] == 'No') {
                    $this->course_members->updateContact($id_data['usr_id'], false);
                }
                $this->course_members->updateBlocked($id_data['usr_id'], false);
            }
        } elseif (isset($a_attribs['action']) && $a_attribs['action'] == 'Detach' && $this->course_members->isTutor($id_data['usr_id'])) {
            // if action set and detach and is tutor of course
            $this->course_members->delete($id_data['usr_id']);
        }
    }

    /**
     * attach or detach user/member/admin from course member
     */
    private function handleMember(array $a_attribs, array $id_data) : void
    {
        if (!isset($a_attribs['action']) || $a_attribs['action'] == 'Attach') {
            // if action not set, or set and attach
            if (!array_key_exists($id_data['usr_id'], $this->course_members_array)) {
                // add only if member is not yet assigned as tutor or admin
                $this->course_members->add($id_data['usr_id'], ilParticipants::IL_CRS_MEMBER);
                if ($a_attribs['blocked'] == 'Yes') {
                    $this->course_members->updateBlocked($id_data['usr_id'], true);
                }
                if ($a_attribs['passed'] == 'Yes') {
                    $this->course_members->updatePassed($id_data['usr_id'], true);
                }
                $this->course_members_array[$id_data['usr_id']] = "added";
            } else {
                // the member does exist. Now update status etc. only
                if ($a_attribs['blocked'] == 'Yes') {
                    $this->course_members->updateBlocked($id_data['usr_id'], true);
                }
                if ($a_attribs['passed'] == 'Yes') {
                    $this->course_members->updatePassed($id_data['usr_id'], true);
                }
            }
        } elseif (isset($a_attribs['action']) && $a_attribs['action'] == 'Detach' && $this->course_members->isMember($id_data['usr_id'])) {
            // if action set and detach and is member of course
            $this->course_members->delete($id_data['usr_id']);
        }
    }

    /**
     * attach or detach members from subscribers
     */
    private function handleSubscriber(array $a_attribs, array $id_data) : void
    {
        if (!isset($a_attribs['action']) || $a_attribs['action'] == 'Attach') {
            // if action not set, or attach
            if (!$this->course_members->isSubscriber($id_data['usr_id'])) {
                // add only if not exist
                $this->course_members->addSubscriber($id_data['usr_id']);
            }
            $this->course_members->updateSubscriptionTime($id_data['usr_id'], $a_attribs['subscriptionTime']);
        } elseif (isset($a_attribs['action']) && $a_attribs['action'] == 'Detach' && $this->course_members->isSubscriber($id_data['usr_id'])) {
            // if action set and detach and is subscriber
            $this->course_members->deleteSubscriber($id_data["usr_id"]);
        }
    }

    /**
     * attach or detach members from waitinglist
     */
    private function handleWaitingList(array $a_attribs, array $id_data) : void
    {
        if (!isset($a_attribs['action']) || $a_attribs['action'] == 'Attach') {
            // if action not set, or attach
            if (!$this->course_waiting_list->isOnList($id_data['usr_id'])) {
                // add only if not exists
                $this->course_waiting_list->addToList($id_data['usr_id']);
            }
            $this->course_waiting_list->updateSubscriptionTime($id_data['usr_id'], $a_attribs['subscriptionTime']);
        } elseif (isset($a_attribs['action']) && $a_attribs['action'] == 'Detach' && $this->course_waiting_list->isOnList($id_data['usr_id'])) {
            // if action set and detach and is on list
            $this->course_waiting_list->removeFromList($id_data['usr_id']);
        }
    }

    /**
     * @inheritDoc
     */
    public function handlerEndTag($a_xml_parser, string $a_name) : void
    {
        if ($this->in_meta_data) {
            parent::handlerEndTag($a_xml_parser, $a_name);
        }

        switch ($a_name) {
            case 'Course':

                $this->log->write('CourseXMLParser: import_id = ' . $this->course_obj->getImportId());
                $this->course_obj->MDUpdateListener('General');
                $this->course_obj->update();
                $this->adv_md_handler->save();
                break;

            case 'Settings':
                break;

            case 'Availability':
                $this->in_availability = false;
                break;

            case 'Registration':
                $this->in_registration = false;
                break;

            case 'Start':
                if ($this->in_availability) {
                    $this->course_obj->setActivationStart((int) trim($this->cdata));
                }
                if ($this->in_registration) {
                    $this->course_obj->setSubscriptionStart((int) trim($this->cdata));
                }
                if ($this->in_period) {
                    if ((int) $this->cdata) {
                        if ($this->in_period_with_time) {
                            $this->period_start = new \ilDateTime((int) $this->cdata, IL_CAL_UNIX);
                        } else {
                            $this->period_start = new \ilDate((int) $this->cdata, IL_CAL_UNIX);
                        }
                    }
                }
                break;

            case 'End':
                if ($this->in_availability) {
                    $this->course_obj->setActivationEnd((int) trim($this->cdata));
                }
                if ($this->in_registration) {
                    $this->course_obj->setSubscriptionEnd((int) trim($this->cdata));
                }
                if ($this->in_period) {
                    if ((int) $this->cdata) {
                        if ($this->in_period_with_time) {
                            $this->period_end = new \ilDateTime((int) $this->cdata, IL_CAL_UNIX);
                        } else {
                            $this->period_end = new \ilDate((int) $this->cdata, IL_CAL_UNIX);
                        }
                    }
                }
                break;

            case 'Syllabus':
                $this->course_obj->setSyllabus(trim($this->cdata));
                break;

            case 'TargetGroup':
                $this->course_obj->setTargetGroup(trim($this->cdata));
                break;

            case 'ImportantInformation':
                $this->course_obj->setImportantInformation(trim($this->cdata));
                break;

            case 'ViewMode':
                $this->course_obj->setViewMode((int) trim($this->cdata));
                break;

            case 'Name':
                $this->course_obj->setContactName(trim($this->cdata));
                break;

            case 'Responsibility':
                $this->course_obj->setContactResponsibility(trim($this->cdata));
                break;

            case 'Phone':
                $this->course_obj->setContactPhone(trim($this->cdata));
                break;

            case 'Email':
                $this->course_obj->setContactEmail(trim($this->cdata));
                break;

            case 'Consultation':
                $this->course_obj->setContactConsultation(trim($this->cdata));
                break;

            case 'Password':
                $this->course_obj->setSubscriptionPassword(trim($this->cdata));
                break;

            case 'MetaData':
                $this->in_meta_data = false;
                parent::handlerEndTag($a_xml_parser, $a_name);
                break;

            case 'ContainerSetting':
                if ($this->current_container_setting) {
                    ilContainer::_writeContainerSetting(
                        $this->course_obj->getId(),
                        $this->current_container_setting,
                        $this->cdata
                    );
                }
                break;

            case 'Period':
                $this->in_period = false;
                try {
                    $this->course_obj->setCoursePeriod($this->period_start, $this->period_end);
                } catch (Exception $e) {
                    $this->log->warning('invalid course period given');
                }
                break;

            case 'WaitingListAutoFill':
                $this->course_obj->setWaitingListAutoFill((bool) $this->cdata);
                break;

            case 'CancellationEnd':
                if ((int) $this->cdata) {
                    $this->course_obj->setCancellationEnd(new ilDate((int) $this->cdata, IL_CAL_UNIX));
                }
                break;

            case 'MinMembers':
                if ((int) $this->cdata) {
                    $this->course_obj->setSubscriptionMinMembers((int) $this->cdata);
                }
                break;

            case 'TimingMode':
                $this->course_obj->setTimingMode((int) $this->cdata);
                break;
        }
        $this->cdata = '';

    }

    // PRIVATE

    public function handlerCharacterData($a_xml_parser, string $a_data) : void
    {
        // call meta data handler
        if ($this->in_meta_data) {
            parent::handlerCharacterData($a_xml_parser, $a_data);
        }
        if ($a_data != "\n") {
            // Replace multiple tabs with one space
            $a_data = preg_replace("/\t+/", " ", $a_data);

            $this->cdata .= $a_data;
        }
    }
}
