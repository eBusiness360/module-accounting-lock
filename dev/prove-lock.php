<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_AccountingLock
 */

/**
 * Preuve du blocage d'annulation (Maxcode_AccountingLock) sur une installation Magento locale.
 *
 * Lancer depuis la racine de l'installation, dans le conteneur DDEV :
 *   php app/code/Maxcode/AccountingLock/dev/prove-lock.php
 * Aucune donnee durable : tout se passe dans une transaction SQL annulee a la fin, et aucun document n'est
 * enregistre (les objets sont modifies en memoire seulement).
 */
declare(strict_types=1);

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;

require dirname(__DIR__, 5) . '/app/bootstrap.php';

$bootstrap = Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();
$om->get(State::class)->setAreaCode('adminhtml');

$scope = $om->get(\Magento\Framework\App\Config\ScopeConfigInterface::class);
$lockOn = (bool) $scope->isSetFlag('maxcode_accounting_lock/general/enabled');
echo 'Reglage maxcode_accounting_lock/general/enabled = ' . ($lockOn ? '1 (blocage actif)' : '0 (blocage inactif)') . PHP_EOL;

$db = $om->get(ResourceConnection::class)->getConnection();
$fails = 0;
$check = static function (string $label, bool $ok, string $detail = '') use (&$fails): void {
    if (!$ok) {
        $fails++;
    }
    echo ($ok ? '[OK]   ' : '[ECHEC]') . ' ' . $label . ($detail !== '' ? ' -> ' . $detail : '') . PHP_EOL;
};

$invoiceRepo = $om->get(\Magento\Sales\Api\InvoiceRepositoryInterface::class);
$creditmemoRepo = $om->get(\Magento\Sales\Api\CreditmemoRepositoryInterface::class);
$invoiceMgmt = $om->get(\Magento\Sales\Api\InvoiceManagementInterface::class);
$creditmemoMgmt = $om->get(\Magento\Sales\Api\CreditmemoManagementInterface::class);

