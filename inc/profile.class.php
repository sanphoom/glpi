<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2013 by the INDEPNET Development Team.

 http://indepnet.net/   http://glpi-project.org
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

/** @file
* @brief
*/

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Profile class
**/
class Profile extends CommonDBTM {

   // Specific ones

   /// Helpdesk fields of helpdesk profiles
   static public $helpdesk_rights = array('create_ticket_on_login', 'followup',
                                          'knowbase', 'helpdesk_hardware', 'helpdesk_item_type',
                                          'password_update', 'reminder_public',
                                          'reservation', 'rssfeed_public',
                                          'show_group_hardware', 'task', 'ticket',
                                          'ticketrecurrent',  'tickettemplates_id', 'ticket_cost',
                                          'validation');


   /// Common fields used for all profiles type
   static public $common_fields = array('id', 'interface', 'is_default', 'name');


   //TODO right never used ?
   /// Fields not related to a basic right
//   static public $noright_fields = array('comment', 'change_status', 'date_mod',
//                                         'helpdesk_hardware','helpdesk_item_type', 'own_ticket',
//                                         'problem_status', 'show_group_hardware',
//                                         'show_group_ticket', 'ticket_status');

   var $dohistory = true;

   static $rightname = 'profile';

   function getForbiddenStandardMassiveAction() {

      $forbidden   = parent::getForbiddenStandardMassiveAction();
      $forbidden[] = 'update';
      return $forbidden;
   }


   static function getTypeName($nb=0) {
      return _n('Profile', 'Profiles', $nb);
   }


