<?php
// app/Controllers/SwapController.php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{CSRF, Validator};
use App\Middleware\Auth;
use App\Models\{SwapModel, ServiceModel, ReviewModel};

class SwapController
{
    private SwapModel    $swaps;
    private ServiceModel $services;

    public function __construct()
    {
        $this->swaps    = new SwapModel();
        $this->services = new ServiceModel();
    }

    // POST /swaps/request
    public function request(): void
    {
        Auth::requireLogin();
        try { CSRF::verify($_POST['_csrf_token'] ?? ''); }
        catch (\RuntimeException) { $this->jsonError('Invalid CSRF token.', 403); return; }

        $v = new Validator($_POST);
        $v->required('service_id')->integer('service_id')
          ->required('message')->min('message', 10)->max('message', 500);

        if ($v->fails()) { $this->jsonError(array_values($v->errors())[0]); return; }

        $service = $this->services->findWithOwner((int)$v->get('service_id'));
        if (!$service) { $this->jsonError('Service not found.', 404); return; }

        if ((int)$service['user_id'] === Auth::id()) {
            $this->jsonError('You cannot request your own service.');
            return;
        }

        $swapId = $this->swaps->createWithEscrow(
            Auth::id(),
            (int)$service['user_id'],
            (int)$service['id'],
            (int)$service['credits'],
            $v->get('message')
        );

        if ($swapId === false) {
            $this->jsonError('Insufficient credits to make this request.');
            return;
        }

        $this->jsonSuccess([
            'swap_id' => $swapId,
            'message' => 'Request sent. Credits are held in escrow.'
        ]);
    }

    // POST /swaps/:id/accept
    public function accept(array $params): void
    {
        Auth::requireLogin();
        try { CSRF::verify($_POST['_csrf_token'] ?? ''); }
        catch (\RuntimeException) { $this->jsonError('Invalid CSRF token.', 403); return; }

        $ok = $this->swaps->accept((int)$params['id'], Auth::id());
        $ok ? $this->jsonSuccess(['message' => 'Swap accepted. You can now message the requester.'])
            : $this->jsonError('Unable to accept this swap.', 403);
    }

    // POST /swaps/:id/decline
    public function decline(array $params): void
    {
        Auth::requireLogin();
        try { CSRF::verify($_POST['_csrf_token'] ?? ''); }
        catch (\RuntimeException) { $this->jsonError('Invalid CSRF token.', 403); return; }

        $ok = $this->swaps->decline((int)$params['id'], Auth::id());
        $ok ? $this->jsonSuccess(['message' => 'Swap declined. Credits returned to requester.'])
            : $this->jsonError('Unable to decline this swap.', 403);
    }

    // NEW: POST /swaps/:id/cancel
    public function cancel(array $params): void
    {
        Auth::requireLogin();
        try { CSRF::verify($_POST['_csrf_token'] ?? ''); }
        catch (\RuntimeException) { $this->jsonError('Invalid CSRF token.', 403); return; }

        $swapId = (int)$params['id'];
        $swap = $this->swaps->getSwap($swapId);

        if (!$swap || $swap['requester_id'] !== Auth::id()) {
            $this->jsonError('Swap not found or access denied.', 403);
            return;
        }

        if (!in_array($swap['status'], [SwapModel::STATUS_REQUESTED, SwapModel::STATUS_ACCEPTED])) {
            $this->jsonError('Cannot cancel this swap at its current status.', 403);
            return;
        }

        $ok = $this->swaps->cancel($swapId, Auth::id());
        $ok ? $this->jsonSuccess(['message' => 'Swap canceled. Credits returned to your wallet.'])
            : $this->jsonError('Unable to cancel this swap.', 403);
    }

    // POST /swaps/:id/complete
    public function complete(array $params): void
    {
        Auth::requireLogin();
        try { CSRF::verify($_POST['_csrf_token'] ?? ''); }
        catch (\RuntimeException) { $this->jsonError('Invalid CSRF token.', 403); return; }

        $ok = $this->swaps->confirmComplete((int)$params['id'], Auth::id());
        $ok ? $this->jsonSuccess(['message' => 'Swap completed! Credits released to provider.'])
            : $this->jsonError('Unable to complete this swap.', 403);
    }

    // POST /swaps/:id/review
    public function review(array $params): void
    {
        Auth::requireLogin();
        try { CSRF::verify($_POST['_csrf_token'] ?? ''); }
        catch (\RuntimeException) { $this->jsonError('Invalid CSRF token.', 403); return; }

        $v = new Validator($_POST);
        $v->required('rating')->integer('rating')->range('rating', 1, 5)
          ->required('comment')->min('comment', 5)->max('comment', 500);

        if ($v->fails()) { $this->jsonError(array_values($v->errors())[0]); return; }

        $swap = $this->swaps->getSwap((int)$params['id']);
        if (!$swap || $swap['status'] !== SwapModel::STATUS_COMPLETED) {
            $this->jsonError('Swap must be completed before reviewing.');
            return;
        }

        $revieweeId = (Auth::id() === (int)$swap['requester_id'])
            ? (int)$swap['provider_id']
            : (int)$swap['requester_id'];

        $reviews = new ReviewModel();
        $id = $reviews->create(
            (int)$swap['id'],
            Auth::id(),
            $revieweeId,
            (int)$v->get('rating'),
            $v->get('comment')
        );

        $id ? $this->jsonSuccess(['message' => 'Review submitted.'])
            : $this->jsonError('You have already reviewed this swap.');
    }

    // GET /swaps/:id
    public function show(array $params): void
    {
        Auth::requireLogin();
        $swapId = (int)$params['id'];

        if (!$this->swaps->canAccess($swapId, Auth::id())) {
            http_response_code(403);
            exit('Access denied.');
        }

        $swap = $this->swaps->getSwapWithDetails($swapId);
        require APP_ROOT . '/app/Views/swaps/detail.php';
    }

    private function jsonSuccess(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, ...$data]);
    }

    private function jsonError(string $message, int $code = 422): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $message]);
    }
}
