<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_AccountingLock
 */
declare(strict_types=1);

namespace Maxcode\AccountingLock\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Réglage du module : blocage actif ou non, au niveau du site web du document.
 */
class Config
{
    public const XML_PATH_ENABLED = 'maxcode_accounting_lock/general/enabled';

    /**
     * @param ScopeConfigInterface $scopeConfig Lecture de la configuration.
     */
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * Le blocage est-il actif pour la vue de magasin donnée (donc pour son site web) ?
     *
     * Sans vue de magasin connue, c'est la valeur par défaut qui s'applique.
     *
     * @param int|string|null $storeId Vue de magasin du document.
     *
     * @return bool
     */
    public function isEnabled(int|string|null $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId === null || $storeId === '' ? null : $storeId
        );
    }
}
