<?php 
ob_start();
session_start();
$page_title = 'New Account';
require 'assets/header.php';
require __DIR__.'/php/bootstrap.php';

if(!isset($_SESSION['dins_user_id']) && !isset($_COOKIE['dins_user_id'])){
  header("Location: login.php");
  exit();
}

// Check the user is admin or not
$user_details = $DB->query(" SELECT * FROM users WHERE id=?" , [$user_id])[0];
if($user_details['admin']!=2){
  header("Location: index.php");
  exit();
}
?>

<div class="page-wrapper">
       <?php require 'assets/navbar.php' ?>
        <!-- PAGE CONTENT-->
        <div class="page-content--bgf7">
            <section>
                <div class="page-wrapper">
                  <div class="page-content--bge5">
                      <div class="container">
                          <div class="login-wrap">
                              <div class="login-content">
                                  <div class="login-logo">
                                      <a href="#">
                                          <h2><b><?=$page_title?></b></h2>
                                      </a>
                                    <br><br>
                                  </div>
                                  <div class="login-form">
                                      <form action="" method="post" id="newAccountForm">
                                          <div class="form-group">
                                              <label>Username <span class="text-danger">*</span> </label>
                                              <input class="au-input au-input--full requiredField" type="text" name="username" placeholder="Username">
                                          </div>
                                          <div class="form-group">
                                              <label>Email Address <span class="text-danger">*</span> </label>
                                              <input class="au-input au-input--full requiredField" type="text" name="email" placeholder="Email">
                                          </div>
                                          <div class="form-group">
                                              <label>Password <span class="text-danger">*</span> </label>
                                              <input class="au-input au-input--full requiredField" type="password" name="password" id="password" placeholder="Password">
                                          </div>
                                          <div class="form-group">
                                              <label>Password Again<span class="text-danger">*</span> </label>
                                              <input class="au-input au-input--full requiredField" type="password" id="rePassword" placeholder="Password">
                                          </div>
                                        <br>
                                          <button class="au-btn au-btn--block au-btn--green m-b-20" type="submit" id="submitBtn">Create New Account</button>
                                      </form>
                                  </div>
                              </div>
                          </div>
                      </div>
                  </div>
                  <!-- COPYRIGHT-->
                  <section class="p-t-60 p-b-20">
                      <div class="container">
                          <div class="row">
                              <div class="col-md-12">
                                  <div class="copyright">
                                      <p>Copyright © <?=date("Y")?> <?=$website_name?>. All rights reserved.</p>
                                  </div>
                              </div>
                          </div>
                      </div>
                  </section>
                  <!-- END COPYRIGHT-->
              </div>
            </section>

            
        </div>

    </div>

<?php require 'assets/footer.php'; ?>

<script>
  $(document).ready(function(){
    $("#newAccountForm").submit(function(e){
      e.preventDefault();
      var form = $(this),
				formData = form.serialize(),
				validToSubmit = true;

			$.each(form.find(".requiredField") , function(index , value){
				if($(value).val()==null || $(value).val().length==0){
					validToSubmit = false;
				}
			})

			if(!validToSubmit){
				Swal.fire(
					'Oops...',
					'All fields are required.',
					'error'
					)
				return false;
			}
      
      if ($("#password").val()!==$("#rePassword").val()) {
				Swal.fire(
					'Oops...',
					'Password not match.',
					'error'
					)
				return false;
			}
      
      $("#spinner").show();
      
      $.ajax({
        type:"POST",
        url:'php/create_new_account.php',
        data:formData
      }).done(function(response){
        console.log(response);
        $("#spinner").hide();
        if(response=='added'){
          Swal.fire(
              'Added!',
              'New account created successfully.',
              'success'
            )
          form[0].reset();
        }else if(response.includes("_exist")){
          response = response.replace("_exist", "");
          Swal.fire(
              'Oops...',
              response+' already exist, please try again.',
              'error'
            )
        }else{
          Swal.fire(
              'Oops...',
              'Something went wrong, please try again.',
              'error'
            )
        }
      })
      
    })
  })
</script>
