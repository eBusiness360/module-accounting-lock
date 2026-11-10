<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_AccountingLock
 */
declare(strict_types=1);

namespace Maxcode\AccountingLock\Plugin;

use Magento\Sales\Model\Order\Payment;
use Maxcode\AccountingLock\Model\GatewayFlow;

/**
 * Laisse passer l'annulation de facture que Magento déclenche lui-même quand la passerelle refuse un paiement.
 *
 * Payment::deny() et Payment::update() (revue de paiement : refus, notification de la passerelle) annulent la
 * facture ouverte créée pour ce paiement, puis annulent la commande. L'interdire laisserait la passerelle
 * (paiement refusé) et Magento (commande en revue) dans deux états différents.
 */
class PaymentGatewayFlow
{
    /**
     * @param GatewayFlow $gatewayFlow Repère du refus de paiement en cours.
     */
    public function __construct(private readonly GatewayFlow $gatewayFlow)
    {
    }

    /**
     * Refus d'un paiement.
     *
     * @param Payment  $subject   Paiement.
     * @param callable $proceed   Traitement de Magento.
     * @param bool     $isOnline  Refus en ligne (true) ou notification de la passerelle (false).
     *
     * @return mixed
     */
    public function aroundDeny(Payment $subject, callable $proceed, $isOnline = true)
    {
        return $this->gatewayFlow->run(static fn () => $proceed($isOnline));
    }

    /**
     * Mise à jour d'un paiement en revue.
     *
     * @param Payment  $subject   Paiement.
     * @param callable $proceed   Traitement de Magento.
     * @param bool     $isOnline  Mise à jour en ligne (true) ou notification de la passerelle (false).
     *
     * @return mixed
     */
    public function aroundUpdate(Payment $subject, callable $proceed, $isOnline = true)
    {
        return $this->gatewayFlow->run(static fn () => $proceed($isOnline));
    }
}
