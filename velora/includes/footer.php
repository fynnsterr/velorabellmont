</main>
<footer class="site-footer">
    <div class="wrap footer-grid">
        <div>
            <p class="brand brand-foot">Velora Bellmont</p>
            <p class="foot-note">Stylish, elegant and affordable jewelry, delivered to your door anywhere in Ghana.</p>
        </div>
        <div>
            <h2 class="foot-title">Shop</h2>
            <ul class="foot-list">
                <?php foreach (categories() as $c): ?>
                    <li><a href="<?= e(url('shop.php?category=' . $c['slug'])) ?>"><?= e($c['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div>
            <h2 class="foot-title">Help</h2>
            <ul class="foot-list">
                <li><a href="<?= e(url('policies.php#delivery')) ?>">Delivery</a></li>
                <li><a href="<?= e(url('policies.php#returns')) ?>">Returns and exchanges</a></li>
                <li><a href="<?= e(url('policies.php#privacy')) ?>">Privacy</a></li>
                <li><a href="<?= e(url('policies.php#terms')) ?>">Terms of sale</a></li>
            </ul>
        </div>
        <div>
            <h2 class="foot-title">Contact</h2>
            <ul class="foot-list">
                <li><a href="mailto:<?= e(cfg('site.support_email')) ?>"><?= e(cfg('site.support_email')) ?></a></li>
                <li><?= e(cfg('site.support_phone')) ?></li>
                <li>Pay by card or mobile money with Paystack, or pay on delivery.</li>
            </ul>
        </div>
    </div>
    <div class="wrap foot-base">
        <p>&copy; <?= date('Y') ?> Velora Bellmont. All rights reserved.</p>
    </div>
</footer>
</body>
</html>
