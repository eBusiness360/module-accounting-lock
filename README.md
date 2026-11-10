<div align="center">

**English** · [Français](README.fr.md)

# Maxcode Accounting Lock for Magento 2

**Issued invoices and credit memos cannot be canceled any more: the Cancel button disappears and every other way to cancel is refused. Free, no core override, one setting.**

![License: free EULA](https://img.shields.io/badge/license-free%20EULA-1E4FA3)
![Magento 2.4](https://img.shields.io/badge/Magento-2.4.x-F26322)
![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777BB4)
![Packagist](https://img.shields.io/packagist/v/maxcode/module-accounting-lock?color=0F1E4D)

</div>

## The problem

Under the usual bookkeeping rules, an issued invoice is not canceled and not deleted: a mistake is
corrected with a **credit memo**, and a credit memo is not canceled either: a **new invoice** is issued.

Magento does not follow that rule. An invoice that is still *open* (state "Pending") has a **Cancel**
button, and so does a credit memo that is still *pending*. Canceling an invoice also changes the totals of
the order: the figures you reported are no longer the ones in the database.

> This rule is the one usually followed in bookkeeping. It is **not accounting or legal advice**: ask your
> accountant to confirm that it applies to you before relying on it.

## What it does

- **No more Cancel button** on invoices and credit memos in the admin, and no more *Void* button on invoices.
- **Every other way is refused with a clear message**: the admin, the REST API
  (`POST /V1/invoices/:id/void`, `POST /V1/creditmemo/:id`) and any code that calls Magento's own services.
  Magento has no GraphQL mutation to cancel an invoice or a credit memo.
- **One line in a log file for each blocked attempt**, with no personal data: the kind of document, its
  internal number, the operation and the word `blocked` (`var/log/maxcode_accounting_lock.log`).
- **One setting**, on by default: *Stores > Configuration > Maxcode > Accounting Lock*. Per default or per
  website, so a multi-website shop can switch it off for one of its websites.
- **Canceling an order is not affected**: it stays possible as long as no invoice exists.
- **Documents that are already canceled are never modified.** There is no migration and no data patch.
- **No core override**: four plugins and one setting. Nothing is written to the database apart from the configuration.
- **Translated** into English and French. **Unit tests** included.

Messages shown to the user:

| Operation | Message |
|---|---|
| Cancel an invoice | An issued invoice cannot be canceled: correct it with a credit memo. |
| Void an invoice | An issued invoice cannot be voided: correct it with a credit memo. |
| Cancel a credit memo | An issued credit memo cannot be canceled: issue a new invoice. |

## Good to know

- **An invoice that is still open can no longer be canceled**, for example an offline payment that is still
  pending. Magento offers a credit memo only once the invoice is paid, so such an invoice has to be paid
  first (or kept open) and then corrected with a credit memo. Decide this with your accountant before
  you install the module on a shop that works that way.
- **Payment refusals reported by the payment provider are still processed.** When a payment under review
  is refused (*Deny payment*, or a notification from the provider), Magento cancels the open invoice
  created for that payment and then cancels the order. Blocking that step would leave the provider
  (payment refused) and Magento (order in review) in two different states, so it is the one case that is
  let through.
- **Documents already canceled** stay as they are. To see how many you have:

  ```sql
  SELECT COUNT(*) FROM sales_invoice WHERE state = 3;     -- canceled invoices
  SELECT COUNT(*) FROM sales_creditmemo WHERE state = 3;  -- canceled credit memos
  ```

- Code that writes to the database directly, or that changes the `state` of a document and saves it
  without calling Magento's methods, is not stopped: the module guards the methods and services that
  Magento itself uses to cancel.

## Installation

```bash
composer require maxcode/module-accounting-lock
bin/magento module:enable Maxcode_AccountingLock
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

Requirements: Magento Open Source or Adobe Commerce 2.4.6 or later, PHP 8.1 or later.
Tested on Magento Open Source 2.4.8-p5 with PHP 8.4. The methods the module relies on were compared by
reading the code of Magento 2.4.6, 2.4.8-p5 and 2.4.9: they are identical.

## Configuration

**Stores > Configuration > Maxcode > Accounting Lock > Invoices and credit memos**

| Setting | Default | Role |
|---|---|---|
| Block the cancellation of invoices and credit memos | Yes | Yes: documents cannot be canceled. No: Magento behaves as without the module. |

The value is read for the website of the document, so each website of a multi-website shop can have its own.
Access to the setting is controlled by the ACL resource `Maxcode_AccountingLock::config`.

## For developers

| Piece | Role |
|---|---|
| `Plugin/InvoiceLock` | `afterCanCancel`, `afterCanVoid` (buttons), `beforeCancel` (only for an open invoice, the only case where Magento cancels something), `beforeVoid` on `Magento\Sales\Model\Order\Invoice` |
| `Plugin/CreditmemoLock` | `afterCanCancel` (button) on `Magento\Sales\Model\Order\Creditmemo`; `beforeCancel` only matters if an extension adds that method, Magento's model has none |
| `Plugin/CreditmemoManagementLock` | `beforeCancel` on `Magento\Sales\Api\CreditmemoManagementInterface` (admin controller and REST route) |
| `Plugin/PaymentGatewayFlow` | `aroundDeny` and `aroundUpdate` on `Magento\Sales\Model\Order\Payment`: lets through the invoice cancellation Magento triggers for a refused payment |
| `Model/Guard` | decides, writes the log line, throws the `LocalizedException` |
| `Model/Config` | reads `maxcode_accounting_lock/general/enabled` for the store of the document |
| `dev/prove-lock.php` | proof script for a local installation: loads an open invoice and a pending credit memo inside an SQL transaction that is rolled back, and checks `canCancel()` and the refusals |

On Magento 2.4.6 to 2.4.9 the core credit memo service already refuses to cancel (*You can not cancel Credit Memo*).
The module still gives its own message and protects a replacement implementation that would really cancel.

To stop using the module, run `bin/magento module:disable Maxcode_AccountingLock`: Magento behaves as before.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License and support

Free for any number of Magento installations that you own or operate. You receive the full source
code and may adapt it to your needs; redistribution is not allowed. See [LICENSE](LICENSE)
(free-module EULA).

Provided as is, **without support or update commitment**. For questions and paid services:
[ebusiness360.fr](https://www.ebusiness360.fr/contact/).