$db->beginTransaction();
try {
    $paidId = (int) $db->fetchOne('SELECT entity_id FROM sales_invoice WHERE state = 2 ORDER BY entity_id DESC LIMIT 1');
    $openRealId = (int) $db->fetchOne('SELECT entity_id FROM sales_invoice WHERE state = 1 ORDER BY entity_id DESC LIMIT 1');
    $cmId = (int) $db->fetchOne('SELECT entity_id FROM sales_creditmemo WHERE state = 2 ORDER BY entity_id DESC LIMIT 1');

    // Facture ouverte de test : une facture reglee passee a l'etat ouvert DANS la transaction (annulee a la fin).
    $db->update('sales_invoice', ['state' => 1], ['entity_id = ?' => $paidId]);
    // Avoir « en attente » de test : meme principe.
    $db->update('sales_creditmemo', ['state' => 1], ['entity_id = ?' => $cmId]);

    echo PHP_EOL . '== Facture ouverte de test (id ' . $paidId . ', etat 1 dans la transaction)' . PHP_EOL;
    $invoice = $om->create(\Magento\Sales\Model\Order\Invoice::class)->load($paidId);
    $check('etat lu = 1 (ouverte)', (int) $invoice->getState() === 1);
    $expectCan = !$lockOn;
    $check('canCancel() === ' . var_export($expectCan, true), $invoice->canCancel() === $expectCan);
    try {
        $invoice->cancel();
        $check($lockOn ? 'cancel() refuse par une exception' : 'cancel() execute (blocage inactif)', !$lockOn);
        if (!$lockOn) {
            $check('etat apres cancel() = 3 (annulee, en memoire)', (int) $invoice->getState() === 3);
        }
    } catch (\Magento\Framework\Exception\LocalizedException $e) {
        $check('cancel() refuse par une exception', $lockOn, '"' . $e->getMessage() . '"');
        $check('facture inchangee (etat 1)', (int) $invoice->getState() === 1);
    }

    echo PHP_EOL . '== Facture ouverte reelle de la base (id ' . $openRealId . ', lecture seule)' . PHP_EOL;
    if ($openRealId) {
        $real = $invoiceRepo->get($openRealId);
        $check('canCancel() === ' . var_export($expectCan, true), $real->canCancel() === $expectCan);
    }

    echo PHP_EOL . '== Facture reglee (id ' . $paidId . ' relue sans transaction de test) : void / REST' . PHP_EOL;
    $db->update('sales_invoice', ['state' => 2], ['entity_id = ?' => $paidId]);
    $paid = $om->create(\Magento\Sales\Model\Order\Invoice::class)->load($paidId);
    $check('etat lu = 2 (reglee)', (int) $paid->getState() === 2);
    try {
        // Chemin de la route REST POST /V1/invoices/:id/void et du bouton « Void » de l'administration.
        // Sans blocage, Magento appellerait la passerelle de paiement : on ne l'essaie donc que bloque.
        if ($lockOn) {
            $invoiceMgmt->setVoid($paidId);
            $check('setVoid() refuse par une exception', false);
        } else {
            $check('setVoid() non essaye (blocage inactif : appellerait la passerelle de paiement)', true);
        }
    } catch (\Magento\Framework\Exception\LocalizedException $e) {
        $check('setVoid() refuse par une exception', $lockOn, '"' . $e->getMessage() . '"');
    }
    $check('canVoid() === false', $paid->canVoid() === false || !$lockOn);

    echo PHP_EOL . '== Avoir en attente de test (id ' . $cmId . ', etat 1 dans la transaction)' . PHP_EOL;
    $cm = $om->create(\Magento\Sales\Model\Order\Creditmemo::class)->load($cmId);
    $check('etat lu = 1 (en attente)', (int) $cm->getState() === 1);
    $check('canCancel() === ' . var_export($expectCan, true), $cm->canCancel() === $expectCan);
    try {
        // Chemin de la route REST POST /V1/creditmemo/:id et du contrôleur d'administration.
        $creditmemoMgmt->cancel($cmId);
        $check('cancel() du service refuse', false);
    } catch (\Magento\Framework\Exception\LocalizedException $e) {
        $check('cancel() du service refuse par une exception', true, '"' . $e->getMessage() . '"');
    }

    echo PHP_EOL . '== Refus de paiement signale par la passerelle (Payment::deny(false)) : passe encore' . PHP_EOL;
    $db->update('sales_invoice', ['state' => 1, 'transaction_id' => 'PROOF-TXN'], ['entity_id = ?' => $paidId]);
    $inv = $om->create(\Magento\Sales\Model\Order\Invoice::class)->load($paidId);
    $order = $inv->getOrder();
    $payment = $order->getPayment();
    $payment->setTransactionId('PROOF-TXN');
    $payment->setNotificationResult(true);
    try {
        $payment->deny(false);
    } catch (\Throwable $e) {
        echo '       (deny() : ' . get_class($e) . ' : ' . $e->getMessage() . ')' . PHP_EOL;
    }
    $denied = null;
    foreach ($order->getInvoiceCollection() as $candidate) {
        if ((int) $candidate->getId() === $paidId) {
            $denied = $candidate;
        }
    }
    $check(
        'la facture ouverte a ete annulee par Magento (etat 3, en memoire)',
        $denied !== null && (int) $denied->getState() === 3
    );
} finally {
    $db->rollBack();
    echo PHP_EOL . 'Transaction SQL annulee : aucune donnee durable.' . PHP_EOL;
}

$left = (int) $db->fetchOne('SELECT COUNT(*) FROM sales_invoice WHERE state = 1');
echo 'Controle apres annulation : factures a l etat 1 en base = ' . $left . ' (attendu : celle de depart)' . PHP_EOL;
echo $fails === 0 ? 'RESULTAT : TOUT EST OK' . PHP_EOL : 'RESULTAT : ' . $fails . ' ECHEC(S)' . PHP_EOL;
exit($fails === 0 ? 0 : 1);
