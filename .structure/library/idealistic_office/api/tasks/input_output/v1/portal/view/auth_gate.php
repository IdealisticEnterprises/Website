<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <title><?= htmlspecialchars($portal->getName()) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="https://www.idealistic.ai/.images/logoCircular.png">
    <link href="https://www.idealistic.ai/.design/bootstrap.min.css" rel="stylesheet">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <style>
        :root { --bg: #0d0d0f; --surface: #1a1a1e; --text: #f9fafb; --border-color: rgba(255,255,255,0.12); }
        body { background: var(--bg); color: var(--text); font-family: 'Inter', sans-serif; height: 100dvh; display: flex; align-items: center; justify-content: center; }
        .bg-card { background-color: var(--surface); border: 1px solid var(--border-color); }
    </style>
</head>
<body>
<div class="bg-card p-5 rounded-4 shadow-sm text-center" style="max-width: 400px; width: 100%;">
    <?php if ($authError): ?>
        <div class="alert alert-danger py-2">
            <?= htmlspecialchars(is_array($authError) ? implode(', ', $authError) : $authError) ?>
        </div>
    <?php endif; ?>
    <form method="POST">
        <?php if ($portal->getPassword() !== null): ?>
            <input type="password" name="password"
                   placeholder="<?= htmlspecialchars($t['f_password_ph'] ?? 'Password', ENT_QUOTES) ?>"
                   required autofocus class="form-control mb-3 text-center rounded-4">
        <?php endif; ?>
        <?php if (!$portal->skipRobotVerification()): ?>
            <div class="g-recaptcha mb-3 d-flex justify-content-center"
                 data-sitekey="<?= IdealisticOfficeVariable::CAPTCHA_SITE_KEY ?>"></div>
        <?php endif; ?>
        <?php if ($portal->is18Plus()): ?>
            <div class="form-check mb-3 d-flex align-items-center justify-content-center gap-2">
                <input class="form-check-input mt-0 bg-transparent border-secondary shadow-none rounded-2"
                       type="checkbox" name="age_verification" id="ageCheck" required>
                <label class="form-check-label text-secondary-theme small" for="ageCheck" style="cursor:pointer;">
                    <?= htmlspecialchars($t['auth_age_confirmation'] ?? 'I confirm that I am 18 years of age or older.', ENT_QUOTES) ?>
                </label>
            </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-light text-dark fw-bold w-100 rounded-4">
            <?= $portal->getPassword() !== null
                ? htmlspecialchars($t['auth_unlock'] ?? 'Unlock')
                : htmlspecialchars($t['auth_enter'] ?? 'Enter') ?>
        </button>
    </form>
</div>
</body>
</html>