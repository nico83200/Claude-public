<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWKSet;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;
use RuntimeException;
use Throwable;

/**
 * Connexion OpenID Connect avec Google ou Apple (flux « authorization code »).
 *
 * L'identité n'est acceptée qu'après vérification du jeton d'identité (id_token) :
 * signature contre les clés publiées par le fournisseur, émetteur, audience,
 * expiration et nonce. Aucun secret ne quitte le serveur.
 */
class OidcProvider
{
    public const PROVIDERS = [
        'google' => [
            'authorize' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token' => 'https://oauth2.googleapis.com/token',
            'jwks' => 'https://www.googleapis.com/oauth2/v3/certs',
            'issuers' => ['https://accounts.google.com', 'accounts.google.com'],
            'scope' => 'openid email profile',
        ],
        'apple' => [
            'authorize' => 'https://appleid.apple.com/auth/authorize',
            'token' => 'https://appleid.apple.com/auth/token',
            'jwks' => 'https://appleid.apple.com/auth/keys',
            'issuers' => ['https://appleid.apple.com'],
            'scope' => 'name email',
        ],
    ];

    /** Tolérance d'horloge sur l'expiration du jeton (secondes). */
    private const LEEWAY = 120;

    public function __construct(private string $provider)
    {
        if (! isset(self::PROVIDERS[$provider])) {
            throw new RuntimeException('Fournisseur inconnu.');
        }
    }

    public static function configured(string $provider): bool
    {
        $c = config('services.'.$provider, []);

        return match ($provider) {
            'google' => filled($c['client_id'] ?? null) && filled($c['client_secret'] ?? null),
            'apple' => filled($c['client_id'] ?? null) && filled($c['team_id'] ?? null) && filled($c['key_id'] ?? null) && filled($c['private_key'] ?? null),
            default => false,
        };
    }

    /** @return string[] fournisseurs configurés, dans l'ordre d'affichage */
    public static function available(): array
    {
        return array_values(array_filter(array_keys(self::PROVIDERS), fn ($p) => self::configured($p)));
    }

    public function authorizationUrl(string $redirectUri, string $state, string $nonce, ?string $codeVerifier): string
    {
        $p = self::PROVIDERS[$this->provider];
        $params = [
            'client_id' => config("services.{$this->provider}.client_id"),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $p['scope'],
            'state' => $state,
            'nonce' => $nonce,
        ];
        if ($this->provider === 'google') {
            $params += ['code_challenge' => self::b64(hash('sha256', (string) $codeVerifier, true)), 'code_challenge_method' => 'S256', 'prompt' => 'select_account'];
        } else {
            // Apple renvoie le nom et l'email par un POST (obligatoire quand ces informations sont demandées).
            $params['response_mode'] = 'form_post';
        }

        return $p['authorize'].'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Échange le code contre les jetons et renvoie les informations vérifiées du jeton d'identité.
     *
     * @return array{sub: string, email: ?string, email_verified: bool, name: ?string}
     */
    public function identity(string $code, string $redirectUri, string $nonce, ?string $codeVerifier): array
    {
        $p = self::PROVIDERS[$this->provider];
        $form = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => config("services.{$this->provider}.client_id"),
            'client_secret' => $this->provider === 'apple' ? $this->appleClientSecret() : config('services.google.client_secret'),
        ];
        if ($this->provider === 'google') {
            $form['code_verifier'] = $codeVerifier;
        }
        $response = Http::asForm()->acceptJson()->timeout(15)->post($p['token'], $form);
        if (! $response->successful() || ! is_string($response->json('id_token'))) {
            throw new RuntimeException('Le fournisseur a refusé la connexion ('.($response->json('error') ?? $response->status()).').');
        }
        $claims = $this->verify($response->json('id_token'), $nonce);

        return [
            'sub' => (string) $claims['sub'],
            'email' => isset($claims['email']) ? strtolower((string) $claims['email']) : null,
            // Apple renvoie « true » sous forme de texte.
            'email_verified' => in_array($claims['email_verified'] ?? false, [true, 'true'], true),
            'name' => isset($claims['name']) ? trim((string) $claims['name']) : null,
        ];
    }

    /** Vérifie le jeton d'identité et renvoie ses revendications. */
    public function verify(string $idToken, string $nonce): array
    {
        try {
            $jws = (new CompactSerializer)->unserialize($idToken);
        } catch (Throwable) {
            throw new RuntimeException('Jeton d\'identité illisible.');
        }
        $verifier = new JWSVerifier(new AlgorithmManager([new RS256, new ES256]));
        if (! $verifier->verifyWithKeySet($jws, $this->keys(), 0)) {
            // Clés renouvelées par le fournisseur : une nouvelle lecture avant de refuser.
            if (! $verifier->verifyWithKeySet($jws, $this->keys(true), 0)) {
                throw new RuntimeException('Signature du jeton d\'identité invalide.');
            }
        }
        $claims = json_decode((string) $jws->getPayload(), true);
        $p = self::PROVIDERS[$this->provider];
        $aud = (array) ($claims['aud'] ?? []);
        $now = time();
        if (! is_array($claims) || empty($claims['sub'])
            || ! in_array($claims['iss'] ?? null, $p['issuers'], true)
            || ! in_array(config("services.{$this->provider}.client_id"), $aud, true)
            || ($claims['exp'] ?? 0) < $now - self::LEEWAY
            || ($claims['iat'] ?? $now) > $now + self::LEEWAY
            || ! hash_equals($nonce, (string) ($claims['nonce'] ?? ''))) {
            throw new RuntimeException('Jeton d\'identité refusé (émetteur, destinataire, validité ou nonce).');
        }

        return $claims;
    }

    private function keys(bool $refresh = false): JWKSet
    {
        $key = 'oidc-jwks:'.$this->provider;
        if ($refresh) {
            Cache::forget($key);
        }
        $data = Cache::remember($key, now()->addHours(6), function () {
            $r = Http::acceptJson()->timeout(10)->get(self::PROVIDERS[$this->provider]['jwks']);
            if (! $r->successful() || ! is_array($r->json('keys'))) {
                throw new RuntimeException('Clés publiques du fournisseur indisponibles.');
            }

            return $r->json();
        });

        return JWKSet::createFromKeyData($data);
    }

    /**
     * Secret client Apple : jeton signé (ES256) avec la clé privée « Sign in with Apple » (.p8),
     * valable 10 minutes. La clé ne quitte jamais le serveur.
     */
    public function appleClientSecret(): string
    {
        $c = config('services.apple');
        $pem = str_replace('\n', "\n", trim((string) $c['private_key']));
        try {
            $jwk = JWKFactory::createFromKey($pem, null, ['use' => 'sig']);
        } catch (Throwable) {
            throw new RuntimeException('Clé privée Apple illisible : collez le contenu complet du fichier .p8.');
        }
        $now = time();
        $payload = json_encode(['iss' => $c['team_id'], 'iat' => $now, 'exp' => $now + 600, 'aud' => 'https://appleid.apple.com', 'sub' => $c['client_id']]);
        $jws = (new JWSBuilder(new AlgorithmManager([new ES256])))->create()->withPayload($payload)
            ->addSignature($jwk, ['alg' => 'ES256', 'kid' => $c['key_id']])->build();

        return (new CompactSerializer)->serialize($jws, 0);
    }

    public static function b64(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
