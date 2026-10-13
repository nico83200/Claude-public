<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Audit;
use App\Services\MailDesign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

/** Administration › E-mails : mise en forme et textes de tous les e-mails envoyés. */
class MailDesignController extends Controller
{
    public function edit(MailDesign $design)
    {
        $settings = $design->settings();

        return view('admin.mail-design', [
            'settings' => $settings,
            'kinds' => MailDesign::kinds(),
            'preview' => view('emails.notification', ['mail' => $design->preview('appointment', $settings)])->render(),
            'customLogo' => (bool) $design->customLogoPath(),
        ]);
    }

    public function update(Request $request, MailDesign $design)
    {
        $design->save($this->validated($request, $design));
        Audit::log('admin.mail_design_updated');

        return back()->with('success', 'Mise en forme des e-mails enregistrée.');
    }

    /** Aperçu des réglages en cours d'édition, sans les enregistrer. */
    public function preview(Request $request, MailDesign $design)
    {
        $kind = $request->input('preview_kind', 'appointment');
        abort_unless(isset(MailDesign::kinds()[$kind]), 422);
        $mail = $design->preview($kind, $this->validated($request, $design), $request->user()->name);

        return response()->json(['subject' => $mail['subject'], 'html' => view('emails.notification', ['mail' => $mail])->render()]);
    }

    /** Envoi réel d'un exemple à l'adresse du super-administrateur, avec les réglages enregistrés. */
    public function test(Request $request, MailDesign $design)
    {
        $data = $request->validate(['preview_kind' => ['required', Rule::in(array_keys(MailDesign::kinds()))]]);
        $user = $request->user();
        $mail = $design->preview($data['preview_kind'], null, $user->name);
        try {
            Mail::send(['emails.notification', 'emails.notification-text'], ['mail' => $mail], fn ($m) => $m->to($user->email)->subject($mail['subject']));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Échec de l\'envoi : '.mb_substr($e->getMessage(), 0, 300));
        }
        Audit::log('admin.mail_design_test', null, ['kind' => $data['preview_kind']]);

        return back()->with('success', config('mail.default') === 'log'
            ? 'E-mail généré, mais le mode « journal » ne l\'envoie pas : il est écrit dans storage/logs.'
            : 'Exemple « '.MailDesign::kinds()[$data['preview_kind']].' » envoyé à '.$user->email.'.');
    }

    public function uploadLogo(Request $request, MailDesign $design)
    {
        $request->validate(['logo_file' => ['required', 'file', 'mimes:png,jpg,jpeg,gif', 'max:1024']], [
            'logo_file.mimes' => 'Format accepté : PNG, JPG ou GIF (les messageries ne lisent pas toutes le SVG ni le WebP).',
        ]);
        $disk = Storage::disk('local');
        if ($old = $design->customLogoPath()) {
            $disk->delete($old);
        }
        $file = $request->file('logo_file');
        $disk->putFileAs(MailDesign::LOGO_DIR, $file, 'logo.'.strtolower($file->guessExtension() ?: 'png'));
        $settings = $design->settings();
        $settings['logo'] = 'custom';
        $design->save($settings);
        Audit::log('admin.mail_logo_uploaded');

        return back()->with('success', 'Logo des e-mails remplacé.');
    }

    public function removeLogo(MailDesign $design)
    {
        if ($old = $design->customLogoPath()) {
            Storage::disk('local')->delete($old);
        }
        $settings = $design->settings();
        if ($settings['logo'] === 'custom') {
            $settings['logo'] = 'logo';
            $design->save($settings);
        }

        return back()->with('success', 'Logo personnalisé retiré : le logo Jack&Cie est utilisé.');
    }

    /** Logo personnalisé, public : il est affiché par les messageries des destinataires. */
    public function logo(MailDesign $design)
    {
        $path = $design->customLogoPath();
        abort_unless($path, 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'public, max-age=604800']);
    }

    private function validated(Request $request, MailDesign $design): array
    {
        $kinds = array_keys(MailDesign::kinds());
        $color = ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'];
        $rules = [
            'logo' => ['required', Rule::in(array_keys(MailDesign::LOGOS))],
            'logo_width' => ['required', 'integer', 'min:40', 'max:400'],
            'align' => ['required', Rule::in(['center', 'left'])],
            'subject_prefix' => ['nullable', 'string', 'max:40'],
            'signature' => ['nullable', 'string', 'max:300'],
            'fallback_text' => ['nullable', 'string', 'max:200'],
            'footer' => ['nullable', 'string', 'max:600'],
            'kinds' => ['array'],
            'kinds.*' => ['array'],
        ];
        foreach (MailDesign::COLOR_FIELDS as $f) {
            $rules[$f] = $color;
        }
        foreach (MailDesign::TEXT_FIELDS as $f) {
            $max = in_array($f, ['intro', 'outro', 'message'], true) ? 2000 : 150;
            $rules[$f] = ['nullable', 'string', 'max:'.$max];
            $rules['kinds.*.'.$f] = ['nullable', 'string', 'max:'.$max];
        }
        $data = $request->validate($rules, ['*.regex' => 'Couleur au format #RRGGBB.']);

        $defaults = MailDesign::defaults();
        $out = [];
        foreach (array_keys($defaults) as $key) {
            if ($key === 'kinds') {
                continue;
            }
            $out[$key] = $data[$key] ?? null;
        }
        foreach (MailDesign::COLOR_FIELDS as $f) {
            $out[$f] = $out[$f] ?: $defaults[$f];
        }
        foreach (['subject', 'message', 'button_label'] as $f) {
            $out[$f] = trim((string) $out[$f]) !== '' ? $out[$f] : $defaults[$f];
        }
        foreach (['subject_prefix', 'greeting', 'intro', 'outro', 'signature', 'fallback_text', 'footer'] as $f) {
            $out[$f] = (string) ($out[$f] ?? '');
        }
        $out['logo_width'] = (int) $out['logo_width'];
        $out['logo_link'] = $request->boolean('logo_link');
        $out['show_fallback_link'] = $request->boolean('show_fallback_link');
        if ($out['logo'] === 'custom' && ! $design->customLogoPath()) {
            $out['logo'] = 'logo';
        }
        $out['kinds'] = [];
        foreach ($kinds as $kind) {
            $row = (array) ($data['kinds'][$kind] ?? []);
            $clean = array_filter(array_map(fn ($v) => trim((string) $v), array_intersect_key($row, array_flip(MailDesign::TEXT_FIELDS))), fn ($v) => $v !== '');
            if (! isset(MailDesign::ACCOUNT_KINDS[$kind]) && ! $request->boolean("kinds.$kind.enabled")) {
                $clean['enabled'] = false;
            }
            if ($clean) {
                $out['kinds'][$kind] = $clean;
            }
        }

        return $out;
    }
}
