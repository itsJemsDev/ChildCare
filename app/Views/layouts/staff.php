<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? 'Dashboard') ?> · Barangay ChildCare</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <script defer src="<?= base_url('assets/vendor/alpine.min.js') ?>"></script>
    <script defer src="<?= base_url('assets/js/form-submit.js') ?>"></script>
</head>
<?php
    $staffUser = auth()->user();
    $currentPath = trim(uri_string(), '/');
    $navGroups = [
        'Workspace' => [
            ['label' => 'Dashboard', 'href' => 'dashboard', 'icon' => 'grid', 'exact' => true],
        ],
        'Child health' => [
            ['label' => 'Child profiles', 'href' => 'children', 'icon' => 'users'],
            ['label' => 'Immunization schedule', 'href' => 'schedules', 'icon' => 'shield'],
        ],
        'Communication' => [
            ['label' => 'SMS center', 'href' => 'sms', 'icon' => 'message'],
            ['label' => 'Notifications', 'href' => 'notifications', 'icon' => 'bell'],
        ],
        'Insights' => [
            ['label' => 'Reports', 'href' => 'reports', 'icon' => 'chart'],
        ],
    ];
    $userLabel = $staffUser ? trim(($staffUser->first_name ?? '') . ' ' . ($staffUser->last_name ?? '')) : 'Staff';
    if ($userLabel === '') $userLabel = 'Staff';
    $userInitials = strtoupper(substr($staffUser->first_name ?? 'S', 0, 1) . substr($staffUser->last_name ?? '', 0, 1));
    if ($userInitials === '') $userInitials = 'S';
