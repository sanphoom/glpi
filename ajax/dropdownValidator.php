<?php
/*
 * @version $Id: dropdownValidator.php 21251 2013-07-24 09:21:49Z ludo $
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
* @since version 0.85
*/

$AJAX_INCLUDE = 1;
include ('../inc/includes.php');

header("Content-Type: text/html; charset=UTF-8");
Html::header_nocache();

if (isset($_POST["validatortype"])) {
   switch ($_POST["validatortype"]){
      case 'user' :
         User::dropdown(array('name'   => !empty($_POST['name']) ? $_POST['name'].'[]'
                                                                 :'users_id_validate[]',
                              'entity' => $_POST['entity'],
                              'right'  => $_POST['right']));
         break;

      case 'group' :
         $condition = "(SELECT count(`users_id`)
                        FROM `glpi_groups_users`
                        WHERE `groups_id` = `glpi_groups`.`id`)";
         $name      = !empty($_POST['name']) ? $_POST['name'].'[groups_id]':'groups_id';
         $rand      = Group::dropdown(array('name'      => $name,
                                            'value'     => $_POST['groups_id'],
                                            'condition' => $condition,
                                            'entity'    => $_SESSION["glpiactive_entity"],
                                            'right'     => array('validate_request',
                                                                 'validate_incident')));

         $param                      = array('validatortype'      => 'list_users',
                                             'validation_percent' => $_POST['validation_percent']);
         $param['name']              = !empty($_POST['name']) ? $_POST['name']:'';
         $param['users_id_validate'] = isset($_POST['users_id_validate'])
                                             ? $_POST['users_id_validate']:'';
         $param['groups_id']         = '__VALUE__';
         Ajax::updateItemOnSelectEvent("dropdown_$name$rand", "show_list_users",
                                       $CFG_GLPI["root_doc"]."/ajax/dropdownValidator.php",
                                       $param);

         echo "<br><span id='show_list_users'>&nbsp;</span>\n";
         break;

      case 'list_users' :
         if(!empty($_POST['groups_id'])){
            $data_users = Group_user::getGroupUsers($_POST['groups_id']);
         } else {
            $data_users = $_POST['users_id_validate'];
         }

         $users           = array();
         $param['values'] = array();
         foreach($data_users as $data){
            $users[$data['id']] = formatUserName($data['id'], $data['name'], $data['realname'],
                                                 $data['firstname']);
         }
         // Dislpay selected users
         if (!empty($_POST['users_id_validate'])){
            $current = $_POST['users_id_validate'];
            foreach($current as $data){
               $current[$data['id']] = formatUserName($data['id'],
                                                    $data['name'],
                                                    $data['realname'],
                                                    $data['firstname']);
            }

            $tabselect = array();
            foreach ($users as $k => $v) {
               if (isset($current[$k])) {
                  $tabselect[] = $k;
               }
            }
            $param['values'] =  $tabselect;
         // Dislpay all users
         } else if (isset($_POST['all_users']) && $_POST['all_users']){
            $param['values'] =  array_keys($users);
         // Dislpay no users
         } else if (isset($_POST['all_users']) && !$_POST['all_users']){
            $users = array();
         }

         $param['multiple']= true;
         $param['display'] = true;
         $param['size']    = count($users);
         $users = Toolbox::stripslashes_deep($users);

         $rand = Dropdown::showFromArray(
                 !empty($_POST['name']) ? $_POST['name']:'users_id_validate',
                 $users,
                 $param);

         // Display all/none buttons to select all or no users in group
         if (!empty($_POST['groups_id'])){
            echo "<a id='all_users' class='vsubmit'>".__('All')."</a>";
            $param_button['validatortype']      = 'list_users';
            $param_button['validation_percent'] = $_POST['validation_percent'];
            $param_button['name']               = !empty($_POST['name']) ? $_POST['name']:'';
            $param_button['users_id_validate']  = '';
            $param_button['all_users']          = 1;
            $param_button['groups_id']          = $_POST['groups_id'];
            Ajax::updateItemOnEvent('all_users', 'show_list_users', $CFG_GLPI["root_doc"]."/ajax/dropdownValidator.php", $param_button, array('click'));

            echo "&nbsp;<a id='no_users' class='vsubmit'>".__('None')."</a>";;
            $param_button['all_users'] = 0;
            Ajax::updateItemOnEvent('no_users', 'show_list_users', $CFG_GLPI["root_doc"]."/ajax/dropdownValidator.php", $param_button, array('click'));
         }

         echo "<br><br>&nbsp;".__('Minimum validation required')."&nbsp;";
         $name = !empty($_POST['name']) ? $_POST['name'].'[validation_percent]':'validation_percent';
         TicketValidation_User::dropdownValidationRequired(array('value' => $_POST['validation_percent'],
                                                                  'name' => $name));

         break;
   }
}
?>