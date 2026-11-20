<?php
$userName = $userName ?? ($_SESSION['full_name'] ?? $_SESSION['name'] ?? 'MIS User');
$initials = strtoupper(mb_substr((string)$userName, 0, 1));
$roleLabel = $roleLabel ?? 'MIS USER';
?>
<header class="topbar">
 <button class="menu-toggle" id="menuToggle" type="button" aria-label="Toggle navigation">☰</button>
 <div class="welcome">
  <h1>Welcome, <?= htmlspecialchars((string)$userName, ENT_QUOTES, 'UTF-8') ?>!</h1>
  <p><?= htmlspecialchars((string)$roleLabel, ENT_QUOTES, 'UTF-8') ?></p>
 </div>

 <div class="topbar-actions">
  <button class="header-icon-btn notification-trigger" type="button"
          data-bs-toggle="modal" data-bs-target="#notifications"
          aria-label="Open notifications" title="Notifications">
   <i class="bi bi-bell"></i>
   <span class="notification-dot" aria-hidden="true"></span>
  </button>

  <a class="profile-area" href="/account/my-account.php" aria-label="Open account profile" title="My Account">
   <span class="avatar"><?= htmlspecialchars((string)$initials, ENT_QUOTES, 'UTF-8') ?></span>
   <span class="profile-name">
    <?= htmlspecialchars((string)$userName, ENT_QUOTES, 'UTF-8') ?>
    <small><?= htmlspecialchars((string)$roleLabel, ENT_QUOTES, 'UTF-8') ?></small>
   </span>
   <i class="bi bi-chevron-down profile-chevron"></i>
  </a>
 </div>
</header>

<!-- Shared notification popup: available to both Admin and User dashboards -->
<div class="modal fade" id="notifications" tabindex="-1" aria-labelledby="notificationsLabel" aria-hidden="true">
 <div class="modal-dialog modal-dialog-centered">
  <div class="modal-content">
   <div class="modal-header">
    <h5 class="modal-title" id="notificationsLabel"><i class="bi bi-bell me-2"></i>Notifications</h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
   </div>
   <div class="modal-body">
    <div class="text-center py-4 text-secondary">
     <i class="bi bi-bell-slash fs-2 d-block mb-2"></i>
     <strong class="d-block mb-1">No notifications yet</strong>
     <small>Your notifications will appear here.</small>
    </div>
   </div>
  </div>
 </div>
</div>

<style>
/* =========================================================
   SHARED MIS APP SHELL
   The same rules apply to Admin and User dashboards.
   Keep this block in the shared header so it loads after
   each page's Bootstrap and dashboard styles.
   ========================================================= */
