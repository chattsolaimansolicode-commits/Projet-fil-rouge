    </main>
  </div><!-- .main-content -->
</div><!-- .app-wrapper -->

<!-- Mobile Bottom Nav -->
<nav class="mobile-nav">
  <div class="mobile-nav-inner">
    <?php if (isset($mobileNavLinks)): ?>
      <?php foreach ($mobileNavLinks as $link): ?>
        <a href="<?= e($link['href']) ?>" class="mobile-nav-item <?= ($activeNav === $link['nav']) ? 'active' : '' ?>">
          <span class="mn-icon"><?= $link['icon'] ?></span>
          <span><?= e($link['label']) ?></span>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</nav>

<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
