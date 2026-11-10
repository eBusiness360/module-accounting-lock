<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_AccountingLock
 */
declare(strict_types=1);

namespace Maxcode\AccountingLock\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Psr\Log\LoggerInterface;

/**
 * Décide si l'annulation est interdite et refuse la tentative avec une trace sans donnée personnelle.
 */
class Guard
{
    public const DOCUMENT_INVOICE = 'invoice';
    public const DOCUMENT_CREDITMEMO = 'creditmemo';

    /**
     * @param Config          $config      Réglage du module.
     * @param GatewayFlow     $gatewayFlow Repère des refus de paiement de la passerelle.
     * @param LoggerInterface $logger      Journal du module (var/log/maxcode_accounting_lock.log).
     */
    public function __construct(
        private readonly Config $config,
        private readonly GatewayFlow $gatewayFlow,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * L'annulation est-elle interdite pour un document de cette vue de magasin ?
     *
     * @param int|string|null $storeId Vue de magasin du document.
     *
     * @return bool
     */
    public function isLocked(int|string|null $storeId): bool
    {
        return !$this->gatewayFlow->isActive() && $this->config->isEnabled($storeId);
    }

    /**
     * Refuse une tentative d'annulation : une ligne de journal, puis une exception au message clair.
     *
     * Le journal ne garde que la classe du document, son identifiant interne, l'opération et « blocked ».
     *
     * @param string          $document  Classe du document (self::DOCUMENT_*).
     * @param int|string|null $id        Identifiant interne du document.
     * @param string          $operation Opération tentée (cancel, void).
     * @param Phrase          $message   Message montré à l'utilisateur.
     *
     * @return never
     * @throws LocalizedException Toujours.
     */
    public function refuse(string $document, int|string|null $id, string $operation, Phrase $message): never
    {
        $this->logger->info('blocked', [
            'document' => $document,
            'id' => $id === null || $id === '' ? null : (int) $id,
            'operation' => $operation,
        ]);

        throw new LocalizedException($message);
    }
}
