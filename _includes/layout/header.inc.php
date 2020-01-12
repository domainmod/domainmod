<?php
/**
 * /_includes/layout/header.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2020 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
 *
 * DomainMOD is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with DomainMOD. If not, see
 * http://www.gnu.org/licenses/.
 *
 */
?>
<!-- Site wrapper -->
<div class="wrapper">

  <header class="main-header">
    <!-- Logo -->
    <a href="<?php echo $web_root; ?>" class="logo">
      <!-- mini logo for sidebar mini 50x50 pixels -->
      <span class="logo-mini">
          <img src="<?php echo $web_root; ?>/images/logo-mini.png">
      </span>
      <!-- logo for regular state and mobile devices -->
      <span class="logo-lg" >
          <img src="<?php echo $web_root; ?>/images/logo.png">
      </span>
    </a>
    <!-- Header Navbar: style can be found in header.less -->
    <nav class="navbar navbar-static-top" role="navigation">
      <!-- Sidebar toggle button-->
      <span class="hidden-md hidden-lg">
         <a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button">
             <span class="sr-only">Toggle navigation</span>
             <span class="icon-bar"></span>
             <span class="icon-bar"></span>
             <span class="icon-bar"></span>
         </a>
      </span>

      <div class="navbar-custom-menu">
        <ul class="nav navbar-nav">
          <!-- Messages: style can be found in dropdown.less-->
<?php /* ?>
          <li class="dropdown messages-menu">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
              <i class="fa fa-envelope-o"></i>
              <span class="label label-success">4</span>
            </a>
            <ul class="dropdown-menu">
              <li class="header">You have 4 messages</li>
              <li>
                <!-- inner menu: contains the actual data -->
                <ul class="menu">
                  <li><!-- start message -->
                    <a href="#">
                      <div class="pull-left">
                        <!img src="../../dist/img/user2-160x160.jpg" class="img-circle" alt="User Image">
                      </div>
                      <h4>
                        Support Team
                        <small><i class="fa fa-clock-o"></i> 5 mins</small>
                      </h4>
                      <p>Why not buy a new awesome theme?</p>
                    </a>
                  </li>
                  <!-- end message -->
                </ul>
              </li>
              <li class="footer"><a href="#">See All Messages</a></li>
            </ul>
          </li>
          <!-- Notifications: style can be found in dropdown.less -->
          <li class="dropdown notifications-menu">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
              <i class="fa fa-bell-o"></i>
              <span class="label label-warning">10</span>
            </a>
            <ul class="dropdown-menu">
              <li class="header">You have 10 notifications</li>
              <li>
                <!-- inner menu: contains the actual data -->
                <ul class="menu">
                  <li>
                    <a href="#">
                      <i class="fa fa-users text-aqua"></i> 5 new members joined today
                    </a>
                  </li>
                </ul>
              </li>
              <li class="footer"><a href="#">View all</a></li>
            </ul>
          </li>
          <!-- Tasks: style can be found in dropdown.less -->
          <li class="dropdown tasks-menu">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
              <i class="fa fa-flag-o"></i>
              <span class="label label-danger">9</span>
            </a>
            <ul class="dropdown-menu">
              <li class="header">You have 9 tasks</li>
              <li>
                <!-- inner menu: contains the actual data -->
                <ul class="menu">
                  <li><!-- Task item -->
                    <a href="#">
                      <h3>
                        Design some buttons
                        <small class="pull-right">20%</small>
                      </h3>
                      <div class="progress xs">
                        <div class="progress-bar progress-bar-aqua" style="width: 20%" role="progressbar" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100">
                          <span class="sr-only">20% Complete</span>
                        </div>
                      </div>
                    </a>
                  </li>
                  <!-- end task item -->
                </ul>
              </li>
              <li class="footer">
                <a href="#">View all tasks</a>
              </li>
            </ul>
          </li>
<?php */ ?>
          <!-- User Account: style can be found in dropdown.less -->
          <li class="dropdown user user-menu">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
              <span class="hidden-xs"><?php echo $_SESSION['s_first_name'] . " "; ?><?php echo $_SESSION['s_last_name']; ?></span>&nbsp;
              <i class="fa fa-user"></i>
            </a>

              <ul class="dropdown-menu">
              <!-- User image -->
              <li class="user-header">
                <!img src="../../dist/img/user2-160x160.jpg" class="img-circle" alt="User Image">

                <p>
                    <?php echo $_SESSION['s_first_name'] . " "; ?> <?php echo $_SESSION['s_last_name']; ?><BR>
                    <?php echo $_SESSION['s_email_address']; ?><BR><BR>
                    <small>
                        Currency: <?php echo $_SESSION['s_default_currency']; ?><BR>
                        Time Zone: <?php echo $_SESSION['s_default_timezone']; ?><BR>
                        Expiration Emails: <?php
                        if ($_SESSION['s_expiration_emails'] == '1') {
                            echo "Yes";
                        } else {
                            echo "No";
                        } ?>
                    </small>
                </p>
              </li>
