<?php
/**
 * /classes/DomainMOD/FeeStatus.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2026 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: https://greg.ca
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
//@formatter:off
namespace DomainMOD;

/*
 * A domain's or SSL certificate's fee, as the Edit Domain and Edit SSL Certificate pages show it, and the warning shown
 * after a save leaves one without a fee. An item with no fee can't be costed, so it's hidden from its list until a fee
 * for its TLD (or certificate type) is added at its registrar (or provider). The names come from the database, where
 * they're stored escaped, so they're unescaped and escaped again here. Users who can edit fees get links to them.
 */
class FeeStatus
{
    public $currency;
    public $deeb;
    public $layout;
    public $web_root;

    public function __construct()
    {
        $this->currency = new Currency();
        $this->deeb = Database::getInstance();
        $this->layout = new Layout();
        $this->web_root = WEB_ROOT;
    }

    // A domain's registrar, TLD, and saved fee (the fee's columns are null when it has none), or false if it's gone
    public function domainFee($did)
    {
        $stmt = $this->deeb->cnxx->prepare("
            SELECT d.domain, d.tld, d.fee_id, r.id AS registrar_id, r.name AS registrar, f.id AS fee_row_id, f.initial_fee,
                f.renewal_fee, f.transfer_fee, f.privacy_fee, f.misc_fee, c.currency, c.symbol, c.symbol_order,
                c.symbol_space
            FROM domains AS d
            JOIN registrars AS r ON d.registrar_id = r.id
            LEFT JOIN fees AS f ON d.fee_id = f.id
            LEFT JOIN currencies AS c ON f.currency_id = c.id
            WHERE d.id = :did");
        $stmt->bindValue('did', $did, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        $stmt->closeCursor();
        return $row;
    }

    // An SSL certificate's provider, type, and saved fee (the fee's columns are null when it has none), or false if
    // it's gone
    public function sslFee($sslcid)
    {
        $stmt = $this->deeb->cnxx->prepare("
            SELECT sslc.name, sslc.fee_id, sslp.id AS provider_id, sslp.name AS provider, t.id AS type_id, t.type,
                f.id AS fee_row_id, f.initial_fee, f.renewal_fee, f.misc_fee, c.currency, c.symbol, c.symbol_order,
                c.symbol_space
            FROM ssl_certs AS sslc
            JOIN ssl_providers AS sslp ON sslc.ssl_provider_id = sslp.id
            JOIN ssl_cert_types AS t ON sslc.type_id = t.id
            LEFT JOIN ssl_fees AS f ON sslc.fee_id = f.id
            LEFT JOIN currencies AS c ON f.currency_id = c.id
            WHERE sslc.id = :sslcid");
        $stmt->bindValue('sslcid', $sslcid, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        $stmt->closeCursor();
        return $row;
    }

    // Whether a row from domainFee() or sslFee() has a fee: one is set and it still exists
    public function hasFee($row)
    {
        return $row && (int) $row->fee_id !== 0 && $row->fee_row_id !== null;
    }

    // The Fee section's contents on Edit Domain: the fee's amounts in its own currency, or what's missing
    public function domainSection($row, $can_edit)
    {
        if ($this->hasFee($row)) {

            $amounts = array(_('Initial Fee') => $row->initial_fee, _('Renewal Fee') => $row->renewal_fee,
                _('Transfer Fee') => $row->transfer_fee, _('Privacy Fee') => $row->privacy_fee,
                _('Misc Fee') => $row->misc_fee);
            $url = '/assets/edit/registrar-fee.php?rid=' . (int) $row->registrar_id . '&fee_id=' . (int) $row->fee_id;
            return $this->feeFacts($row, $amounts, $can_edit ? $url : '', _('Edit Fee'));

        }

        return $this->missing($this->noFeeText('.' . $this->text($row->tld), $this->text($row->registrar)),
            _("This domain is hidden from the Domains list until its TLD has a fee at this registrar."),
            $can_edit ? $this->addDomainFeeUrl($row) : '');
    }

    // The Fee section's contents on Edit SSL Certificate: the fee's amounts in its own currency, or what's missing
    public function sslSection($row, $can_edit)
    {
        if ($this->hasFee($row)) {

            $amounts = array(_('Initial Fee') => $row->initial_fee, _('Renewal Fee') => $row->renewal_fee,
                _('Misc Fee') => $row->misc_fee);
            $url = '/assets/edit/ssl-provider-fee.php?sslpid=' . (int) $row->provider_id . '&fee_id=' . (int) $row->fee_id;
            return $this->feeFacts($row, $amounts, $can_edit ? $url : '', _('Edit Fee'));

        }

        return $this->missing($this->noFeeText($this->text($row->type), $this->text($row->provider)),
            _("This SSL certificate is hidden from the SSL Certificates list until its type has a fee at this provider."),
            $can_edit ? $this->addSslFeeUrl($row) : '');
    }

    // The warning after a domain is added or saved without a fee (HTML, for the page's warning message)
    public function domainWarning($row, $can_edit)
    {
        $text = sprintf(_("%1\$s has no fee yet, so it's hidden from the Domains list until a fee for %2\$s at %3\$s is added."),
            $this->layout->ltr($this->text($row->domain)), $this->layout->ltr('.' . $this->text($row->tld)),
            $this->escape($this->text($row->registrar)));
        return $text . ($can_edit ? ' ' . $this->link($this->addDomainFeeUrl($row), _('Add Fee')) : '');
    }

    // The warning after an SSL certificate is added or saved without a fee (HTML, for the page's warning message)
    public function sslWarning($row, $can_edit)
    {
        $text = sprintf(_("%1\$s has no fee yet, so it's hidden from the SSL Certificates list until a fee for %2\$s at %3\$s is added."),
            $this->escape($this->text($row->name)), $this->escape($this->text($row->type)),
            $this->escape($this->text($row->provider)));
        return $text . ($can_edit ? ' ' . $this->link($this->addSslFeeUrl($row), _('Add Fee')) : '');
    }

    // Add Registrar Fee for the domain's registrar, with its TLD filled in
    public function addDomainFeeUrl($row)
    {
        return '/assets/add/registrar-fee.php?rid=' . (int) $row->registrar_id . '&tld=' . rawurlencode($this->text($row->tld));
    }

    // Add SSL Provider Fee for the certificate's provider, with its type chosen
    public function addSslFeeUrl($row)
    {
        return '/assets/add/ssl-provider-fee.php?sslpid=' . (int) $row->provider_id . '&type_id=' . (int) $row->type_id;
    }

    // "No fee for .com at Example Registrar", from plain text: the TLD (or type) is kept left to right
    private function noFeeText($item, $where)
    {
        return sprintf(_('No fee for %1$s at %2$s'), $this->layout->ltr($item), $this->escape($where));
    }

    private function feeFacts($row, $amounts, $url, $link_label)
    {
        $pairs = array();
        foreach ($amounts as $label => $amount) {
            $pairs[] = array('label' => $label, 'value' => $this->currency->format($amount, $this->text($row->symbol),
                $row->symbol_order, $row->symbol_space), 'ltr' => true);
        }
        $pairs[] = array('label' => _('Currency'), 'value' => $this->text($row->currency));
        $html = $this->layout->factList($pairs);
        if ($url !== '') $html .= '<div class="mb-3">' . $this->layout->linkButton($this->web_root . $url, $link_label, 'secondary', 'bi-pencil') . '</div>';
        return '<div class="domainmod-span-all">' . $html . '</div>';
    }

    private function missing($heading, $text, $url)
    {
        $html = '<p class="fw-semibold mb-1">' . $heading . '</p><p>' . $this->escape($text) . '</p>';
        if ($url !== '') $html .= '<div class="mb-3">' . $this->layout->linkButton($this->web_root . $url, _('Add Fee'), 'secondary', 'bi-plus-lg') . '</div>';
        return '<div class="domainmod-span-all">' . $html . '</div>';
    }

    private function link($url, $label)
    {
        return '<a href="' . $this->escape($this->web_root . $url) . '">' . $this->escape($label) . '</a>';
    }

    // A value as stored in the database (escaped, or for a currency symbol, as the pages print it) as plain text
    private function text($value)
    {
        return html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

} //@formatter:on
