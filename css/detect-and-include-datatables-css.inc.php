<?php
$include_datatable_css = 0;

switch ($slug ?? '') {
    case 'admin-custom-ssl-fields':
    case 'admin-custom-domain-fields':
    case 'admin-scheduler-main':
    case 'admin-users-main':
    case 'admin-debug-log-main':
    case 'assets-categories':
    case 'assets-dns':
    case 'assets-hosting':
    case 'assets-ip-addresses':
    case 'assets-owners':
    case 'assets-registrar-accounts':
    case 'assets-registrar-fees':
    case 'assets-registrar-fees-missing':
    case 'assets-registrars':
    case 'assets-ssl-accounts':
    case 'assets-ssl-providers':
    case 'assets-ssl-provider-fees':
    case 'assets-ssl-provider-fees-missing':
    case 'assets-ssl-types':
    case 'domains-edit':
    case 'domains-main':
    case 'dw-list-accounts':
    case 'dw-list-zones':
    case 'dw-main':
    case 'dw-servers':
    case 'reporting-domain-cost-by-category':
    case 'reporting-domain-cost-by-dns':
    case 'reporting-domain-cost-by-host':
    case 'reporting-domain-cost-by-ip':
    case 'reporting-domain-cost-by-month':
    case 'reporting-domain-cost-by-owner':
    case 'reporting-domain-fees':
    case 'reporting-domain-cost-by-registrar':
    case 'reporting-domain-cost-by-tld':
    case 'reporting-main':
    case 'reporting-ssl-cost-by-category':
    case 'reporting-ssl-cost-by-domain':
    case 'reporting-ssl-cost-by-ip':
    case 'reporting-ssl-cost-by-month':
    case 'reporting-ssl-cost-by-owner':
    case 'reporting-ssl-cost-by-provider':
    case 'reporting-ssl-cost-by-type':
    case 'reporting-ssl-fees':
    case 'queue-main':
    case 'segments-main':
    case 'ssl-main':
        $include_datatable_css = 1;
        break;
}

if ($include_datatable_css === 1) {

    if ($_SESSION['s_dark_mode'] === 1) { ?>

        <link rel="stylesheet" href="<?php echo $web_root; ?>/css/datatables/<?php echo $slug; ?>-dark.css"><?php

    } else { ?>

        <link rel="stylesheet" href="<?php echo $web_root; ?>/css/datatables/<?php echo $slug; ?>.css"><?php

    }

}
