<?php
$layoutRole = $layoutRole ?? 'user';
$currentPage = $currentPage ?? '';
$portalLabel = $portalLabel ?? 'User Portal';

$adminLinks = [
 ['key'=>'dashboard','label'=>'Dashboard','icon'=>'▦','href'=>'admin-dashboard.php'],
 ['key'=>'requests','label'=>'IT Requests','icon'=>'▤','href'=>'admin-requests.php'],
 ['section'=>'TASK MANAGEMENT'],
 ['key'=>'pending','label'=>'Pending','icon'=>'◉','href'=>'admin-requests.php?status=Pending','sub'=>true],
 ['key'=>'progress','label'=>'In Progress','icon'=>'◉','href'=>'admin-requests.php?status=In%20Progress','sub'=>true],
 ['key'=>'completed','label'=>'Completed','icon'=>'◉','href'=>'admin-requests.php?status=Completed','sub'=>true],
 ['key'=>'cancelled','label'=>'Cancelled','icon'=>'◉','href'=>'admin-requests.php?status=Cancelled','sub'=>true],
 ['key'=>'reports','label'=>'Reports','icon'=>'▥','href'=>'admin-reports.php'],
 ['key'=>'associates','label'=>'IT Associates','icon'=>'♙','href'=>'admin-associates.php']
];
$userLinks = [
 ['key'=>'dashboard','label'=>'Dashboard','icon'=>'▦','href'=>'user-dashboard.php'],
 ['key'=>'mine','label'=>'My Tasks','icon'=>'☑','href'=>'#taskGrid','view'=>'mine'],
 ['key'=>'all','label'=>'All Tasks','icon'=>'▤','href'=>'#taskGrid','view'=>'all'],
 ['key'=>'leave','label'=>'File a Leave','icon'=>'▣','href'=>'#leaveRequestModal','modal'=>'leaveRequestModal']
];
$navLinks = $layoutRole === 'admin' ? $adminLinks : $userLinks;
?>
<aside class="sidebar" id="sidebar">
 <a class="brand" href="<?= $layoutRole === 'admin' ? 'admin-dashboard.php' : 'user-dashboard.php' ?>">
  <span class="brand-icon">⚙</span><span><strong>MIS HELPDESK</strong><small><?= htmlspecialchars($portalLabel, ENT_QUOTES, 'UTF-8') ?></small></span>
 </a>
 <nav class="side-nav">
 <?php foreach ($navLinks as $item): ?>
  <?php if (isset($item['section'])): ?>
   <div class="nav-label"><?= htmlspecialchars($item['section'], ENT_QUOTES, 'UTF-8') ?></div>
  <?php else: ?>
   <a class="nav-link <?= !empty($item['sub']) ? 'sub-link' : '' ?> <?= $currentPage === $item['key'] ? 'active' : '' ?>"
      href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
      <?= isset($item['view']) ? 'data-view="' . htmlspecialchars($item['view'], ENT_QUOTES, 'UTF-8') . '"' : '' ?>
      <?= isset($item['modal']) ? 'data-bs-toggle="modal" data-bs-target="#' . htmlspecialchars($item['modal'], ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
    <span><?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?></span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
   </a>
  <?php endif; ?>
 <?php endforeach; ?>
 <a class="nav-link" href="../logout.php"><span>⇥</span>Logout</a>
 </nav>
 <div class="sidebar-foot">IT SERVICE MANAGEMENT<br><small><?= $layoutRole === 'admin' ? 'Admin workspace' : 'User workspace' ?></small></div>
</aside>
