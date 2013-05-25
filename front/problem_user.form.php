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
* @since version 0.83
*/

if (!defined('GLPI_ROOT')) {
   include ('../inc/includes.php');
}

$ticket_user = new Ticket_User();

Session ::checkLoginUser();
Html::popHeader(__('Email followup'), $_SERVER['PHP_SELF']);

if (isset($_POST["update"])) {
   $ticket_user->check($_POST["id"], UPDATE);
   $ticket_user->update($_POST);

} else if (isset($_GET["id"])) {
   $ticket_user->showUserNotificationForm($_GET["id"]);
} else {
   Html::displayErrorAndDie('Lost');
}

Html::popFooter();
?>