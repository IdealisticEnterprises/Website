<?php

require_once '/var/www/.structure/library/idealistic_office/api/tasks/website/v1/footer/index.php';

IdealisticOfficeBrand::start();

$siteUrl = IdealisticOfficeVariable::COMPANY_WEBSITE_URL . '/';
$assetBase = IdealisticOfficeVariable::COMPANY_WEBSITE_URL;
$pageUrl = IdealisticOfficeVariable::WORK_URL;
$workEmail = IdealisticOfficeVariable::WORK_EMAIL;
$title = IdealisticOfficeWebsite::WORK_TITLE;
$description = IdealisticOfficeWebsite::WORK_DESCRIPTION;
$mailto = function (string $subject) use ($workEmail): string {
    return 'mailto:' . $workEmail . '?subject=' . rawurlencode($subject);
};

header('Cache-Control: public, max-age=300');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= io_e($title) ?></title>
    <meta name="description" content="<?= io_e($description) ?>">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="theme-color" content="#06070a">
    <link rel="canonical" href="<?= io_e($pageUrl) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= io_e($assetBase) ?>/.images/favicon.svg">
    <link rel="icon" type="image/png" href="<?= io_e($assetBase) ?>/.images/logoSquare.png">
    <link rel="apple-touch-icon" href="<?= io_e($assetBase) ?>/.images/logoSquare.png">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= IdealisticOfficeVariable::APPLICATION_NAME ?>">
    <meta property="og:locale" content="en_US">
    <meta property="og:url" content="<?= io_e($pageUrl) ?>">
    <meta property="og:title" content="<?= io_e($title) ?>">
    <meta property="og:description" content="<?= io_e($description) ?>">
    <meta property="og:image" content="<?= io_e($assetBase) ?>/.images/social.png">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= io_e($title) ?>">
    <meta name="twitter:description" content="<?= io_e($description) ?>">
    <meta name="twitter:image" content="<?= io_e($assetBase) ?>/.images/social.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="<?= io_e(io_asset('.assets/css/landing.css')) ?>">
    <link rel="stylesheet" href="<?= io_e(io_asset('.assets/css/footer.css')) ?>">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<header class="island nav" id="nav">
    <a class="brand" href="<?= io_e($siteUrl) ?>" aria-label="<?= IdealisticOfficeVariable::APPLICATION_NAME ?>">
        <img src="<?= io_e($assetBase) ?>/.images/logoWhite.svg" alt="" width="30" height="30">
        <span><?= IdealisticOfficeVariable::APPLICATION_NAME ?></span>
    </a>
    <nav aria-label="Main">
        <a href="<?= io_e($siteUrl) ?>">Home</a>
        <a href="#roles">Who we look for</a>
        <a href="#apply">How to apply</a>
    </nav>
    <a class="button solid small" href="<?= io_e($mailto(IdealisticOfficeWebsite::WORK_TITLE)) ?>"><?= io_e(IdealisticOfficeWebsite::WORK_APPLY_BUTTON) ?></a>
</header>

<main id="main">
    <section class="hero compact">
        <div class="hero-mark" aria-hidden="true"><img src="<?= io_e($assetBase) ?>/.images/logoWhite.svg" alt="" width="640" height="640"></div>
        <div class="wrap">
            <p class="eyebrow"><?= io_e(IdealisticOfficeWebsite::WORK_EYEBROW) ?></p>
            <h1>Work with<br><span>humans.</span></h1>
            <p class="lead"><?= io_e(IdealisticOfficeWebsite::WORK_LEAD) ?></p>
        </div>
    </section>

    <section id="roles">
        <div class="wrap">
            <p class="label"><?= io_e(IdealisticOfficeWebsite::WORK_ROLES_LABEL) ?></p>
            <h2><?= io_e(IdealisticOfficeWebsite::WORK_ROLES_TITLE) ?></h2>
            <div class="grid halves">
                <?php foreach (IdealisticOfficeWebsite::WORK_ROLES as $role) : ?>
                    <article class="island step">
                        <h3><?= io_e($role['title']) ?></h3>
                        <p><?= io_e($role['text']) ?></p>
                        <p class="fine-print"><a href="<?= io_e($mailto($role['title'])) ?>">Apply as <?= io_e(strtolower($role['title'])) ?></a></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="apply">
        <div class="wrap">
            <div class="island human">
                <p class="label"><?= io_e(IdealisticOfficeWebsite::WORK_APPLY_LABEL) ?></p>
                <h2><?= io_e(IdealisticOfficeWebsite::WORK_APPLY_TITLE) ?></h2>
                <p><?= io_e(IdealisticOfficeWebsite::WORK_APPLY_TEXT) ?></p>
                <div class="actions">
                    <a class="button solid large" href="<?= io_e($mailto(IdealisticOfficeWebsite::WORK_TITLE)) ?>"><?= io_e($workEmail) ?></a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php io_footer(); ?>
</body>
</html>
