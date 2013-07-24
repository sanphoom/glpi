<?php
/*
 * @version $Id: ticketvalidation_user.class.php 21251 2013-07-24 09:21:49Z ludo $
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
 * Groups_TicketValidation class
 */
class TicketValidation_User  extends CommonDBRelation {
   // From CommonDBRelation
   static public $itemtype_1          = 'TicketValidation';
   static public $items_id_1          = 'ticketvalidations_id';
   static public $itemtype_2          = 'User';
   static public $items_id_2          = 'users_id_validate';
   
   static public $checkItem_2_Rights  = self::DONT_CHECK_ITEM_RIGHTS;
   static public $logs_for_item_2     = false;
   
    /**
    * Get Ticket validation groups id
    * 
    * @param $ticketvalidations_id ID of the ticketvalidation
    * @param $users_id array of a user ID
    * @param $groups_id ID of the group
    * 
    * @param $value ticketvalidation ID
   **/
   static function getUsersTicketValidationId($ticketvalidations_id, $users_id) {
      global $DB;
      
      $users_validations_id = 0;
      if(!is_array($users_id)) $users_id = array($users_id);

      $query = "SELECT `id`
                   FROM `glpi_ticketvalidations_users`
                   WHERE `ticketvalidations_id` = '".$ticketvalidations_id."'
                   AND `users_id_validate` IN ('".implode("','", $users_id)."')";
      if ($result = $DB->query($query)) {
         if ($DB->numrows($result) > 0) {
            $users_validations_id = $DB->result($result,0,'id');
         }
      }
      return $users_validations_id;
   }
   
   
   /**
    * Get users for a group ticketvalidation
    *
    * @param $ticketvalidations_id ID of the ticketvalidation
    *
    * @return array of users linked to a ticketvalidation
   **/
   static function getUsersValidation($ticketvalidations_id) {
      global $DB;

      $users = array();
      $query = "SELECT `glpi_ticketvalidations_users`.`users_id_validate` as id, 
                       `glpi_ticketvalidations_users`.`status`,
                       `glpi_ticketvalidations_users`.`comment_validation`,
                       `glpi_users`.`name`,
                       `glpi_users`.`realname`,
                       `glpi_users`.`firstname`
                   FROM `glpi_ticketvalidations_users`
                   LEFT JOIN `glpi_users`
                     ON(`glpi_ticketvalidations_users`.`users_id_validate` = `glpi_users`.`id`)
                   WHERE `ticketvalidations_id` = '".$ticketvalidations_id."'";

      foreach ($DB->request($query) as $data) {
         $users[$data['id']] = $data;
      }
      return $users;
   }

   /**
    * Get validation status for each user of validation group
    *
    * @param $ticketvalidations_id ID of the ticketvalidation
    * @param $html_output return html or array
    * 
    * @return user name - status
   **/
   static function getUsersValidationStatus($ticketvalidations_id) {
      global $DB;
      
      $status = array();
      
      $query = "SELECT `glpi_ticketvalidations_users`.`users_id_validate` as id, 
                       `glpi_ticketvalidations_users`.`status`,
                       `glpi_users`.`name`,
                       `glpi_users`.`realname`,
                       `glpi_users`.`firstname`
                       FROM `glpi_ticketvalidations_users`
                       LEFT JOIN `glpi_users`
                         ON(`glpi_ticketvalidations_users`.`users_id_validate` = `glpi_users`.`id`)
                       WHERE `ticketvalidations_id` = '".$ticketvalidations_id."' 
                       AND `glpi_ticketvalidations_users`.`users_id_validate` != 0
                       ORDER BY validation_date DESC";
      

      foreach ($DB->request($query) as $data) {
         $status[$data['id']]['status'] = $data['status'];
         $status[$data['id']]['name'] = formatUserName($data['id'], $data['name'], 
                                          $data['realname'], $data['firstname']);
      }
      return $status;
      
   }
   
