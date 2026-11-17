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
    case 'reporting-domain-costs':
    case 'reporting-domain-fees':
    case 'reporting-main':
    case 'reporting-ssl-costs':
    case 'reporting-ssl-fees':
    case 'queue-main':
    case 'segments-main':
    case 'ssl-main':
        $include_datatable_css = 1;
        break;
}

if ($include_datatable_css === 1) { ?>

    <link rel="stylesheet" href="<?php echo $layout->assetUrl('css/datatables/' . $slug . '.css'); ?>"><?php

}