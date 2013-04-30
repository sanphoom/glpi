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

Session::checkLoginUser();

if (isset($_GET["popup"])) {
   $_SESSION["glpipopup"]["name"] = $_GET["popup"];
}

if (isset($_SESSION["glpipopup"]["name"])) {
   switch ($_SESSION["glpipopup"]["name"]) {
      case "test_rule" :
         Html::popHeader(__('Test'), $_SERVER['PHP_SELF']);
         include "rule.test.php";
         break;

      case "test_all_rules" :
         Html::popHeader(__('Test rules engine'), $_SERVER['PHP_SELF']);
         include "rulesengine.test.php";
         break;

      case "show_cache" :
         Html::popHeader(__('Cache information'), $_SERVER['PHP_SELF']);
         include "rule.cache.php";
         break;

      case "add_ldapuser" :
         Html::popHeader(__('Import a user'), $_SERVER['PHP_SELF']);
         include "ldap.import.php";
         break;

  }
   echo "<div class='center'><br><a href='javascript:window.close()'>".__('Close')."</a>";
   echo "</div>";
   Html::popFooter();
}
?>
