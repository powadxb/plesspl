<?php 
$page_title = 'Login';
require 'assets/header.php';
?>

<div class="page-wrapper">
    <div class="page-content--bge5">
        <div class="container">
            <div class="login-wrap">
                <div class="login-content">
                    <div class="login-logo">
                        <a href="#">
                            <h2><b><?=$website_name?></b></h2>
                        </a>
                      <br><br>
                    </div>
                    <div class="login-form">
                        <form action="" method="post" id="loginForm">
                            <div class="form-group">
                                <label>Username <span class="text-danger">*</span> </label>
                                <input class="au-input au-input--full requiredField" type="text" name="username" placeholder="Username">
                            </div>
                            <div class="form-group">
                                <label>Password <span class="text-danger">*</span> </label>
                                <input class="au-input au-input--full requiredField" type="password" name="password" placeholder="Password">
                            </div>
                            <div class="login-checkbox mb-2">
                                <label>
                                    <input type="checkbox" name="remember">Remember Me
                                </label>
                            </div>
                            <button class="au-btn au-btn--block au-btn--green m-b-20" type="submit" id="submitBtn">sign in</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require 'assets/footer.php'; ?>

<script>
  $(document).ready(function(){
    $("#loginForm").submit(function(e){
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
					'Both username & password are required.',
					'error'
					)
				return false;
			}
      
      $("#spinner").show();
      $.ajax({
        type:"POST",
        url:'php/login.php',
        data:formData
      }).done(function(response){
        console.log(response);
        if(response!='valid_login') {
          $("#spinner").hide();
        }
        if(response=='valid_login'){
          window.location.href = 'index.php';
        }else if(response=="invalid_login"){
          Swal.fire(
              'Oops...',
              'Wrong username or password, please try again.',
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