   /**
    * Show status for selected validation users
    *
    * @param $ticketvalidations_id ID of the ticketvalidation
    * @param $options - add_status      : user name => status
    *                 - showprogressbar : show progress bar of validation
    *                 - width           : progress bar width
    *
    * @return html
   **/
   static function showUsersValidationStatus($ticketvalidations_id, $all_users, $options=array()){
      if(!isset($options['showprogressbar'])) $options['showprogressbar'] = true;
      $data = self::getUsersValidationStatus($ticketvalidations_id);
      
      $content = '';
      $ticketvalidation = new TicketValidation();
      
      if(count($data) > 1){
         $content .= "<table style='border-collapse:collapse;'>";
         foreach($data as $status){
            if(!empty($status['status']) && !empty($status['name'])){
               $bgcolor = $ticketvalidation->getStatusColor($status['status']);
                  $content .= "<tr style='background-color:".$bgcolor.";'><td><b>".$status['name']."</b></td><td>&nbsp;".$ticketvalidation->getStatus($status['status'])."</td></tr>";
            }
         }
         $content .= "</table>";
         
         // Show progress bar of validation
         if($options['showprogressbar']){
            $accepted = 0;
            $rand = mt_rand();
            foreach($data as $status){
               switch ($status['status']){
                  case 'accepted' : $accepted++; break;
               }
            }
            echo "<div id='validator_progress$rand'>";
            Html::displayProgressBar(isset($options['width']) ? $options['width']:100, 
                    $accepted/count($all_users)*100, array('simple' => true));
            echo "</div>";
            Html::showToolTip($content, array('applyto' => "validator_progress$rand"));
         } else {
            echo $content;
         }
         
      } elseif(count($data) == 1) {
         foreach($data as $status){
            if(!empty($status['name'])){
               echo $status['name'];
            }
         }
      } 
   }
   
   /**
    * Get validation comments for each user of validation group
    *
    * @param $ticketvalidations_id ID of the ticketvalidation
    * @param $html_output return html or array
    *
    * @return user name - comments
   **/
   static function getUsersValidationComments($ticketvalidations_id, $html_output=true) {
      global $DB;
      
      $comment = array();
      
      $query = "SELECT `glpi_ticketvalidations_users`.`users_id_validate` as id, 
                       `glpi_ticketvalidations_users`.`comment_validation`,
                       `glpi_users`.`name`,
                       `glpi_users`.`realname`,
                       `glpi_users`.`firstname`
                       FROM `glpi_ticketvalidations_users`
                       LEFT JOIN `glpi_users`
                         ON(`glpi_ticketvalidations_users`.`users_id_validate` = `glpi_users`.`id`)
                       WHERE `ticketvalidations_id` = '".$ticketvalidations_id."'
                       AND comment_validation != ''
                       ORDER BY validation_date DESC";

      if($html_output){
         foreach ($DB->request($query) as $data) {
            $comment[] = "<b>".formatUserName($data['id'], 
                    $data['name'], $data['realname'], $data['firstname'])."</b> - ".
                    $data['comment_validation'];
         }
         return implode('<br>', $comment);
      } else {
         foreach ($DB->request($query) as $data) {
            $comment[$data['id']]['comment_validation'] = $data['comment_validation'];
            $comment[$data['id']]['name'] = formatUserName($data['id'], $data['name'], 
                                             $data['realname'], $data['firstname']);
         }
         return $comment;
      }
   }
   
   
   /**
    * Show comments for selected validation users
    *
    * @param $ticketvalidations_id ID of the ticketvalidation
    * @param $options - add_comment : user name => comment
    *
    * @return html
   **/
   static function showUsersValidationComments($ticketvalidations_id){
      $data = self::getUsersValidationComments($ticketvalidations_id, false);
      
      if(count($data) > 1) {
         foreach($data as $comments) {
            if(!empty($comments['comment_validation'])) {
               echo $comments['name'].'&nbsp;';
               Html::showToolTip($comments['comment_validation']);
               echo (count($data) > 1) ? '<br>':'';
            }
         }
      } elseif(count($data) == 1) {
         foreach($data as $comments) {
            if(!empty($comments['comment_validation'])){
               echo $comments['comment_validation'];
            }
         }
      }
   }
   
   
   /**
    * Get the validation status 
    *
    * @param $ticketvalidations_id ID of the ticketvalidation
    * @param $groups_id ID of the ticketvalidation group
    * @param $validation_percent validation mode for the group : 0 - first user validate or reject
    *                                                           1 - 50% of user validate
    *                                                           2 - 100% of user validate 
    *
    * @return validation status
   **/
   static function getValidationStatus($ticketvalidations_id, $all_users, $validation_percent=0) {
      $validation_status = 'waiting';
      
      $accepted = 0;
      $rejected = 0;
      $waiting  = 0;

      $status = self::getUsersValidationStatus($ticketvalidations_id);

      // Percent of validation
      if($validation_percent > 0){
         $percent = self::showValidationRequired($validation_percent);
         foreach($status as $data){
            switch ($data['status']){
               case 'accepted' : $accepted++; break;
               case 'rejected' : $rejected++; break;
               case 'waiting'  : $waiting++;  break;
            }
         }
         if($accepted/count($all_users)*100 >= $percent){
            $validation_status = 'accepted';
         } elseif($rejected/count($all_users)*100 >= $percent){
            $validation_status = 'rejected';
         }
      } else {
         // One user validate, we get the last validation
         $data = reset($status);
         switch ($data['status']){
            case 'accepted' : $accepted++; break;
            case 'rejected' : $rejected++; break;
            case 'waiting'  : $waiting++;  break;
         }
         if($accepted){
            $validation_status = 'accepted';
         } elseif($rejected){
            $validation_status = 'rejected';
         }
      }

      return $validation_status;
   }

