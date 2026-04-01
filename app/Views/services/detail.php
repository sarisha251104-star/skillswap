<?php
// app/Views/services/detail.php
$pageTitle = $service['title'];
use App\Core\Validator;
use App\Middleware\Auth;
require APP_ROOT . '/app/Views/layouts/header.php';

$isOwner = Auth::check() && Auth::id() === (int)$service['user_id'];
$catIcons = ['Design'=>'🎨','Tech'=>'💻','Writing'=>'📝','Photography'=>'📸',
             'Tutoring'=>'🎓','Home Services'=>'🌿','Music'=>'🎵','Other'=>'✨'];
?>

<div class="page-wrap" style="max-width:900px">

  <div style="margin-bottom:12px">
    <a href="<?= APP_BASE ?>/services" class="text-muted" style="font-size:14px">← Back to Browse</a>
  </div>

  <div style="display:grid;grid-template-columns:1fr 340px;gap:32px;align-items:start">

    <!-- ── Main ── -->
    <div>
      <div class="card" style="margin-bottom:24px">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px">
          <div style="width:60px;height:60px;border-radius:16px;background:var(--cream);
                      display:flex;align-items:center;justify-content:center;font-size:28px;flex-shrink:0">
            <?= $catIcons[$service['category']] ?? '✨' ?>
          </div>
          <div>
            <div style="font-size:12px;text-transform:uppercase;letter-spacing:1.5px;color:var(--caramel);margin-bottom:4px">
              <?= Validator::e($service['category']) ?>
            </div>
            <h1 style="font-family:'Cormorant Garamond',serif;font-size:32px;font-weight:300;line-height:1.1">
              <?= Validator::e($service['title']) ?>
            </h1>
          </div>
        </div>

        <div style="font-size:15px;line-height:1.75;color:var(--charcoal);white-space:pre-wrap">
          <?= Validator::e($service['description']) ?>
        </div>

        <?php if ($isOwner): ?>
          <div style="display:flex;gap:10px;margin-top:24px;padding-top:20px;border-top:1px solid var(--cream)">
            <button class="btn btn-outline btn-sm" onclick="openEditModal()">Edit Listing</button>
            <button class="btn btn-danger btn-sm" onclick="deleteService(<?= (int)$service['id'] ?>)">Delete</button>
          </div>
        <?php endif; ?>
      </div>

      <!-- Provider info -->
      <div class="card">
        <h3 class="card-title" style="font-size:18px">About the Provider</h3>
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px">
          <a href="<?= APP_BASE ?>/users/<?= (int)$service['user_id'] ?>" style="display:flex;align-items:center;gap:14px;flex:1;text-decoration:none;color:inherit">
            <div style="width:52px;height:52px;border-radius:50%;background:var(--caramel);
                        display:flex;align-items:center;justify-content:center;
                        font-family:'Cormorant Garamond',serif;font-size:20px;color:white;flex-shrink:0">
              <?= strtoupper(mb_substr($service['provider_name'], 0, 1)) ?>
            </div>
            <div>
              <div style="font-weight:500;font-size:16px"><?= Validator::e($service['provider_name']) ?></div>
              <?php if (!empty($service['provider_skills'])): ?>
                <div style="font-size:13px;color:var(--gray)"><?= Validator::e(mb_substr($service['provider_skills'], 0, 80)) ?></div>
              <?php endif; ?>
            </div>
          </a>
          <?php if (!empty($service['subscription_plan']) && $service['subscription_plan'] !== 'free'): ?>
            <span class="badge badge-<?= Validator::e($service['subscription_plan']) ?>">
              <?= ucfirst(Validator::e($service['subscription_plan'])) ?>
            </span>
          <?php endif; ?>
        </div>
        <?php if (!empty($service['provider_bio'])): ?>
          <p style="font-size:14px;color:var(--gray);line-height:1.65">
            <?= Validator::e(mb_substr($service['provider_bio'], 0, 200)) ?>…
          </p>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── Sidebar ── -->
    <div style="display:flex;flex-direction:column;gap:16px">
      <!-- Request card -->
      <div class="card" style="text-align:center">
        <div style="font-family:'Cormorant Garamond',serif;font-size:52px;font-weight:300;color:var(--caramel);line-height:1">
          <?= (int)$service['credits'] ?>
        </div>
        <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;color:var(--gray);margin-bottom:24px">
          Credits Required
        </div>

        <?php if (!Auth::check()): ?>
          <a href="<?= APP_BASE ?>/register" class="btn btn-primary" style="width:100%;display:block;text-align:center">
            Join to Request
          </a>
          <p style="font-size:12px;color:var(--gray);margin-top:10px">
            Free to join — 50 starter credits included
          </p>
        <?php elseif ($isOwner): ?>
          <div style="background:var(--cream);border-radius:var(--r-sm);padding:12px;font-size:13px;color:var(--gray)">
            This is your own listing
          </div>
        <?php else: ?>
          <button class="btn btn-primary" style="width:100%"
                  onclick="openRequestModal(<?= (int)$service['id'] ?>, '<?= addslashes(Validator::e($service['title'])) ?>', <?= (int)$service['credits'] ?>)">
            Request This Service
          </button>
          <div style="font-size:12px;color:var(--gray);margin-top:10px;line-height:1.5">
            🔒 Credits held in <strong>escrow</strong> until you confirm completion
          </div>
        <?php endif; ?>
      </div>

      <!-- Meta -->
      <div class="card card-sm">
        <div style="display:flex;flex-direction:column;gap:12px;font-size:13px">
          <div class="flex-between">
            <span class="text-muted">Category</span>
            <span><?= Validator::e($service['category']) ?></span>
          </div>
          <div class="flex-between">
            <span class="text-muted">Listed</span>
            <span><?= date('M j, Y', strtotime($service['created_at'])) ?></span>
          </div>
          <div class="flex-between">
            <span class="text-muted">Provider</span>
            <a href="<?= APP_BASE ?>/users/<?= (int)$service['user_id'] ?>" style="color:var(--caramel)">
              <?= Validator::e($service['provider_name']) ?>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- CSRF Token for JS -->
