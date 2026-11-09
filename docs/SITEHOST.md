# SiteHost Domain Queue connector

SiteHost ([api.sitehost.nz](https://api.sitehost.nz), docs at
[docs.sitehost.nz](https://docs.sitehost.nz/api/v1.5/?path=%2Fsrs)) is a New Zealand
registrar. This connector adds SiteHost to DomainMOD's **Domain Queue** so you can import
domains the same way as GoDaddy, Hostinger, and the other API registrars.

## Credentials

In the SiteHost Control Panel, create an API key with **Domain (SRS)** access. IP-allowlist
the DomainMOD server if possible.

In DomainMOD, create or edit a registrar account with:

- **API Key** — the SiteHost API key
- **Account ID** — the SiteHost **Client ID**

## What DomainMOD imports

| Field | Source |
|---|---|
| Domain list | `GET /1.5/srs/list_domains.json` (paginated) |
| Expiry | `DateBilledUntil` from `srs/get_domain` |
| Auto-renew | `autorenew_term > 0` |
| Nameservers | `srs/list_name_servers` |
| WHOIS privacy | `private` on `srs/list_domains` |

## Pricing (NZD)

SiteHost's `srs/get_domain_price` returns amounts in **New Zealand dollars (NZD)**.
DomainMOD does not pull registrar prices through the Domain Queue. When you add SiteHost
fee rows under Assets → Registrar Fees, choose **NZD** so costs and reports stay correct.

## Out of scope (by design)

SiteHost also exposes purchase (`create_domain`), renew (`renew_domain`), transfer,
contacts, lock/UDAI, and DNS zone APIs. DomainMOD's registrar integrations only support
Domain Queue list/import (read expiry, DNS hosts, privacy, auto-renew). Manage
registration and renewal in the SiteHost panel (or another billing system).

## Install / upgrade

- New installs: SiteHost is seeded in `install/go.php`.
- Existing installs: database update **4.24.01 → 4.24.02** inserts the `api_registrars`
  row (idempotent). Open DomainMOD after deploying so the updater runs.

Then set the registrar's **API Support** to SiteHost and fill in the account fields above.

## Remove

1. Clear API Support on any registrar using SiteHost.
2. `DELETE FROM api_registrars WHERE name = 'SiteHost';`
3. Delete `classes/DomainMOD/SiteHost.php` and the SiteHost branches in
   `classes/DomainMOD/DomainQueue.php`, plus the install/update seed rows and composer
   classmap entries if you are maintaining a fork without the connector.