?>
<body class="min-h-screen bg-gray-50" x-data="{ menuOpen: false }" @keydown.escape.window="menuOpen = false">
<div class="min-h-screen lg:flex">
    <div x-cloak x-show="menuOpen" x-transition.opacity @click="menuOpen = false" class="fixed inset-0 z-30 bg-gray-950/50 backdrop-blur-sm lg:hidden"></div>

    <aside
        id="staff-sidebar"
        :class="menuOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        class="fixed inset-y-0 left-0 z-40 flex w-[17.5rem] flex-col bg-[#103a2d] text-white shadow-2xl shadow-gray-950/20 transition-transform duration-300 ease-out lg:translate-x-0"
        aria-label="Staff navigation"
    >
        <div class="flex items-center justify-between border-b border-white/10 px-5 py-5">
            <a href="<?= site_url('dashboard') ?>" class="flex min-w-0 items-center gap-3 rounded-xl focus-visible:outline-white">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl border border-emerald-200/20 bg-emerald-300/10 text-emerald-200">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="h-6 w-6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-4.35-7-10a4 4 0 0 1 7-2.65A4 4 0 0 1 19 11c0 5.65-7 10-7 10Z"/><path d="M9 12h1.5l1-2 1.5 4 1-2H15"/></svg>
                </span>
                <span class="min-w-0">
                    <span class="block truncate text-sm font-bold tracking-[0.08em]">BARANGAY</span>
                    <span class="block truncate text-base font-semibold leading-tight text-emerald-100">ChildCare</span>
                </span>
            </a>
            <button type="button" class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-emerald-100 hover:bg-white/10 focus-visible:outline-white lg:hidden" aria-label="Close menu" @click="menuOpen = false">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="h-5 w-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="m18 6-12 12M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="mx-4 mt-4 flex items-center gap-2 rounded-xl border border-emerald-100/10 bg-white/[0.045] px-3 py-2.5">
            <span class="h-2 w-2 rounded-full bg-emerald-300 shadow-[0_0_0_3px_rgba(110,231,183,0.12)]"></span>
            <span class="truncate text-xs font-medium text-emerald-50/80">Health center workspace</span>
        </div>

        <nav class="scrollbar-hidden flex-1 space-y-6 overflow-y-auto px-3 pb-4 pt-5" aria-label="Main menu">
            <?php foreach ($navGroups as $groupLabel => $items): ?>
                <div>
                    <p class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-100/45"><?= esc($groupLabel) ?></p>
                    <div class="space-y-1">
                        <?php foreach ($items as $item): ?>
                            <?php
                                $isActive = ($item['exact'] ?? false)
                                    ? $currentPath === $item['href']
                                    : ($currentPath === $item['href'] || str_starts_with($currentPath, $item['href'] . '/'));
                                $linkClass = $isActive
                                    ? 'bg-emerald-300 text-[#103a2d] shadow-lg shadow-black/10'
                                    : 'text-emerald-50/75 hover:bg-white/[0.07] hover:text-white';
                                $iconClass = $isActive ? 'text-[#103a2d]' : 'text-emerald-200/75 group-hover:text-emerald-100';
                            ?>
                            <a href="<?= site_url($item['href']) ?>" <?= $isActive ? 'aria-current="page"' : '' ?> @click="menuOpen = false" class="group flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors <?= $linkClass ?>">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg <?= $isActive ? 'bg-white/35' : 'bg-white/[0.045]' ?> <?= $iconClass ?>">
                                    <?php if ($item['icon'] === 'grid'): ?><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="h-[18px] w-[18px]" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/></svg>
                                    <?php elseif ($item['icon'] === 'users'): ?><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="h-[18px] w-[18px]" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><path d="M3.5 19v-1.2A4.8 4.8 0 0 1 8.3 13h1.4a4.8 4.8 0 0 1 4.8 4.8V19zM16 5.3a3 3 0 0 1 0 5.8M17 13.2a4.6 4.6 0 0 1 3.5 4.5V19h-3"/></svg>
                                    <?php elseif ($item['icon'] === 'shield'): ?><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="h-[18px] w-[18px]" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-3.5 7-9V5l-7-2-7 2v7c0 5.5 7 9 7 9Z"/><path d="m9 12 2 2 4-4"/></svg>
                                    <?php elseif ($item['icon'] === 'message'): ?><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="h-[18px] w-[18px]" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H5l-2 2v-7.5A7.5 7.5 0 1 1 20 11.5Z"/><path d="M8 11h8M8 8h5"/></svg>
                                    <?php elseif ($item['icon'] === 'bell'): ?><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="h-[18px] w-[18px]" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4"/></svg>
                                    <?php elseif ($item['icon'] === 'chart'): ?><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="h-[18px] w-[18px]" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V4M4 20h17"/><path d="m7 15 4-4 3 2 5-6"/><circle cx="7" cy="15" r=".7" fill="currentColor"/><circle cx="11" cy="11" r=".7" fill="currentColor"/><circle cx="14" cy="13" r=".7" fill="currentColor"/><circle cx="19" cy="7" r=".7" fill="currentColor"/></svg><?php endif ?>
                                </span>
                                <span class="min-w-0 flex-1 truncate"><?= esc($item['label']) ?></span>
                                <?php if ($isActive): ?><span class="h-1.5 w-1.5 rounded-full bg-[#103a2d]"></span><?php endif ?>
                            </a>
                        <?php endforeach ?>
                    </div>
                </div>
            <?php endforeach ?>

            <div>
                <p class="mb-2 px-3 text-[10px] font-bold uppercase tracking-[0.18em] text-emerald-100/45">Administration</p>
                <div class="space-y-1">
                    <?php if ($staffUser && $staffUser->inGroup('admin')): ?>
                        <a href="<?= site_url('users') ?>" <?= str_starts_with($currentPath, 'users') ? 'aria-current="page"' : '' ?> @click="menuOpen = false" class="group flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors <?= str_starts_with($currentPath, 'users') ? 'bg-emerald-300 text-[#103a2d] shadow-lg shadow-black/10' : 'text-emerald-50/75 hover:bg-white/[0.07] hover:text-white' ?>">
                            <span class="grid h-8 w-8 place-items-center rounded-lg bg-white/[0.045] text-emerald-200/75 group-hover:text-emerald-100"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="h-[18px] w-[18px]" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><path d="M3.5 19v-1.2A4.8 4.8 0 0 1 8.3 13h1.4a4.8 4.8 0 0 1 4.8 4.8V19zM16 5.3a3 3 0 0 1 0 5.8M17 13.2a4.6 4.6 0 0 1 3.5 4.5V19h-3"/></svg></span>
                            <span class="flex-1">Users management</span>
                        </a>
                        <a href="<?= site_url('settings') ?>" <?= str_starts_with($currentPath, 'settings') ? 'aria-current="page"' : '' ?> @click="menuOpen = false" class="group flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors <?= str_starts_with($currentPath, 'settings') ? 'bg-emerald-300 text-[#103a2d] shadow-lg shadow-black/10' : 'text-emerald-50/75 hover:bg-white/[0.07] hover:text-white' ?>">
                            <span class="grid h-8 w-8 place-items-center rounded-lg bg-white/[0.045] text-emerald-200/75 group-hover:text-emerald-100"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="h-[18px] w-[18px]" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="m19.4 15 .1.1 1.4 1.1-1.4 2.4-1.7-.6a8 8 0 0 1-1.5.9l-.3 1.8h-2.8l-.3-1.8a8 8 0 0 1-1.5-.9l-1.7.6-1.4-2.4 1.4-1.1A7 7 0 0 1 7.5 13l-1.8-.7.8-2.7 1.8.2a7 7 0 0 1 1.2-1.2l-.2-1.8L12 6l.7 1.7a7 7 0 0 1 1.7 0L15 6l2.7.8-.2 1.8a7 7 0 0 1 1.2 1.2l1.8-.2.8 2.7-1.8.7a7 7 0 0 1-.1 2Z"/></svg></span>
                            <span class="flex-1">System settings</span>
                        </a>
                    <?php elseif ($staffUser): ?>
                        <a href="<?= site_url('users') ?>" <?= str_starts_with($currentPath, 'users') ? 'aria-current="page"' : '' ?> @click="menuOpen = false" class="group flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors <?= str_starts_with($currentPath, 'users') ? 'bg-emerald-300 text-[#103a2d] shadow-lg shadow-black/10' : 'text-emerald-50/75 hover:bg-white/[0.07] hover:text-white' ?>">
                            <span class="grid h-8 w-8 place-items-center rounded-lg bg-white/[0.045] text-emerald-200/75"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="h-[18px] w-[18px]" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3"/><path d="M3.5 19v-1.2A4.8 4.8 0 0 1 8.3 13h1.4a4.8 4.8 0 0 1 4.8 4.8V19zM16 5.3a3 3 0 0 1 0 5.8M17 13.2a4.6 4.6 0 0 1 3.5 4.5V19h-3"/></svg></span>
                            <span class="flex-1">Parent accounts</span>
                        </a>
                    <?php endif ?>
                </div>
            </div>
        </nav>

        <div class="border-t border-white/10 p-3">
            <p class="px-2 pb-2 text-[11px] leading-relaxed text-emerald-100/45">Barangay Kalawag 1 Health Center-B</p>
            <a href="<?= site_url('profile') ?>" @click="menuOpen = false" class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium text-emerald-50/75 transition-colors hover:bg-white/[0.07] hover:text-white">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="h-5 w-5 text-emerald-200/75" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/></svg><span>My profile</span>
            </a>
            <a href="<?= site_url('logout') ?>" class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium text-rose-100/80 transition-colors hover:bg-rose-400/10 hover:text-rose-100">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="h-5 w-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg><span>Log out</span>
            </a>
        </div>
    </aside>

    <main class="min-w-0 flex-1 lg:ml-[17.5rem]">
        <header class="sticky top-0 z-20 flex min-h-16 items-center justify-between border-b border-gray-200/80 bg-white/95 px-4 shadow-sm shadow-gray-900/[0.02] backdrop-blur sm:px-8">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-gray-200 text-gray-700 transition-colors hover:bg-gray-50 focus-visible:outline-brand-700 lg:hidden" aria-label="Open menu" aria-controls="staff-sidebar" :aria-expanded="menuOpen.toString()" @click="menuOpen = true">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="h-5 w-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="min-w-0">
                    <p class="hidden text-[10px] font-bold uppercase tracking-[0.16em] text-gray-400 sm:block">Child Health Workspace</p>
                    <h1 class="truncate text-base font-semibold text-gray-900 sm:text-lg"><?= esc($title ?? 'Dashboard') ?></h1>
                </div>
            </div>
            <div class="ml-3 flex shrink-0 items-center gap-2 sm:gap-3">
                <a href="<?= site_url('notifications') ?>" class="relative grid h-11 w-11 place-items-center rounded-xl text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900" aria-label="Notifications">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" class="h-5 w-5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4"/></svg>
                </a>
                <div class="hidden h-9 w-px bg-gray-200 sm:block"></div>
                <div class="hidden text-right sm:block">
                    <p class="max-w-40 truncate text-sm font-semibold text-gray-800"><?= esc($userLabel) ?></p>
                    <p class="text-xs text-gray-500"><?= esc(implode(', ', $staffUser ? $staffUser->getGroups() : [])) ?></p>
                </div>
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-100 text-sm font-bold text-brand-800"><?= esc($userInitials) ?></span>
            </div>
        </header>
        <section class="p-4 sm:p-8"><?= $this->renderSection('content') ?></section>
    </main>
</div>
</body>
</html>
