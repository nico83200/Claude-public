<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title><?= e($subject ?? '') ?></title></head>
<body style="margin:0;padding:0;background:#f5efe3;font-family:Georgia,'Times New Roman',serif;color:#1f2a44;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5efe3;padding:24px 12px;">
  <tr><td align="center">
    <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;">
      <tr><td style="background:#1f2a44;padding:24px;text-align:center;color:#f5efe3;font-size:22px;letter-spacing:1px;">
        <?= e(setting('shop_name')) ?>
      </td></tr>
      <tr><td style="padding:32px 28px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#2b2b2b;">
        <?= $content ?>
      </td></tr>
      <tr><td style="padding:20px 28px;background:#faf7f1;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#777;text-align:center;">
        <?= e(setting('shop_name')) ?> · <a href="<?= e(url('/')) ?>" style="color:#9a6b33;"><?= e(parse_url(base_url(), PHP_URL_HOST)) ?></a>
        <?php if (setting('contact_email')): ?> · <?= e(setting('contact_email')) ?><?php endif; ?>
      </td></tr>
    </table>
  </td></tr>
</table>
</body>
</html>
