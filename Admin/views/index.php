<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$inactive_timeout = 600;
if (isset($_SESSION['last_activity'])) {
    $inactive_time = time() - $_SESSION['last_activity'];
    if ($inactive_time >= $inactive_timeout) {
        session_unset();
        session_destroy();
        session_start();
    }
}
$_SESSION['last_activity'] = time();

if (isset($_SESSION['message']) && $_SESSION['message'] != "") {
    $message = '<div class="small bg-light text-muted p-2 border rounded">' . $_SESSION['message'] . '</div>';
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Petra Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" media="screen" href="css/main_min.css" />
    <link rel="stylesheet" type="text/css" media="screen" href="bootstrap-4.3.1-dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.1.0/css/all.css" integrity="sha384-lKuwvrZot6UHsBSfcMvOkWwlCMgc0TaWr+30HWe3a4ltaBwTZhyTEggF5tJv8tbt" crossorigin="anonymous">
    <link href='https://fonts.googleapis.com/css?family=Montserrat' rel='stylesheet'>
    <link href='https://fonts.googleapis.com/css?family=Lato' rel='stylesheet'>
    <script src="js/jquery-3.3.1.min.js"></script>
    <script src="bootstrap-4.3.1-dist/js/bootstrap.min.js"></script>

    <script>
        $(function() {

            $('input[type="password"]').on('paste', function(e) {
                e.preventDefault();
            });

                $('#showForgotPassword').on('click', function(e) {
                    e.preventDefault();
                    $('#loginDiv').addClass('d-none');
                    $('#forgotPasswordDiv').removeClass('d-none');
                    
                    // Move hidden field and update value
                    $('#action').appendTo('#forgotPasswordDiv').val('forgot_password');
                });

                $('#backToLogin').on('click', function(e) {
                    e.preventDefault();
                    $('#forgotPasswordDiv').addClass('d-none');
                    $('#loginDiv').removeClass('d-none');
                    
                    // Move hidden field back and update value
                    $('#action').appendTo('#loginDiv').val('login');
                });

            $('#loginform').bind('submit', function(event) {
                console.log($('#action').val());
                switch ($('#action').val()) {
                    case "login": {
                        if ($.trim($('#loginame').val()) == '') {
                            $('#loginame').css('border-color', '#f00');
                            event.preventDefault();
                        } else if ($.trim($('#password').val()) == '') {
                            $('#password').css('border-color', '#f00');
                            event.preventDefault();
                        } else {
                            return true;
                        }
                        break;
                    }

                    case "changepassword": {
                        if ($.trim($('#currentpassword').val()) == '') {
                            $('#currentpassword').css('border-color', '#f00');
                            event.preventDefault();
                        }

                        if ($.trim($('#newpassword').val()) == '') {
                            $('#newpassword').css('border-color', '#f00');
                            event.preventDefault();
                        }

                        if ($.trim($('#confirmpassword').val()) == '') {
                            $('#confirmpassword').css('border-color', '#f00');
                            event.preventDefault();
                        }

                        if ($.trim($('#confirmpassword').val()) != $.trim($('#newpassword').val())) {
                            $('#confirmpassword').css('border-color', '#f00');
                            $('#newpassword').css('border-color', '#f00');
                            event.preventDefault();
                            alert("New Password not the same, please revalidate");
                        }
                        return true;
                        break;
                    }

                    case "register2fa": {
                        if ($.trim($('#otpcode').val()) == '') {
                            $('#otpcode').css('border-color', '#f00');
                            event.preventDefault();
                        }
                        return true;
                        break;
                    }

                    case "forgot_password": {
                        if ($.trim($('#userID').val()) == '') {
                            $('#userID').css('border-color', '#f00');
                            alert("Please enter the userID");
                            event.preventDefault();
                        }
                        return true;
                        break;
                    }

                    case "validateOTP": {
                        if ($.trim($('#otpcode').val()) == '') {
                            $('#otpcode').css('border-color', '#f00');
                            event.preventDefault();
                        }
                        return true;
                        break;
                    }

                }
            });
        });
    </script>
</head>


<body>
    <div class="cont">
        <div class="container-fluid">
            <div class="row vh-100">
                <div class="col-sm-12 col-md-7 bg-darkblue d-flex justify-content-center align-items-center flex-column">
                    <div class="m-4">
                        <h1 class='display-2 text-white font-weight-bold'>Petra Admin&reg;</h1>
                    </div>
                </div>
                <div class="col-sm-12 col-md-5 d-flex justify-content-center align-items-center bg-grey pl-0 pr-0">
                    <div class="form-div w-lg-75 p-lg-4">
                        <form class="mx-lg-5 p-4 p-lg-5 bg-white shadow" id="loginform" name="loginform" method="post" action="../src/validate.Authenticate.php">
                            <p class="text-muted mb-1">Welcome to Petra Admin&reg;</p>
                            <?php

                            $login_ui = '<div id="loginDiv">
                                <h1 class="h3">Log into your Account</h1>
                                ' . @$message . '
                                <div class="form-group mt-sm-4">
                                    <label for="loginame">LoginID</label>
                                    <input type="text" class="form-control" id="loginame" name="loginame">
                                </div>
                                <br/>
                                <div class="form-group mb-sm-4">
                                    <label for="password">Password</label>
                                    <input type="password" class="form-control" id="password" name="password">
                                    <input type="hidden" id="action" name="action" value="login">
                                </div>
                                <button type="submit" class="btn btn-primary" value="login">Log In</button>
                                <button type="button" class="btn btn-link" id="showForgotPassword">Forgot Password</button>
                            </div>

                            <div id="forgotPasswordDiv" class="d-none">
                                <h1 class="h3">Forgot Password</h1>
                                <div class="form-group mb-sm-4">
                                    <label for="userID">UserID</label>
                                    <input type="text" class="form-control" id="userID" name="userID">
                                </div>
                                <button type="submit" class="btn btn-primary">Reset Password</button>
                                <button type="button" class="btn btn-link" id="backToLogin">Back to Login</button>
                            </div>';

                            $change_password_ui = '<div>
                                    <h1 class="h3">Change your password</h1>
                                    ' . @$message . '
                                    <br/>
                                    <div class="form-group mb-sm-4">
                                        <label for="currentpassword">Current Password</label>
                                        <input type="password" class="form-control" id="currentpassword" name="currentpassword">
                                    </div>
                                    <div class="form-group mb-sm-4">
                                        <label for="NewPassword">New Password</label>
                                        <input type="password" class="form-control" id="newpassword" name="newpassword">
                                    </div>
                                    <div class="form-group mb-sm-4">
                                        <label for="confirmpassword">Confirm Password</label>
                                        <input type="password" class="form-control" id="confirmpassword" name="confirmpassword">
                                        <input type="hidden" class="form-control" id="action" name="action" value="changepassword"> 
                                    </div>
                                    <button type="submit" class="btn btn-primary">Change Password</button>
                                </div>';

                            $reg_2fa_ui = '<div>
                                    <h1 class="h3">Register for 2FA</h1>
                                     <div class="small bg-light text-muted p-2 border rounded">Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)</div>
                                    <div class="form-group mt-sm-4">
                                       <img src="' . @$_SESSION['qr_code_url'] . '"/>
                                    </div>
                                    <div class="form-group mb-sm-4">
                                        <label for="code">OTP</label>
                                        <input type="text" class="form-control" id="otpcode" name="otpcode" autocomplete="off" autofill="off" />
                                        <input type="hidden" class="form-control" id="action" name="action" value="register2fa"> 
                                    </div>
                                    <button type="submit" class="btn btn-primary">Register</button>
                                </div>';


                            $enter_2fa_ui = '<div>
                                    <h1 class="h3">Log into your Account</h1>
                                    <div class="form-group mt-sm-4">
                                        
                                    </div>
                                    <div class="form-group mb-sm-4">
                                        <label for="otpcode">OTPCode</label>
                                        <input type="text" class="form-control" id="otpcode" name="otpcode">
                                        <input type="hidden" class="form-control" id="action" name="action" value="validateOTP"> 
                                    </div>
                                    <button type="submit" class="btn btn-primary">Validate Code</button>
                                </div>';





                            switch ($_SESSION['status']) {
                                case "change_password_ui": {
                                        echo $change_password_ui;
                                        break;
                                    }

                                case "reg_2fa_ui": {
                                        echo $reg_2fa_ui;
                                        break;
                                    }

                                case "enter_2fa_ui": {
                                        echo $enter_2fa_ui;
                                        break;
                                    }

                                case "forgot_password": {
                                        echo $forgot_password;
                                        break;
                                    }

                                default: {
                                        echo $login_ui;
                                        break;
                                    }
                            }
                            ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>