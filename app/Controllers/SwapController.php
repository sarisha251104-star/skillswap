<?php
// app/Controllers/SwapController.php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\{CSRF, Validator};
use App\Middleware\Auth;
use App\Models\{SwapModel, ServiceModel, ReviewModel};

class SwapController
{
    private SwapModel $swaps;
    private ServiceModel $services;

    public function __construct()
    {
        $this->swaps = new SwapModel();
        $this->services = new ServiceModel();
    }

    public function request(): void
    {
        Auth::requireLogin();
        try { CSRF::verify($_POST['_csrf_token'] ?? ''); }
        catch (\RuntimeException) { $this->jsonError('Invalid CSRF token', 403); return; }

        $v = new Validator($_POST);
        $v->required('service_id')->integer('service_id')
          ->required('message')->min('message',10)->max('message',500);

        if($v->fails()){ $this->jsonError(array_values($v->errors())[0]); return; }

        $service = $this->services->findWithOwner((int)$v->get('service_id'));
        if(!$service){ $this->jsonError('Service not found',404); return; }
        if((int)$service['user_id'] === Auth::id()){ $this->jsonError('Cannot request own service'); return; }

        $swapId = $this->swaps->createWithEscrow(
            Auth::id(),
            (int)$service['user_id'],
            (int)$service['id'],
            (int)$service['credits'],
            $v->get('message')
        );

        if($swapId === false){ $this->jsonError('Insufficient credits'); return; }

        $this->jsonSuccess(['swap_id'=>$swapId,'message'=>'Request sent. Credits held in escrow.']);
    }

    public function accept(array $params): void
    {
        Auth::requireLogin();
        try{ CSRF::verify($_POST['_csrf_token'] ?? ''); } catch(\RuntimeException){ $this->jsonError('Invalid CSRF',403); return; }
        $ok = $this->swaps->accept((int)$params['id'], Auth::id());
        $ok ? $this->jsonSuccess(['message'=>'Swap accepted.']) : $this->jsonError('Cannot accept.',403);
    }

    public function decline(array $params): void
    {
        Auth::requireLogin();
        try{ CSRF::verify($_POST['_csrf_token'] ?? ''); } catch(\RuntimeException){ $this->jsonError('Invalid CSRF',403); return; }
        $ok = $this->swaps->decline((int)$params['id'], Auth::id());
        $ok ? $this->jsonSuccess(['message'=>'Swap declined. Credits returned.']) : $this->jsonError('Cannot decline.',403);
    }

    public function complete(array $params): void
    {
        Auth::requireLogin();
        try{ CSRF::verify($_POST['_csrf_token'] ?? ''); } catch(\RuntimeException){ $this->jsonError('Invalid CSRF',403); return; }
        $ok = $this->swaps->confirmComplete((int)$params['id'], Auth::id());
        $ok ? $this->jsonSuccess(['message'=>'Swap completed. Credits released.']) : $this->jsonError('Cannot complete.',403);
    }

    public function cancel(array $params): void
    {
        Auth::requireLogin();
        try{ CSRF::verify($_POST['_csrf_token'] ?? ''); } catch(\RuntimeException){ $this->jsonError('Invalid CSRF',403); return; }
        $ok = $this->swaps->cancelRequest((int)$params['id'], Auth::id());
        $ok ? $this->jsonSuccess(['message'=>'Swap request canceled. Credits returned.']) : $this->jsonError('Cannot cancel.',403);
    }

    private function jsonSuccess(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode(['success'=>true, ...$data]);
    }

    private function jsonError(string $msg,int $code=422): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['success'=>false,'error'=>$msg]);
    }
}
