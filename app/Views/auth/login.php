<?= $this->extend('layouts/auth') ?>
<?= $this->section('content') ?>
<main class="min-h-screen lg:grid lg:grid-cols-2">
    <section class="relative hidden min-h-screen overflow-hidden bg-gradient-to-br from-brand-900 via-brand-800 to-emerald-600 p-12 text-white lg:flex lg:flex-col lg:justify-between">
        <div class="absolute inset-0 bg-[url('/assets/img/login-hero.jpg')] bg-cover bg-center opacity-20"></div>
        <div class="relative flex items-center gap-3"><?php if(setting('ChildCare.center_logo')): ?><img src="<?= base_url(setting('ChildCare.center_logo')) ?>" alt="Center logo" class="h-12 w-12 rounded-lg bg-white/90 object-contain p-1"><?php else: ?><span class="grid h-12 w-12 place-items-center rounded-lg bg-white/90 text-sm font-bold text-brand-800" aria-hidden="true">BC</span><?php endif ?><div><p class="font-bold tracking-wide">BARANGAY CHILDCARE</p><p class="text-sm text-green-100">Child Health Record System</p></div></div>
        <div class="relative max-w-xl"><p class="text-sm font-semibold uppercase tracking-[.2em] text-green-100">Barangay health, made easier</p><h1 class="mt-4 text-4xl font-semibold leading-tight">Your Child’s Health,<br>Our Priority.</h1><p class="mt-4 text-lg text-green-50">Healthy Children, Stronger Community.</p><div class="mt-8 flex flex-wrap gap-3"><span class="rounded-full border border-white/30 bg-white/10 px-4 py-2 text-sm">Trusted child health records</span><span class="rounded-full border border-white/30 bg-white/10 px-4 py-2 text-sm">Timely care reminders</span></div></div>
        <p class="relative text-sm text-green-100">Barangay Kalawag 1 Health Center-B · Isulan, Sultan Kudarat</p>
    </section>
    <section class="flex min-h-screen items-center justify-center px-5 py-10 sm:px-10">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center"><?php if(setting('ChildCare.center_logo')): ?><img src="<?= base_url(setting('ChildCare.center_logo')) ?>" alt="Center logo" class="mx-auto h-16 w-16 rounded-xl bg-white object-contain shadow-sm"><?php else: ?><span class="mx-auto grid h-16 w-16 place-items-center rounded-xl border bg-white text-xl font-bold text-brand-800 shadow-sm" aria-hidden="true">BC</span><?php endif ?><p class="mt-4 text-sm font-semibold tracking-wide text-brand-800">BARANGAY CHILDCARE</p><h2 class="mt-2 text-2xl font-semibold">Welcome back</h2><p class="mt-2 text-sm text-gray-500">Sign in to continue to your account</p></div>
            <?php if (session('message')): ?><p class="mb-4 rounded-lg bg-blue-50 p-3 text-sm text-blue-800"><?= esc(session('message')) ?></p><?php endif ?>
            <?php if (session('error') || ! empty($error)): ?><p class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-800"><?= esc(session('error') ?: $error) ?></p><?php endif ?>
            <form class="card space-y-5 p-6 sm:p-8" method="post" action="<?= site_url('login') ?>">
                <?= csrf_field() ?>
                <div><label class="form-label" for="user_type">User Type</label><select class="form-control" id="user_type" name="user_type"><option value="bhw">Barangay Health Worker</option><option value="parent">Parent or Guardian</option><option value="admin">Administrator</option></select></div>
                <div><label class="form-label" for="username">Username</label><input class="form-control" id="username" name="username" autocomplete="username" required></div>
                <div x-data="{ show: false }"><div class="mb-1 flex items-center justify-between"><label class="form-label mb-0" for="password">Password</label><a class="text-sm font-medium text-accent" href="<?= site_url('forgot-password') ?>">Forgot Password?</a></div><div class="relative"><input class="form-control pr-16" :type="show ? 'text' : 'password'" id="password" name="password" autocomplete="current-password" required><button class="absolute inset-y-0 right-2 px-2 text-sm font-medium text-gray-600" type="button" @click="show=!show" x-text="show ? 'Hide' : 'Show'">Show</button></div></div>
                <button class="btn-primary w-full" type="submit">Log In</button>
                <div class="relative py-1 text-center text-xs text-gray-500"><span class="relative z-10 bg-white px-3">or continue with</span><span class="absolute inset-x-0 top-1/2 border-t border-gray-200"></span></div>
                <a class="btn-accent w-full" href="<?= site_url('login/sms') ?>">Log in with SMS Verification</a>
                <p class="text-center text-sm text-gray-600">Don’t have an account? <a class="font-semibold text-accent" href="<?= site_url('contact-admin') ?>">Contact Administrator</a></p>
            </form>
            <p class="mt-6 text-center text-xs text-gray-500">Your child’s health information is private and protected.</p>
        </div>
    </section>
</main>
<?= $this->endSection() ?>
