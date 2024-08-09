<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
  include_file('desktop', '404', 'php');
  die();
}
?>
<form class="form-horizontal">
  <fieldset>
    <legend>{{Configuration connection cloud}}</legend>
    <div class="form-group">
      <label class="col-sm-3 control-label">{{Connection}}</label>
      <div class="col-sm-7">
        <a class="btn btn-default" id="bt_loginToAjaxSystem">{{Se connecter}}</a>
      </div>
    </div>
    <div class="form-group">
      <label class="col-lg-3 control-label">{{Synchronisation}}</label>
      <div class="col-lg-4">
        <a class="btn btn-default" id="bt_syncWithAjaxSystem"><i class="fas fa-sync"></i> {{Synchroniser mes équipements}}</a>
      </div>
    </div>
  </fieldset>
</form>

<script>
  $('#bt_loginToAjaxSystem').off('click').on('click', function() {
     jeeDialog.dialog({
              id: 'jee_AjaxModal',
              title: '{{Connexion de Jeedom à Ajax}}',
              width: '85vw',
              height: '51vw',
              top: '8vh',
              contentUrl: 'index.php?v=d&modal=login&plugin=ajaxSystem'
      }) 
  })
  
  $('#bt_syncWithAjaxSystem').on('click', function() {
    $.ajax({
      type: "POST",
      url: "plugins/ajaxSystem/core/ajax/ajaxSystem.ajax.php",
      data: {
        action: "sync",
      },
      dataType: 'json',
      error: function(request, status, error) {
        handleAjaxError(request, status, error);
      },
      success: function(data) {
        if (data.state != 'ok') {
          $('#div_alert').showAlert({
            message: data.result,
            level: 'danger'
          });
          return;
        }
        $('#div_alert').showAlert({
          message: '{{Synchronisation réussie}}',
          level: 'success'
        });
      }
    });
  });
</script>
