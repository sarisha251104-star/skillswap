<?php 
// app/Views/dashboard/index.php
$pageTitle = 'Dashboard';
use App\Core\Validator;
use App\Middleware\Auth;
use App\Models\SwapModel;
require APP_ROOT . '/app/Views/layouts/header.php';

$statusIcon = ['requested'=>'⏳','accepted'=>'✅','in_progress'=>'🔄','completed'=>'🎉','declined'=>'❌','disputed'=>'⚠️'];
$catIcons   = ['Design'=>'🎨','Tech'=>'💻','Writing'=>'📝','Photography'=>'📸','Tutoring'=>'🎓','Home Services'=>'🌿','Music'=>'🎵','Other'=>'✨'];
$categories = ['Design','Tech','Writing','Photography','Tutoring','Home Services','Music','Other'];
?>

<div class="page-wrap">
  <div class="section-header">
    <div>
      <h1 class="section-title">Your <em>Dashboard</em></h1>
      <p class="section-sub">Welcome back, <?= Validator::e($user['full_name']) ?></p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addService')">+ List a Service</button>
  </div>

  <div class="dash-grid">
    <!-- ── Sidebar ── -->
    <div class="dash-sidebar">
      <div class="profile-card">
        <div class="profile-big-avatar"><?= strtoupper(mb_substr($user['full_name'], 0, 1)) ?></div>
        <div class="profile-name"><?= Validator::e($user['full_name']) ?></div>
        <div class="profile-role"><?= Validator::e($user['bio'] ? mb_substr($user['bio'], 0, 40) : 'SkillSwap Member') ?></div>
        <div class="credit-display">
          <div class="credit-number" id="dashCredits"><?= (int)$user['credits'] ?></div>
          <div class="credit-label">Available Credits</div>
        </div>
        <span class="badge badge-<?= Validator::e($user['subscription_plan']) ?>" style="font-size:12px;padding:5px 14px">
          <?= ucfirst(Validator::e($user['subscription_plan'])) ?> Plan
        </span>
      </div>

      <?php
        $swapsDone = count(array_filter($mySwaps, fn($s) => $s['status'] === 'completed'));
        $activeSwaps = count(array_filter($mySwaps, fn($s) => in_array($s['status'], ['accepted','in_progress'])));
      ?>
      <div class="sidebar-stat-card">
        <div class="sidebar-stat-label">Swaps Completed</div>
        <div class="sidebar-stat-val"><?= $swapsDone ?></div>
      </div>
      <div class="sidebar-stat-card">
        <div class="sidebar-stat-label">Active Swaps</div>
        <div class="sidebar-stat-val"><?= $activeSwaps ?></div>
      </div>
      <div class="sidebar-stat-card">
        <div class="sidebar-stat-label">My Listings</div>
        <div class="sidebar-stat-val"><?= count($myServices) ?></div>
      </div>

      <a href="<?= APP_BASE ?>/profile" class="btn btn-outline" style="text-align:center">Edit Profile</a>
      <a href="<?= APP_BASE ?>/subscriptions" class="btn btn-dark" style="text-align:center">Upgrade Plan</a>
    </div>

    <!-- ── Main content ── -->
    <div class="dash-main">

      <!-- Active Swaps -->
      <div class="card">
        <div class="flex-between" style="margin-bottom:16px">
          <h2 class="card-title" style="margin-bottom:0">Active Swaps</h2>
          <a href="<?= APP_BASE ?>/messages" style="font-size:13px;color:var(--caramel)">View messages →</a>
        </div>

        <?php
          $activeList = array_filter($mySwaps, fn($s) => !in_array($s['status'], ['completed','declined']));
        ?>
        <?php if (empty($activeList)): ?>
          <div class="empty-state" style="padding:30px 0">
            <div class="empty-icon">🤝</div>
            <p>No active swaps yet. <a href="<?= APP_BASE ?>/services" style="color:var(--caramel)">Browse services</a> to get started.</p>
          </div>
        <?php else: ?>
          <?php foreach (array_slice($activeList, 0, 5) as $swap): ?>
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

                <?php if ($swap['status'] === 'requested'): ?>
                  <?php if (Auth::id() === (int)$swap['provider_id']): ?>
                    <button class="btn btn-primary btn-sm"
                            onclick="swapAction(<?= (int)$swap['id'] ?>, 'accept', this)">Accept</button>
                    <button class="btn btn-ghost btn-sm"
                            onclick="swapAction(<?= (int)$swap['id'] ?>, 'decline', this)">Decline</button>
                  <?php elseif (Auth::id() === (int)$swap['requester_id']): ?>
                    <form method="POST" action="<?= APP_BASE ?>/swaps/<?= (int)$swap['id'] ?>/cancel" class="inline-form" style="margin:0">
                      <input type="hidden" name="_csrf_token" value="<?= \App\Core\CSRF::getToken() ?>">
                      <button type="submit" class="btn btn-warning btn-sm">Cancel</button>
                    </form>
                  <?php endif; ?>
                <?php elseif ($swap['status'] === 'accepted' && Auth::id() === (int)$swap['requester_id']): ?>
                  <button class="btn btn-primary btn-sm"
                          onclick="swapAction(<?= (int)$swap['id'] ?>, 'complete', this)">✓ Complete</button>
                <?php endif; ?>

              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- My Listings -->
      <div class="card">
        <div class="flex-between" style="margin-bottom:16px">
          <h2 class="card-title" style="margin-bottom:0">My Listings</h2>
          <button class="btn btn-outline btn-sm" onclick="openModal('addService')">+ Add</button>
        </div>

        <?php if (empty($myServices)): ?>
          <div class="empty-state" style="padding:24px 0">
            <p>No listings yet. <button class="btn btn-primary btn-sm" onclick="openModal('addService')">Create your first</button></p>
          </div>
        <?php else: ?>
          <?php foreach ($myServices as $svc): ?>
            <div class="swap-item">
              <div class="swap-icon"><?= $catIcons[$svc['category']] ?? '✨' ?></div>
              <div class="swap-info">
                <div class="swap-title"><?= Validator::e($svc['title']) ?></div>
                <div class="swap-with"><?= Validator::e($svc['category']) ?></div>
              </div>
              <div style="display:flex;align-items:center;gap:8px">
                <span class="sc-credits"><?= (int)$svc['credits'] ?> cr</span>
                <a href="<?= APP_BASE ?>/services/<?= (int)$svc['id'] ?>" class="btn btn-outline btn-sm">View</a>
                <button class="btn btn-danger btn-sm" onclick="deleteService(<?= (int)$svc['id'] ?>)">Delete</button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Completed swaps -->
      <?php
        $completedList = array_filter($mySwaps, fn($s) => $s['status'] === 'completed');
      ?>
      <?php if (!empty($completedList)): ?>
      <div class="card">
        <h2 class="card-title">Completed Swaps</h2>
        <?php foreach (array_slice($completedList, 0, 4) as $swap): ?>
          <div class="swap-item">
            <div class="swap-icon">🎉</div>
            <div class="swap-info">
              <div class="swap-title"><?= Validator::e($swap['service_title']) ?></div>
              <div class="swap-with">with <?= Validator::e(Auth::id() === (int)$swap['requester_id'] ? $swap['provider_name'] : $swap['requester_name']) ?></div>
            </div>
            <button class="btn btn-outline btn-sm" onclick="openReviewModal(<?= (int)$swap['id'] ?>)">
              ★ Review
            </button>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Add Service Modal & Review Modal remain unchanged -->

<?php require APP_ROOT . '/app/Views/layouts/footer.php'; ?>
