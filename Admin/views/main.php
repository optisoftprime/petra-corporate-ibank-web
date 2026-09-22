<?php

include '../src/include.all.php';
$user = new UserAdmin($_SESSION['uname']);
$menu = new Menu();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Secondcaesar&reg;::Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <link rel="stylesheet" type="text/css" media="screen" href="css/main_min.css" />

    <link href='https://fonts.googleapis.com/css?family=Montserrat' rel='stylesheet'>
    <link rel="stylesheet" href="//netdna.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="css/richtext.min.css">
    <link href='https://fonts.googleapis.com/css?family=Lato' rel='stylesheet'>
    <script src="https://kit.fontawesome.com/ac75b3ec47.js" crossorigin="anonymous"></script>

   
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js" integrity="sha384-+sLIOodYLS7CIrQpBjl+C7nPvqq+FbNUBDunl/OZv93DB7Ln/533i8e/mZXLi/P+" crossorigin="anonymous"></script>

    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.12.1/b-2.2.3/b-colvis-2.2.3/b-html5-2.2.3/b-print-2.2.3/date-1.1.2/fh-3.2.4/kt-2.7.0/r-2.3.0/sb-1.3.4/sp-2.0.2/sl-1.4.0/datatables.min.js"></script>

    <script src="js/main_min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jqueryui@1.11.1/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery-migrate@3.3.1/dist/jquery-migrate.min.js"></script>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/jqueryui@1.11.1/jquery-ui.min.css">
   <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>
    <script src="js/actions.js" type="text/javascript"></script>

    <script src="https://cdn.jsdelivr.net/tablesorter/2.17.4/js/jquery.tablesorter.min.js"></script>
    <link rel="stylesheet" href="css/main.css" type="text/css" />
    <link rel="stylesheet" href="css/calendar-main.css" type="text/css" />
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.12.1/b-2.2.3/b-colvis-2.2.3/b-html5-2.2.3/b-print-2.2.3/date-1.1.2/fh-3.2.4/kt-2.7.0/r-2.3.0/sb-1.3.4/sp-2.0.2/sl-1.4.0/datatables.min.css"/>
 
</head>

<body>
    <div class="main h-100 d-flex">
        <input type="hidden" value="<?php echo $user->username ?>" name="loggedInUser" id="loggedInUser" />
        <aside>
            <div class="sidebar float-left">
                <div class="user-panel">
                    <!-- 
                        <div class="logo">
                            <img src="../embeded_images/logo_big.jpg" width="200px"/>
                        </div>
                        -->
                    <div>
                        <div class="float-left info">
                            <a href="#">
                                <p class='logo-name'>Omni&reg;</p>
                            </a>
                        </div>
                        <div class="menu-icon">
                            <a href="#" class="button-left">
                                <span class="fa fa-fw fa-bars "></span>
                            </a>
                        </div>
                    </div>
                </div>
                <ul class="list-sidebar bg-defoult" id="side-nav">
                    <?php
                    $services = $user->getServices();
                    if(is_array ($services)) {
                        foreach ($services as $service) {
                            echo '<li> <a href="#" data-toggle="collapse" data-target="#' . $service['Stubs'] . '" class="collapsed menu-item">' . $service['Icon'] . '<span class="nav-label">' . trim($service['Name']) . '</span><span class="fa fa-chevron-up float-right"></span></a> ';
                            echo $menu->getChildren($service['id'], $user->getUserRoleID());
                            echo '</li>';
                        }
                    }
                    
                    ?>

                </ul>
                <div class="fixed-bottom bg-grey-2 profile-div">
                    <a data-toggle="collapse" href="#collapseExample" role="button" class="profile-header d-flex align-items-center collapsed">
                        <i class="far fa-user-circle"></i>
                        <div class="pl-3">
                            <p class="float-left mb-0 no-show name text-center"><?php echo $user->username ?></p><br />
                            <p class="float-left mb-0 no-show title text-center"><?php echo $user->getRoleName() ?></p>
                        </div>
                        <span class="fa fa-chevron-up float-right no-show"></span>
                    </a>
                    <ul class="collapse sub-menu" id="collapseExample">
                        <li><a href="#" title="createChangePasswordForm">Change password</a></li>
                        <li><a href="logout.php">Log out</a></li>
                    </ul>
                </div>

            </div>
        </aside>
        <div class='main-content-space'>
            <div class="content">
            </div>
        </div>
    </div>
</body>

</html>