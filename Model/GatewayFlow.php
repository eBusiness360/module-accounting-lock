<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_AccountingLock
 */
declare(strict_types=1);

namespace Maxcode\AccountingLock\Model;

/**
 * Repère le traitement d'un refus de paiement signalé par la passerelle de paiement.
 *
 * Magento annule alors lui-même la facture ouverte créée pour ce paiement (Payment::deny() et
 * Payment::update(), pendant une revue de paiement). Bloquer cette annulation laisserait la commande
 * et la passerelle dans deux états différents : elle reste donc permise, et elle seule.
 */
class GatewayFlow
{
    /**
     * @var int Nombre de traitements de refus de paiement imbriqués en cours.
     */
    private int $depth = 0;

    /**
     * Un refus de paiement de la passerelle est-il en cours de traitement ?
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->depth > 0;
    }

    /**
     * Exécute le traitement en le déclarant comme refus de paiement de la passerelle.
     *
     * @param callable $callback Traitement à exécuter.
     *
     * @return mixed Résultat du traitement.
     */
    public function run(callable $callback): mixed
    {
        $this->depth++;
        try {
            return $callback();
        } finally {
            $this->depth--;
        }
    }
}