:root{
 --mis-navy:#0c3158;
 --mis-navy-deep:#082747;
 --mis-blue:#0861d7;
 --mis-sidebar-width:258px;
}
.app-shell{min-height:100vh;display:flex;width:100%}
.app-shell>.sidebar{
 width:var(--mis-sidebar-width)!important;
 min-width:var(--mis-sidebar-width)!important;
 max-width:var(--mis-sidebar-width)!important;
 flex:0 0 var(--mis-sidebar-width)!important;
 min-height:100vh;
 height:auto;
 box-sizing:border-box;
 padding:20px 12px!important;
 margin:0!important;
 display:flex;
 flex-direction:column;
 background:linear-gradient(180deg,var(--mis-navy) 0%,var(--mis-navy-deep) 100%)!important;
 color:#fff!important;
 border:0!important;
 border-radius:0!important;
 box-shadow:none!important;
}
.app-shell>.sidebar .brand{
 display:flex!important;
 align-items:center!important;
 gap:12px!important;
 min-height:74px;
 padding:2px 8px 26px!important;
 margin:0!important;
 color:#fff!important;
 text-decoration:none!important;
 background:transparent!important;
 border:0!important;
}
.app-shell>.sidebar .brand-icon{
 display:grid!important;
 place-items:center!important;
 width:48px!important;
 height:48px!important;
 min-width:48px;
 border:2px solid #d9e8ff!important;
 border-radius:9px!important;
 color:#fff!important;
 font-size:29px!important;
}
.app-shell>.sidebar .brand strong{
 display:block;
 color:#fff!important;
 font-size:17px!important;
 line-height:1.2;
 font-weight:800!important;
 white-space:nowrap;
}
.app-shell>.sidebar .brand small{
 display:block;
 margin-top:5px!important;
 color:#d2e0f0!important;
 font-size:12px!important;
 line-height:1.3;
}
.app-shell>.sidebar .side-nav{
 display:flex!important;
 flex-direction:column!important;
 align-items:stretch!important;
 gap:5px!important;
 width:100%;
 margin:0!important;
 padding:0!important;
}
.app-shell>.sidebar .side-nav .nav-link{
 display:flex!important;
 align-items:center!important;
 justify-content:flex-start!important;
 gap:14px!important;
 width:100%!important;
 min-height:44px!important;
 height:44px;
 padding:0 14px!important;
 margin:0!important;
 border:0!important;
 border-radius:8px!important;
 background:transparent!important;
 color:#e7f0fc!important;
 font-family:inherit!important;
 font-size:14px!important;
 font-weight:500!important;
 line-height:1.2!important;
 text-align:left!important;
 text-decoration:none!important;
 box-shadow:none!important;
 transition:background .15s ease,color .15s ease;
}
.app-shell>.sidebar .side-nav .nav-link>span{
 display:inline-flex!important;
 align-items:center;
 justify-content:center;
 flex:0 0 22px;
 width:22px!important;
 min-width:22px;
 margin:0!important;
 padding:0!important;
 color:inherit!important;
 font-size:20px!important;
 line-height:1!important;
 text-align:center!important;
}
.app-shell>.sidebar .side-nav .nav-link:hover,
.app-shell>.sidebar .side-nav .nav-link.active{
 background:#0861d7!important;
 color:#fff!important;
}
.app-shell>.sidebar .side-nav .nav-link.sub-link{
 min-height:36px!important;
 height:36px;
 padding-left:26px!important;
 font-size:13px!important;
}
.app-shell>.sidebar .nav-label{
 display:block;
 padding:18px 14px 6px!important;
 margin:0!important;
 color:#93b0d1!important;
 background:transparent!important;
 font-size:10px!important;
 font-weight:700!important;
 letter-spacing:1px!important;
 line-height:1.3;
}
.app-shell>.sidebar .sidebar-foot{
 display:block;
 margin:auto 0 0!important;
 padding:18px 10px 0!important;
 border-top:1px solid #ffffff24!important;
 color:#b9cde5!important;
 background:transparent!important;
 font-size:10px!important;
 letter-spacing:.7px!important;
 line-height:1.8!important;
}
.app-shell>.sidebar .sidebar-foot small{
 color:#b9cde5!important;
 font-size:11px!important;
 letter-spacing:0!important;
}
.app-shell>.main-area{
 min-width:0;
 flex:1 1 auto;
 margin:0!important;
 padding:0!important;
}
.app-shell>.main-area>.topbar{
 display:flex!important;
 align-items:center!important;
 gap:20px!important;
 width:100%;
 height:76px!important;
 min-height:76px!important;
 padding:0 25px!important;
 margin:0!important;
 background:#fff!important;
 border:0!important;
 border-bottom:1px solid #e9eef6!important;
 border-radius:0!important;
 box-shadow:none!important;
 box-sizing:border-box;
}
.app-shell>.main-area>.topbar .menu-toggle{
 display:inline-flex;
 align-items:center;
 justify-content:center;
 width:34px;
 height:40px;
 padding:0!important;
 margin:0!important;
 border:0!important;
 background:transparent!important;
 color:var(--mis-navy)!important;
 font-size:25px!important;
 line-height:1;
 cursor:pointer;
}
.app-shell>.main-area>.topbar .welcome{flex:1 1 auto;min-width:0;margin:0!important}
.app-shell>.main-area>.topbar .welcome h1{
 margin:0 0 5px!important;
 color:#101e50!important;
 font-size:23px!important;
 font-weight:800!important;
 line-height:1.2!important;
 letter-spacing:-.5px!important;
}
.app-shell>.main-area>.topbar .welcome p{
 margin:0!important;
 color:#30456d!important;
 font-size:14px!important;
 line-height:1.3!important;
}
.app-shell>.main-area>.topbar .topbar-actions{
 display:flex!important;
 align-items:center!important;
 gap:14px!important;
 margin-left:auto!important;
}
.app-shell>.main-area>.topbar .header-icon-btn{
 position:relative;
 display:grid!important;
 place-items:center!important;
 width:42px!important;
 min-width:42px;
 height:42px!important;
 padding:0!important;
 border:1px solid #e5edf6!important;
 border-radius:11px!important;
 background:#fff!important;
 color:#0c3158!important;
 font-size:19px!important;
 line-height:1;
 cursor:pointer;
}
.app-shell>.main-area>.topbar .header-icon-btn:hover{background:#f4f7fc!important}
.app-shell>.main-area>.topbar .notification-dot{
 position:absolute;
 right:9px;
 top:8px;
 width:7px;
 height:7px;
 border-radius:50%;
 background:#ef4444;
}
.app-shell>.main-area>.topbar .profile-area{
 display:flex!important;
 align-items:center!important;
 gap:10px!important;
 margin:0!important;
 padding:0!important;
 border:0!important;
 background:transparent!important;
 color:inherit!important;
 text-decoration:none!important;
 cursor:pointer;
}
.app-shell>.main-area>.topbar .avatar{
 display:grid!important;
 place-items:center!important;
 width:44px!important;
 min-width:44px;
 height:44px!important;
 margin:0!important;
 border-radius:50%!important;
 background:#0879ed!important;
 color:#fff!important;
 font-size:18px!important;
 font-weight:700!important;
}
.app-shell>.main-area>.topbar .profile-name{
 display:block;
 margin:0!important;
 color:#101e50!important;
 font-size:13px!important;
 font-weight:700!important;
 line-height:1.25;
}
.app-shell>.main-area>.topbar .profile-name small{
 display:block;
 margin-top:4px!important;
 color:#64748b!important;
 font-size:11px!important;
 font-weight:500!important;
}
.app-shell>.main-area>.topbar .profile-chevron{font-size:11px;color:#64748b}
.app-shell>.main-area>.dashboard-content{min-width:0}
@media(max-width:900px){
 .app-shell>.sidebar{
  position:fixed!important;
  z-index:1050;
  top:0;
  bottom:0;
  left:0;
  height:100vh!important;
  min-height:100vh!important;
  transform:translateX(-100%);
  transition:transform .2s ease;
  overflow-y:auto;
 }
 .app-shell>.sidebar.open{transform:translateX(0)}
 .app-shell>.main-area{width:100%;max-width:100%}
}
@media(max-width:600px){
 .app-shell>.main-area>.topbar{height:68px!important;min-height:68px!important;padding:0 12px!important;gap:10px!important}
 .app-shell>.main-area>.topbar .welcome h1{font-size:17px!important}
 .app-shell>.main-area>.topbar .welcome p{font-size:11px!important}
 .app-shell>.main-area>.topbar .topbar-actions{gap:8px!important}
 .app-shell>.main-area>.topbar .profile-name,
 .app-shell>.main-area>.topbar .profile-chevron{display:none!important}
}
</style>
