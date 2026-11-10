<div align="center">

[English](README.md) · **Français**

# Maxcode Accounting Lock pour Magento 2

**Une facture ou un avoir émis ne peuvent plus être annulés : le bouton Annuler disparaît et toutes les autres voies d'annulation sont refusées. Gratuit, sans surcharge du cœur, un seul réglage.**

![Licence : CLUF gratuit](https://img.shields.io/badge/licence-CLUF%20gratuit-1E4FA3)
![Magento 2.4](https://img.shields.io/badge/Magento-2.4.x-F26322)
![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777BB4)
![Packagist](https://img.shields.io/packagist/v/maxcode/module-accounting-lock?color=0F1E4D)

</div>

## Le problème

Selon les règles habituelles de la comptabilité, une facture émise ne s'annule pas et ne se supprime pas :
une erreur se corrige par un **avoir**, et un avoir ne s'annule pas non plus : on émet une **nouvelle facture**.

Magento ne suit pas cette règle. Une facture encore *ouverte* (état « En attente ») a un bouton **Annuler**,
comme un avoir encore *en attente*. L'annulation d'une facture modifie aussi les totaux de la commande : les
chiffres que vous avez déclarés ne sont plus ceux de la base.

> Cette règle est celle que l'on suit habituellement en comptabilité. Ce n'est **pas un avis comptable ou
> juridique** : faites confirmer par votre expert-comptable qu'elle s'applique à votre cas avant de vous y fier.

## Ce que fait le module

- **Plus de bouton Annuler** sur les factures et les avoirs dans l'administration, et plus de bouton
  *Void* sur les factures.
- **Toutes les autres voies sont refusées avec un message clair** : l'administration, l'API REST
  (`POST /V1/invoices/:id/void`, `POST /V1/creditmemo/:id`) et tout code qui appelle les services de Magento.
  Magento n'a pas de mutation GraphQL pour annuler une facture ou un avoir.
- **Une ligne de journal par tentative bloquée**, sans donnée personnelle : le type du document, son numéro
  interne, l'opération et le mot `blocked` (`var/log/maxcode_accounting_lock.log`).
- **Un seul réglage**, activé par défaut : *Boutiques > Configuration > Ventes > Accounting Lock*. Par défaut ou par
  site web : une boutique à plusieurs sites web peut le désactiver pour l'un d'eux.
- **L'annulation d'une commande n'est pas concernée** : elle reste possible tant qu'aucune facture n'existe.
- **Les documents déjà annulés ne sont jamais modifiés.** Aucune migration, aucun patch de données.
- **Aucune surcharge du cœur** : quatre plugins et un réglage. Rien n'est écrit en base en dehors de la configuration.
- **Traduit** en anglais et en français. **Tests unitaires** inclus.

Messages affichés à l'utilisateur :

| Opération | Message |
|---|---|
| Annuler une facture | Une facture émise ne peut pas être annulée : corrigez-la par un avoir. |
| « Void » d'une facture | Une facture émise ne peut pas être annulée par un « void » : corrigez-la par un avoir. |
| Annuler un avoir | Un avoir émis ne peut pas être annulé : émettez une nouvelle facture. |

## À savoir

- **Une facture encore ouverte ne peut plus être annulée non plus**, par exemple un paiement hors ligne
  encore en attente. Magento ne propose un avoir qu'une fois la facture payée : une telle facture doit donc
  d'abord être réglée (ou rester ouverte), puis corrigée par un avoir. À décider avec votre
  expert-comptable avant d'installer le module sur une boutique qui fonctionne ainsi.
- **Les refus de paiement signalés par le prestataire de paiement sont toujours traités.** Quand un paiement en
  revue est refusé (*Refuser le paiement*, ou notification du prestataire), Magento annule la facture ouverte
  créée pour ce paiement, puis annule la commande. Bloquer cette étape laisserait le prestataire (paiement
  refusé) et Magento (commande en revue) dans deux états différents : c'est le seul cas laissé passer.
- **Les documents déjà annulés** restent tels quels. Pour savoir combien vous en avez :

  ```sql
  SELECT COUNT(*) FROM sales_invoice WHERE state = 3;     -- factures annulées
  SELECT COUNT(*) FROM sales_creditmemo WHERE state = 3;  -- avoirs annulés
  ```

- Un code qui écrit directement en base, ou qui change l'état (`state`) d'un document et l'enregistre sans
  appeler les méthodes de Magento, n'est pas arrêté : le module garde les méthodes et les services que
  Magento utilise lui-même pour annuler.

## Installation

```bash
composer require maxcode/module-accounting-lock
bin/magento module:enable Maxcode_AccountingLock
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

Prérequis : Magento Open Source ou Adobe Commerce 2.4.6 ou plus récent, PHP 8.1 ou plus récent.
Testé sur Magento Open Source 2.4.8-p5 avec PHP 8.4. Les méthodes dont le module dépend ont été comparées par
lecture du code de Magento 2.4.6, 2.4.8-p5 et 2.4.9 : elles sont identiques.

## Configuration

**Boutiques > Configuration > Ventes > Accounting Lock > Factures et avoirs**

| Réglage | Défaut | Rôle |
|---|---|---|
| Bloquer l'annulation des factures et des avoirs | Oui | Oui : les documents ne peuvent pas être annulés. Non : Magento se comporte comme sans le module. |

La valeur est lue pour le site web du document : chaque site web d'une boutique multi-sites peut avoir la sienne.
L'accès au réglage est contrôlé par la ressource ACL `Maxcode_AccountingLock::config`.

## Pour les développeurs

| Élément | Rôle |
|---|---|
| `Plugin/InvoiceLock` | `afterCanCancel`, `afterCanVoid` (boutons), `beforeCancel` (seulement pour une facture ouverte, le seul cas où Magento annule quelque chose), `beforeVoid` sur `Magento\Sales\Model\Order\Invoice` |
| `Plugin/CreditmemoLock` | `afterCanCancel` (bouton) sur `Magento\Sales\Model\Order\Creditmemo` ; `beforeCancel` ne sert que si une extension ajoute cette méthode, le modèle de Magento n'en a pas |
| `Plugin/CreditmemoManagementLock` | `beforeCancel` sur `Magento\Sales\Api\CreditmemoManagementInterface` (contrôleur d'administration et route REST) |
| `Plugin/PaymentGatewayFlow` | `aroundDeny` et `aroundUpdate` sur `Magento\Sales\Model\Order\Payment` : laisse passer l'annulation de facture que Magento déclenche pour un paiement refusé |
| `Model/Guard` | décide, écrit la ligne de journal, lève la `LocalizedException` |
| `Model/Config` | lit `maxcode_accounting_lock/general/enabled` pour la vue de magasin du document |
| `dev/prove-lock.php` | script de preuve pour une installation locale : charge une facture ouverte et un avoir en attente dans une transaction SQL annulée à la fin, et vérifie `canCancel()` et les refus |

Sur Magento 2.4.6 à 2.4.9, le service d'avoirs du cœur refuse déjà d'annuler (*You can not cancel Credit Memo*).
Le module donne tout de même son propre message et protège une implémentation de remplacement qui annulerait vraiment.

Pour ne plus utiliser le module : `bin/magento module:disable Maxcode_AccountingLock`, Magento se comporte comme avant.

## Changelog

Voir [CHANGELOG.md](CHANGELOG.md).

## Licence et support

Gratuit pour un nombre illimité d'installations Magento que vous possédez ou exploitez. Vous recevez le code
source complet et pouvez l'adapter à vos besoins ; la redistribution n'est pas autorisée. Voir [LICENSE](LICENSE)
(CLUF des modules gratuits).

Fourni en l'état, **sans support ni engagement de mise à jour**. Pour toute question et pour les prestations payantes :
[ebusiness360.fr](https://www.ebusiness360.fr/contact/).
