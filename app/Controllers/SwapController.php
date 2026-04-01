<?php
// app/Controllers/SwapController.php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\Auth;
use App\Core\CSRF;
use App\Models\SwapModel;

class SwapController extends Controller
{
    protected SwapModel $swapModel;

    public function __construct()
    {
        $this->swapModel = new SwapModel();
    }

    /**
     * Accept a swap (Provider)
     */
    public function accept(Request $request, Response $response, int $swapId)
    {
        $userId = Auth::id();

        if ($this->swapModel->accept($swapId, $userId)) {
            return $response->redirect(APP_BASE . '/dashboard?msg=Swap+accepted');
        }

        return $response->redirect(APP_BASE . '/dashboard?error=Cannot+accept+swap');
    }

    /**
     * Decline a swap (Provider)
     */
    public function decline(Request $request, Response $response, int $swapId)
    {
        $userId = Auth::id();

        if ($this->swapModel->decline($swapId, $userId)) {
            return $response->redirect(APP_BASE . '/dashboard?msg=Swap+declined');
        }

        return $response->redirect(APP_BASE . '/dashboard?error=Cannot+decline+swap');
    }

    /**
     * Complete a swap (Requester)
     */
    public function complete(Request $request, Response $response, int $swapId)
    {
        $userId = Auth::id();

        if ($this->swapModel->confirmComplete($swapId, $userId)) {
            return $response->redirect(APP_BASE . '/dashboard?msg=Swap+completed');
        }

        return $response->redirect(APP_BASE . '/dashboard?error=Cannot+complete+swap');
    }

    /**
     * Cancel a swap (Requester) — NEW
     */
    public function cancel(Request $request, Response $response, int $swapId)
    {
        $userId = Auth::id();

        // CSRF check
        if (!CSRF::verify($request->post('_csrf_token'))) {
            return $response->redirect(APP_BASE . '/dashboard?error=Invalid+CSRF+token');
        }

        // Get swap
        $swap = $this->swapModel->getSwap($swapId);
        if (!$swap || (int)$swap['requester_id'] !== $userId) {
            return $response->redirect(APP_BASE . '/dashboard?error=Cannot+cancel+this+swap');
        }

        // Only allow cancel if still requested
        if ($swap['status'] !== SwapModel::STATUS_REQUESTED) {
            return $response->redirect(APP_BASE . '/dashboard?error=Only+requested+swaps+can+be+cancelled');
        }

        try {
            $this->swapModel->decline($swapId, $swap['provider_id']); // Return credits and update status
            return $response->redirect(APP_BASE . '/dashboard?msg=Swap+cancelled');
        } catch (\Throwable $e) {
            error_log('SwapController::cancel failed: ' . $e->getMessage());
            return $response->redirect(APP_BASE . '/dashboard?error=Failed+to+cancel+swap');
        }
    }
}
