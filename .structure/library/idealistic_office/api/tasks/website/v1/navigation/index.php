<?php

require_once '/var/www/.structure/library/idealistic_office/api/tasks/website/v1/footer/index.php';

if (!function_exists('io_navigation')) {
    function io_navigation(string $current = '', string $extra = ''): void
    {
        $site = IdealisticOfficeVariable::COMPANY_WEBSITE_URL;
        $links = array(
            'home' => array('Home', $site . '/'),
            'chat' => array('Chat', IdealisticOfficeVariable::PORTAL_CHAT_URL),
            'structure' => array('Structure', IdealisticOfficeVariable::PORTAL_STRUCTURE_URL)
        );
        ?>
<header class="topnav">
    <a class="topnav-brand" href="<?= io_e($site) ?>/">
        <img src="<?= io_e($site) ?>/.images/logoWhite.svg" alt="" width="28" height="28">
        <span><?= io_e(IdealisticOfficeVariable::APPLICATION_NAME) ?></span>
    </a>
    <nav aria-label="Main">
        <?php foreach ($links as $key => $link) : ?>
            <a href="<?= io_e($link[1]) ?>"<?= $key === $current ? ' aria-current="page"' : '' ?>><?= io_e($link[0]) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="topnav-extra"><?= $extra ?></div>
</header>
        <?php
    }
}
