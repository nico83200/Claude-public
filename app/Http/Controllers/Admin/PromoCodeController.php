<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcquisitionSource;
use App\Models\PromoCode;
use App\Models\SubscriptionPlan;
use App\Services\AcquisitionTracker;
use App\Services\Audit;
use App\Services\Billing\StripeGateway;
use App\Services\PromoCodeService;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Codes de réduction (Stripe) et codes d'accès gratuit pour une durée. */
class PromoCodeController extends Controller
{
    public function __construct(private PromoCodeService $promos) {}

    public function index(Request $request, StripeGateway $stripe)
    {
        $codes = PromoCode::with('plan')->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))->latest()->paginate(30)->withQueryString();

        return view('admin.promo-codes.index', [
            'qrCodes' => PromoCode::where('is_active', true)->orderBy('code')->get(['code', 'label']),
            'sources' => AcquisitionSource::orderBy('label')->get(),
            'codes' => $codes,
            'plans' => SubscriptionPlan::whereNull('archived_at')->where('is_default_free', false)->orderBy('sort_order')->pluck('name', 'id'),
            'stripeReady' => $stripe->configured(),
            'suggested' => strtoupper(Str::random(8)),
        ]);
    }

    public function store(Request $request)
    {
        $request->merge(['code' => PromoCode::normalize((string) $request->input('code'))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'min:3', 'max:40', Rule::unique('promo_codes', 'code')],
            'type' => ['required', Rule::in(array_keys(PromoCode::TYPES))],
            'label' => ['required', 'string', 'max:120'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:now', 'after:starts_at'],
            'max_redemptions' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'first_subscription_only' => ['boolean'],
            // Accès gratuit
            'subscription_plan_id' => ['nullable', 'required_if:type,free_access', 'exists:subscription_plans,id'],
            'free_days' => ['nullable', 'required_if:type,free_access', 'integer', 'min:1', 'max:3650'],
            // Réduction
            'discount_kind' => ['nullable', 'required_if:type,discount', Rule::in(['percent', 'amount'])],
            'percent_off' => ['nullable', 'required_if:discount_kind,percent', 'numeric', 'min:1', 'max:100'],
            'amount_off' => ['nullable', 'required_if:discount_kind,amount', 'numeric', 'min:0.5', 'max:100000'],
            'duration' => ['nullable', 'required_if:type,discount', Rule::in(['once', 'repeating', 'forever'])],
            'duration_months' => ['nullable', 'required_if:duration,repeating', 'integer', 'min:1', 'max:36'],
            'plan_ids' => ['array'], 'plan_ids.*' => ['integer', 'exists:subscription_plans,id'],
        ], [
            'code.unique' => 'Ce code existe déjà.',
            'free_days.required_if' => 'Indiquez la durée offerte (en jours).',
            'subscription_plan_id.required_if' => 'Choisissez l\'offre accordée.',
            'percent_off.required_if' => 'Indiquez le pourcentage de réduction.',
            'amount_off.required_if' => 'Indiquez le montant de la réduction.',
            'duration_months.required_if' => 'Indiquez le nombre de mois.',
        ]);

        if ($data['type'] === 'free_access') {
            $data = array_diff_key($data, array_flip(['discount_kind', 'percent_off', 'amount_off', 'duration', 'duration_months', 'plan_ids']));
        } else {
            $kind = $data['discount_kind'];
            unset($data['discount_kind'], $data['subscription_plan_id'], $data['free_days']);
            $data[$kind === 'percent' ? 'amount_off' : 'percent_off'] = null;
            if ($data['duration'] !== 'repeating') {
                $data['duration_months'] = null;
            }
            $data['plan_ids'] = array_values(array_unique(array_map('intval', $data['plan_ids'] ?? []))) ?: null;
            $data['currency'] = 'EUR';
        }
        $data['first_subscription_only'] = $request->boolean('first_subscription_only');

        try {
            $promo = $this->promos->create(array_filter($data, fn ($v) => $v !== null), $request->user());
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'Stripe a refusé la création du code : '.mb_substr($e->getMessage(), 0, 300));
        }

        return redirect()->route('admin.promo-codes.show', $promo)->with('success', 'Code '.$promo->code.' créé.');
    }

    public function show(PromoCode $promoCode)
    {
        return view('admin.promo-codes.show', [
            'promo' => $promoCode->load('plan', 'author'),
            'redemptions' => $promoCode->redemptions()->with('organization', 'user')->latest()->paginate(50),
            'plans' => SubscriptionPlan::whereIn('id', $promoCode->plan_ids ?? [])->pluck('name'),
            'acquisition' => app(AcquisitionTracker::class)->report(null, $promoCode->code),
            'sources' => AcquisitionSource::orderBy('label')->get(),
        ]);
    }

    /** Validité, nombre d'utilisations, nouveaux clients et libellé, modifiables après la création. */
    public function updateSettings(Request $request, PromoCode $promoCode)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'max_redemptions' => ['nullable', 'integer', 'min:'.max(1, $promoCode->redemptions_count), 'max:1000000'],
        ], [
            'max_redemptions.min' => 'Le code a déjà été utilisé '.$promoCode->redemptions_count.' fois : la limite ne peut pas être inférieure.',
            'expires_at.after_or_equal' => 'La fin de validité doit suivre le début.',
        ]);
        $data['first_subscription_only'] = $request->boolean('first_subscription_only');
        try {
            $this->promos->updateSettings($promoCode, $data);
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'Stripe a refusé la modification : '.mb_substr($e->getMessage(), 0, 300).' Le code n\'a pas été modifié.');
        }

        return back()->with('success', 'Code '.$promoCode->code.' mis à jour.');
    }

    /** QR code (SVG, pour l'impression) du lien d'inscription suivi d'une source. */
    public function qr(PromoCode $promoCode, string $source)
    {
        $source = AcquisitionSource::where('key', $source)->firstOrFail();

        return $this->qrResponse($promoCode->code, $source->key, true);
    }

    /**
     * Générateur de QR code : code promo et source facultatifs (au moins l'adresse d'inscription).
     * ?download=1 pour enregistrer le fichier, sinon affichage (aperçu).
     */
    public function qrGenerator(Request $request)
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', Rule::exists('promo_codes', 'code')],
            'src' => ['nullable', 'string', Rule::exists('acquisition_sources', 'key')],
        ]);

        return $this->qrResponse($data['code'] ?? null, $data['src'] ?? null, $request->boolean('download'));
    }

    private function qrResponse(?string $code, ?string $source, bool $download)
    {
        $link = $code || $source ? AcquisitionTracker::link($code, (string) $source) : url('/register');
        $svg = (new Writer(new ImageRenderer(new RendererStyle(600, 2), new SvgImageBackEnd)))->writeString($link);
        $name = 'qr-'.implode('-', array_filter(['jackcie', $code ? strtolower($code) : null, $source])).'.svg';

        return response($svg, 200, array_filter([
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=300',
            'Content-Disposition' => $download ? 'attachment; filename="'.$name.'"' : null,
        ]));
    }

    public function storeSource(Request $request)
    {
        $data = $this->sourceData($request);
        $source = AcquisitionSource::create($data);
        AcquisitionTracker::forgetLabels();
        Audit::log('admin.acquisition_source_created', $source, $data);

        return back()->with('success', 'Source « '.$source->label.' » ajoutée : son lien et son QR code sont disponibles sur la fiche de chaque code.');
    }

    public function updateSource(Request $request, AcquisitionSource $source)
    {
        $source->update($request->validate(['label' => ['required', 'string', 'max:80']]));
        AcquisitionTracker::forgetLabels();

        return back()->with('success', 'Source renommée.');
    }

    public function destroySource(AcquisitionSource $source)
    {
        $source->delete();
        AcquisitionTracker::forgetLabels();
        Audit::log('admin.acquisition_source_deleted', null, ['key' => $source->key]);

        return back()->with('success', 'Source retirée de la liste. Les arrivées déjà comptées restent dans les statistiques ; les liens déjà diffusés continuent d\'être comptés comme « source non déclarée ».');
    }

    private function sourceData(Request $request): array
    {
        $request->merge(['key' => AcquisitionTracker::normalizeSource($request->input('key') ?: $request->input('label'))]);

        return $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'key' => ['required', 'string', 'max:40', 'not_in:lien', Rule::unique('acquisition_sources', 'key')],
        ], [
            'key.unique' => 'Une source utilise déjà cet identifiant.',
            'key.required' => 'Indiquez un nom de source.',
        ]);
    }

    public function update(Request $request, PromoCode $promoCode)
    {
        $data = $request->validate(['is_active' => ['required', 'boolean'], 'label' => ['sometimes', 'required', 'string', 'max:120']]);
        try {
            if ((bool) $data['is_active'] !== $promoCode->is_active) {
                $this->promos->setActive($promoCode, (bool) $data['is_active']);
            }
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Stripe n\'a pas pu mettre à jour le code : '.mb_substr($e->getMessage(), 0, 300));
        }
        if (isset($data['label'])) {
            $promoCode->update(['label' => $data['label']]);
        }

        return back()->with('success', $promoCode->is_active ? 'Code actif.' : 'Code désactivé : il ne peut plus être utilisé (les avantages déjà accordés sont conservés).');
    }
}