   function defineTabs($options=array()) {

      $ong = array();
      $this->addDefaultFormTab($ong);
      $this->addStandardTab(__CLASS__, $ong, $options);
      $this->addStandardTab('Profile_User', $ong, $options);
      $this->addStandardTab('Log',$ong, $options);
      return $ong;
   }


   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      if (!$withtemplate) {
         switch ($item->getType()) {
            case __CLASS__ :
               if ($item->fields['interface'] == 'helpdesk') {
                  $ong[1] = __('Simplified interface'); // Helpdesk

               } else {
                  /// TODO split it in 2 or 3 tabs
                  $ong[2] = __('Assets');
                  $ong[3] =  sprintf(__('%1$s/%2$s'), __('Management'), __('Tools'));
                  $ong[4] = __('Assistance');
                  $ong[5] = __('Life cycles');
                  $ong[6] = __('Administration');
                  $ong[7] = __('Setup');
               }
               return $ong;
         }
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {

      if ($item->getType() == __CLASS__) {
         $item->cleanProfile();
         switch ($tabnum) {
            case 1 :
               $item->showFormHelpdesk();
               break;

            case 2 :
               $item->showFormAsset();
               break;

            case 3 :
               $item->showFormInventory();
               break;

            case 4 :
               $item->showFormTracking();
               break;

            case 5 :
               $item->showFormLifeCycle();
               break;

            case 6 :
               $item->showFormAdmin();
               break;
            case 7 :
               $item->showFormSetup();
               break;

         }
      }
      return true;
   }


   function post_updateItem($history=1) {
      global $DB;

      if (count($this->profileRight) > 0) {
         $profile_right = new ProfileRight();
         $profile_right->updateProfileRights($this->getID(), $this->profileRight);
         unset($this->profileRight);
      }

      if (in_array('is_default',$this->updates) && ($this->input["is_default"] == 1)) {
         $query = "UPDATE ". $this->getTable()."
                   SET `is_default` = '0'
                   WHERE `id` <> '".$this->input['id']."'";
         $DB->query($query);
      }
   }


   function post_addItem() {
      global $DB;

      if (count($this->profileRight) > 0) {
         $profile_right = new ProfileRight();
         $profile_right->updateProfileRights($this->getID(), $this->profileRight);
         unset($this->profileRight);
      }
      if (isset($this->fields['is_default']) && ($this->fields["is_default"] == 1)) {
         $query = "UPDATE ". $this->getTable()."
                   SET `is_default` = '0'
                   WHERE `id` <> '".$this->fields['id']."'";
         $DB->query($query);
      }
   }


   function cleanDBonPurge() {
      global $DB;

      $gpr = new ProfileRight();
      $gpr->cleanDBonItemDelete($this->getType(), $this->fields['id']);

      $gpu = new Profile_User();
      $gpu->cleanDBonItemDelete($this->getType(), $this->fields['id']);


      Rule::cleanForItemAction($this);
      // PROFILES and UNIQUE_PROFILE in RuleMailcollector
      Rule::cleanForItemCriteria($this, 'PROFILES');
      Rule::cleanForItemCriteria($this, 'UNIQUE_PROFILE');

      $gki = new KnowbaseItem_Profile();
      $gki->cleanDBonItemDelete($this->getType(), $this->fields['id']);

      $gr = new Profile_Reminder();
      $gr->cleanDBonItemDelete($this->getType(), $this->fields['id']);

   }


   function prepareInputForUpdate($input) {

      // Check for faq
      if (isset($input["interface"]) && ($input["interface"] == 'helpdesk')) {
         if (isset($input["faq"]) && ($input["faq"] == KnowbaseItem::PUBLISHFAQ)) {
            $input["faq"] == KnowbaseItem::READFAQ;
         }
      }

      if (isset($input["_helpdesk_item_types"])) {
         if (isset($input["helpdesk_item_type"])) {
            $input["helpdesk_item_type"] = exportArrayToDB($input["helpdesk_item_type"]);
         } else {
            $input["helpdesk_item_type"] = exportArrayToDB(array());
         }
      }

      if (isset($input["_cycles_ticket"])) {
         $tab   = Ticket::getAllStatusArray();
         $cycle = array();
         foreach ($tab as $from => $label) {
            foreach ($tab as $dest => $label) {
               if (($from != $dest)
                   && ($input["_cycle_ticket"][$from][$dest] == 0)) {
                  $cycle[$from][$dest] = 0;
               }
            }
         }
         $input["ticket_status"] = exportArrayToDB($cycle);
      }

      if (isset($input["_cycles_problem"])) {
         $tab   = Problem::getAllStatusArray();
         $cycle = array();
         foreach ($tab as $from => $label) {
            foreach ($tab as $dest => $label) {
               if (($from != $dest)
                   && ($input["_cycle_problem"][$from][$dest] == 0)) {
                  $cycle[$from][$dest] = 0;
               }
            }
         }
         $input["problem_status"] = exportArrayToDB($cycle);
      }

      if (isset($input["_cycles_change"])) {
         $tab   = Change::getAllStatusArray();
         $cycle = array();
         foreach ($tab as $from => $label) {
            foreach ($tab as $dest => $label) {
               if (($from != $dest)
                   && ($input["_cycle_change"][$from][$dest] == 0)) {
                  $cycle[$from][$dest] = 0;
               }
            }
         }
         $input["change_status"] = exportArrayToDB($cycle);
      }

      $this->profileRight = array();
      foreach (ProfileRight::getAllPossibleRights() as $right => $default) {
         if (isset($input['_'.$right])) {
            $this->profileRight[$right] = array_sum($input['_'.$right]);
            unset($input['_'.$right]);
         }
      }

      // check if right if the last write profile on Profile object
      if (($this->fields['profile'] & UPDATE)
          && isset($input['profile']) && !($input['profile'] & UPDATE)
          && (countElementsInTable("glpi_profilerights",
                                   "`name` = 'profile' AND `rights` & ".UPDATE))) {
         Session::addMessageAfterRedirect(__("This profile is the last with write rights on profiles"),
         false, ERROR);
         Session::addMessageAfterRedirect(__("Deletion refused"), false, ERROR);
         unset($input["profile"]);
      }

      return $input;
   }


   /**
    * check right before delete
    *
    * @since version 0.85
    *
    * @return boolean
   **/
   function pre_deleteItem() {
      global $DB;

      if (($this->fields['profile'] & DELETE)
          && (countElementsInTable("glpi_profilerights",
                                   "`name` = 'profile' AND `rights` & ".DELETE))) {
          Session::addMessageAfterRedirect(__("This profile is the last with write rights on profiles"),
                                           false, ERROR);
          Session::addMessageAfterRedirect(__("Deletion refused"), false, ERROR);
          return false;
      }
      return true;
   }


   function prepareInputForAdd($input) {

      if (isset($input["helpdesk_item_type"])) {
         $input["helpdesk_item_type"] = exportArrayToDB($input["helpdesk_item_type"]);
      }

      $this->profileRight = array();
      foreach (ProfileRight::getAllPossibleRights() as $right => $default) {
         if (isset($input[$right])) {
            $this->profileRight[$right] = $input[$right];
            unset($input[$right]);
         }
      }

      return $input;
   }


   /**
    * Unset unused rights for helpdesk
   **/
   function cleanProfile() {

      if ((self::$helpdesk_rights == 'reservation')
          && !ReservationItem::RESERVEANITEM) {
         return false;
      }
      if ((self::$helpdesk_rights == 'ticket')
          && !Session::haveRightsOr("ticket", array(CREATE, Ticket::READGROUP))) {
         return false;
      }
      if ((self::$helpdesk_rights == 'followup')
          && !Session::haveRightsOr('followup',
                                    array(TicketFollowup::ADDMYTICKET, TicketFollowup::UPDATEMY,
                                          TicketFollowup::SEEPUBLIC))) {
         return false;
      }
      if ((self::$helpdesk_rights == 'task')
         && !Session::haveRight('followup', TicketTask::SEEPUBLIC)) {
         return false;
      }
      if ((self::$helpdesk_rights == 'validation')
            && !Session::haveRightsOr('validation', array(TicketValidation::CREATEREQUEST,
                                                          TicketValidation::CREATEINCIDENT,
                                                          TicketValidation::VALIDATEREQUEST,
                                                          TicketValidation::VALIDATEINCIDENT))) {
         return false;
      }


      if ($this->fields["interface"] == "helpdesk") {
         foreach ($this->fields as $key=>$val) {
            if (!in_array($key,self::$common_fields)
                && !in_array($key,self::$helpdesk_rights)) {
               unset($this->fields[$key]);
            }
         }
      }

      // decode array
      if (isset($this->fields["helpdesk_item_type"])
          && !is_array($this->fields["helpdesk_item_type"])) {

         $this->fields["helpdesk_item_type"] = importArrayFromDB($this->fields["helpdesk_item_type"]);
      }

      // Empty/NULL case
      if (!isset($this->fields["helpdesk_item_type"])
          || !is_array($this->fields["helpdesk_item_type"])) {

         $this->fields["helpdesk_item_type"] = array();
      }

      // Decode status array
      $fields_to_decode = array('ticket_status', 'problem_status', 'change_status');
      foreach ($fields_to_decode as $val) {
         if (isset($this->fields[$val]) && !is_array($this->fields[$val])) {
            $this->fields[$val] = importArrayFromDB($this->fields[$val]);
            // Need to be an array not a null value
            if (is_null($this->fields[$val])) {
               $this->fields[$val] = array();
            }
         }
      }
   }


   /**
    * Get SQL restrict request to determine profiles with less rights than the active one
    *
    * @param $separator string   Separator used at the beginning of the request (default 'AND')
    *
    * @return SQL restrict string
   **/
   static function getUnderActiveProfileRestrictRequest($separator="AND") {

      if ((self::$helpdesk_rights == 'reservation')
          & !ReservationItem::RESERVEANITEM) {
         return false;
      }
      if ((self::$helpdesk_rights == 'ticket')
          & !Session::haveRightsOr("ticket", array(CREATE, Ticket::READGROUP))) {
         return false;
      }
      if ((self::$helpdesk_rights == 'followup')
          && !Session::haveRightsOr('followup',
                                    array(TicketFollowup::ADDMYTICKET, TicketFollowup::UPDATEMY,
                                          TicketFollowup::SEEPUBLIC))) {
         return false;
      }
      if ((self::$helpdesk_rights == 'task')
         && !Session::haveRight('task', TicketTask::SEEPUBLIC)) {
         return false;
      }
      if ((self::$helpdesk_rights == 'validation')
            && !Session::haveRightsOr('validation', array(TicketValidation::CREATEREQUEST,
                                                          TicketValidation::CREATEINCIDENT,
                                                          TicketValidation::VALIDATEREQUEST,
                                                          TicketValidation::VALIDATEINCIDENT))) {
         return false;
      }


      $query = $separator ." ";

      // Not logged -> no profile to see
      if (!isset($_SESSION['glpiactiveprofile'])) {
         return $query." 0 ";
      }

      // Profile right : may modify profile so can attach all profile
      if (Profile::canCreate()) {
         return $query." 1 ";
      }

      if ($_SESSION['glpiactiveprofile']['interface']=='central') {
         $query .= " (`glpi_profiles`.`interface` = 'helpdesk') " ;
      }

      $query .= " OR (`glpi_profiles`.`interface` = '" .
                $_SESSION['glpiactiveprofile']['interface'] . "' ";

      // First, get all possible rights
      $right_subqueries = array();
      foreach (ProfileRight::getAllPossibleRights() as $key => $default) {
         $val = $_SESSION['glpiactiveprofile'][$key];

         if (!is_array($val) // Do not include entities field added by login
             && (($_SESSION['glpiactiveprofile']['interface'] == 'central')
                 || in_array($key,self::$helpdesk_rights))) {

            $right_subqueries[] = "(`glpi_profilerights`.`name` = '$key'
                                   AND (`glpi_profilerights`.`rights` | $val) = $val)";
         }
      }
      $query .= " AND ".count($right_subqueries)." = (
                    SELECT count(*)
                    FROM `glpi_profilerights`
                    WHERE `glpi_profilerights`.`profiles_id` = `glpi_profiles`.`id`
                     AND (".implode(' OR ', $right_subqueries).")))";
      return $query;
   }


   /**
    * Is the current user have more right than all profiles in parameters
    *
    * @param $IDs array of profile ID to test
    *
    * @return boolean true if have more right
   **/
   static function currentUserHaveMoreRightThan($IDs=array()) {
      global $DB;

      if (count($IDs) == 0) {
         // Check all profiles (means more right than all possible profiles)
         return (countElementsInTable('glpi_profiles')
                     == countElementsInTable('glpi_profiles',
                                             self::getUnderActiveProfileRestrictRequest('')));
      }
      $under_profiles = array();
      $query          = "SELECT *
                         FROM `glpi_profiles` ".
                         self::getUnderActiveProfileRestrictRequest("WHERE");
      $result         = $DB->query($query);

      while ($data = $DB->fetch_assoc($result)) {
         $under_profiles[$data['id']] = $data['id'];
      }

      foreach ($IDs as $ID) {
         if (!isset($under_profiles[$ID])) {
            return false;
         }
      }
      return true;
   }


   function showLegend() {

      echo "<div class='spaced'>";
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr class='tab_bg_2'><td width='70' style='text-decoration:underline' class='b'>";
      echo __('Caption')."</td>";
      echo "<td class='tab_bg_4' width='15' style='border:1px solid black'></td>";
      echo "<td class='b'>".__('Global right')."</td></tr>\n";
      echo "<tr class='tab_bg_2'><td></td>";
      echo "<td class='tab_bg_2' width='15' style='border:1px solid black'></td>";
      echo "<td class='b'>".__('Entity right')."</td></tr>";
      echo "</table></div>\n";
   }


   function post_getEmpty() {

      $this->fields["interface"] = "helpdesk";
      $this->fields["name"]      = __('Without name');
      unset($_SESSION['glpi_all_possible_rights']);
      $this->fields = array_merge($this->fields, ProfileRight::getAllPossibleRights());
   }


   function post_getFromDB() {
      $this->fields = array_merge($this->fields, ProfileRight::getProfileRights($this->getID()));
   }

   /**
    * Print the profile form headers
    *
    * @param $ID        integer : Id of the item to print
    * @param $options   array of possible options
    *     - target filename : where to go when done.
    *     - withtemplate boolean : template or basic item
    *
    * @return boolean item found
    **/
   function showForm($ID, $options=array()) {

      $onfocus = "";
      $new     = false;
      $rowspan = 5;
      if ($ID > 0) {
         $rowspan++;
         $this->check($ID, READ);
      } else {
         // Create item
         $this->check(-1, CREATE);
         $onfocus = "onfocus=\"if (this.value=='".$this->fields["name"]."') this.value='';\"";
         $new     = true;
      }

      $rand = mt_rand();

      $this->showFormHeader($options);

      echo "<tr class='tab_bg_1'><td>".__('Name')."</td>";
      echo "<td><input type='text' name='name' value=\"".$this->fields["name"]."\" $onfocus></td>";
      echo "<td rowspan='$rowspan' class='middle right'>".__('Comments')."</td>";
      echo "<td class='center middle' rowspan='$rowspan'>";
      echo "<textarea cols='45' rows='4' name='comment' >".$this->fields["comment"]."</textarea>";
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'><td>".__('Default profile')."</td><td>";
      Dropdown::showYesNo("is_default", $this->fields["is_default"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'><td>".__("Profile's interface")."</td>";
      echo "<td>";
      Dropdown::showFromArray('interface', self::getInterfaces(),
                              array('value'=>$this->fields["interface"]));
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'><td>".__('Update password')."</td><td>";
      Dropdown::showYesNo("password_update", $this->fields["password_update"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'><td>".__('Ticket creation form on login')."</td><td>";
      Dropdown::showYesNo("create_ticket_on_login", $this->fields["create_ticket_on_login"]);
      echo "</td></tr>\n";

      if ($ID > 0) {
         echo "<tr class='tab_bg_1'><td>".__('Last update')."</td>";
         echo "<td>";
         echo ($this->fields["date_mod"] ? Html::convDateTime($this->fields["date_mod"])
                                         : __('Never'));
         echo "</td></tr>";
      }

      $this->showFormButtons($options);

      return true;
   }


   /**
    * Print the helpdesk right form for the current profile
   **/
   function showFormHelpdesk() {
      global $CFG_GLPI;

      if (!self::canView()) {
         return false;
      }
      if ($canedit = Session::haveRightsOr(self::$rightname, array(CREATE, UPDATE, PURGE))) {
         echo "<form method='post' action='".$this->getFormURL()."'>";
      }

      echo "<table class='tab_cadre_fixe'>";
      echo "<tr class='tab_bg_1'><th colspan='4'>".__('Assistance')."</th></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td width='25%'>".__('Ticket')."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Ticket', 'helpdesk'), "_ticket",
                           $this->fields["ticket"]);
      echo "</td></tr>\n";


      echo "<tr class='tab_bg_2'>";
      echo "<td width='20%'>".__('Link with items for the creation of tickets')."</td>";
      echo "<td colspan='5'>";
      self::dropdownRights(self::getHelpdeskHardwareTypes(), 'helpdesk_hardware',
                           $this->fields["helpdesk_hardware"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td width='20%'>".__('Associable items to a ticket')."</td>";
      echo "<td colspan='5'><input type='hidden' name='_helpdesk_item_types' value='1'>";
      self::dropdownHelpdeskItemtypes(array('values' => $this->fields["helpdesk_item_type"]));
      echo "</td>";
      echo "</tr>\n";


      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('Default ticket template')."</td><td>";
      // Only root entity ones and recursive
      $options = array('value'     => $this->fields["tickettemplates_id"],
                       'entity'    => 0);
      if (Session::isMultiEntitiesMode()) {
         $options['condition'] = '`is_recursive` = 1';
      }
      $entity = implode(",", $_SESSION['glpiactiveentities']);
      if ($entity != 0) {
         $options['addicon'] = false;
      }

      TicketTemplate::dropdown($options);
      echo "</td>";
      echo "<td colspan='2'>&nbsp;";
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td width='18%'>"._n('Followup', 'Followups', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('TicketFollowup', 'helpdesk'), "_followup",
                           $this->fields["followup"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td width='18%'>"._n('Task', 'Tasks', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('TicketTask', 'helpdesk'), "_task",
                           $this->fields["task"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td width='18%'>"._n('Validation', 'Validations', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('TicketValidation', 'helpdesk'), "_validation",
            $this->fields["validation"]);
      echo "</td></tr>\n";




      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('See hardware of my group(s)')."</td><td>";
      Dropdown::showYesNo("show_group_hardware", $this->fields["show_group_hardware"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'><th colspan='4'>".__('Tools')."</th></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('FAQ')."</td><td>";
      if (($this->fields["interface"] == "helpdesk")
          && ($this->fields["knowbase"] == KnowbaseItem::PUBLISHFAQ)) {
         $this->fields["knowbase"] = KnowbaseItem::READFAQ;
      }
      self::dropdownRights(Profile::getRightsFor('KnowbaseItem', 'helpdesk'), "_knowbase",
                           $this->fields["knowbase"]);
      echo "</td>";
      echo "<td>"._n('Reservation', 'Reservations', 2)."</td><td>";
      self::dropdownRights(Profile::getRightsFor('ReservationItem', 'helpdesk'), "_reservation",
                           $this->fields["reservation"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>"._n('Public reminder', 'Public reminders', 2)."</td><td>";
      self::dropdownRights(Profile::getRightsFor('Reminder', 'helpdesk'), "_reminder_public",
                           $this->fields["reminder_public"]);
      echo "</td>";
      echo "<td>"._n('Public RSS feed', 'Public RSS feeds', 2)."</td><td>";
      self::dropdownRights(Profile::getRightsFor('RSSFeed', 'helpdesk'), "_rssfeed_public",
                           $this->fields["rssfeed_public"]);
      echo "</td>";
      echo "</td></tr>\n";

      if ($canedit) {
         echo "<tr class='tab_bg_1'>";
         echo "<td colspan='4' class='center'>";
         echo "<input type='hidden' name='id' value='".$this->fields['id']."'>";
         echo "<input type='submit' name='update' value=\"".__s('Save')."\" class='submit'>";
         echo "</td></tr>\n";
         echo "</table>\n";
         Html::closeForm();
      } else {
         echo "</table>\n";
      }
   }



   /**
    * Print the Asset rights form for the current profile
    *
    * @since version 0.85
    *
    * @param $openform  boolean open the form (true by default)
    * @param $closeform boolean close the form (true by default)
    *
   **/
   function showFormAsset($openform=true, $closeform=true) {

      if (!self::canView()) {
         return false;
      }
      if (($canedit = Session::haveRightsOr(self::$rightname, array(UPDATE, CREATE, PURGE)))
          && $openform) {
         echo "<form method='post' action='".$this->getFormURL()."'>";
      }

      echo "<div class='spaced'>";
      echo "<table class='tab_cadre_fixe'>";

      // Inventory
      echo "<tr class='tab_bg_1'><th colspan='6'>".__('Assets')."</th></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td width='18%'>"._n('Computer', 'Computers', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Computer'), "_computer",
                           $this->fields["computer"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td width='18%'>"._n('Monitor', 'Monitors', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Monitor'), "_monitor",
                           $this->fields["monitor"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td width='18%'>"._n('Software', 'Software', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Software'), "_software",
                           $this->fields["software"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>"._n('Network', 'Networks', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('NetworkEquipment'), "_networking",
                           $this->fields["networking"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>"._n('Printer', 'Printers', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Printer'), "_printer",
                           $this->fields["printer"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>"._n('Cartridge', 'Cartridges', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Cartridge'), "_cartridge",
                           $this->fields["cartridge"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>"._n('Consumable', 'Consumables', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Consumable'), "_consumable",
                           $this->fields["consumable"]);

      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>"._n('Phone', 'Phones', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Phone'), "_phone", $this->fields["phone"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>"._n('Device', 'Devices', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Peripheral'), "_peripheral",
                           $this->fields["peripheral"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('Internet')."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('NetworkName'), "_internet",
                           $this->fields["internet"]);
      echo "</td>\n";
      echo "<td colspan='4'>&nbsp;</td></tr>";

      if ($canedit
          && $closeform) {
         echo "<tr class='tab_bg_1'>";
         echo "<td colspan='6' class='center'>";
         echo "<input type='hidden' name='id' value='".$this->fields['id']."'>";
         echo "<input type='submit' name='update' value=\""._sx('button','Save')."\" class='submit'>";
         echo "</td></tr>\n";
         echo "</table>\n";
         Html::closeForm();
      } else {
         echo "</table>\n";
      }
      echo "</div>";
   }


   /**
    * Print the Management/Tools rights form for the current profile
    *
    * @param $openform  boolean open the form (true by default)
    * @param $closeform boolean close the form (true by default)
   **/
   function showFormInventory($openform=true, $closeform=true) {

      if (!self::canView()) {
         return false;
      }
      if (($canedit = Session::haveRightsOr(self::$rightname, array(UPDATE, CREATE, PURGE)))
          && $openform) {
         echo "<form method='post' action='".$this->getFormURL()."'>";
      }

      echo "<div class='spaced'>";
      echo "<table class='tab_cadre_fixe'>";

       // Gestion / Management
      echo "<tr class='tab_bg_1'><th colspan='6'>".__('Management')."</th></tr>";

      echo "<tr class='tab_bg_2'>";
      echo "<td width='18%'>"._n('Contacts', 'Contacts', 2)." / "._n('Supplier', 'Suppliers', 2).
           "</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Contact'), "_contact_enterprise",
                           $this->fields["contact_enterprise"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td width='18%'>"._n('Document', 'Documents', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Document'), "_document",
                           $this->fields["document"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td width='18%'>"._n('Contract', 'Contracts', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Contract'), "_contract",
                           $this->fields["contract"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'><td>".__('Financial and administratives information')."</td>".
           "<td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Infocom'), "_infocom",
                           $this->fields["infocom"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('Budget')."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Budget'), "_budget", $this->fields["budget"]);
      echo "</td></tr>\n";

      // Outils / Tools
      echo "<tr class='tab_bg_1'><th colspan='6'>".__('Tools')."</th></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>"._n('Public reminder', 'Public reminders', 2)."</td><td>";
      self::dropdownRights(Profile::getRightsFor('Reminder'), "_reminder_public",
                           $this->fields["reminder_public"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>"._n('Public RSS feed', 'Public RSS feeds', 2)."</td><td>";
      self::dropdownRights(Profile::getRightsFor('RSSFeed'), "_rssfeed_public",
                           $this->fields["rssfeed_public"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>"._n('Public bookmark', 'Public bookmarks', 2)."</td><td>";
      self::dropdownRights(Profile::getRightsFor('Bookmark'), "_bookmark_public",
                           $this->fields["bookmark_public"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>"._n('Report', 'Reports', 2)."</td><td>";
      self::dropdownRights(Profile::getRightsFor('Report'), "_reports", $this->fields["reports"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('Knowledge base')."</td><td>";
      self::dropdownRights(Profile::getRightsFor('KnowbaseItem'), "_knowbase",
                           $this->fields["knowbase"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('Administration of reservations')."</td><td>";
      self::dropdownRights(Profile::getRightsFor('ReservationItem'), "_reservation",
                           $this->fields["reservation"]);
      echo "</td></tr>\n";

      if ($canedit
          && $closeform) {
         echo "<tr class='tab_bg_1'>";
         echo "<td colspan='6' class='center'>";
         echo "<input type='hidden' name='id' value='".$this->fields['id']."'>";
         echo "<input type='submit' name='update' value=\""._sx('button','Save')."\" class='submit'>";
         echo "</td></tr>\n";
         echo "</table>\n";
         Html::closeForm();
      } else {
         echo "</table>\n";
      }
      echo "</div>";
   }


   /**
    * Print the Tracking right form for the current profile
    *
    * @param $openform     boolean  open the form (true by default)
    * @param $closeform    boolean  close the form (true by default)
   **/
   function showFormTracking($openform=true, $closeform=true) {
      global $CFG_GLPI;

      if (!self::canView()) {
         return false;
      }
      if (($canedit = Session::haveRightsOr(self::$rightname, array(CREATE, UPDATE, PURGE)))
          && $openform) {
         echo "<form method='post' action='".$this->getFormURL()."'>";
      }

      echo "<div class='spaced'>";
      echo "<table class='tab_cadre_fixe'>";

      // Assistance / Tracking-helpdesk
      echo "<tr class='tab_bg_1'><th colspan='6'>".__('Assistance')."</th></tr>\n";

      echo "<tr class='tab_bg_5'><th colspan='6'>"._n('Ticket', 'Tickets', 2)."</th></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td width='18%'>"._n('Ticket', 'Tickets', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Ticket'), "_ticket",
                           $this->fields["ticket"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>"._n('Ticket cost', 'Ticket costs', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('TicketCost'), "_ticketcost",
                           $this->fields["ticketcost"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('Recurrent tickets')."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('TicketRecurrent'), "_ticketrecurrent",
                           $this->fields["ticketrecurrent"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>"._n('Ticket template', 'Ticket templates', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('TicketTemplate'), "_tickettemplate",
                           $this->fields["tickettemplate"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('Default ticket template')."</td><td  width='30%'>";
      // Only root entity ones and recursive
      $options = array('value'     => $this->fields["tickettemplates_id"],
                       'entity'    => 0);
      if (Session::isMultiEntitiesMode()) {
         $options['condition'] = '`is_recursive` = 1';
      }
      $entity = implode(",", $_SESSION['glpiactiveentities']);
      if ($entity != 0) {
         $options['addicon'] = false;
      }

      TicketTemplate::dropdown($options);
      echo "</td></tr>\n";


      echo "<tr class='tab_bg_5'>";
      echo "<th colspan='6'>"._n('Followup', 'Followups', 2)." / "._n('Task', 'Tasks', 2) ."</th>";
      echo "</tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td width='18%'>"._n('Followup', 'Followups', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('TicketFollowup'), "_followup",
                           $this->fields["followup"]);
      echo "</td></tr>\n";
      echo "<tr class='tab_bg_2'>";
      echo "<td width='18%'>"._n('Task', 'Tasks', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('TicketTask'), "_task", $this->fields["task"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_5'>";
      echo "<th colspan='6'>"._n('Validation', 'Validations', 2)."</th>";
      echo "</tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td width='18%'>"._n('Validation', 'Validations', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('TicketValidation'), "_validation",
                           $this->fields["validation"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_5'><th colspan='6'>".__('Association')."</th>";
      echo "</tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('See hardware of my groups')."</td><td>";
      Dropdown::showYesNo("show_group_hardware", $this->fields["show_group_hardware"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('Link with items for the creation of tickets')."</td>";
      echo "\n<td>";
      self::dropdownRights(self::getHelpdeskHardwareTypes(), 'helpdesk_hardware',
                           $this->fields["helpdesk_hardware"]);

      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('Associable items to a ticket')."</td>";
      echo "<td  colspan='5'><input type='hidden' name='_helpdesk_item_types' value='1'>";
      self::dropdownHelpdeskItemtypes(array('values' => $this->fields["helpdesk_item_type"]));
      echo "</td>";
      echo "</tr>\n";

      echo "<tr class='tab_bg_5'><th colspan='6'>".__('Visibility')."</th>";
      echo "</tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('Statistics')."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Stat'), "_statistic", $this->fields["statistic"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('Planning')."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Planning'), "_planning",
                           $this->fields["planning"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_5'>";
      echo "<th colspan='6'>"._n('Problem', 'Problems', 2)." / "._n('Change', 'Changes', 2);
      echo "</th></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>"._n('Problem', 'Problems', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Problem'), "_problem", $this->fields["problem"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>"._n('Change', 'Changes', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Change'), "_change", $this->fields["change"]);
      echo "</td></tr>\n";

      if ($canedit
          && $closeform) {
         echo "<tr class='tab_bg_1'>";
         echo "<td colspan='6' class='center'>";
         echo "<input type='hidden' name='id' value='".$this->fields['id']."'>";
         echo "<input type='submit' name='update' value=\""._sx('button','Save')."\" class='submit'>";
         echo "</td></tr>\n";
         echo "</table>\n";
         Html::closeForm();
      } else {
         echo "</table>\n";
      }
      echo "</div>";
   }


   /**
   * Print the Life Cycles form for the current profile
   *
   * @param $openform   boolean  open the form (true by default)
   * @param $closeform  boolean  close the form (true by default)
   **/
   function showFormLifeCycle($openform=true, $closeform=true) {

      if (!self::canView()) {
         return false;
      }

      if (($canedit = Session::haveRightsOr(self::$rightname, array(CREATE, UPDATE, PURGE)))
          && $openform) {
         echo "<form method='post' action='".$this->getFormURL()."'>";
      }

      echo "<div class='spaced'>";

      echo "<table class='tab_cadre_fixe'>";
      $tabstatus = Ticket::getAllStatusArray();

      echo "<th colspan='".(count($tabstatus)+1)."'>".__('Life cycle of tickets')."</th>";
      //TRANS: \ to split row heading (From) and colums headin (To) for life cycles
      echo "<tr class='tab_bg_1'><td class='b center'>".__("From \ To");
      echo "<input type='hidden' name='_cycles_ticket' value='1'</td>";
      foreach ($tabstatus as $label) {
         echo "<td class='rotate4cb'>$label</td>";
      }
      echo "</tr>\n";

      foreach ($tabstatus as $from => $label) {
         echo "<tr class='tab_bg_2'><td class='tab_bg_1'>$label</td>";
         foreach ($tabstatus as $dest => $label) {
            echo "<td class='center'>";
            if ($dest == $from) {
               echo Dropdown::getYesNo(1);
            } else {
               Dropdown::showYesNo("_cycle_ticket[$from][$dest]",
                                   (!isset($this->fields['ticket_status'][$from][$dest])
                                    || $this->fields['ticket_status'][$from][$dest]));
            }
            echo "</td>";
         }
         echo "</tr>\n";
      }
      echo "</table>";

      echo "<table class='tab_cadre_fixe'>";
      $tabstatus = Problem::getAllStatusArray();

      echo "<th colspan='".(count($tabstatus)+1)."'>".__('Life cycle of problems')."</th>";
      echo "<tr class='tab_bg_1'><td class='b center'>".__('From \ To');
      echo "<input type='hidden' name='_cycles_problem' value='1'</td>";
      foreach ($tabstatus as $label) {
         echo "<td class='center' width='11%'>$label</td>";
      }
      echo "</tr>\n";

      foreach ($tabstatus as $from => $label) {
         echo "<tr class='tab_bg_2'><td class='tab_bg_1'>$label</td>";
         foreach ($tabstatus as $dest => $label) {
            echo "<td class='center'>";
            if ($dest == $from) {
               echo Dropdown::getYesNo(1);
            } else {
               Dropdown::showYesNo("_cycle_problem[$from][$dest]",
                                   (!isset($this->fields['problem_status'][$from][$dest])
                                    || $this->fields['problem_status'][$from][$dest]));
            }
            echo "</td>";
         }
         echo "</tr>\n";
      }

      echo "</table>";

      echo "<table class='tab_cadre_fixe'>";
      $tabstatus = Change::getAllStatusArray();

      echo "<th colspan='".(count($tabstatus)+1)."'>".__('Life cycle of changes')."</th>";
      echo "<tr class='tab_bg_1'><td class='b center'>".__('From \ To');
      echo "<input type='hidden' name='_cycles_change' value='1'</td>";
      foreach ($tabstatus as $label) {
         echo "<td class='center' width='9%'>$label</td>";
      }
      echo "</tr>\n";

      foreach ($tabstatus as $from => $label) {
         echo "<tr class='tab_bg_2'><td class='tab_bg_1'>$label</td>";
         foreach ($tabstatus as $dest => $label) {
            echo "<td class='center'>";
            if ($dest == $from) {
               echo Dropdown::getYesNo(1);
            } else {
               Dropdown::showYesNo("_cycle_change[$from][$dest]",
                                   (!isset($this->fields['change_status'][$from][$dest])
                                    || $this->fields['change_status'][$from][$dest]));
            }
            echo "</td>";
         }
         echo "</tr>\n";
      }

      if ($canedit
          && $closeform) {
         echo "<tr class='tab_bg_1'>";
         echo "<td colspan='".(count($tabstatus)+1)."' class='center'>";
         echo "<input type='hidden' name='id' value='".$this->fields['id']."'>";
         echo "<input type='submit' name='update' value=\""._sx('button','Save')."\" class='submit'>";
         echo "</td></tr>\n";
         echo "</table>\n";
         Html::closeForm();
      } else {
         echo "</table>\n";
      }
      echo "</div>";
   }


   /**
    * Print the central form for a profile
    *
    * @param $openform     boolean  open the form (true by default)
    * @param $closeform    boolean  close the form (true by default)
   **/
   function showFormAdmin($openform=true, $closeform=true) {
      global $DB;

      if (!self::canView()) {
         return false;
      }

      echo "<div class='firstbloc'>";
      if (($canedit = Session::haveRightsOr(self::$rightname, array(CREATE, UPDATE, PURGE)))
          && $openform) {
         echo "<form method='post' action='".$this->getFormURL()."'>";
      }

      echo "<table class='tab_cadre_fixe'>";

      // Administration
      echo "<tr class='tab_bg_1'><th colspan='6'>".__('Administration')."</th></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td width='18%'>"._n('User', 'Users', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('User'), "_user", $this->fields["user"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_4'>";
      echo "<td>"._n('Entity', 'Entities', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Entity'), "_entity", $this->fields["entity"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_4'>";
      echo "<td>"._n('Group', 'Groups', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Group'), "_group", $this->fields["group"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_4'>";
      echo "<td>".self::getTypeName(2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Profile'), "_profile",
                           $this->fields["profile"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_4'>";
      echo "<td>".__('Mail queue')."</td><td>";
      self::dropdownRights(Profile::getRightsFor('QueuedMail'), "_queuedmail",
                           $this->fields["queuedmail"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_4'>";
      echo "<td>".__('Maintenance')."</td><td>";
      self::dropdownRights(Profile::getRightsFor('Backup'), "_backup", $this->fields["backup"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_4'>";
      echo "<td>"._n('Log', 'Logs', 2)."</td><td>";
      self::dropdownRights(Profile::getRightsFor('Log'), "_logs", $this->fields["logs"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_4'>";
      echo "<td>".__('Transfer')."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Transfer'), "_transfer",
                           $this->fields["transfer"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_1'><th colspan='6'>"._n('Rule', 'Rules', 2)."</th>";

      echo "<tr class='tab_bg_4'>";
      echo "<td>".__('Authorizations assignment rules')."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Rule'), "_rule_ldap",
                           $this->fields["rule_ldap"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_4'>";
      echo "<td>".__('Rules for assigning a computer to an entity')."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('RuleImportComputer'), "_rule_import",
                           $this->fields["rule_import"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_4'>";
      echo "<td>".__('Rules for assigning a ticket created through a mails receiver')."</td>";
      echo "<td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('RuleMailCollector'), "_rule_mailcollector",
                           $this->fields["rule_mailcollector"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_4'>";
      echo "<td>".__('Rules for assigning a category to a software')."</td><td>";
      self::dropdownRights(Profile::getRightsFor('RuleSoftwareCategory'),
                           "_rule_softwarecategories", $this->fields["rule_softwarecategories"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_4'>";
      echo "<td class='tab_bg_1'>".__('Business rules for tickets (entity)')."</td>";
      echo "<td class='tab_bg_1' colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('RuleTicket'), "_rule_ticket",
                           $this->fields["rule_ticket"]);
      echo"</td></tr>\n";

      echo "<tr class='tab_bg_1'><th colspan='6'>"._n('Dictionary', 'Dictionaries', 2)."</th></tr>\n";

      echo "<tr class='tab_bg_4'>";
      echo "<td>".__('Dropdowns dictionary')."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('RuleDictionnaryDropdown'),
                           "_rule_dictionnary_dropdown",
                           $this->fields["rule_dictionnary_dropdown"]);
      echo"</td></tr>\n";

      echo "<tr class='tab_bg_4'>";
      //TRANS: software in plural
      echo "<td>".__('Software dictionary')."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('RuleDictionnarySoftware'),
                           "_rule_dictionnary_software",
                           $this->fields["rule_dictionnary_software"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_4'>";
      echo "<td>".__('Printers dictionnary')."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('RuleDictionnaryPrinter'),
                           "_rule_dictionnary_printer",
                           $this->fields["rule_dictionnary_printer"]);
      echo "</td></tr>";


      if ($canedit
          && $closeform) {
         echo "<tr class='tab_bg_1'>";
         echo "<td colspan='6' class='center'>";
         echo "<input type='hidden' name='id' value='".$this->fields['id']."'>";
         echo "<input type='submit' name='update' value=\""._sx('button','Save')."\" class='submit'>";
         echo "</td></tr>\n";
         echo "</table>\n";
         Html::closeForm();
      } else {
         echo "</table>\n";
      }
      echo "</div>";

      $this->showLegend();
   }

   /**
    * Print the central form for a profile
    *
    * @param $openform     boolean  open the form (true by default)
    * @param $closeform    boolean  close the form (true by default)
   **/
   function showFormSetup($openform=true, $closeform=true) {

      if (!self::canView()) {
         return false;
      }

      echo "<div class='firstbloc'>";
      if (($canedit = Session::haveRightsOr(self::$rightname, array(CREATE, UPDATE, PURGE)))
          && $openform) {
         echo "<form method='post' action='".$this->getFormURL()."'>";
      }

      echo "<table class='tab_cadre_fixe'>";

      // Setup
      echo "<tr class='tab_bg_1'><th colspan='6'>".__('Setup')."</th></tr>\n";

      echo "<tr class='tab_bg_4'>";
      echo "<td width='18%'>".__('General setup')."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Config'), "_config",
                           $this->fields["config"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_4'>";
      echo "<td width='18%'>".__('Search result display')."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('DisplayPreference'), "_search_config",
                           $this->fields["search_config"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_4'>";
      echo "<td>"._n('Component', 'Components', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Item_Devices'), "_device",
                           $this->fields["device"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_4'>";
      echo "<td>"._n('Global dropdown', 'Global dropdowns', 2)."</td><td>";
      $tab = CommonDBTM::getRights();
      unset($tab[DELETE]);
      self::dropdownRights($tab, "_dropdown", $this->fields["dropdown"]);
      echo "</td></tr>";

      echo "<tr class='tab_bg_2'>";
      echo "<td class=' b'>".__('Entity dropdowns')." :</td>";
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>&nbsp;&nbsp;&nbsp;"._n('Domain', 'Domains', 2)."</td>";
      echo "<td>";
      self::dropdownRights(Profile::getRightsFor('Domain'), "_domain", $this->fields["domain"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>&nbsp;&nbsp;&nbsp;"._n('Location', 'Locations', 2)."</td>";
      echo "<td>";
      self::dropdownRights(Profile::getRightsFor('Location'), "_location",
                           $this->fields["location"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>&nbsp;&nbsp;&nbsp;"._n('Category of ticket', 'Categories of tickets', 2)."</td>";
      echo "<td>";
      self::dropdownRights(Profile::getRightsFor('ITILCategory'), "_itilcategory",
                           $this->fields["itilcategory"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>&nbsp;&nbsp;&nbsp;"._n('Knowledge base category', 'Knowledge base categories', 2);
      echo "</td>";
      echo "<td>";
      self::dropdownRights(Profile::getRightsFor('KnowbaseItemCategory'), "_knowbasecategory",
                           $this->fields["knowbasecategory"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>&nbsp;&nbsp;&nbsp;"._n('Network outlet', 'Network outlets', 2)."</td>";
      echo "<td>";
      self::dropdownRights(Profile::getRightsFor('Netpoint'), "_netpoint",
                           $this->fields["netpoint"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>&nbsp;&nbsp;&nbsp;"._n('Tasks category','Tasks categories', 2)."</td>";
      echo "<td>";
      self::dropdownRights(Profile::getRightsFor('TaskCategory'), "_taskcategory",
                           $this->fields["taskcategory"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td class='tab_bg_2'>&nbsp;&nbsp;&nbsp;"._n('Status of items', 'Statuses of items', 2);
      echo "</td>";
      echo "<td class='tab_bg_2'>";
      self::dropdownRights(Profile::getRightsFor('State'), "_state", $this->fields["state"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>&nbsp;&nbsp;&nbsp;"._n('Solution template', 'Solution templates', 2)."</td>";
      echo "<td>";
      self::dropdownRights(Profile::getRightsFor('SolutionTemplate'), "_solutiontemplate",
                           $this->fields["solutiontemplate"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>&nbsp;&nbsp;&nbsp;"._n('Calendar', 'Calendars', 2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Calendar'), "_calendar",
                           $this->fields["calendar"]);
      echo "</td></tr>\n";



      echo "<tr class='tab_bg_4'>";
      echo "<td>".__('Document type')."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('DocumentType'), "_typedoc",
                           $this->fields["typedoc"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_4'>";
      echo "<td>"._n('External link', 'External links',2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Link'), "_link", $this->fields["link"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>"._n('Notification', 'Notifications',2)."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('Notification'), "_notification",
                           $this->fields["notification"]);
      echo "</td></tr>\n";

      echo "<tr class='tab_bg_2'>";
      echo "<td>".__('SLA')."</td><td colspan='5'>";
      self::dropdownRights(Profile::getRightsFor('SLA'), "_sla", $this->fields["sla"]);
      echo "</td></tr>\n";


      if ($canedit
          && $closeform) {
         echo "<tr class='tab_bg_1'>";
         echo "<td colspan='6' class='center'>";
         echo "<input type='hidden' name='id' value='".$this->fields['id']."'>";
         echo "<input type='submit' name='update' value=\""._sx('button','Save')."\" class='submit'>";
         echo "</td></tr>\n";
         echo "</table>\n";
         Html::closeForm();
      } else {
         echo "</table>\n";
      }
      echo "</div>";

      $this->showLegend();
   }


   function getSearchOptions() {

      $tab                       = array();
      $tab['common']             = __('Characteristics');

      $tab[1]['table']           = $this->getTable();
      $tab[1]['field']           = 'name';
      $tab[1]['name']            = __('Name');
      $tab[1]['datatype']        = 'itemlink';
      $tab[1]['massiveaction']   = false;

      $tab[19]['table']          = $this->getTable();
      $tab[19]['field']          = 'date_mod';
      $tab[19]['name']           = __('Last update');
      $tab[19]['datatype']       = 'datetime';
      $tab[19]['massiveaction']  = false;

      $tab[2]['table']           = $this->getTable();
      $tab[2]['field']           = 'interface';
      $tab[2]['name']            = __("Profile's interface");
      $tab[2]['massiveaction']   = false;
      $tab[2]['datatype']        = 'specific';

      $tab[3]['table']           = $this->getTable();
      $tab[3]['field']           = 'is_default';
      $tab[3]['name']            = __('Default profile');
      $tab[3]['datatype']        = 'bool';
      $tab[3]['massiveaction']   = false;

      $tab[118]['table']         = $this->getTable();
      $tab[118]['field']         = 'create_ticket_on_login';
      $tab[118]['name']          = __('Ticket creation form on login');
      $tab[118]['datatype']      = 'bool';

      $tab[16]['table']          = $this->getTable();
      $tab[16]['field']          = 'comment';
      $tab[16]['name']           = __('Comments');
      $tab[16]['datatype']       = 'text';

      $tab['inventory']          = __('Assets');

      $tab[20]['table']          = 'glpi_profilerights';
      $tab[20]['field']          = 'rights';
      $tab[20]['name']           = _n('Computer', 'Computers', 2);
      $tab[20]['datatype']       = 'right';
      $tab[20]['rightclass']     = 'Computer';
      $tab[20]['rightname']      = 'computer';
      $tab[20]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'computer'");

      $tab[21]['table']          = 'glpi_profilerights';
      $tab[21]['field']          = 'rights';
      $tab[21]['name']           = _n('Monitor', 'Monitors', 2);
      $tab[21]['datatype']       = 'right';
      $tab[21]['rightclass']     = 'Monitor';
      $tab[21]['rightname']      = 'monitor';
      $tab[21]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'monitor'");

      $tab[22]['table']          = 'glpi_profilerights';
      $tab[22]['field']          = 'rights';
      $tab[22]['name']           = _n('Software', 'Software', 2);
      $tab[22]['datatype']       = 'right';
      $tab[22]['rightclass']     = 'Software';
      $tab[22]['rightname']      = 'software';
      $tab[22]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'software'");

      $tab[23]['table']          = 'glpi_profilerights';
      $tab[23]['field']          = 'rights';
      $tab[23]['name']           = _n('Network', 'Networks', 2);
      $tab[23]['datatype']       = 'right';
      $tab[23]['rightclass']     = 'Networking';
      $tab[23]['rightname']      = 'networking';
      $tab[23]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'networking'");

      $tab[24]['table']          = 'glpi_profilerights';
      $tab[24]['field']          = 'rights';
      $tab[24]['name']           = _n('Printer', 'Printers',2);
      $tab[24]['datatype']       = 'right';
      $tab[24]['rightclass']     = 'Printer';
      $tab[24]['rightname']      = 'printer';
      $tab[24]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'printer'");

      $tab[25]['table']          = 'glpi_profilerights';
      $tab[25]['field']          = 'rights';
      $tab[25]['name']           = _n('Device', 'Devices', 2);
      $tab[25]['datatype']       = 'right';
      $tab[25]['rightclass']     = 'Peripheral';
      $tab[25]['rightname']      = 'peripheral';
      $tab[25]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'peripheral'");

      $tab[26]['table']          = 'glpi_profilerights';
      $tab[26]['field']          = 'rights';
      $tab[26]['name']           = _n('Cartridge', 'Cartridges', 2);
      $tab[26]['datatype']       = 'right';
      $tab[26]['rightclass']     = 'Cartridge';
      $tab[26]['rightname']      = 'cartridge';
      $tab[26]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'cartridge'");

      $tab[27]['table']          = 'glpi_profilerights';
      $tab[27]['field']          = 'rights';
      $tab[27]['name']           = _n('Consumable', 'Consumables', 2);
      $tab[27]['datatype']       = 'right';
      $tab[27]['rightclass']     = 'Consumable';
      $tab[27]['rightname']      = 'consumable';
      $tab[27]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'consumable'");

      $tab[28]['table']          = 'glpi_profilerights';
      $tab[28]['field']          = 'rights';
      $tab[28]['name']           = _n('Phone', 'Phones', 2);
      $tab[28]['datatype']       = 'right';
      $tab[28]['rightclass']     = 'Phone';
      $tab[28]['rightname']      = 'phone';
      $tab[28]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'phone'");

      $tab[129]['table']         = 'glpi_profilerights';
      $tab[129]['field']         = 'rights';
      $tab[129]['name']          = __('Internet');
      $tab[129]['datatype']      = 'right';
      $tab[129]['rightclass']    = 'NetworkName';
      $tab[129]['rightname']     = 'internet';
      $tab[129]['joinparams']    = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'internet'");

      $tab['management']         = __('Management');

      $tab[30]['table']          = 'glpi_profilerights';
      $tab[30]['field']          = 'rights';
      $tab[30]['name']           = __('Contact')." / ".__('Supplier');
      $tab[30]['datatype']       = 'right';
      $tab[30]['rightclass']     = 'Contact';
      $tab[30]['rightname']      = 'contact_entreprise';
      $tab[30]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'contact_enterprise'");

      $tab[31]['table']          = 'glpi_profilerights';
      $tab[31]['field']          = 'rights';
      $tab[31]['name']           = _n('Document', 'Documents', 2);
      $tab[31]['datatype']       = 'right';
      $tab[31]['rightclass']     = 'Document';
      $tab[31]['rightname']      = 'document';
      $tab[31]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'document'");

      $tab[32]['table']          ='glpi_profilerights';
      $tab[32]['field']          = 'rights';
      $tab[32]['name']           = _n('Contract', 'Contracts', 2);
      $tab[32]['datatype']       = 'right';
      $tab[32]['rightclass']     = 'Contract';
      $tab[32]['rightname']      = 'contract';
      $tab[32]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'contract'");

      $tab[33]['table']          = 'glpi_profilerights';
      $tab[33]['field']          = 'rights';
      $tab[33]['name']           = __('Financial and administratives information');
      $tab[33]['datatype']       = 'right';
      $tab[33]['rightclass']     = 'Infocom';
      $tab[33]['rightname']      = 'infocom';
      $tab[33]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'infocom'");

      $tab[101]['table']         = 'glpi_profilerights';
      $tab[101]['field']         = 'rights';
      $tab[101]['name']          = __('Budget');
      $tab[101]['datatype']      = 'right';
      $tab[101]['rightclass']    = 'Budget';
      $tab[101]['rightname']     = 'budget';
      $tab[101]['joinparams']    = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'budget'");

      $tab['tools']              = __('Tools');

      $tab[34]['table']          = 'glpi_profilerights';
      $tab[34]['field']          = 'rights';
      $tab[34]['name']           = __('Knowledge base');
      $tab[34]['datatype']       = 'right';
      $tab[34]['rightclass']     = 'KnowbaseItem';
      $tab[34]['rightname']      = 'knowbase';
      $tab[34]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'knowbase'");

      $tab[36]['table']          = 'glpi_profilerights';
      $tab[36]['field']          = 'rights';
      $tab[36]['name']           = _n('Reservation', 'Reservations', 2);
      $tab[36]['datatype']       = 'right';
      $tab[36]['rightclass']     = 'ReservationItem';
      $tab[36]['rightname']      = 'reservation';
      $tab[36]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'reservation'");

      $tab[38]['table']          = 'glpi_profilerights';
      $tab[38]['field']          = 'rights';
      $tab[38]['name']           = _n('Report', 'Reports', 2);
      $tab[38]['datatype']       = 'right';
      $tab[38]['rightclass']     = 'Report';
      $tab[38]['rightname']      = 'reports';
      $tab[38]['nowrite']        = true;
      $tab[38]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'reports'");

      $tab['config']             = __('Setup');

      $tab[42]['table']          = 'glpi_profilerights';
      $tab[42]['field']          = 'rights';
      $tab[42]['name']           = _n('Dropdown', 'Dropdowns', 2);
      $tab[42]['datatype']       = 'right';
      $tab[42]['rightclass']     = 'DropdownTranslation';
      $tab[42]['rightname']      = 'dropdown';
      $tab[42]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'dropdown'");

      $tab[44]['table']          = 'glpi_profilerights';
      $tab[44]['field']          = 'rights';
      $tab[44]['name']           = _n('Component', 'Components', 2);
      $tab[44]['datatype']       = 'right';
      $tab[44]['rightclass']     = 'Item_Devices';
      $tab[44]['rightname']      = 'device';
      $tab[44]['noread']         = true;
      $tab[44]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'device'");

      $tab[106]['table']         = 'glpi_profilerights';
      $tab[106]['field']         = 'rights';
      $tab[106]['name']          = _n('Notification', 'Notifications',2);
      $tab[106]['datatype']      = 'right';
      $tab[106]['rightclass']    = 'Notification';
      $tab[106]['rightname']     = 'notification';
      $tab[106]['joinparams']    = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'notification'");

      $tab[45]['table']          = 'glpi_profilerights';
      $tab[45]['field']          = 'rights';
      $tab[45]['name']           = __('Document type');
      $tab[45]['datatype']       = 'right';
      $tab[45]['rightclass']     = 'DocumentType';
      $tab[45]['rightname']      = 'typedoc';
      $tab[45]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'typedoc'");

      $tab[46]['table']          = 'glpi_profilerights';
      $tab[46]['field']          = 'rights';
      $tab[46]['name']           = _n('External link', 'External links',2);
      $tab[46]['datatype']       = 'right';
      $tab[46]['rightclass']     = 'Link';
      $tab[46]['rightname']      = 'link';
      $tab[46]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'link'");

      $tab[47]['table']          = 'glpi_profilerights';
      $tab[47]['field']          = 'rights';
      $tab[47]['name']           = __('General setup');
      $tab[47]['datatype']       = 'right';
      $tab[47]['rightclass']     = 'Config';
      $tab[47]['rightname']      = 'config';
      $tab[47]['noread']         = true;
      $tab[47]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'config'");

      $tab[52]['table']          = 'glpi_profilerights';
      $tab[52]['field']          = 'rights';
      $tab[52]['name']           = __('Search result user display');
      $tab[52]['datatype']       = 'right';
      $tab[52]['rightclass']     = 'DisplayPreference';
      $tab[52]['rightname']      = 'search_config';
      $tab[52]['noread']         = true;
      $tab[52]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'search_config'");

      $tab[107]['table']         = 'glpi_profilerights';
      $tab[107]['field']         = 'rights';
      $tab[107]['name']          = _n('Calendar', 'Calendars', 2);
      $tab[107]['datatype']      = 'right';
      $tab[107]['rightclass']    = 'Calendar';
      $tab[107]['rightname']     = 'calendar';
      $tab[107]['joinparams']    = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'calendar'");

      $tab['admin']              = __('Administration');

      $tab[48]['table']          = 'glpi_profilerights';
      $tab[48]['field']          = 'rights';
      $tab[48]['name']           = __('Business rules for tickets');
      $tab[48]['datatype']       = 'right';
      $tab[48]['rightclass']     = 'RuleTicket';
      $tab[48]['rightname']      = 'rule_ticket';
      $tab[48]['nowrite']        = true;
      $tab[48]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'rule_ticket'");

      $tab[105]['table']         = 'glpi_profilerights';
      $tab[105]['field']         = 'rights';
      $tab[105]['name']          = __('Rules for assigning a ticket created through a mails receiver');
      $tab[105]['datatype']      = 'right';
      $tab[105]['rightclass']    = 'RuleMailCollector';
      $tab[105]['rightname']     = 'rule_mailcollector';
      $tab[105]['joinparams']    = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'rule_mailcollector'");

      $tab[49]['table']          = 'glpi_profilerights';
      $tab[49]['field']          = 'rights';
      $tab[49]['name']           = __('Rules for assigning a computer to an entity');
      $tab[49]['datatype']       = 'right';
      $tab[49]['rightclass']     = 'RuleImportComputer';
      $tab[49]['rightname']      = 'rule_import';
      $tab[49]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'rule_import'");

      $tab[50]['table']          = 'glpi_profilerights';
      $tab[50]['field']          = 'rights';
      $tab[50]['name']           = __('Authorizations assignment rules');
      $tab[50]['datatype']       = 'right';
      $tab[50]['rightclass']     = 'Rule';
      $tab[50]['rightname']      = 'rule_ldap';
      $tab[50]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'rule_ldap'");

      $tab[51]['table']          = 'glpi_profilerights';
      $tab[51]['field']          = 'rights';
      $tab[51]['name']           = __('Rules for assigning a category to a software');
      $tab[51]['datatype']       = 'right';
      $tab[51]['rightclass']     = 'RuleSoftwareCategory';
      $tab[51]['rightname']      = 'rule_softwarecategories';
      $tab[51]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'rule_softwarecategories'");

      $tab[90]['table']          = 'glpi_profilerights';
      $tab[90]['field']          = 'rights';
      $tab[90]['name']           = __('Software dictionary');
      $tab[90]['datatype']       = 'right';
      $tab[90]['rightclass']     = 'RuleDictionnarySoftware';
      $tab[90]['rightname']      = 'rule_dictionnary_software';
      $tab[90]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'rule_dictionnary_software'");

      $tab[91]['table']          = 'glpi_profilerights';
      $tab[91]['field']          = 'rights';
      $tab[91]['name']           =__('Dropdowns dictionary');
      $tab[91]['datatype']       = 'right';
      $tab[91]['rightclass']     = 'RuleDictionnaryDropdown';
      $tab[91]['rightname']      = 'rule_dictionnary_dropdown';
      $tab[91]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'rule_dictionnary_dropdown'");

      $tab[55]['table']          = 'glpi_profilerights';
      $tab[55]['field']          = 'rights';
      $tab[55]['name']           = self::getTypeName(2);
      $tab[55]['datatype']       = 'right';
      $tab[55]['rightclass']     = 'Profile';
      $tab[55]['rightname']      = 'profile';
      $tab[55]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'profile'");

      $tab[56]['table']          = 'glpi_profilerights';
      $tab[56]['field']          = 'rights';
      $tab[56]['name']           = _n('User', 'Users', 2);
      $tab[56]['datatype']       = 'right';
      $tab[56]['rightclass']     = 'User';
      $tab[56]['rightname']      = 'user';
      $tab[56]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'user'");

      $tab[58]['table']          = 'glpi_profilerights';
      $tab[58]['field']          = 'rights';
      $tab[58]['name']           = _n('Group', 'Groups', 2);
      $tab[58]['datatype']       = 'right';
      $tab[58]['rightclass']     = 'Group';
      $tab[58]['rightname']      = 'group';
      $tab[58]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'group'");

      $tab[59]['table']          = 'glpi_profilerights';
      $tab[59]['field']          = 'rights';
      $tab[59]['name']           = _n('Entity', 'Entities', 2);
      $tab[59]['datatype']       = 'right';
      $tab[59]['rightclass']     = 'Entity';
      $tab[59]['rightname']      = 'entity';
      $tab[59]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'entity'");

      $tab[60]['table']          = 'glpi_profilerights';
      $tab[60]['field']          = 'rights';
      $tab[60]['name']           = __('Transfer');
      $tab[60]['datatype']       = 'right';
      $tab[60]['rightclass']     = 'Transfer';
      $tab[60]['rightname']      = 'transfer';
      $tab[60]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'transfer'");

      $tab[61]['table']          = 'glpi_profilerights';
      $tab[61]['field']          = 'rights';
      $tab[61]['name']           = _n('Log', 'Logs', 2);
      $tab[61]['datatype']       = 'right';
      $tab[61]['rightclass']     = 'Log';
      $tab[61]['rightname']      = 'logs';
      $tab[61]['nowrite']        = true;
      $tab[61]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'logs'");

      $tab[62]['table']          = 'glpi_profilerights';
      $tab[62]['field']          = 'rights';
      $tab[62]['name']           = __('Maintenance');
      $tab[62]['datatype']       = 'right';
      $tab[62]['rightclass']     = 'Backup';
      $tab[62]['rightname']      = 'backup';
      $tab[62]['noread']         = true;
      $tab[62]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'backup'");

      $tab['ticket']             = __('Assistance');

      $tab[102]['table']         = 'glpi_profilerights';
      $tab[102]['field']         = 'rights';
      $tab[102]['name']          = __('Create a ticket');
      $tab[102]['datatype']      = 'right';
      $tab[102]['rightclass']    = 'Ticket';
      $tab[102]['rightname']     = 'ticket';
      $tab[102]['joinparams']    = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'ticket'");

      $tab[108]['table']         = 'glpi_tickettemplates';
      $tab[108]['field']         = 'name';
      $tab[108]['name']          = __('Default ticket template');
      $tab[108]['datatype']      = 'dropdown';
      if (Session::isMultiEntitiesMode()) {
         $tab[108]['condition']     = '`entities_id` = 0 AND `is_recursive` = 1';
      } else {
         $tab[108]['condition']     = '`entities_id` = 0';
      }

      $tab[103]['table']         = 'glpi_profilerights';
      $tab[103]['field']         = 'rights';
      $tab[103]['name']          = _n('Ticket template', 'Ticket templates', 2);
      $tab[103]['datatype']      = 'right';
      $tab[103]['rightclass']    = 'TicketTemplate';
      $tab[103]['rightname']     = 'tickettemplate';
      $tab[103]['joinparams']    = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'tickettemplate'");

      $tab[79]['table']          = 'glpi_profilerights';
      $tab[79]['field']          = 'rights';
      $tab[79]['name']           = __('Plannings');
      $tab[79]['datatype']       = 'right';
      $tab[79]['rightclass']     = 'Planning';
      $tab[79]['rightname']      = 'planning';
      $tab[79]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'planning'");

      $tab[85]['table']          = 'glpi_profilerights';
      $tab[85]['field']          = 'rights';
      $tab[85]['name']           = __('Statistics');
      $tab[85]['datatype']       = 'right';
      $tab[85]['rightclass']     = 'Stat';
      $tab[85]['rightname']      = 'statistic';
      $tab[85]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'statistic'");

      $tab[119]['table']         = 'glpi_profilerights';
      $tab[119]['field']         = 'rights';
      $tab[119]['name']          = _n('Ticket cost', 'Ticket costs', 2);
      $tab[119]['datatype']      = 'right';
      $tab[119]['rightclass']    = 'TicketCost';
      $tab[119]['rightname']     = 'ticketcost';
      $tab[119]['joinparams']    = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'ticketcost'");

      $tab[86]['table']          = $this->getTable();
      $tab[86]['field']          = 'helpdesk_hardware';
      $tab[86]['name']           = __('Link with items for the creation of tickets');
      $tab[86]['massiveaction']  = false;
      $tab[86]['datatype']       = 'specific';

      $tab[87]['table']          = $this->getTable();
      $tab[87]['field']          = 'helpdesk_item_type';
      $tab[87]['name']           = __('Associable items to a ticket');
      $tab[87]['massiveaction']  = false;
      $tab[87]['datatype']       = 'specific';

      $tab[89]['table']          = 'glpi_profilerights';
      $tab[89]['field']          = 'rights';
      $tab[89]['name']           = __('See hardware of my groups');
      $tab[89]['datatype']       = 'bool';
      $tab[89]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'show_group_hardware'");

      $tab[100]['table']         = $this->getTable();
      $tab[100]['field']         = 'ticket_status';
      $tab[100]['name']          = __('Life cycle of tickets');
      $tab[100]['nosearch']      = true;
      $tab[100]['datatype']      = 'text';
      $tab[100]['massiveaction'] = false;

      $tab[110]['table']         = $this->getTable();
      $tab[110]['field']         = 'problem_status';
      $tab[110]['name']          = __('Life cycle of problems');
      $tab[110]['nosearch']      = true;
      $tab[110]['datatype']      = 'text';
      $tab[110]['massiveaction'] = false;

      $tab[112]['table']         = 'glpi_profilerights';
      $tab[112]['field']         = 'right';
      $tab[112]['name']          = _n('Problem', 'Problems', 2);
      $tab[112]['datatype']      = 'right';
      $tab[112]['rightclass']    = 'Problem';
      $tab[112]['rightname']     = 'problem';
      $tab[112]['joinparams']    = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'problem'");


      $tab[111]['table']         = $this->getTable();
      $tab[111]['field']         = 'change_status';
      $tab[111]['name']          = __('Life cycle of changes');
      $tab[111]['nosearch']      = true;
      $tab[111]['datatype']      = 'text';
      $tab[111]['massiveaction'] = false;

      $tab[115]['table']         = 'glpi_profilerights';
      $tab[115]['field']         = 'right';
      $tab[115]['name']          =_n('Change', 'Changes', 2);
      $tab[115]['datatype']      = 'right';
      $tab[115]['rightclass']    = 'Change';
      $tab[115]['rightname']     = 'change';
      $tab[115]['joinparams']    = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'change'");


      $tab['other']              = __('Other');

      $tab[4]['table']           = 'glpi_profilerights';
      $tab[4]['field']           = 'right';
      $tab[4]['name']            = __('Update password');
      $tab[4]['datatype']        = 'bool';
      $tab[4]['joinparams']     = array('jointype' => 'child',
                                        'condition' => "AND `NEWTABLE`.`name`= 'password_update'");

      $tab[63]['table']          = 'glpi_profilerights';
      $tab[63]['field']          = 'rights';
      $tab[63]['name']           = _n('Public reminder', 'Public reminders', 2);
      $tab[63]['datatype']       = 'right';
      $tab[63]['rightclass']     = 'Reminder';
      $tab[63]['rightname']      = 'reminder_public';
      $tab[63]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'reminder_public'");

      $tab[64]['table']          = 'glpi_profilerights';
      $tab[64]['field']          = 'rights';
      $tab[64]['name']           = _n('Public bookmark', 'Public bookmarks', 2);
      $tab[64]['datatype']       = 'right';
      $tab[64]['rightclass']     = 'Bookmark';
      $tab[64]['rightname']      = 'bookmark_public';
      $tab[64]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'bookmark_public'");

      $tab[120]['table']          = 'glpi_profilerights';
      $tab[120]['field']          = 'rights';
      $tab[120]['name']           = _n('Public RSS feed', 'Public RSS feeds', 2);
      $tab[120]['datatype']       = 'right';
      $tab[120]['rightclass']     = 'RSSFeed';
      $tab[120]['rightname']      = 'rssfeed_public';
      $tab[120]['joinparams']     = array('jointype' => 'child',
                                         'condition' => "AND `NEWTABLE`.`name`= 'rssfeed_public'");

      return $tab;
   }


   /**
    * @since version 0.84
    *
    * @param $field
    * @param $values
    * @param $options   array
    **/
   static function getSpecificValueToDisplay($field, $values, array $options=array()) {

      if (!is_array($values)) {
         $values = array($field => $values);
      }
      switch ($field) {
         case 'interface':
            return self::getInterfaceName($values[$field]);

         case 'helpdesk_hardware':
            return self::getHelpdeskHardwareTypeName($values[$field]);

         case "helpdesk_item_type":
            $types = explode(',', $values[$field]);
            $message = array();
            foreach ($types as $type) {
               if ($item = getItemForItemtype($type)) {
                  $message[] = $item->getTypeName();
               }
            }
            return implode(', ',$message);
      }
      return parent::getSpecificValueToDisplay($field, $values, $options);
   }


   /**
    * @since version 0.84
    *
    * @param $field
    * @param $name               (default '')
    * @param $values             (default '')
    * @param $options      array
   **/
   static function getSpecificValueToSelect($field, $name='', $values='', array $options=array()) {

      if (!is_array($values)) {
         $values = array($field => $values);
      }
      $options['display'] = false;
      switch ($field) {
         case 'interface' :
            $options['value'] = $values[$field];
            return Dropdown::showFromArray($name, self::getInterfaces(), $options);

         case 'helpdesk_hardware' :
            $options['value'] = $values[$field];
            return Dropdown::showFromArray($name, self::getHelpdeskHardwareTypes(), $options);

         case "helpdesk_item_type":
            $options['values'] = explode(',', $values[$field]);
            $options['name']  = $name;
            return self::dropdownHelpdeskItemtypes($options);
      }
      return parent::getSpecificValueToSelect($field, $name, $values, $options);
   }


   /**
    * Make a select box for a None Read Write choice
    *
    * @param $name      select name
    * @param $value     preselected value.
    * @param $none      display none choice ? (default 1)
    * @param $read      display read choice ? (default 1)
    * @param $write     display write choice ? (default 1)
    *
    * @return nothing (print out an HTML select box)
    * \deprecated since version 0.84 use dropdownRight instead
   **/
   static function dropdownNoneReadWrite($name, $value, $none=1, $read=1, $write=1) {

      return self::dropdownRight($name, array('value'     => $value,
                                              'nonone'  => !$none,
                                              'noread'  => !$read,
                                              'nowrite' => !$write));
   }


   /**
    * Make a select box for rights
    *
    * @since version 0.85
    *
    * @param $values    array    of values to display
    * @param $name      integer  name of the dropdown
    * @param $current   integer  value in database (sum of rights)
   **/
   static function dropdownRights(array $values, $name, $current, $options=array()) {

      $param['multiple']= true;
      $param['display'] = true;
      $param['size']    = count($values);
      $tabselect = array();
      foreach ($values as $k => $v) {
         if ($current & $k) {
            $tabselect[] = $k;
         }
      }
      $param['values'] =  $tabselect;

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $param[$key] = $val;
         }
      }

      // To allow dropdown with no value to be in prepareInputForUpdate
      // without this, you can't have an empty dropdown
      // done to avoid define NORIGHT value
      if ($param['multiple']) {
         echo "<input type='hidden' name='".$name."[]' value='0'>";
      }
      return Dropdown::showFromArray($name, $values, $param);
   }



   /**
    * Make a select box for a None Read Write choice
    *
    * @since version 0.84
    *
    * @param $name          select name
    * @param $options array of possible options:
    *       - value   : preselected value.
    *       - nonone  : hide none choice ? (default false)
    *       - noread  : hide read choice ? (default false)
    *       - nowrite : hide write choice ? (default false)
    *       - display : display or get string (default true)
    *       - rand    : specific rand (default is generated one)
    *
    * @return nothing (print out an HTML select box)
   **/
   static function dropdownRight($name, $options=array()) {

      $param['value']   = '';
      $param['display'] = true;
      $param['nonone']  = false;
      $param['noread']  = false;
      $param['nowrite'] = false;
      $param['rand']    = mt_rand();

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $param[$key] = $val;
         }
      }

      $values = array();
      if (!$param['nonone']) {
         $values[0] = __('No access');
      }
      if (!$param['noread']) {
         $values[READ] = __('Read');
      }
      if (!$param['nowrite']) {
         $values[CREATE] = __('Write');
      }
      return Dropdown::showFromArray($name, $values,
                                     array('value'   => $param['value'],
                                           'rand'    => $param['rand'],
                                           'display' => $param['display']));
   }


   /**
    * Dropdown profiles which have rights under the active one
    *
    * @param $options array of possible options:
    *    - name : string / name of the select (default is profiles_id)
    *    - value : integer / preselected value (default 0)
    *
   **/
   static function dropdownUnder($options=array()) {
      global $DB;

      $p['name']  = 'profiles_id';
      $p['value'] = '';

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }

      $profiles[0] = Dropdown::EMPTY_VALUE;

      $query = "SELECT *
                FROM `glpi_profiles` ".
                self::getUnderActiveProfileRestrictRequest("WHERE")."
                ORDER BY `name`";
      $res = $DB->query($query);

      //New rule -> get the next free ranking
      if ($DB->numrows($res)) {
         while ($data = $DB->fetch_assoc($res)) {
            $profiles[$data['id']] = $data['name'];
         }
      }
      Dropdown::showFromArray($p['name'], $profiles, array('value' => $p['value']));
   }


   /**
    * Get the default Profile for new user
    *
    * @return integer profiles_id
   **/
   static function getDefault() {
      global $DB;

      foreach ($DB->request('glpi_profiles', array('is_default'=>1)) as $data) {
         return $data['id'];
      }
      return 0;
   }


   /**
    * @since version 0.84
   **/
   static function getInterfaces() {

     return array('central'  => __('Standard interface'),
                  'helpdesk' => __('Simplified interface'));
   }


   /**
    * @param $value
   **/
   static function getInterfaceName($value) {

      $tab = self::getInterfaces();
      if (isset($tab[$value])) {
         return $tab[$value];
      }
      return NOT_AVAILABLE;
   }


   /**
    * @since version 0.84
   **/
   static function getHelpdeskHardwareTypes() {

      return array(0                                        => Dropdown::EMPTY_VALUE,
                   pow(2, Ticket::HELPDESK_MY_HARDWARE)     => __('My devices'),
                   pow(2, Ticket::HELPDESK_ALL_HARDWARE)    => __('All items'),
                   pow(2, Ticket::HELPDESK_MY_HARDWARE)
                    + pow(2, Ticket::HELPDESK_ALL_HARDWARE) => __('My devices and all items'));
   }


   /**
    * @since version 0.84
    *
    * @param $value
   **/
   static function getHelpdeskHardwareTypeName($value) {

      $tab = self::getHelpdeskHardwareTypes();
      if (isset($tab[$value])) {
         return $tab[$value];
      }
      return NOT_AVAILABLE;
   }


   /**
    * Dropdown profiles which have rights under the active one
    *
    * @since ersin 0.84
    *
    * @param $options array of possible options:
    *    - name : string / name of the select (default is profiles_id)
    *    - values : array of values
   **/
   static function dropdownHelpdeskItemtypes($options) {
      global $CFG_GLPI;

      $p['name']    = 'helpdesk_item_type';
      $p['values']  = array();
      $p['display'] = true;

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }
      $values = array();
      foreach ($CFG_GLPI["ticket_types"] as $key => $itemtype) {
         if ($item = getItemForItemtype($itemtype)) {
            if (!isPluginItemType($itemtype)) { // No Plugin for the moment
               $values[$itemtype] = $item->getTypeName();
            }
         } else {
            unset($CFG_GLPI["ticket_types"][$key]);
         }
      }

      $p['multiple'] = true;
      $p['size']     = 3;
      return Dropdown::showFromArray($p['name'], $values, $p);
   }


   /**
    * function to check one right of a user
    *
    * @since version 0.84
    *
    * @param $user       integer                id of the user to check rights
    * @param $right      string                 right to check
    * @param $valright   integer/string/array   value of the rights searched
    * @param $entity     integer                id of the entity
    *
    * @return boolean
    */
   static function haveUserRight($user, $right, $valright, $entity) {
      global $DB;

      $query = "SELECT $right
                FROM `glpi_profiles`
                INNER JOIN `glpi_profiles_users`
                   ON (`glpi_profiles`.`id` = `glpi_profiles_users`.`profiles_id`)
                WHERE `glpi_profiles_users`.`users_id` = '$user'
                      AND $right IN ('$valright') ".
                      getEntitiesRestrictRequest(" AND ", "glpi_profiles_users", '', $entity, true);

      if ($result = $DB->query($query)) {
         if ($DB->numrows($result)) {
            return true;
         }
      }
      return false;
   }


   /**
    * Get rights for an itemtype
    *
    * @since version 0.85
    *
    * @param $itemtype   string   itemtype
    * @param $interface  string   (defautl 'central')
    *
    * @return rights
   **/
   static function getRightsFor($itemtype, $interface='central') {

      if (class_exists($itemtype)) {
         $item = new $itemtype();
         return $item->getRights($interface);
      }
      return false;
   }

}
?>
