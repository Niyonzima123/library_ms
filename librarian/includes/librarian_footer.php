        </div>
        
        <footer class="main-footer">
            <div class="footer-content">
                <span>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Librarian Portal.</span>
                <span>Logged in as: <?php echo sanitize($_SESSION['name']); ?> (<?php echo ucfirst($_SESSION['role']); ?>)</span>
            </div>
        </footer>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php';
ob_end_flush(); ?>
