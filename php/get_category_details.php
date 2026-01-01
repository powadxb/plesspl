//php/get_category_details.php
<?php
require 'bootstrap.php';

$response = $DB->query(" SELECT * FROM master_categories WHERE id=?", [$_POST['id']]);
if(!empty($response)){
  $details = $response[0];
}else{
  die();
}
?>

<form method="POST" id="updateDetailsForm">
  <input type="hidden" name="id" value="<?=$details['id']?>">
  <div class="form-group">
    <label for="catID">ID <span class="text-danger">*</span></label>
    <input type="text" name="cat_id" id="catID" class="form-control requiredField" value="<?=$details['id']?>">
  </div>
  <div class="form-group">
    <label for="pless_main_category">pless_main_category <span class="text-danger">*</span></label>
    <input type="text" name="pless_main_category" id="pless_main_category" class="form-control requiredField" value="<?=$details['pless_main_category']?>">
  </div>
  <div class="form-group">
    <label for="pos_category">pos_category <span class="text-danger">*</span></label>
    <input type="text" name="pos_category" id="pos_category" class="form-control requiredField" value="<?=$details['pos_category']?>">
  </div>
  <hr>
  <button class="btn btn-success d-block mx-auto">Update Category</button>
</form>