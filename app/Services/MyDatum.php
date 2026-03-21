<?php

namespace App\Services;

use DateTime;

class MyDatum {

        public function abkWochentag($datum)
        {
                $wochentag = date('N', strtotime($datum));
                $wochentagAbkuerzung = '';
                if ($wochentag == 1) {
                    $wochentagAbkuerzung = 'Mo.';
                } elseif ($wochentag == 2) {
                    $wochentagAbkuerzung = 'Di.';
                } elseif ($wochentag == 3) {
                    $wochentagAbkuerzung = 'Mi.';
                } elseif ($wochentag == 4) {
                    $wochentagAbkuerzung = 'Do.';
                } elseif ($wochentag == 5) {
                    $wochentagAbkuerzung = 'Fr.';
                } elseif ($wochentag == 6) {
                    $wochentagAbkuerzung = 'Sa.';
                } elseif ($wochentag == 7) {
                    $wochentagAbkuerzung = 'So.';
                }
                return $wochentagAbkuerzung;
        }

        public function wochentag($datum){
            $tag = date('N', strtotime($datum));
            $wochentag = '';

            if ($tag == 1) {
                $wochentag = 'Montag';
            } elseif ($tag == 2) {
                $wochentag = 'Dienstag';
            } elseif ($tag == 3) {
                $wochentag = 'Mittwoch';
            } elseif ($tag == 4) {
                $wochentag = 'Donnerstag';
            } elseif ($tag == 5) {
                $wochentag = 'Freitag';
            } elseif ($tag == 6) {
                $Wochentag1 = 'Samstag';
            } elseif ($tag == 7) {
                $wochentag = 'Sonntag';
            }
            return $wochentag;
        }

        public function convertDatumToDB($datum){
            $date = DateTime::createFromFormat('d/m/Y', $datum);
            $convertDatum= $date->format('Y-m-d');
            return $convertDatum;
        }
    }
