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

if (!isConnect('admin')) {
  throw new Exception('{{401 - Accès non autorisé}}');
}
?>

<div id='div_ajaxSystemLoginAlert' style="display: none;"></div>
<form class="form-horizontal">
  <fieldset>
    <div class="form-group netatmomode internal">
      <label class="col-sm-2 control-label">{{Nom d'utilisateur (email)}}</label>
      <div class="col-sm-3">
        <input type="text" class="form-control" id="in_ajaxSystemLogin_username" placeholder="{{Nom d'utilisateur (email)}}" />
      </div>
    </div>
    <div class="form-group netatmomode internal">
      <label class="col-sm-2 control-label">{{Mot de passe}}</label>
      <div class="col-sm-3">
        <input type="password" class="form-control" id="in_ajaxSystemLogin_password" placeholder="{{Mot de passe}}" />
      </div>
    </div>
    <div class="form-group">
      <label class="col-sm-2 control-label"></label>
      <div class="col-sm-7">
        <a class="btn btn-success" id="bt_validateLoginToAjaxSystem">{{Valider}}</a>
      </div>
    </div>
  </fieldset>
</form>

<script>
  $('#bt_validateLoginToAjaxSystem').off('click').on('click', function() {
    $.ajax({
      type: "POST",
      url: "plugins/ajaxSystem/core/ajax/ajaxSystem.ajax.php",
      data: {
        action: "login",
        username: $('#in_ajaxSystemLogin_username').value(),
        password: $('#in_ajaxSystemLogin_password').value()
      },
      dataType: 'json',
      error: function(request, status, error) {
        handleAjaxError(request, status, error);
      },
      success: function(data) {
        if (data.state != 'ok') {
          $('#div_ajaxSystemLoginAlert').showAlert({
            message: data.result,
            level: 'danger'
          });
          return;
        }
        $('#div_ajaxSystemLoginAlert').showAlert({
          message: '{{Connexion réussie}}',
          level: 'success'
        });
      }
    });
  })
</script>