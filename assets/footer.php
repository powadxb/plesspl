    <!-- Jquery JS-->
    <script src="assets/vendor/jquery-3.2.1.min.js"></script>
    <!-- Bootstrap JS-->
    <script src="assets/vendor/bootstrap-4.1/popper.min.js"></script>
    <script src="assets/vendor/bootstrap-4.1/bootstrap.min.js"></script>
    <!-- Vendor JS       -->
    <script src="assets/vendor/slick/slick.min.js">
    </script>
    <script src="assets/vendor/wow/wow.min.js"></script>
    <script src="assets/vendor/animsition/animsition.min.js"></script>
    <script src="assets/vendor/bootstrap-progressbar/bootstrap-progressbar.min.js">
    </script>
    <script src="assets/vendor/counter-up/jquery.waypoints.min.js"></script>
    <script src="assets/vendor/counter-up/jquery.counterup.min.js">
    </script>
    <script src="assets/vendor/circle-progress/circle-progress.min.js"></script>
    <script src="assets/vendor/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="assets/vendor/chartjs/Chart.bundle.min.js"></script>
    <script src="assets/vendor/select2/select2.min.js"></script>

    <!-- Main JS-->
    <script src="assets/js/main.js"></script>

    <!-- Include a polyfill for ES6 Promises (optional) for IE11 -->
    <script src="https://cdn.jsdelivr.net/npm/promise-polyfill@8/dist/polyfill.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/chosen/1.8.7/chosen.jquery.min.js" integrity="sha512-rMGGF4wg1R73ehtnxXBt5mbUfN9JUJwbk21KMlnLZDJh7BkPmeovBuddZCENJddHYYMkCh9hPFnPmS9sspki8g==" crossorigin="anonymous"></script>

<!-- Change Password Popup -->
<div class="modal fade" id="changePasswordPopup" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Change Account Password</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form method="POST" id="changePasswordForm">
          <div class="form-group">
              <label>Current Password <span class="text-danger">*</span> </label>
              <input class="au-input au-input--full requiredField" type="password" name="current_password">
          </div>
          <div class="form-group">
              <label>New Password <span class="text-danger">*</span> </label>
              <input class="au-input au-input--full requiredField" id="newPassword" type="password" name="new_password">
          </div>
          <div class="form-group">
              <label>New Password Again<span class="text-danger">*</span> </label>
              <input class="au-input au-input--full requiredField" id="newPasswordAgain" type="password">
          </div>
          <hr>
          <button class="au-btn au-btn--block au-btn--green m-b-20" type="submit" id="changePassBtn">Change Password</button>
        </form>
      </div>
    </div>
  </div>
</div>

    <script>
      $(document).ready(function(){
        $(window).resize(function (e) {
          var bodyPaddingTop = parseFloat($("body").css("padding-top").replace("px" , ""));
          $("#spinner").css("margin-top" , -bodyPaddingTop+"px")
            var windowHeight = $(this).outerHeight();
            $('.spinner').css('margin-top' , windowHeight/2).show();
        });
        $(window).trigger('resize');
        
        $(".changePassword").click(function(e){
          e.preventDefault();
          $("#changePasswordPopup").modal("show");
        })
        
        $("#changePasswordForm").submit(function(e){
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
          
          if($("#newPassword").val() !== $("#newPasswordAgain").val()) {
            Swal.fire(
              'Oops...',
              'PAssword not match.',
              'error'
              )
            return false;
          }

          $("#spinner").show();
          $.ajax({
            type:"POST",
            url:'php/change_account_password.php',
            data:formData
          }).done(function(response){
            console.log(response);
            $("#spinner").hide();
            if(response=='updated'){
              Swal.fire(
                  'Updated!',
                  'You account password changed successfully.',
                  'success'
                )
              $("#changePasswordPopup").modal("hide");
              form[0].reset();
            }else if(response=="wrong_password"){
              Swal.fire(
                  'Oops...',
                  'You have entered wrong current password, please try again.',
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

</body>

</html>
<!-- end document-->