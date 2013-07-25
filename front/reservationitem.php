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

include ('../inc/includes.php');

if (!Session::haveRightsOr('reservation', array(READ, ReservationItem::RESERVEANITEM))) {
   Session::redirectIfNotLoggedIn();
   Html::displayRightError();
}

if ($_SESSION["glpiactiveprofile"]["interface"] == "helpdesk") {
   Html::helpHeader(__('Simplified interface'), $_SERVER['PHP_SELF'], $_SESSION["glpiname"]);
} else {
   Html::header(Reservation::getTypeName(2), $_SERVER['PHP_SELF'], "tools", "reservationitem");
}

if (!Session::haveRight("reservation", READ)) {
   ReservationItem::showListSimple();
} else {
   if (isset($_POST['reserve'])) {
      echo "<div id='viewresasearch'  class='center'>";
      Toolbox::manageBeginAndEndPlanDates($_POST['reserve']);
      echo "<div id='nosearch' class='center firstbloc'>".
            "<a href=\"".$CFG_GLPI['root_doc']."/front/reservationitem.php\">";
      echo __('See all reservable items')."</a></div>\n";
   } else {
      Search::show('ReservationItem');
      echo "<div id='makesearch' class='center firstbloc'>".
            "<a class='pointer' onClick=\"javascript:showHideDiv('viewresasearch','','','');".
                "showHideDiv('makesearch','','','')\">";
      echo __('Find a free item in a specific period')."</a></div>\n";
      echo "<div id='viewresasearch' style=\"display:none;\" class='center'>";
      $begin_time = time();
      $begin_time -= ($begin_time%HOUR_TIMESTAMP);
      $_POST['reserve']["begin"] = date("Y-m-d H:i:s",$begin_time);
      $_POST['reserve']["end"]   = date("Y-m-d H:i:s",$begin_time+HOUR_TIMESTAMP);
      $_POST['reservation_types'] = '';
   }

   echo "<form method='post' name='form' action='".$_SERVER['PHP_SELF']."'>---";
   echo "<table class='tab_cadre'><tr class='tab_bg_2'>";
   echo "<th colspan='3'>".__('Find a free item in a specific period')."</th></tr>";

   echo "<tr class='tab_bg_2'><td>".__('Start date')."</td><td>";
   Html::showDateTimeField("reserve[begin]", array('value'      =>  $_POST['reserve']["begin"],
                                                   'maybeempty' => false));
   echo "</td><td rowspan='4'>";
   echo "<input type='submit' class='submit' name='submit' value=\""._sx('button', 'Search')."\">";
   echo "</td></tr>";

   echo "<tr class='tab_bg_2'><td>".__('Duration')."</td><td>";
   $default_delay = floor((strtotime($_POST['reserve']["end"]) - strtotime($_POST['reserve']["begin"]))
                          /$CFG_GLPI['time_step']/MINUTE_TIMESTAMP)
                    *$CFG_GLPI['time_step']*MINUTE_TIMESTAMP;

   Dropdown::showTimeStamp("reserve[_duration]", array('min'        => 0,
                                                       'max'        => 48*HOUR_TIMESTAMP,
                                                       'value'      => $default_delay,
                                                       'emptylabel' => __('Specify an end date')));
   echo "</td></tr>";

   echo "<tr class='tab_bg_2'><td>".__('Item type')."</td><td>";
   $values[0] = Dropdown::EMPTY_VALUE;
   foreach ($CFG_GLPI["reservation_types"] as $key => $val) {
      $values[$val] = $val;
   }
   foreach ($DB->request('glpi_peripheraltypes', array('ORDER' => 'name')) as $ptype) {
      $id = $ptype['id'];
      $values["Peripheral#$id"] = $ptype['name'];
   }
   Dropdown::showFromArray("reservation_types", $values,
                           array('value' => $_POST['reservation_types']));

   echo "</td></tr>";

   echo "<tr class='tab_bg_2'><td>".__('Location')."</td><td>";
   Location::dropdown(array('entity'     =>  $_SESSION['glpiactiveentities'],
                            'emptylabel' => Dropdown::EMPTY_VALUE));


   echo "</table>";
   Html::closeForm();
   echo "</div>";

   if (isset($_POST['_reserve'])) {
      echo "<div id='nosearch' class='center'>";
      echo "<form name='form' method='GET' action='reservationitem.php'>";

      echo "<table class='tab_cadre'>";
      echo "<tr><th colspan='6'>".$_POST['reservation_types']."</th></tr>\n";
      echo "<tr><th colspan='2'>".__('Name')."</th>".
               "<th>".__('Location')."</th>".
               "<th>".__('Comments')."</th>".
               "<th>".__('Entity')."</th>".
               "<th>".__('Inventory number')."</th>";

      foreach ($CFG_GLPI["reservation_types"] as $itemtype) {
         if (!($item = getItemForItemtype($itemtype))) {
            continue;
         }
         $itemtable = getTableForItemType($itemtype);
         $otherserial = "'' AS otherserial";
         if ($item->isField('otherserial')) {
            $otherserial = "`$itemtable`.`otherserial`";
         }
         $begin = $_POST['reserve']["begin"];
         $end   = $_POST['reserve']["end"];

         $left = "";
         $where = "";
         if (isset($begin) && isset($end)) {
            $left = "LEFT JOIN `glpi_reservations`
                        ON (`glpi_reservationitems`.`id` = `glpi_reservations`.`reservationitems_id`
                            AND '". $begin."' < `glpi_reservations`.`end`
                            AND '". $end."' > `glpi_reservations`.`begin`)";

            $where = " AND `glpi_reservations`.`id` IS NULL ";
         }
         if (isset($_POST["reservation_types"]) && ($_POST["reservation_types"])) {
            $tmp = explode('#', $_POST["reservation_types"]);
            $where .= " AND `glpi_reservationitems`.`itemtype` = '".$tmp[0]."'";
            if (isset($tmp[1]) && ($tmp[0] == 'Peripheral')) {
               $where .= " AND `$itemtable`.`peripheraltypes_id` = '".$tmp[1]."'";
            }
         }
         if (isset($_POST["locations_id"]) && ($_POST["locations_id"])) {
           $where = " AND `glpi_locations`.`id` = '".$_POST["locations_id"]."'";
         }

         $query = "SELECT `glpi_reservationitems`.`id`,
                          `glpi_reservationitems`.`comment`,
                          `$itemtable`.`name` AS name,
                          `$itemtable`.`entities_id` AS entities_id,
                          $otherserial,
                          `glpi_locations`.`completename` AS location,
                          `glpi_reservationitems`.`items_id` AS items_id
                   FROM `glpi_reservationitems`
                   $left
                   INNER JOIN `$itemtable`
                      ON (`glpi_reservationitems`.`itemtype` = '$itemtype'
                          AND `glpi_reservationitems`.`items_id` = `$itemtable`.`id`)
                   LEFT JOIN `glpi_locations`
                      ON (`$itemtable`.`locations_id` = `glpi_locations`.`id`)
                   WHERE `glpi_reservationitems`.`is_active` = '1'
                         AND `glpi_reservationitems`.`is_deleted` = '0'
                         AND `$itemtable`.`is_deleted` = '0'
                         $where ".
                         getEntitiesRestrictRequest(" AND", $itemtable, '',
                                                    $_SESSION['glpiactiveentities'],
                                                    $item->maybeRecursive())."
                  ORDER BY `$itemtable`.`entities_id`,
                           `$itemtable`.`name`";

         if ($result = $DB->query($query)) {
           while ($row = $DB->fetch_assoc($result)) {
               echo "<tr class='tab_bg_2'><td>";
               echo "<input type='checkbox' name='item[".$row["id"]."]' value='".$row["id"]."'></td>";
               $typename = $item->getTypeName();
               if ($itemtype == 'Peripheral') {
                  $item->getFromDB($row['items_id']);
                  if (isset($item->fields["peripheraltypes_id"])
                      && ($item->fields["peripheraltypes_id"] != 0)) {

                     $typename = Dropdown::getDropdownName("glpi_peripheraltypes",
                                                           $item->fields["peripheraltypes_id"]);
                  }
               }
               echo "<td><a href='reservation.php?reservationitems_id=".$row['id']."'>".
                          (($row["name"] == '') ?"(".$row["id"].")": $row["name"])."</a></td>";
               echo "<td>".$row["location"]."</td>";
               echo "<td>".nl2br($row["comment"])."</td>";
               echo "<td>".Dropdown::getDropdownName("glpi_entities", $row["entities_id"])."</td>";
               echo "<td>".$row["otherserial"]."</td>";
               echo "</tr>\n";
               $ok = true;
            }
         }
      }
      echo "<tr><td class='center b' colspan='6'>".__('No item found')."</td></tr>";
      echo "</table>\n";

      echo "<input type='hidden' name='id' value=''>";
      echo "</form>";// No CSRF token needed
      echo "</div>\n";
   }
}

if ($_SESSION["glpiactiveprofile"]["interface"] == "helpdesk") {
   Html::helpFooter();
} else {
   Html::footer();
}
?>