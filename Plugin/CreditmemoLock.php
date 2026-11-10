<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_AccountingLock
 */
declare(strict_types=1);

namespace Maxcode\AccountingLock\Plugin;

use Magento\Sales\Model\Order\Creditmemo;
use Maxcode\AccountingLock\Model\Guard;

/**
 * Un avoir émis ne s'annule pas : le bouton « Annuler » disparaît.
 *
 * Le modèle Creditmemo de Magento (2.4.6 à 2.4.9) n'a pas de méthode cancel() : seul canCancel() existe, et il
 * commande le bouton de l'administration. beforeCancel() ne sert donc que si une extension ajoute cette méthode.
 */
class CreditmemoLock
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
     * @param Creditmemo $subject Avoir.
     * @param bool       $result  Réponse de Magento.
     *
     * @return bool
     */
    public function afterCanCancel(Creditmemo $subject, $result)
    {
        return $this->guard->isLocked($subject->getStoreId()) ? false : $result;
    }

    /**
     * Refuse l'annulation d'un avoir si une extension a ajouté cancel() au modèle.
     *
     * @param Creditmemo $subject Avoir.
     *
     * @return void
     */
    public function beforeCancel(Creditmemo $subject)
    {
        if ($this->guard->isLocked($subject->getStoreId())) {
            $this->guard->refuse(
                Guard::DOCUMENT_CREDITMEMO,
                $subject->getId(),
                'cancel',
                __('An issued credit memo cannot be canceled: issue a new invoice.')
            );
        }
    }
}
