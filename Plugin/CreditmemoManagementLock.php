<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_AccountingLock
 */
declare(strict_types=1);

namespace Maxcode\AccountingLock\Plugin;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Maxcode\AccountingLock\Model\Guard;

/**
 * Point d'entrée « annuler un avoir » : contrôleur d'administration et route REST POST /V1/creditmemo/:id.
 *
 * Magento 2.4.6 à 2.4.9 refuse déjà (« You can not cancel Credit Memo ») : ce plugin donne le message du
 * module et protège aussi une implémentation de remplacement qui annulerait vraiment.
 */
class CreditmemoManagementLock
{
    /**
     * @param Guard                         $guard      Décision et refus.
     * @param CreditmemoRepositoryInterface $repository Lecture de l'avoir (vue de magasin, donc site web).
     */
    public function __construct(
        private readonly Guard $guard,
        private readonly CreditmemoRepositoryInterface $repository
    ) {
    }

    /**
     * Refuse l'annulation de l'avoir.
     *
     * @param CreditmemoManagementInterface $subject Service d'avoirs.
     * @param int                           $id      Identifiant de l'avoir.
     *
     * @return void
     */
    public function beforeCancel(CreditmemoManagementInterface $subject, $id)
    {
        try {
            $storeId = $this->repository->get($id)->getStoreId();
        } catch (NoSuchEntityException) {
            return; // Avoir inconnu : Magento répond lui-même.
        }

        if ($this->guard->isLocked($storeId)) {
            $this->guard->refuse(
                Guard::DOCUMENT_CREDITMEMO,
                $id,
                'cancel',
                __('An issued credit memo cannot be canceled: issue a new invoice.')
            );
        }
    }
}