<script>
const CSRF_TOKEN = "<?= \App\Core\CSRF::token() ?>";
</script>

<!-- ── Request Modal ── -->
<?php if (Auth::check() && !$isOwner): ?>
<div class="modal-overlay" id="modal-request">
  <div class="modal">
    <h2 class="modal-title" id="reqModalTitle">Request Service</h2>
    <p class="modal-sub" id="reqModalCredits">Credits will be held in escrow</p>
    <input type="hidden" id="reqServiceId" value="<?= (int)$service['id'] ?>">
    <div class="form-group">
      <label>Message to provider</label>
      <textarea id="reqMessage" rows="4"
                placeholder="Tell them what you need, your timeline, and any specific requirements…"></textarea>
    </div>
    <div style="background:var(--cream);border-radius:var(--r-sm);padding:14px 16px;font-size:13px;color:var(--gray);margin-bottom:4px">
      🔒 <strong style="color:var(--charcoal)"><?= (int)$service['credits'] ?> credits</strong> will be locked in escrow when you send this request. They'll be returned if declined, or released to the provider when you confirm completion.
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('request')">Cancel</button>
      <button class="btn btn-primary" id="reqSubmitBtn" onclick="submitRequest()">Send Request</button>
    </div>
  </div>
</div>

<script>
// Submit request with CSRF token
function submitRequest() {
  const serviceId = document.getElementById('reqServiceId').value;
  const message   = document.getElementById('reqMessage').value;

  const formData = new FormData();
  formData.append('service_id', serviceId);
  formData.append('message', message);
  formData.append('_csrf_token', CSRF_TOKEN);

  fetch('<?= APP_BASE ?>/swaps/request', {
    method: 'POST',
    body: formData,
    credentials: 'same-origin'
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      alert(data.message);
      closeModal('request');
    } else {
      alert(data.error);
    }
  })
  .catch(() => alert('Something went wrong.'));
}
</script>
<?php endif; ?>

<?php require APP_ROOT . '/app/Views/layouts/footer.php'; ?>