   /**
    * Get sql restriction for validation groups
    * 
    * @return restriction
   **/
   static function getGroupsIdValidateRestrictions() {
      $restrict = "`glpi_ticketvalidations_users`.`users_id` = '".Session::getLoginUserID()."'";
      
      return $restrict;
   }
   

   /**
    * Check if logged user is in validation
    *
    * @param $ticketvalidations_id ID of the validation  
    * 
    * @return boolean
   **/
   static function isUserInValidation($ticketvalidations_id){
      
      return array_key_exists(Session::getLoginUserID(), self::getUsersValidation($ticketvalidations_id));
   }

   /**
    * Delete or add users of a validation
    *
    * @param $input validation input
    * 
   **/
   function updateValidationUsers($input){
      // Check deleted users
      $current = self::getUsersValidation($input['ticketvalidations_id']);
      foreach($current as $users_id => $data){
         $input['id'] = TicketValidation_User::getUsersTicketValidationId(
                    $input['ticketvalidations_id'], 
                    $users_id);
         
         if(!in_array($users_id, $input['users_id_validate'])){
            $this->delete($input);
         }
      }

      // Check added users
      if(isset($input['users_id_validate'])){
         foreach($input['users_id_validate'] as $users_id){
            if(!in_array($users_id, array_keys($current))){
               $input['users_id_validate'] = $users_id;
               unset($input['id']);
               unset($input['comment_validation']);
               $this->add($input);
            }
         }
      } 
      
      // Update current user validation
      $input['id'] = TicketValidation_User::getUsersTicketValidationId(
           $input['ticketvalidations_id'], 
           Session::getLoginUserID());
      unset($input['users_id_validate']);
      $this->update($input);
   }
   
   /**
    * Show group validation value
    * 
    * @param integer $validation_percent
    */
   static function showValidationRequired($validation_percent, $integer=true){
      $validation = self::getValidationRequired($integer);
      
      return array_key_exists($validation_percent, $validation) ? $validation[$validation_percent]:'';
   }
   
   /**
    * Get group validation values
    *
    * @return array
    */
   static function getValidationRequired($integer=true){
      $validation = array(0, 50, 100);
      if(!$integer){
         foreach($validation as &$value){
            $value = $value.'%';
         }
      }
      
      return $validation;
   }
   
   /**
    * Get validation percent required
    * 
    * @param $options : name - name of the dropdown
    *                   value - preselected value
    *
    * @return array
    */
   static function dropdownValidationRequired(array $options=array()){
      $params['name']  = 'validation_percent';
      $params['value'] = '';
      
      foreach ($options as $key => $val) {
         $params[$key] = $val;
      }
      
      Dropdown::showFromArray($params['name'], 
                              TicketValidation_User::getValidationRequired(false), 
                              array('value' => $params['value']));  
   }

   function getSearchOptions() {
      $tab                       = array();
      
      $tab['common']             = __('Approver group');
      
      $tab[8]['table']           = 'glpi_users';
      $tab[8]['field']           = 'name';
      $tab[8]['linkfield']       = 'users_id';
      $tab[8]['name']            = __('Approver');
      $tab[8]['datatype']        = 'itemlink';
      $tab[8]['right']           = array('validate_request', 'validate_incident');

      return $tab;
   }
   
}
?>