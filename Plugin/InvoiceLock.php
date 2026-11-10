<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_AccountingLock
 */
declare(strict_types=1);

namespace Maxcode\AccountingLock\Plugin;

use Magento\Sales\Model\Order\Invoice;
use Maxcode\AccountingLock\Model\Guard;

/**
 * Une facture émise ne s'annule pas : le bouton disparaît et l'annulation est refusée.
 *
 * Couvre le bouton « Annuler » de l'administration (canCancel), le contrôleur d'annulation (cancel) et le
 * « void » d'une facture (void), qui sert aussi la route REST POST /V1/invoices/:id/void.
 */
class InvoiceLock
{
    /**
     * @param Guard $guard Décision et refus.
     */
    public function __construct(private readonly Guard $guard)
    {
    }

    /**
     * Le bouton « Annuler » n'est plus proposé.
     *
     * @param Invoice $subject Facture.
     * @param bool    $result  Réponse de Magento.
     *
     * @return bool
     */
    public function afterCanCancel(Invoice $subject, $result)
    {
        return $this->guard->isLocked($subject->getStoreId()) ? false : $result;
    }

    /**
     * Le bouton « Void » n'est plus proposé.
     *
     * @param Invoice $subject Facture.
     * @param bool    $result  Réponse de Magento.
     *
     * @return bool
     */
    public function afterCanVoid(Invoice $subject, $result)
    {
        return $this->guard->isLocked($subject->getStoreId()) ? false : $result;
    }

    /**
     * Refuse l'annulation d'une facture ouverte (c'est le seul cas où Magento annulerait quelque chose).
     *
     * @param Invoice $subject Facture.
     *
     * @return void
     */
    public function beforeCancel(Invoice $subject)
    {
        if ((int) $subject->getState() === Invoice::STATE_OPEN && $this->guard->isLocked($subject->getStoreId())) {
            $this->guard->refuse(
                Guard::DOCUMENT_INVOICE,
                $subject->getId(),
                'cancel',
                __('An issued invoice cannot be canceled: correct it with a credit memo.')
            );
        }
    }

    /**
     * Refuse le « void » d'une facture, quel que soit son état.
     *
     * @param Invoice $subject Facture.
     *
     * @return void
     */
    public function beforeVoid(Invoice $subject)
    {
        if ($this->guard->isLocked($subject->getStoreId())) {
            $this->guard->refuse(
                Guard::DOCUMENT_INVOICE,
                $subject->getId(),
                'void',
                __('An issued invoice cannot be voided: correct it with a credit memo.')
            );
        }
    }
}
