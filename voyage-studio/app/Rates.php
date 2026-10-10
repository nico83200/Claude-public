<?php
declare(strict_types=1);

/**
 * Taux de change : base EUR (1 EUR = x devise), source Banque centrale européenne (gratuit, sans clé).
 * Les taux restent modifiables à la main (taux fournisseur, taux bancaire négocié…).
 */
final class Rates
{
    public const ECB_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';

    /** Valeurs de secours pour une installation hors ligne (à rafraîchir). */
    public const SEED = [
        'EUR' => 1.0, 'USD' => 1.08, 'GBP' => 0.85, 'CHF' => 0.95, 'CAD' => 1.48, 'AUD' => 1.65, 'JPY' => 162.0,
        'THB' => 38.5, 'AED' => 3.97, 'MAD' => 10.8, 'TND' => 3.35, 'MUR' => 50.0, 'ZAR' => 20.0, 'MXN' => 19.5,
        'NOK' => 11.6, 'SEK' => 11.3, 'DKK' => 7.46, 'CNY' => 7.8, 'INR' => 90.0, 'IDR' => 17500.0, 'XPF' => 119.33,
    ];

    public function __construct(private Repository $repo, private Database $db)
    {
    }

    public function seedIfEmpty(): void
    {
        if ((int)$this->db->value('SELECT COUNT(*) FROM currencies') > 0) {
            return;
        }
        foreach (self::SEED as $code => $taux) {
            $this->repo->create('currencies', ['code' => $code, 'taux' => $taux, 'maj' => 'valeur initiale']);
        }
    }

    /** @return array{updated:int,date:string} */
    public function refreshFromEcb(?string $xml = null): array
    {
        $xml ??= self::fetch(self::ECB_URL);
        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
        libxml_use_internal_errors($prev);
        if ($doc === false) {
            throw new RuntimeException('Réponse BCE illisible');
        }
        $doc->registerXPathNamespace('e', 'http://www.ecb.int/vocabulary/2002-08-01/eurofxref');
        $cubes = $doc->xpath('//e:Cube[@currency]');
        $dateNode = $doc->xpath('//e:Cube[@time]');
        $date = $dateNode ? (string)$dateNode[0]['time'] : date('Y-m-d');
        if (!$cubes) {
            throw new RuntimeException('Aucun taux dans la réponse BCE');
        }
        $n = 0;
        $this->db->transaction(function () use ($cubes, $date, &$n) {
            $this->upsert('EUR', 1.0, "BCE $date");
            foreach ($cubes as $c) {
                $this->upsert((string)$c['currency'], (float)$c['rate'], "BCE $date");
                $n++;
            }
        });
        return ['updated' => $n, 'date' => $date];
    }

    private function upsert(string $code, float $taux, string $maj): void
    {
        $id = $this->db->value('SELECT id FROM currencies WHERE code = ?', [$code]);
        if ($id) {
            $this->repo->update('currencies', (int)$id, ['taux' => $taux, 'maj' => $maj]);
        } else {
            $this->repo->create('currencies', ['code' => $code, 'taux' => $taux, 'maj' => $maj]);
        }
    }

    public static function fetch(string $url): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT => 'VoyageStudio/1.0',
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($body === false || $code >= 400) {
                throw new RuntimeException('Téléchargement impossible : ' . ($err ?: "HTTP $code"));
            }
            return (string)$body;
        }
        $ctx = stream_context_create(['http' => ['timeout' => 10, 'user_agent' => 'VoyageStudio/1.0']]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            throw new RuntimeException('Téléchargement impossible (allow_url_fopen désactivé ?)');
        }
        return $body;
    }
}
