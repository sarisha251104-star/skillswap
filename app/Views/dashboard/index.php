<?php 
$pageTitle = 'Dashboard';
use App\Core\Validator;
use App\Middleware\Auth;
require APP_ROOT . '/app/Views/layouts/header.php';

$statusIcon = ['requested'=>'⏳','accepted'=>'✅','in_progress'=>'🔄','completed'=>'🎉','declined'=>'❌','disputed'=>'⚠️'];
$catIcons   = ['Design'=>'🎨','Tech'=>'💻','Writing'=>'📝','Photography'=>'📸','Tutoring'=>'🎓','Home Services'=>'🌿','Music'=>'🎵','Other'=>'✨'];
$categories = ['Design','Tech','Writing','Photography','Tutoring','Home Services','Music','Other'];
?>

<div class="page-wrap">
  <div class="dash-grid">
    <div class="dash-main">
      <div class="card">
        <h2 class="card-title">Active Swaps</h2>

        <?php
        $activeList = array_filter($mySwaps, fn($s) => !in_array($s['status'], ['completed','declined']));
        ?>

        <?php if (empty($activeList)): ?>
          <div class="empty-state" style="padding:30px 0">
            <p>No active swaps yet.</p>
          </div>
        <?php else: ?>
          <?php foreach ($activeList as $swap): ?>
            <div class="swap-item">
              <div class="swap-icon"><?= $statusIcon[$swap['status']] ?? '📋' ?></div>
              <div class="swap-info">
                <div class="swap-title"><?= Validator::e($swap['service_title']) ?></div>
                <div class="swap-with">
                  with <?= Validator::e(Auth::id() === (int)$swap['requester_id'] ? $swap['provider_name'] : $swap['requester_name']) ?>
                </div>
              </div>
              <div style="display:flex;align-items:center;gap:8px">
                <span class="badge badge-<?= Validator::e($swap['status']) ?>"><?= ucfirst(str_replace('_',' ',$swap['status'])) ?></span>
                <a href="<?= APP_BASE ?>/messages/<?= (int)$swap['id'] ?>" class="btn btn-outline btn-sm">Chat</a>

                <!-- Provider actions -->
                <?php if ($swap['status'] === 'requested' && Auth::id() === (int)$swap['provider_id']): ?>
                  <button class="btn btn-primary btn-sm"
                          onclick="swapAction(<?= (int)$swap['id'] ?>, 'accept', this)">Accept</button>
                  <button class="btn btn-ghost btn-sm"
                          onclick="swapAction(<?= (int)$swap['id'] ?>, 'decline', this)">Decline</button>
                <!-- Requester cancel -->
                <?php elseif ($swap['status'] === 'requested' && Auth::id() === (int)$swap['requester_id']): ?>
                  <button class="btn btn-ghost btn-sm"
                          onclick="swapAction(<?= (int)$swap['id'] ?>, 'cancel', this)">Cancel</button>
                <?php elseif ($swap['status'] === 'accepted' && Auth::id() === (int)$swap['requester_id']): ?>
                  <button class="btn btn-primary btn-sm"
                          onclick="swapAction(<?= (int)$swap['id'] ?>, 'complete', this)">✓ Complete</button>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
function swapAction(swapId, action, btn) {
    const formData = new FormData();
    formData.append('_csrf_token', '<?= \App\Core\CSRF::generate() ?>');

    fetch('/swaps/' + swapId + '/' + action, { method:'POST', body: formData, credentials:'same-origin' })
    .then(res => res.json())
    .then(data => {
        if(data.success) location.reload();
        else alert(data.error);
    });
}
</script>

<?php require APP_ROOT . '/app/Views/layouts/footer.php'; ?>
