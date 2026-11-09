<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<footer class="site-footer">
    <div class="site-footer__inner">

        <p>
            &copy; <?php echo esc_html(wp_date('Y')); ?>
            <?php bloginfo('name'); ?>.
            <?php esc_html_e('All rights reserved.', 'blue-sheep'); ?>
        </p>

    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>