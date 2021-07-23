<?php
switch ($slug) {
    case 'admin-custom-domain-fields':
        $include_datatable_css = 1;
        break;
    case 'admin-custom-ssl-fields':
        $include_datatable_css = 1;
        break;
    case 'admin-scheduler-main':
        $include_datatable_css = 1;
        break;
    case 'admin-users-main':
        $include_datatable_css = 1;
        break;
    case 'admin-debug-log-main':
        $include_datatable_css = 1;
        break;
    case 'assets-categories':
        $include_datatable_css = 1;
        break;
    case 'assets-dns':
        $include_datatable_css = 1;
        break;
    case 'assets-hosting':
        $include_datatable_css = 1;
        break;
    case 'assets-ip-addresses':
        $include_datatable_css = 1;
        break;
    case 'assets-owners':
        $include_datatable_css = 1;
        break;
    case 'assets-registrar-accounts':
        $include_datatable_css = 1;
        break;
    case 'assets-registrar-fees':
        $include_datatable_css = 1;
        break;
    case 'assets-registrar-fees-missing':
        $include_datatable_css = 1;
        break;
    case 'assets-registrars':
        $include_datatable_css = 1;
        break;
    case 'assets-ssl-accounts':
        $include_datatable_css = 1;
        break;
    case 'assets-ssl-providers':
        $include_datatable_css = 1;
        break;
    case 'assets-ssl-provider-fees':
        $include_datatable_css = 1;
        break;
    case 'assets-ssl-provider-fees-missing':
        $include_datatable_css = 1;
        break;
    case 'assets-ssl-types':
        $include_datatable_css = 1;
        break;
    case 'domains-edit':
        $include_datatable_css = 1;
        break;
    case 'domains-main':
        $include_datatable_css = 1;
        break;
    case 'dw-list-accounts':
        $include_datatable_css = 1;
        break;
    case 'dw-list-zones':
        $include_datatable_css = 1;
        break;
    case 'dw-main':
        $include_datatable_css = 1;
        break;
    case 'dw-servers':
        $include_datatable_css = 1;
        break;
    case 'reporting-domain-cost-by-category':
        $include_datatable_css = 1;
        break;
    case 'reporting-domain-cost-by-dns':
        $include_datatable_css = 1;
        break;
    case 'reporting-domain-cost-by-host':
        $include_datatable_css = 1;
        break;
    case 'reporting-domain-cost-by-ip':
        $include_datatable_css = 1;
        break;
    case 'reporting-domain-cost-by-month':
        $include_datatable_css = 1;
        break;
    case 'reporting-domain-cost-by-owner':
        $include_datatable_css = 1;
        break;
    case 'reporting-domain-fees':
        $include_datatable_css = 1;
        break;
    case 'reporting-domain-cost-by-registrar':
        $include_datatable_css = 1;
        break;
    case 'reporting-domain-cost-by-tld':
        $include_datatable_css = 1;
        break;
    case 'reporting-main':
        $include_datatable_css = 1;
        break;
    case 'reporting-ssl-cost-by-category':
        $include_datatable_css = 1;
        break;
    case 'reporting-ssl-cost-by-domain':
        $include_datatable_css = 1;
        break;
    case 'reporting-ssl-cost-by-ip':
        $include_datatable_css = 1;
        break;
    case 'reporting-ssl-cost-by-month':
        $include_datatable_css = 1;
        break;
    case 'reporting-ssl-cost-by-owner':
        $include_datatable_css = 1;
        break;
    case 'reporting-ssl-cost-by-provider':
        $include_datatable_css = 1;
        break;
    case 'reporting-ssl-cost-by-type':
        $include_datatable_css = 1;
        break;
    case 'reporting-ssl-fees':
        $include_datatable_css = 1;
        break;
    case 'queue-main':
        $include_datatable_css = 1;
        break;
    case 'segments-main':
        $include_datatable_css = 1;
        break;
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
