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

// Direct access to file
if (strpos($_SERVER['PHP_SELF'],"getDropdownValue.php")) {
   include ('../inc/includes.php');
   header("Content-Type: text/html; charset=UTF-8");
   Html::header_nocache();
}

if (!defined('GLPI_ROOT')) {
   die("Can not acces directly to this file");
}

Session::checkLoginUser();

// Security
if (!($item = getItemForItemtype($_GET['itemtype']))) {
   exit();
}
$table = $item->getTable();
$datas = array();

$displaywith = false;
if (isset($_GET['displaywith'])) {
   if (is_array($_GET['displaywith'])
       && count($_GET['displaywith'])) {
      $displaywith = true;
   }
}

// No define value
if (!isset($_GET['value'])) {
   $_GET['value'] = '';
}

if (isset($_GET['condition']) && !empty($_GET['condition'])) {
   $_GET['condition'] = rawurldecode(stripslashes($_GET['condition']));
}

if (!isset($_GET['emptylabel']) || ($_GET['emptylabel'] == '')) {
   $_GET['emptylabel'] = Dropdown::EMPTY_VALUE;
}

$where = "WHERE 1 ";

if ($item->maybeDeleted()) {
   $where .= " AND `is_deleted` = '0' ";
}
if ($item->maybeTemplate()) {
   $where .= " AND `is_template` = '0' ";
}

$NBMAX = $CFG_GLPI["dropdown_max"];
$LIMIT = "LIMIT 0,$NBMAX";

$where .=" AND `$table`.`id` NOT IN ('".$_GET['value']."'";

if (isset($_GET['used'])) {
   $used = $_GET['used'];

   if (count($used)) {
      $where .= ",'".implode("','",$used)."'";
   }
}

if (isset($_GET['toadd'])) {
   $toadd = $_GET['toadd'];
} else {
   $toadd = array();
}

$where .= ") ";

if (isset($_GET['condition']) && $_GET['condition'] != '') {
   $where .= " AND ".$_GET['condition']." ";
}

