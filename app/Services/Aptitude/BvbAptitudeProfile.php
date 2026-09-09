<?php

namespace App\Services\Aptitude;

class BvbAptitudeProfile
{
    public static function definition(): array
    {
        $item = fn ($key, $label, $max) => compact('key', 'label', 'max');
        $writing = $item('writing', 'Schriftlicher Ausdruck', 22);
        $writing['children'] = [
            ['key' => 'guidelines', 'label' => 'Berücksichtigung der Leitpunkte', 'max' => 7, 'children' => [
                $item('trips', 'Ausflüge', 2), $item('clothing', 'Kleidung', 2), $item('gift', 'Geschenk', 2), $item('extra', 'Zusatzinformationen', 1),
            ]],
            ['key' => 'communication', 'label' => 'Kommunikative Gestaltung', 'max' => 7, 'children' => [
                $item('greeting', 'Anrede und Abschied', 2), $item('expression', 'Ausdrucksweise und Wortwahl', 2), $item('connections', 'Verknüpfung der Sätze', 2), $item('structure', 'Anordnung der Leitpunkte', 1),
            ]],
            [...$item('correctness', 'Formale Richtigkeit: Satzbau, Grammatik, Rechtschreibung', 8), 'choices' => [8, 6, 4, 2]],
        ];
        return ['sections' => [
            ['key' => 'german', 'label' => 'Deutsch HSA-Niveau', 'category' => 'school', 'threshold' => 75, 'grades' => [], 'items' => [
                $item('opposites', 'Gegensätze', 6), $item('errors', 'Fehlersuche', 10), $item('verbs', 'Verben', 9),
                $item('vocabulary', 'Wortschatz', 3), $item('adjectives', 'Adjektive', 4), $item('endings', 'Endungen', 5),
                $item('plural', 'Plural', 6), $item('punctuation', 'Satzzeichen', 7), $item('meanings', 'Wortbedeutungen', 6),
                $item('analogies', 'Analogien', 4), $item('reading', 'Leseverstehen', 18), $writing,
            ]],
            ['key' => 'math', 'label' => 'Grundlagen Mathematik', 'category' => 'school', 'threshold' => null, 'grades' => [], 'items' => [
                ['key' => 'simple', 'label' => 'Einfache Aufgaben', 'max' => 34, 'children' => [
                    $item('simple_add', 'Addition', 8), $item('simple_subtract', 'Subtraktion', 10), $item('simple_multiply', 'Multiplikation', 8), $item('simple_divide', 'Division', 8),
                ]],
                ['key' => 'medium', 'label' => 'Mittelschwere Aufgaben', 'max' => 66, 'children' => [
                    $item('medium_add', 'Addition', 19), $item('medium_subtract', 'Subtraktion', 20), $item('medium_multiply', 'Multiplikation', 10), $item('medium_divide', 'Division', 17),
                ]],
            ]],
        ]];
    }
}
