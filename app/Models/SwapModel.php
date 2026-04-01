<?php
// app/Models/SwapModel.php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class SwapModel extends BaseModel
{
    protected string $table = 'swap_requests';

    public const STATUS_REQUESTED  = 'requested';
    public const STATUS_ACCEPTED   = 'accepted';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_DECLINED   = 'declined';
    public const STATUS_DISPUTED   = 'disputed';

    /**
     * Create a swap request and lock credits into escrow atomically.
     */
    public function createWithEscrow(int $requesterId, int $providerId, int $serviceId, int $credits, string $message): int|false
    {
        try {
            $this->db->beginTransaction();

            // 1. Check requester has enough credits
            $stmt = $this->db->prepare('SELECT credits FROM users WHERE id = ? FOR UPDATE');
            $stmt->execute([$requesterId]);
            $row = $stmt->fetch();

            if (!$row || (int)$row['credits'] < $credits) {
                $this->db->rollBack();
                return false; // Insufficient credits
            }

            // 2. Deduct from requester
            $this->db->prepare('UPDATE users SET credits = credits - ? WHERE id = ?')
                     ->execute([$credits, $requesterId]);

            // 3. Create the swap record
            $this->db->prepare(
                'INSERT INTO swap_requests
                 (requester_id, provider_id, service_id, credits_escrowed, message, status, created_at, updated_at)
                 VALUES (?,?,?,?,?,?,NOW(),NOW())'
            )->execute([$requesterId, $providerId, $serviceId, $credits, $message, self::STATUS_REQUESTED]);

            $swapId = (int)$this->db->lastInsertId();

            // 4. Record escrow ledger entry
            $this->db->prepare(
                'INSERT INTO escrow_ledger (swap_id, user_id, amount, type, created_at)
                 VALUES (?,?,?,?,NOW())'
            )->execute([$swapId, $requesterId, $credits, 'locked']);

            $this->db->commit();
            return $swapId;

        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('SwapModel::createWithEscrow failed: ' . $e->getMessage());
            return false;
        }
    }

    public function accept(int $swapId, int $providerId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE swap_requests SET status=?, updated_at=NOW()
             WHERE id=? AND provider_id=? AND status=?'
        );
        return $stmt->execute([self::STATUS_ACCEPTED, $swapId, $providerId, self::STATUS_REQUESTED]);
    }

    public function decline(int $swapId, int $providerId): bool
    {
        try {
            $this->db->beginTransaction();

            $swap = $this->getSwap($swapId);
            if (!$swap || $swap['provider_id'] != $providerId || $swap['status'] !== self::STATUS_REQUESTED) {
                $this->db->rollBack();
                return false;
            }

            // Return credits
            $this->db->prepare('UPDATE users SET credits = credits + ? WHERE id = ?')
                     ->execute([$swap['credits_escrowed'], $swap['requester_id']]);

            $this->db->prepare('UPDATE swap_requests SET status=?, updated_at=NOW() WHERE id=?')
                     ->execute([self::STATUS_DECLINED, $swapId]);

            $this->db->prepare(
                'INSERT INTO escrow_ledger (swap_id, user_id, amount, type, created_at) VALUES (?,?,?,?,NOW())'
            )->execute([$swapId, $swap['requester_id'], $swap['credits_escrowed'], 'returned']);

            $this->db->commit();
            return true;

        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('SwapModel::decline failed: ' . $e->getMessage());
            return false;
        }
    }

    public function confirmComplete(int $swapId, int $requesterId): bool
    {
        try {
            $this->db->beginTransaction();

            $swap = $this->getSwap($swapId);
            if (!$swap || $swap['requester_id'] != $requesterId || $swap['status'] !== self::STATUS_ACCEPTED) {
                $this->db->rollBack();
                return false;
            }

            // Release credits to provider
            $this->db->prepare('UPDATE users SET credits = credits + ? WHERE id = ?')
                     ->execute([$swap['credits_escrowed'], $swap['provider_id']]);

            $this->db->prepare('UPDATE swap_requests SET status=?, completed_at=NOW(), updated_at=NOW() WHERE id=?')
                     ->execute([self::STATUS_COMPLETED, $swapId]);

            $this->db->prepare(
                'INSERT INTO escrow_ledger (swap_id, user_id, amount, type, created_at) VALUES (?,?,?,?,NOW())'
            )->execute([$swapId, $swap['provider_id'], $swap['credits_escrowed'], 'released']);

            $this->db->commit();
            return true;

        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('SwapModel::confirmComplete failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Cancel a requested swap by requester, return credits
     */
    public function cancel(int $swapId, int $requesterId): bool
    {
        try {
            $this->db->beginTransaction();

            $swap = $this->getSwap($swapId);
            if (!$swap || $swap['requester_id'] != $requesterId || $swap['status'] !== self::STATUS_REQUESTED) {
                $this->db->rollBack();
                return false;
            }

            // Return credits
            $this->db->prepare('UPDATE users SET credits = credits + ? WHERE id = ?')
                     ->execute([$swap['credits_escrowed'], $requesterId]);

            // Mark as canceled
            $this->db->prepare('UPDATE swap_requests SET status=?, updated_at=NOW() WHERE id=?')
                     ->execute(['canceled', $swapId]);

            // Ledger entry
            $this->db->prepare(
                'INSERT INTO escrow_ledger (swap_id, user_id, amount, type, created_at) VALUES (?,?,?,?,NOW())'
            )->execute([$swapId, $requesterId, $swap['credits_escrowed'], 'returned']);

            $this->db->commit();
            return true;

        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('SwapModel::cancel failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get total escrow credits for a user
     */
    public function getUserEscrowCredits(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(credits_escrowed),0) as escrow
             FROM swap_requests
             WHERE requester_id = ? AND status = ?'
        );
        $stmt->execute([$userId, self::STATUS_REQUESTED]);
        return (int)$stmt->fetchColumn();
    }

    public function getSwap(int $id): array|false
    {
        return $this->find($id);
    }

    public function getSwapWithDetails(int $id): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT sr.*,
                    u_req.full_name as requester_name,
                    u_pro.full_name as provider_name,
                    s.title as service_title, s.credits as service_credits
             FROM swap_requests sr
             JOIN users u_req ON u_req.id = sr.requester_id
             JOIN users u_pro ON u_pro.id = sr.provider_id
             JOIN services s   ON s.id    = sr.service_id
             WHERE sr.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getUserSwaps(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT sr.*,
                    u_req.full_name as requester_name,
                    u_pro.full_name as provider_name,
                    s.title as service_title
             FROM swap_requests sr
             JOIN users u_req ON u_req.id = sr.requester_id
             JOIN users u_pro ON u_pro.id = sr.provider_id
             JOIN services s   ON s.id    = sr.service_id
             WHERE sr.requester_id = ? OR sr.provider_id = ?
             ORDER BY sr.updated_at DESC'
        );
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll();
    }

    public function canAccess(int $swapId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM swap_requests WHERE id=? AND (requester_id=? OR provider_id=?)'
        );
        $stmt->execute([$swapId, $userId, $userId]);
        return (bool)$stmt->fetch();
    }
}
