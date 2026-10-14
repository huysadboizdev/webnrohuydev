<?php
// admin/includes/footer.php
?>
        </main>
    </div>
</div>

<?php if (!empty($queued_command_id)): ?><p data-admin-command-id="<?= (int)$queued_command_id ?>">Lệnh đang chờ máy chủ xử lý.</p><?php endif; ?>
<script src="<?= htmlspecialchars(rtrim(admin_url(''), '/'), ENT_QUOTES, 'UTF-8') ?>/admin/assets/js/admin.js?v=<?= time() ?>"></script>
</body>
</html>
