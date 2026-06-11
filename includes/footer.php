<?php $siteRoot = $siteRoot ?? ''; ?>
</main>

<!-- FOOTER -->
<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <strong style="font-size:1.125rem;">Elea Store</strong>
                <p style="margin-top:.5rem;line-height:1.7;">Fashion for all pilihan — tampil elegan, stylish, dan percaya diri tanpa harus menguras kantong. Kualitas premium dengan harga paling bersahabat!</p>
            </div>
            <div class="footer-col">
                <h4>Navigasi</h4>
                <a href="<?= $siteRoot ?>index.php">Beranda</a>
                <a href="<?= $siteRoot ?>katalog.php">Katalog Produk</a>
                <a href="<?= $siteRoot ?>preorder.php">Pre Order</a>
                <a href="<?= $siteRoot ?>tentang.php">Tentang Kami</a>
                <a href="<?= $siteRoot ?>kontak.php">Kontak</a>
            </div>
            <div class="footer-col">
                <h4>Hubungi Kami</h4>
                <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:.625rem;">
                    <a href="https://wa.me/6288220357699" target="_blank" style="display:flex;align-items:center;gap:.375rem;color:inherit;text-decoration:none;font-size:.875rem;">
                        <i class="fab fa-whatsapp" style="color:#25d366;font-size:1rem;"></i>+62 882-2035-7699
                    </a>
                    <a href="mailto:sintanurlaelaa@gmail.com" style="display:flex;align-items:center;gap:.375rem;color:inherit;text-decoration:none;font-size:.875rem;">
                        <i class="fas fa-envelope" style="font-size:.875rem;"></i>sintanurlaelaa@gmail.com
                    </a>
                </div>
                <p style="font-size:.875rem;margin-bottom:.375rem;"><i class="fas fa-map-marker-alt" style="margin-right:.375rem;"></i>Blok Karang Mekar Gang Mekar Indah 4 RT.50 RW.21, Kel. Cigadung, Kec. Subang, Kab. Subang (41213)</p>
                <p style="font-size:.875rem;"><i class="fas fa-clock" style="margin-right:.375rem;"></i>Setiap hari, 05.00 – 01.00 WIB</p>
            </div>
        </div>
        <div class="footer-bottom">
            &copy; <?= date('Y') ?> Elea Store. All rights reserved. &nbsp;·&nbsp; Fashion for All
        </div>
    </div>
</footer>

</body>
</html>
