<?php
$sidebar_base = $sidebar_base ?? '';
$me = current_user();
$current = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
  <div class="brand"><div class="brandmark">LM</div><div><b>LAB MANAGER</b><small>Sistem Manajemen Laboratorium</small></div></div>
  <div class="side-section">WORKSPACE</div>
  <a class="side-link <?=$current==='index.php'?'active':''?>" href="<?=$sidebar_base?>index.php"><span class="ico">⌂</span>Dashboard</a>
  <?php if(can_manage_operations()): ?>
  <a class="side-link <?=$current==='logbook.php'?'active':''?>" href="<?=$sidebar_base?>logbook.php"><span class="ico">▣</span>Logbook</a>
  <a class="side-link <?=$current==='alat.php'?'active':''?>" href="<?=$sidebar_base?>alat.php"><span class="ico">⚙</span>Inventory Alat Laboratorium</a>
  <?php endif; ?>
  <a class="side-link <?=$current==='laporan.php'?'active':''?>" href="<?=$sidebar_base?>laporan.php"><span class="ico">▤</span>Laporan & Export</a>
  <?php if(is_admin()): ?><div class="side-section">ADMINISTRASI</div><a class="side-link <?=$current==='users.php'?'active':''?>" href="<?=$sidebar_base?>admin/users.php"><span class="ico">♙</span>Pengguna & Hak Akses</a><?php endif; ?>
  <div class="sidebar-bottom"><div class="side-user"><div class="avatar"><?=e(strtoupper(substr($me['nama'],0,1)))?></div><div><b><?=e($me['nama'])?></b><small><?=e(ucfirst($me['role']))?></small></div></div><a class="side-link logout" href="<?=$sidebar_base?>logout.php"><span class="ico">↪</span>Keluar</a></div>
</aside>