if ($item instanceof CommonTreeDropdown) {

   $where .= " AND `completename` ".Search::makeTextSearch($_GET['searchText']);

   $multi = false;

   // Manage multiple Entities dropdowns
   $add_order = "";

   if ($item->isEntityAssign()) {
      $recur = $item->maybeRecursive();

       // Entities are not really recursive : do not display parents
      if ($_GET['itemtype'] == 'Entity') {
         $recur = false;
      }

      if (isset($_GET["entity_restrict"]) && !($_GET["entity_restrict"]<0)) {
         $where .= getEntitiesRestrictRequest(" AND ", $table, '', $_GET["entity_restrict"],
                                              $recur);

         if (is_array($_GET["entity_restrict"]) && count($_GET["entity_restrict"])>1) {
            $multi = true;
         }

      } else {
         $where .= getEntitiesRestrictRequest(" AND ", $table, '', '', $recur);

         if (count($_SESSION['glpiactiveentities'])>1) {
            $multi = true;
         }
      }

      // Force recursive items to multi entity view
      if ($recur) {
         $multi = true;
      }

      // no multi view for entitites
      if ($_GET['itemtype'] == "Entity") {
         $multi = false;
      }

      if ($multi) {
         $add_order = '`entities_id`, ';
      }

   }

   $query = "SELECT *
             FROM `$table`
             $where
             ORDER BY $add_order `completename`
             $LIMIT";

   if ($result = $DB->query($query)) {

      if (count($toadd)) {
         foreach ($toadd as $key => $val) {
            array_push($datas, array('id'   => $key,
                                     'text' => $val));
         }
      }

      if ($_GET['display_emptychoice']) {
            array_push($datas, array ('id'   => 0,
                                      'text' => $_GET['emptylabel']));
      }

      $last_level_displayed = array();
      $datastoadd = array();
      if ($DB->numrows($result)) {
         $prev = -1;

         while ($data = $DB->fetch_assoc($result)) {
            $ID        = $data['id'];
            $level     = $data['level'];
            $outputval = $data['name'];

            if ($displaywith) {
               foreach ($_GET['displaywith'] as $key) {
                  if (isset($data[$key])) {
                     $withoutput = $data[$key];
                     if (isForeignKeyField($key)) {
                        $withoutput = Dropdown::getDropdownName(getTableNameForForeignKeyField($key),
                                                                $data[$key]);
                     }
                     if ((strlen($withoutput) > 0) && ($withoutput != '&nbsp;')) {
                        $outputval = sprintf(__('%1$s - %2$s'), $outputval, $withoutput);
                     }
                  }
               }
            }

            if ($multi
                && ($data["entities_id"] != $prev)) {
               if ($prev >= 0) {
                  if (count($datastoadd)) {
                     array_push($datas, array('text'    => Dropdown::getDropdownName("glpi_entities", $prev),
                                             'children' => $datastoadd));
                  }
               }
               $prev = $data["entities_id"];
               // Reset last level displayed :
               $datastoadd = array();
            }


            if ($_SESSION['glpiuse_flat_dropdowntree']) {
               $outputval = $data['completename'];
               if ($level > 1) {
                  $level = 0;
               }

            } else { // Need to check if parent is the good one
               if ($level > 1) {
                  // Last parent is not the good one need to display arbo
                  if (!isset($last_level_displayed[$level-1])
                      || ($last_level_displayed[$level-1] != $data[$item->getForeignKeyField()])) {

                     $work_level    = $level-1;
                     $work_parentID = $data[$item->getForeignKeyField()];
                     $to_display    = '';

                     do {
                        // Get parent
                        if ($item->getFromDB($work_parentID)) {
                           $title = $item->fields['completename'];

                           if (isset($item->fields["comment"])) {
                              $title = sprintf(__('%1$s - %2$s'), $title, $item->fields["comment"]);
                           }
                           $output2 = $item->getName();

                           array_push($datastoadd, array ('id'      => $ID,
                                                         'text'     => $output2,
                                                         'level'    => $work_level,
                                                         'disabled' => true));

                           $last_level_displayed[$work_level] = $item->fields['id'];
                           $work_level--;
                           $work_parentID = $item->fields[$item->getForeignKeyField()];

                        } else { // Error getting item : stop
                           $work_level = -1;
                        }

                     } while (($work_level >= 1)
                              && (!isset($last_level_displayed[$work_level])
                                  || ($last_level_displayed[$work_level] != $work_parentID)));

                  }
               }
               $last_level_displayed[$level] = $data['id'];
            }

            if ($_SESSION["glpiis_ids_visible"]
                || (Toolbox::strlen($outputval) == 0)) {
               $outputval = sprintf(__('%1$s (%2$s)'), $outputval, $ID);
            }

            $title = $data['completename'];
            if (isset($data["comment"])) {
               $title = sprintf(__('%1$s - %2$s'), $title, $data["comment"]);
            }
            array_push($datastoadd, array ('id'    => $ID,
                                           'text'  => $outputval,
                                           'level' => $level));

         }
      }
   }
   if ($multi) {
      if (count($datastoadd)) {
         array_push($datas, array('text'     => Dropdown::getDropdownName("glpi_entities", $prev),
                                  'children' => $datastoadd));
      }
   } else {
      if (count($datastoadd)) {
         $datas = array_merge($datas, $datastoadd);
      }
   }
} else { // Not a dropdowntree
   $multi = false;

   if ($item->isEntityAssign()) {
      $multi = $item->maybeRecursive();

      if (isset($_GET["entity_restrict"]) && !($_GET["entity_restrict"] < 0)) {
         $where .= getEntitiesRestrictRequest("AND", $table, "entities_id",
                                              $_GET["entity_restrict"], $multi);

         if (is_array($_GET["entity_restrict"]) && (count($_GET["entity_restrict"]) > 1)) {
            $multi = true;
         }

      } else {
         $where .= getEntitiesRestrictRequest("AND", $table, '', '', $multi);

         if (count($_SESSION['glpiactiveentities'])>1) {
            $multi = true;
         }
      }
   }

   $field = "name";
   if ($item instanceof CommonDevice) {
      $field = "designation";
   }

   $search = Search::makeTextSearch($_GET['searchText']);
   $where .=" AND  (`$table`.`$field` ".$search;

   if ($_GET['itemtype']=="SoftwareLicense") {
      $where .= " OR `glpi_softwares`.`name` ".$search;
   }
   $where .= ')';

   switch ($_GET['itemtype']) {
      case "Contact" :
         $query = "SELECT `$table`.`entities_id`,
                          CONCAT(`name`,' ',`firstname`) AS $field,
                          `$table`.`comment`, `$table`.`id`
                   FROM `$table`
                   $where";
         break;

      case "SoftwareLicense" :
         $query = "SELECT `$table`.*,
                          CONCAT(`glpi_softwares`.`name`,' - ',`glpi_softwarelicenses`.`name`)
                              AS $field
                   FROM `$table`
                   LEFT JOIN `glpi_softwares`
                        ON (`glpi_softwarelicenses`.`softwares_id` = `glpi_softwares`.`id`)
                   $where";
         break;

      default :
         $query = "SELECT *
                   FROM `$table`
                   $where";
   }

   if ($multi) {
      $query .= " ORDER BY `entities_id`, $field
                 $LIMIT";
   } else {
      $query .= " ORDER BY $field
                 $LIMIT";
   }
   Toolbox::logDebug($query);
   if ($result = $DB->query($query)) {

      if (!isset($_GET['display_emptychoice']) || $_GET['display_emptychoice']) {
         array_push($datas, array ('id'    => 0,
                                   'text'  => $_GET["emptylabel"]));
      }

      if (count($toadd)) {
         foreach ($toadd as $key => $val) {
            array_push($datas, array ('id'    => $key,
                                      'text'  => $val));
         }
      }

      $outputval = Dropdown::getDropdownName($table,$_GET['value']);

      $datastoadd = array();

      if ($DB->numrows($result)) {
         $prev = -1;

         while ($data =$DB->fetch_assoc($result)) {
            if ($multi
                && ($data["entities_id"] != $prev)) {
               if ($prev >= 0) {
                  if (count($datastoadd)) {
                     array_push($datas, array('text'    => Dropdown::getDropdownName("glpi_entities", $prev),
                                             'children' => $datastoadd));
                  }
               }
               $prev = $data["entities_id"];
               $datastoadd = array();
            }
            
            $outputval = $data[$field];

            if ($displaywith) {
               foreach ($_GET['displaywith'] as $key) {
                  if (isset($data[$key])) {
                     $withoutput = $data[$key];
                     if (isForeignKeyField($key)) {
                        $withoutput = Dropdown::getDropdownName(getTableNameForForeignKeyField($key),
                                                                $data[$key]);
                     }
                     if ((strlen($withoutput) > 0) && ($withoutput != '&nbsp;')) {
                        $outputval = sprintf(__('%1$s - %2$s'), $outputval, $withoutput);
                     }
                  }
               }
            }
            $ID         = $data['id'];
            $addcomment = "";
            $title      = $outputval;
            if (isset($data["comment"])) {
               $title = sprintf(__('%1$s - %2$s'), $title, $data["comment"]);
            }
            if ($_SESSION["glpiis_ids_visible"]
                || (strlen($outputval) == 0)) {
               //TRANS: %1$s is the name, %2$s the ID
               $outputval = sprintf(__('%1$s (%2$s)'), $outputval, $ID);
            }
            array_push($datastoadd, array ('id'    => $ID,
                                           'text'  => $outputval));
         }
         if ($multi) {
            if (count($datastoadd)) {
               array_push($datas, array('text'     => Dropdown::getDropdownName("glpi_entities", $prev),
                                        'children' => $datastoadd));
            }
         } else {
            if (count($datastoadd)) {
               $datas = array_merge($datas, $datastoadd);
            }
         }
      }
   }
}

$ret['results'] = $datas;

echo json_encode($ret);
?>
