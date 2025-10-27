<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Yasumi\Yasumi;

class TageSeeder extends Seeder
{
    public function run(): void
    {

        DB::table('zeitens')->insert([
            [ // id = 1
                'startzeit' => '08:00:00',
                'endzeit' => '12:00:00',
            ],
            [ // id = 2
                'startzeit' => '13:00:00',
                'endzeit' => '17:00:00',
            ],
            [ // id = 3
                'startzeit' => '08:00:00',
                'endzeit' => '17:00:00',
            ],
        ]);

        DB::table('anwesenheitsstatutens')->insert([
            [ // id = 1
                'status' => 'anwesend',
                'farben' => 'bg-green-500',
            ],
            [ // id = 2
                'status' => 'krank',
                'farben' => 'bg-yellow-400',
            ],
            [ // id = 3
                'status' => 'entschuldigt',
                'farben' => 'bg-blue-400',
            ],
            [ // id = 4
                'status' => 'unentschuldigt',
                'farben' => 'bg-red-500',
            ],
            [ // id = 5
                'status' => 'urlaub',
                'farben' => 'bg-purple-500',
            ],
            [ // id = 6
                'status' => 'feiertag',
                'farben' => 'bg-gray-400',
            ]
        ]);


        ##################################################



        /* Flls Composer noch nicht eingegen ist, bitte ausfhren:
        composer require azuyalabs/yasumi

        betrieblich freier Tag
        Vom Arbeitgeber oder Betrieb freiwillig festgelegt (z. B. Brückentage, Betriebsruhe). */
        $jahr = now()->year;
        $start = Carbon::create($jahr, 1, 1);
        $end = Carbon::create($jahr, 12, 31);

        // 🇩🇪 Feiertage für Saarland (korrekte Reihenfolge der Parameter)
        $feiertage = Yasumi::create('Germany', $jahr, 'de_DE', 'Saarland');

        $wochentage = [
            'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag',
        ];

        $data = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $wochentag = $wochentage[$date->dayOfWeekIso - 1];
            $feiertag_typ = 'kein_feiertag';
            $feiertag_name = null;

            // Prüfen, ob Tag ein offizieller Feiertag ist
            if ($feiertage->isHoliday($date)) {
                $feiertag_typ = 'gesetzlicher_feiertag';

                // So bekommt man den Feiertagsnamen (aktuelles Yasumi-Verhalten)
                foreach ($feiertage as $holiday) {
                    if ($holiday->format('Y-m-d') === $date->format('Y-m-d')) {
                        $feiertag_name = $holiday->getName();
                        break;
                    }
                }
            }

            $data[] = [
                'datum' => $date->format('Y-m-d'),
                'wochentag' => $wochentag,
                'feiertag_typ' => $feiertag_typ,
                'feiertag_name' => $feiertag_name,
            ];
        }

        DB::table('tages')->insert($data);






    }
}
