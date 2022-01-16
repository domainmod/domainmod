<?php
/**
 * /feedback/index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2022 Greg Chetcuti <greg@chetcuti.com>
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
<?php
require_once __DIR__ . '/../_includes/start-session.inc.php';
require_once __DIR__ . '/../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/feedback-main.inc.php';

$system->authCheck();
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>

Whether you have a comment, a question, a feature request, or a bug report, we want to hear from you!<BR>
<BR>
We recently launched a new Support Portal that will allow us to have better communication and transparency with our users. Not only does the new portal allow users to submit questions and issues, but users can then vote on the items that are most important to them, which helps us prioritize what should be worked on next.<BR>
<BR>
On the subject of what's being worked on next, integrated with our new Support Portal is a project Roadmap. The Roadmap is created automatically based on the issues submitted by users and their current status (Planned, In Progress, Completed, etc.). This Roadmap will give users a public look into how things are progressing with the development of <?php echo SOFTWARE_TITLE; ?>.<BR>
<BR>
So what are you waiting for? Speak your mind or checkout the Roadmap below!<BR>
<BR>
<a target="_blank" href="https://support.domainmod.org/b/90pj1r0j/domainmod-support/idea/new"><?php echo $layout->showButton('button', _('Submit Feedback')); ?></a>
&nbsp;&nbsp;&nbsp;
<a target="_blank" href="https://support.domainmod.org/roadmap"><?php echo $layout->showButton('button', _('View The Project Roadmap')); ?></a>

<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
