# Changelog — Maxcode_AccountingLock

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), versionnage [SemVer](https://semver.org/lang/fr/).

## [1.0.0] - 2026-11-10 — Première version

### Ajouté
- Une facture émise et un avoir émis ne peuvent plus être annulés : le bouton « Annuler » (et le bouton « Void » d'une facture) disparaît de l'administration, et toute tentative passant par Magento est refusée avec un message clair (contrôleurs d'administration, routes REST `POST /V1/invoices/:id/void` et `POST /V1/creditmemo/:id`, code qui appelle les services de Magento). Message pour une facture : « corrigez-la par un avoir » ; pour un avoir : « émettez une nouvelle facture ».
- Réglage Boutiques > Configuration > Ventes > Accounting Lock (par défaut ou par site web, activé par défaut) avec un texte d'aide qui dit ce que le blocage change.
- Une ligne dans `var/log/maxcode_accounting_lock.log` par tentative bloquée (type du document, identifiant interne, opération, « blocked »), sans donnée personnelle.
- Le refus de paiement signalé par la passerelle de paiement (revue de paiement) annule toujours la facture ouverte créée pour ce paiement, pour ne pas laisser la passerelle et Magento dans deux états différents.
- L'annulation d'une commande n'est pas concernée. Les documents déjà annulés ne sont jamais modifiés ; aucune migration.
- Traductions anglais et français, tests unitaires, script de preuve `dev/prove-lock.php`.
