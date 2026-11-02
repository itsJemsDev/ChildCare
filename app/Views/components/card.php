<section class="card <?= esc($class ?? 'p-5') ?>"><?php if (!empty($title)): ?>
        <h2 class="mb-4 text-lg font-semibold"><?= esc($title) ?></h2><?php endif ?><?= $content ?? '' ?>
</section>