<?php
require 'bootstrap.php';

$response = $DB->query(" SELECT * FROM users WHERE id=?", [$_POST['id']]);
if(!empty($response)){
  $details = $response[0];
}else{
  die();
}
?>

<form method="POST" id="updateDetailsForm">
  <input type="hidden" name="id" value="<?=$details['id']?>">
  <div class="form-group">
      <label>Username <span class="text-danger">*</span> </label>
      <input class="au-input au-input--full requiredField" type="text" name="username" placeholder="Username" value="<?=$details['username']?>">
  </div>
  <div class="form-group">
      <label>Email Address <span class="text-danger">*</span> </label>
      <input class="au-input au-input--full requiredField" type="text" name="email" placeholder="Email" value="<?=$details['email']?>">
  </div>
  <div class="form-group">
      <label>Password</label>
      <input class="au-input au-input--full" type="password" name="password" id="password" placeholder="Password">
  </div>
  <div class="form-group">
      <label>Password Again</label>
      <input class="au-input au-input--full" type="password" id="rePassword" placeholder="Password">
  </div>
  <div class="form-group">
      <label>First Name</label>
      <input class="au-input au-input--full" type="text" name="first_name" placeholder="First Name" value="<?=$details['first_name']?>">
  </div>
  <div class="form-group">
      <label>Last Name</label>
      <input class="au-input au-input--full" type="text" name="last_name" placeholder="Last Name" value="<?=$details['last_name']?>">
  </div>
  <div class="form-group">
      <label>User Role</label>
      <select name="admin" id="admin" class="form-control">
        <option value="0" <?=($details['admin']==0)?'selected':''?> >User</option>
        <option value="1" <?=($details['admin']==1)?'selected':''?> >Manager</option>
        <option value="2" <?=($details['admin']==2)?'selected':''?> >Admin</option>
      </select>
  </div>
  <hr>
  <button class="btn btn-success d-block mx-auto">Update User</button>
</form>