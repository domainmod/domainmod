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
 * A domain's, SSL certificate's, or hosting service's fee, as the Edit Domain, Edit SSL Certificate, and Edit Hosting
 * Service pages show it, and the warning shown after a save leaves one without a fee. A domain or SSL certificate with
 * no fee can't be costed, so it's hidden from its list until a fee for its TLD (or certificate type) is added at its
 * registrar (or provider). A hosting service with no fee stays in its list, saved without a cost until a fee for its
 * type and billing term is added at its provider. The names come from the database, where they're stored escaped, so
 * they're unescaped and escaped again here. Users who can edit fees get links to them.
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

    // A hosting service's provider, type, billing term, and saved fee (the fee's columns are null when it has none), or
    // false if it's gone (or the hosting tables don't exist yet)
    public function hostingFee($service_id)
    {
        if (!Hosting::isAvailable($this->deeb->cnxx)) return false;

        $stmt = $this->deeb->cnxx->prepare("
            SELECT hs.name, hs.fee_id, hs.billing_term, h.id AS provider_id, h.name AS provider, t.id AS type_id, t.type,
                f.id AS fee_row_id, f.initial_fee, f.renewal_fee, f.misc_fee, c.currency, c.symbol, c.symbol_order,
                c.symbol_space
            FROM hosting_services AS hs
            JOIN hosting AS h ON hs.hosting_id = h.id
            JOIN hosting_types AS t ON hs.type_id = t.id
            LEFT JOIN hosting_fees AS f ON hs.fee_id = f.id
            LEFT JOIN currencies AS c ON f.currency_id = c.id
            WHERE hs.id = :service_id");
        $stmt->bindValue('service_id', $service_id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        $stmt->closeCursor();
        return $row;
    }

    // Whether a row from domainFee(), sslFee(), or hostingFee() has a fee: one is set and it still exists
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

    /*
     * The Fee section's contents on Edit Hosting Service: the service's billing term, the fee's Renewal Fee and Misc Fee
     * in its own currency, what one term costs (the two together), and its yearly figure, or what's missing. The
     * Initial Fee isn't shown, since it never counts toward a service's cost, and the yearly figure is left out for a
     * term that isn't an allowed one, as the list leaves it out.
     */
    public function hostingSection($row, $can_edit)
    {
        if ($this->hasFee($row)) {

            $cost = (float) $row->renewal_fee + (float) $row->misc_fee;
            // TRANSLATORS: The Misc Fee on a hosting service's Fee card. It's added to the Renewal Fee each term.
            $amounts = array(_('Renewal Fee') => $row->renewal_fee, _('Misc Fee (added to each renewal)') => $row->misc_fee,
                _('Cost Per Term') => $cost);
            $yearly = Hosting::yearlyCost($cost, (string) $row->billing_term);
            if ($yearly !== null) $amounts[_('Yearly Cost')] = $yearly;
            $url = '/assets/edit/hosting-fee.php?hosting_id=' . (int) $row->provider_id . '&fee_id=' . (int) $row->fee_id;
            return $this->feeFacts($row, $amounts, $can_edit ? $url : '', _('Edit Fee'),
                array(array('label' => _('Billing Term'), 'value' => Hosting::termLabel($row->billing_term))));

        }

        // The type is the user's own text and the term is translated, so neither is kept left to right
        return $this->missing(sprintf(_('No fee for %1$s at %2$s'), $this->escape($this->hostingTypeText($row)),
            $this->escape($this->text($row->provider))),
            _("No fee matches this provider, type, and billing term yet. The service is saved without a cost until you add one."),
            $can_edit ? $this->addHostingFeeUrl($row) : '');
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

    // The warning after a hosting service is added or saved without a fee (HTML, for the page's warning message)
    public function hostingWarning($row, $can_edit)
    {
        $text = sprintf(_("%1\$s has no fee yet, so it's saved without a cost until a fee for %2\$s at %3\$s is added."),
            $this->escape($this->text($row->name)), $this->escape($this->hostingTypeText($row)),
            $this->escape($this->text($row->provider)));
        return $text . ($can_edit ? ' ' . $this->link($this->addHostingFeeUrl($row), _('Add Fee')) : '');
    }

    /*
     * Add Hosting Fee for the service's provider, with its type and billing term chosen, from a row with provider_id,
     * type_id, and billing_term (such as hostingFee()'s, or a Hosting Services list row). It's static, so the list can
     * build the same link without a FeeStatus.
     */
    public static function addHostingFeeUrl($row)
    {
        return '/assets/add/hosting-fee.php?hosting_id=' . (int) $row->provider_id . '&type_id=' . (int) $row->type_id
            . '&billing_term=' . (int) $row->billing_term;
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

    // A hosting service's type and billing term as plain text, such as "Shared Pro (12 Months)"
    private function hostingTypeText($row)
    {
        // TRANSLATORS: %1$s is a hosting service type, such as Shared Pro, and %2$s its billing term, such as 12 Months.
        return sprintf(_('%1$s (%2$s)'), $this->text($row->type), Hosting::termLabel($row->billing_term));
    }

    private function feeFacts($row, $amounts, $url, $link_label, $extra_pairs = array())
    {
        $pairs = $extra_pairs;
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