<?php /* ?>
              <!-- Menu Body -->
              <li class="user-body">
                <div class="row">
                  <div class="col-xs-4 text-center">
                    <a href="#">Followers</a>
                  </div>
                  <div class="col-xs-4 text-center">
                    <a href="#">Sales</a>
                  </div>
                  <div class="col-xs-4 text-center">
                    <a href="#">Friends</a>
                  </div>
                </div>
                <!-- /.row -->
              </li>
              <!-- Menu Footer-->
<?php */ ?>
              <li class="user-footer">
                <div class="pull-left">
                  <a href="<?php echo $web_root; ?>/settings/profile/" class="btn btn-default btn-flat">User Profile</a>&nbsp;&nbsp;
                </div>
                <div class="pull-right">
                  <a href="<?php echo $web_root; ?>/logout.php" class="btn btn-default btn-flat">Sign out</a>
                </div>
              </li>
            </ul>
          </li>

<?php /* ?>
          <!-- Control Sidebar Toggle Button -->
          <li>
            <a href="#" data-toggle="control-sidebar"><i class="fa fa-gears"></i></a>
          </li>
<?php */ ?>
        </ul>
      </div>
    </nav>
  </header>

  <!-- =============================================== -->

  <!-- Left side column. contains the sidebar -->
  <aside class="main-sidebar">
    <!-- sidebar: style can be found in sidebar.less -->
    <section class="sidebar">
      <!-- Sidebar user panel -->
<?php /* ?>
      <div class="user-panel">
        <div class="pull-left image">
          <img src="../../dist/img/user2-160x160.jpg" class="img-circle" alt="User Image">
        </div>
        <div class="pull-left info">
          <p>Alexander Pierce</p>
          <a href="#"><i class="fa fa-circle text-success"></i> Online</a>
        </div>
      </div>
<?php */ ?>
      <!-- search form -->
      <form action="<?php echo $web_root; ?>/domains/index.php" method="get" class="sidebar-form">
        <div class="input-group">
          <input type="text" name="search_for" class="form-control" placeholder="Domain Keyword Search"<?php if ($search_for && $search_for != '') echo ' value="' . $search_for . '"'; ?>>
              <span class="input-group-btn">
                <button type="submit" name="search" id="search-btn" class="btn btn-flat"><i class="fa fa-search"></i>
                </button>
              </span>
        </div>
      </form>
      <!-- /.search form -->
      <!-- sidebar menu: : style can be found in sidebar.less -->
      <?php require_once DIR_INC . '/layout/menu-main.inc.php'; ?>
    </section>
    <!-- /.sidebar -->
  </aside>

  <!-- =============================================== -->

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">

      <!-- Content Header (Page header) -->
      <section class="content-header">

          <span class="visible-sm visible-md visible-lg">
              <?php
              $breadcrumb_position = 'left';
              require_once DIR_INC . '/layout/breadcrumbs.inc.php';
              ?>
          </span>

          <span class="visible-xs">
              <?php
              $breadcrumb_position = 'right';
              require_once DIR_INC . '/layout/breadcrumbs.inc.php';
              ?>
          </span>
          <BR>

<?php /* ?>
      <h1>
        <?php echo $page_title; ?>
      </h1>
<?php */ ?>

        <?php
        if ($_SESSION['s_message_danger'] != "") {
            echo $system->showMessageDanger($_SESSION['s_message_danger']);
            unset($_SESSION['s_message_danger']);
        }

        if ($_SESSION['s_message_success'] != "") {
            echo $system->showMessageSuccess($_SESSION['s_message_success']);
            unset($_SESSION['s_message_success']);
        }

        if ($_SESSION['s_message_info'] != "") {
            echo $system->showMessageInfo($_SESSION['s_message_info']);
            unset($_SESSION['s_message_info']);
        }

        require_once DIR_INC . '/layout/table-maintenance.inc.php';
        ?>

    </section>

    <!-- Main content -->
    <section class="content">

      <!-- Default box -->
      <div class="box box-solid box-danger">
<?php /* ?>
        <div class="box-header with-border">
          <h3 class="box-title">Title</h3>
          <div class="box-tools pull-right">
            <button type="button" class="btn btn-box-tool" data-widget="collapse" data-toggle="tooltip" title="Collapse">
              <i class="fa fa-minus"></i></button>
            <button type="button" class="btn btn-box-tool" data-widget="remove" data-toggle="tooltip" title="Remove">
              <i class="fa fa-times"></i></button>
          </div>
        </div>
<?php */ ?>
        <div class="box-header with-border">
            <h3 class="box-title"><?php echo $page_title; ?></h3>
            <?php if ($software_section_logo != '') { ?>
                <span class="pull-right"><i class="fa <?php echo $software_section_logo; ?>"></i></span>
            <?php } ?>
        </div>
        <div class="box-body">
