<?php

namespace Tests\Unit;

use App\Services\Assistant\Speaker;
use App\Services\WeeklyDigest;
use PHPUnit\Framework\TestCase;

/** Fonctions de texte pures (sans base ni application) : lecture à voix haute et nettoyage des textes de l'IA. */
class TextForUsersTest extends TestCase
{
    public function test_hours_and_dates_are_spelled_out_for_the_voice(): void
    {
        $this->assertSame('Le maréchal vient à 9 heures 30, le 20 octobre.', Speaker::spoken('Le maréchal vient à 9h30, le 20/10.'));
        $this->assertSame('De 8 heures à midi, puis minuit.', Speaker::spoken('De 8h-12h, puis 0h.'));
        $this->assertSame('3/4 de ration, 62,40 €.', Speaker::spoken('3/4 de ration, 62,40 €.'));
    }

    public function test_ai_intro_keeps_only_complete_french_sentences(): void
    {
        $this->assertSame('Jeudi est chargé pour Uni.', WeeklyDigest::cleanIntro('Bonjour, ... wait, I must not start with "Bonjour". Jeudi est chargé pour Uni.'));
        $this->assertSame('Jeudi est chargé pour Uni.', WeeklyDigest::cleanIntro('Jeudi est chargé pour Uni. Puis deux séances de'));
        $this->assertNull(WeeklyDigest::cleanIntro('Here is your week.'));
    }
}
